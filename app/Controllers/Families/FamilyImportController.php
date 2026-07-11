<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyExcelImporter;
use App\Libraries\FamilyExcelTemplate;
use App\Libraries\ImportReviewPresenter;
use App\Libraries\ImportStagingStore;
use App\Models\Families\MemberModel;
use App\Models\Jobs\JobQueueModel;
use CodeIgniter\HTTP\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Excel import side of Manage Family: template download, import form,
 * import submission (queued as a `family_import` job), and status polling.
 * Split out of FamilyController; the importStatus() JSON payload shape is a
 * frontend contract (family-import.js polling) and must not change.
 */
class FamilyImportController extends BaseController
{
    use FamilyRequestContext;

    /**
     * GET `{admin|employee}/manage-family/template`: streams the blank, fillable
     * .xlsx template (App\Libraries\FamilyExcelTemplate) workers use to collect
     * family records offline. Same entry-access guard as the Add form.
     */
    public function downloadTemplate()
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $spreadsheet = (new FamilyExcelTemplate())->build();

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = (string) ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="family-import-template.xlsx"')
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
    }

    /**
     * GET `{admin|employee}/manage-family/import`: returns the Excel import modal
     * fragment (file upload + results area) loaded by family-import.js into the
     * shared dashboard modal.
     */
    public function importForm(): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to import family records.');
        }

        return view('Family/import-modal', [
            'action'      => site_url($this->currentRouteBase() . '/import'),
            'templateUrl' => site_url($this->currentRouteBase() . '/template'),
        ]);
    }

    /**
     * POST `{admin|employee}/manage-family/import`: QUEUES a filled .xlsx of
     * families for background import. The file is only validated as an upload here
     * (a valid .xlsx), moved to writable/uploads, and recorded as a `pending`
     * job_queue row (type 'family_import'). A scheduled worker (php spark queue:work) parses,
     * validates, and writes it batched + resumably, so very large files never hit the
     * web request's timeout or memory limit. The modal polls importStatus() for the
     * row errors and final summary. Always responds as JSON (the modal posts over AJAX).
     */
    public function import()
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to import family records.', 403);
        }

        $memberModel = new MemberModel();

        if (! $memberModel->hasRequiredFamilyTables()) {
            return $this->jsonError('The accesscard database is missing required tables from accesscardV14.sql.', 422);
        }

        $file = $this->request->getFile('import_file');

        if ($file === null || ! $file->isValid()) {
            return $this->jsonError('Please choose a valid .xlsx file to import.', 422);
        }

        // guessExtension() derives the extension server-side from the file's MIME
        // type, so a renamed .exe/.php can't pass as .xlsx (getClientExtension is
        // attacker-controlled). xlsx is a zip container, so allow the zip guess too.
        $guessedExtension = strtolower((string) $file->guessExtension());

        if (! in_array($guessedExtension, ['xlsx', 'zip'], true)) {
            return $this->jsonError('The file must be an .xlsx workbook saved from the template.', 422);
        }

        // Capture the original name before move() (which renames the file on disk).
        $originalName = (string) ($file->getClientName() ?: 'import.xlsx');

        // Durable name (not a temp the request deletes): the worker owns its lifecycle
        // and unlinks it once the job is done/failed.
        $storedName = 'family-import-' . bin2hex(random_bytes(8)) . '.xlsx';

        try {
            $file->move(WRITEPATH . 'uploads', $storedName, true);
        } catch (Throwable $exception) {
            return $this->jsonError('The uploaded file could not be saved for processing. Please try again.', 500);
        }

        $storedPath = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $storedName;

        $jobs = new JobQueueModel();

        if (! $jobs->hasTable()) {
            @unlink($storedPath);

            return $this->jsonError('The background job queue is unavailable (missing job_queue table from accesscardV14.sql).', 422);
        }

        try {
            $jobId = $jobs->enqueue(
                'family_import',
                ['phase' => 'review', 'storedPath' => $storedPath, 'originalName' => $originalName],
                (int) session()->get('user_id'),
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (Throwable $exception) {
            @unlink($storedPath);
            $this->auditSystemError('queueing a family Excel import', $exception);

            return $this->jsonError('The import could not be queued. Please try again.', 500);
        }

        return $this->response->setJSON([
            'status'    => 'queued',
            'jobID'     => $jobId,
            'statusUrl' => site_url($this->currentRouteBase() . '/import/status/' . $jobId),
            'message'   => 'Your file is queued and being checked for problems.',
            'csrf'      => csrf_hash(),
        ]);
    }

    /**
     * GET `{admin|employee}/manage-family/import/status/(:num)`: JSON progress for a
     * queued import job, polled by family-import.js. Returns the job's status, a human
     * message, progress counters, and (once finished) any validation/per-family write
     * errors so the modal can render them exactly as the old synchronous flow did.
     */
    public function importStatus(int $jobId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to view import status.', 403);
        }

        $jobs = new JobQueueModel();

        if (! $jobs->hasTable()) {
            return $this->jsonError('No import is in progress.', 404);
        }

        $job = $jobs->find($jobId);

        if ($job === null || ($job['type'] ?? '') !== 'family_import') {
            return $this->jsonError('Import job not found.', 404);
        }

        $status = (string) $job['status'];
        $total  = (int) $job['progress_total'];
        $done   = (int) $job['progress_done'];

        $result = [];

        if (! empty($job['result_json'])) {
            $decoded = json_decode((string) $job['result_json'], true);

            if (is_array($decoded)) {
                $result = $decoded;
            }
        }

        $errors   = (isset($result['errors']) && is_array($result['errors'])) ? $result['errors'] : [];
        $imported = (int) ($result['imported'] ?? 0);
        $failed   = (int) ($result['failed'] ?? 0);
        $skipped  = (int) ($result['skipped'] ?? 0);
        $members  = (int) ($result['members'] ?? 0);

        // Review-phase jobs stage for the operator to inspect; write-phase jobs persist.
        $phase    = (string) ($result['phase'] ?? 'write');
        $finished = in_array($status, ['done', 'partial', 'failed'], true);
        $reviewUrl = ($phase === 'review' && $finished && $status !== 'failed')
            ? site_url($this->currentRouteBase() . '/import/review/' . $jobId)
            : null;

        return $this->response->setJSON([
            'status'    => $status,
            'phase'     => $phase,
            'finished'  => $finished,
            'reviewUrl' => $reviewUrl,
            'message'   => (string) ($job['message'] ?? ''),
            'progress' => [
                'total'     => $total,
                'processed' => $done,
                'imported'  => $imported,
                'failed'    => $failed,
                'skipped'   => $skipped,
                'members'   => $members,
                'percent'   => $total > 0 ? (int) floor($done * 100 / $total) : 0,
            ],
            // The review page is the uncapped surface; this list only feeds the toast.
            'errors'   => array_slice($errors, 0, 500),
            'summary'  => [
                'families' => $imported,
                'members'  => $members,
            ],
            'csrf'     => csrf_hash(),
        ]);
    }

    /**
     * GET `{admin|employee}/manage-family/import/review/(:num)`: the full-page Import
     * Review screen for a staged job — grouped errors the operator fixes inline before
     * confirming the import.
     */
    public function reviewPage(int $jobId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $jobs = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded === null) {
            return redirect()->to(site_url($this->currentRouteBase()))
                ->with('error', 'That import is no longer available to review.');
        }

        return view('Family/import-review', [
            'jobId'     => $jobId,
            'routeBase' => $this->currentRouteBase(),
            'review'    => (new ImportReviewPresenter())->build($loaded['result']),
            'username'  => (string) (session()->get('username') ?? ''),
        ]);
    }

    /**
     * POST `{admin|employee}/manage-family/import/review/(:num)/row`: patches one staged
     * cell (sheetRow + field + value), re-validates the whole batch, persists the edit
     * onto the job, and returns the fresh grouped review payload.
     */
    public function reviewRow(int $jobId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to edit this import.', 403);
        }

        $jobs = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded === null) {
            return $this->jsonError('That import is no longer available to review.', 404);
        }

        $sheetRow = (int) $this->request->getPost('sheetRow');
        $field    = trim((string) $this->request->getPost('field'));
        $value    = (string) $this->request->getPost('value');

        if ($sheetRow <= 0 || $field === '') {
            return $this->jsonError('Nothing to update.', 422);
        }

        $result = $loaded['result'];
        $rows   = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $found  = false;

        foreach ($rows as &$entry) {
            if ((int) ($entry['sheetRow'] ?? 0) === $sheetRow) {
                if (! is_array($entry['data'] ?? null)) {
                    $entry['data'] = [];
                }
                $entry['data'][$field] = $value;
                $found = true;
                break;
            }
        }
        unset($entry);

        if (! $found) {
            return $this->jsonError('That row could not be found in this import.', 404);
        }

        $result = $this->revalidate($result, $rows);
        $this->persistReview($jobs, $jobId, $result);

        return $this->response->setJSON([
            'status' => 'ok',
            'review' => (new ImportReviewPresenter())->build($result, $this->pinnedQrs()),
            'csrf'   => csrf_hash(),
        ]);
    }

    /** Comma-separated list of QR numbers the operator has touched (kept visible). */
    private function pinnedQrs(): array
    {
        $raw = (string) $this->request->getPost('pinned');

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * POST `{admin|employee}/manage-family/import/review/(:num)/commit`: re-validates
     * the reviewed batch and, only when no blocking issues remain, queues the write job
     * that persists the families. Returns that job's status URL for the progress toast.
     */
    public function reviewCommit(int $jobId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to import family records.', 403);
        }

        $jobs = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded === null) {
            return $this->jsonError('That import is no longer available to review.', 404);
        }

        $rows   = is_array($loaded['result']['rows'] ?? null) ? $loaded['result']['rows'] : [];
        $result = $this->revalidate($loaded['result'], $rows);

        $blocking = (int) ($result['counts']['blocking'] ?? 0);
        $pending  = (int) ($result['counts']['appendsPending'] ?? 0);

        if ($blocking > 0 || $pending > 0) {
            $this->persistReview($jobs, $jobId, $result);

            $message = $blocking > 0
                ? 'There ' . ($blocking === 1 ? 'is 1 issue' : 'are ' . $blocking . ' issues') . ' still to fix before importing.'
                : 'Choose add or remove for ' . $pending . ' member(s) bound for existing families before importing.';

            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'blocked',
                'message' => $message,
                'review'  => (new ImportReviewPresenter())->build($result, $this->pinnedQrs()),
                'csrf'    => csrf_hash(),
            ]);
        }

        // Keep the corrected rows on disk for the write job (its payload only points at
        // this review job's staging file — never carries the rows through the DB).
        (new ImportStagingStore())->save($jobId, $result);

        try {
            $writeJobId = $jobs->enqueue(
                'family_import',
                ['phase' => 'write', 'stageJobId' => $jobId, 'originalName' => (string) ($result['file'] ?? 'import.xlsx')],
                (int) session()->get('user_id'),
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (Throwable $exception) {
            $this->auditSystemError('queueing a reviewed family import', $exception);

            return $this->jsonError('The import could not be queued. Please try again.', 500);
        }

        // Flip the review job's phase so it can't be re-opened or committed twice
        // (loadReviewJob only accepts phase 'review'). The staging file stays until the
        // write job consumes it.
        $jobs->finish($jobId, 'done', 'Reviewed and imported.', (string) json_encode([
            'phase'  => 'committed',
            'file'   => (string) ($result['file'] ?? 'import.xlsx'),
            'counts' => $result['counts'] ?? [],
        ]));

        return $this->response->setJSON([
            'status'     => 'queued',
            'jobID'      => $writeJobId,
            'statusUrl'  => site_url($this->currentRouteBase() . '/import/status/' . $writeJobId),
            'redirect'   => site_url($this->currentRouteBase()),
            'message'    => 'Import started for ' . (int) ($result['counts']['families'] ?? 0) . ' family group(s).',
            'csrf'       => csrf_hash(),
        ]);
    }

    /**
     * POST `{admin|employee}/manage-family/import/review/(:num)/cancel`: discards a
     * staged import without writing anything.
     */
    public function reviewCancel(int $jobId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to cancel this import.', 403);
        }

        $jobs = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded !== null) {
            (new ImportStagingStore())->delete($jobId);
            $jobs->finish($jobId, 'failed', 'Import cancelled during review.');
        }

        return $this->response->setJSON([
            'status'   => 'ok',
            'redirect' => site_url($this->currentRouteBase()),
            'csrf'     => csrf_hash(),
        ]);
    }

    /**
     * Loads a staged review job by ID, or null when it is missing, the wrong type, or no
     * longer in the review phase (already committed / cancelled).
     *
     * @return array{job: array, result: array}|null
     */
    private function loadReviewJob(JobQueueModel $jobs, int $jobId): ?array
    {
        $job = $jobs->find($jobId);

        if ($job === null || ($job['type'] ?? '') !== 'family_import') {
            return null;
        }

        $summary = json_decode((string) ($job['result_json'] ?? ''), true);

        if (! is_array($summary) || ($summary['phase'] ?? '') !== 'review') {
            return null;
        }

        // The rows + errors live in the staging file, not the DB (they are too big).
        $bundle = (new ImportStagingStore())->load($jobId);

        if ($bundle === null) {
            return null;
        }

        return ['job' => $job, 'result' => $bundle];
    }

    /**
     * Persists an edited review bundle: the big rows/errors to the staging file, and a
     * tiny summary (phase + counts) to job_queue.result_json.
     */
    private function persistReview(JobQueueModel $jobs, int $jobId, array $result): void
    {
        (new ImportStagingStore())->save($jobId, $result);

        $counts = is_array($result['counts'] ?? null) ? $result['counts'] : [];
        $jobs->saveResult($jobId, (string) json_encode([
            'phase'   => 'review',
            'file'    => (string) ($result['file'] ?? 'import.xlsx'),
            'counts'  => $counts,
            'members' => (int) ($counts['members'] ?? 0),
        ]));
    }

    /**
     * Re-runs validation over the (edited) staged rows and returns an updated review
     * result: refreshed rows, errors (file-level errors re-merged) and counts.
     *
     * @param array $result the staged review result
     * @param array $rows   the current row set (possibly edited)
     */
    private function revalidate(array $result, array $rows): array
    {
        $importer      = new FamilyExcelImporter();
        $existingHeads = $importer->existingHeadsForRows($rows);
        $built         = $importer->validateAndBuild($rows, $existingHeads);
        $fileErrors    = is_array($result['fileErrors'] ?? null) ? $result['fileErrors'] : [];
        $errors        = array_merge($fileErrors, $built['errors']);
        $counts        = $importer->summarize($built['families'], $errors, $built['appends']);

        $result['rows']    = $rows;
        $result['errors']  = $errors;
        $result['counts']  = $counts;
        $result['members'] = (int) ($counts['members'] ?? 0);
        $result['phase']   = 'review';

        return $result;
    }
}
