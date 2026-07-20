<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyExcelImporter;
use App\Libraries\FamilyExcelTemplate;
use App\Libraries\ImportFamilyModalBuilder;
use App\Libraries\ImportReviewPresenter;
use App\Libraries\ImportStagingStore;
use App\Models\Families\MemberModel;
use App\Models\Jobs\JobQueueModel;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;
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

        // One operator, one open review: uploading a file retires any review they left
        // staged. Typical flow is upload -> read the errors -> fix the .xlsx -> upload
        // again; without this the first upload's staging file (family PII, up to several
        // MB) is orphaned on disk with nothing to ever delete it. Runs after the enqueue
        // so a failed enqueue leaves the previous review intact.
        $this->retirePreviousReviews($jobs, (int) session()->get('user_id'), $jobId);

        return $this->response->setJSON([
            'status'    => 'queued',
            'jobID'     => $jobId,
            'statusUrl' => site_url($this->currentRouteBase() . '/import/status/' . $jobId),
            'message'   => 'Your file is queued and being checked for problems.',
            'csrf'      => csrf_hash(),
        ]);
    }

    /**
     * Discards the staging files of this user's earlier, never-committed reviews and marks
     * those jobs terminal, so they can no longer be re-opened or committed.
     *
     * $keepJobId is the upload that just queued — it is still `pending`, so it is never
     * returned as a staged review, but it is excluded explicitly all the same.
     */
    private function retirePreviousReviews(JobQueueModel $jobs, int $userId, int $keepJobId): void
    {
        $store = new ImportStagingStore();

        foreach ($jobs->stagedReviewIds($userId) as $priorId) {
            if ($priorId === $keepJobId) {
                continue;
            }

            $store->delete($priorId);
            $jobs->finish($priorId, 'failed', 'Replaced by a newer upload.', (string) json_encode(['phase' => 'superseded']));
        }
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
            // This page is a standalone shell (not a dashboard layout), so it has to wire
            // up the idle-timeout logout itself — otherwise sitting on the review screen
            // never times out.
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
        ]);
    }

    /**
     * POST `{admin|employee}/manage-family/import/review/(:num)/commit`: re-validates the
     * staged batch and, only when no blocking issues remain, queues the write job that
     * persists the families. Returns that job's status URL for the progress toast.
     *
     * Nothing is edited in the browser — the spreadsheet is the source of truth — so this
     * only ever confirms or refuses what was uploaded.
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

        if ($blocking > 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'blocked',
                'message' => 'There ' . ($blocking === 1 ? 'is 1 issue' : 'are ' . $blocking . ' issues')
                    . ' to fix. Correct them in the spreadsheet and upload it again.',
                'review'  => (new ImportReviewPresenter())->build($result),
                'csrf'    => csrf_hash(),
            ]);
        }

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
     * GET `{admin|employee}/manage-family/import/review/(:num)/family?fno=<qr>`: the shared
     * Add/Update family modal, prefilled from the staged rows of one QR group so the operator
     * fixes it in the browser instead of editing the .xlsx and re-uploading. The QR group is a
     * query param (a raw QR cell is not URL-path-safe). Posts to reviewFamilySave().
     */
    public function reviewFamilyModal(int $jobId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to edit import records.');
        }

        $jobs   = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded === null) {
            return '<div class="alert alert-danger mb-0">That import is no longer available to review.</div>';
        }

        $builder = new ImportFamilyModalBuilder();
        $action  = site_url($this->currentRouteBase() . '/import/review/' . $jobId . '/family/save');

        // A blank-QR row is keyed by its sheet row (?row=), since it has no QR to key by.
        $row = (int) $this->request->getGet('row');

        if ($row > 0) {
            return view('Family/family-modal', $builder->viewDataForRow($loaded['result'], $row, $action));
        }

        // The QR group is passed as a query param, not a path segment: a QR cell can hold any
        // raw text (a negative number, "N/A", "5880.0", a slash) that is not URL-path-safe.
        $familyNo = trim((string) $this->request->getGet('fno'));

        if ($familyNo === '') {
            return '<div class="alert alert-danger mb-0">No family was selected to edit.</div>';
        }

        return view('Family/family-modal', $builder->viewData($loaded['result'], $familyNo, $action));
    }

    /**
     * POST `{admin|employee}/manage-family/import/review/(:num)/family/save`: replaces one QR
     * group (from the POST's import_family_no) with the modal's submitted values, re-validates
     * the whole batch, re-stages it, and returns the refreshed review report. Mirrors
     * store()/update()'s JSON success contract so the shared modal submit handler reuses it.
     */
    public function reviewFamilySave(int $jobId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to edit import records.', 403);
        }

        $jobs   = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded === null) {
            return $this->jsonError('That import is no longer available to review.', 404);
        }

        $bundle  = $loaded['result'];
        $builder = new ImportFamilyModalBuilder();
        $post    = $this->request->getPost();

        // A blank-QR row is keyed by import_row; a normal family by import_family_no.
        $row      = (int) ($post['import_row'] ?? 0);
        $familyNo = trim((string) ($post['import_family_no'] ?? ''));

        if ($row > 0) {
            $rows = $this->replaceRows($bundle, ['row' => $row], $builder->toStagedRowsForRow($post, $bundle, $row));
        } elseif ($familyNo !== '') {
            $rows = $this->replaceRows($bundle, ['fno' => $familyNo], $builder->toStagedRows($post, $bundle, $familyNo));
        } else {
            return $this->jsonError('No family was selected to edit.', 422);
        }

        $result = $this->revalidate($bundle, $rows);
        $this->restageReview($jobId, $result);

        $newQr = trim((string) ($post['qr_control_no'] ?? ''));

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => $newQr !== '' ? 'Saved family ' . $newQr . '.' : 'Changes saved.',
            'review'  => (new ImportReviewPresenter())->build($result),
            'csrf'    => csrf_hash(),
        ]);
    }

    /**
     * POST `{admin|employee}/manage-family/import/review/(:num)/family/remove`: drops one QR
     * group (from the POST's import_family_no) from the staged batch, re-validates the rest,
     * re-stages, and returns the refreshed report.
     */
    public function reviewFamilyRemove(int $jobId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->jsonError('You do not have permission to edit import records.', 403);
        }

        $jobs   = new JobQueueModel();
        $loaded = $jobs->hasTable() ? $this->loadReviewJob($jobs, $jobId) : null;

        if ($loaded === null) {
            return $this->jsonError('That import is no longer available to review.', 404);
        }

        $bundle   = $loaded['result'];
        $row      = (int) ($this->request->getPost('import_row') ?? 0);
        $familyNo = trim((string) ($this->request->getPost('import_family_no') ?? ''));

        if ($row > 0) {
            $rows    = $this->replaceRows($bundle, ['row' => $row], []);
            $message = 'Row ' . $row . ' removed from this import.';
        } elseif ($familyNo !== '') {
            $rows    = $this->replaceRows($bundle, ['fno' => $familyNo], []);
            $message = 'Family ' . $familyNo . ' removed from this import.';
        } else {
            return $this->jsonError('No family was selected to remove.', 422);
        }

        $result = $this->revalidate($bundle, $rows);
        $this->restageReview($jobId, $result);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => $message,
            'review'  => (new ImportReviewPresenter())->build($result),
            'csrf'    => csrf_hash(),
        ]);
    }

    /**
     * Returns the bundle's rows with the targeted rows swapped for $replacement (an empty
     * replacement just drops them), ordered by sheet row. The target is either a whole QR
     * group (`['fno' => qr]`) or a single blank-QR sheet row (`['row' => n]`).
     *
     * @param array{fno?: string, row?: int} $key
     * @param list<array>                    $replacement
     * @return list<array>
     */
    private function replaceRows(array $bundle, array $key, array $replacement): array
    {
        $rows = is_array($bundle['rows'] ?? null) ? $bundle['rows'] : [];

        if (isset($key['row'])) {
            $target = (int) $key['row'];
            $kept   = array_values(array_filter($rows, static fn (array $row): bool =>
                (int) ($row['sheetRow'] ?? -1) !== $target));
        } else {
            $familyNo = (string) ($key['fno'] ?? '');
            $kept     = array_values(array_filter($rows, static fn (array $row): bool =>
                trim((string) (($row['data'] ?? [])['familyno'] ?? '')) !== $familyNo));
        }

        $merged = array_merge($kept, $replacement);

        usort($merged, static fn (array $a, array $b): int =>
            ((int) ($a['sheetRow'] ?? 0)) <=> ((int) ($b['sheetRow'] ?? 0)));

        return $merged;
    }

    /** Persists a re-validated review result back to the job's staging file. */
    private function restageReview(int $jobId, array $result): void
    {
        (new ImportStagingStore())->save($jobId, [
            'phase'      => 'review',
            'file'       => (string) ($result['file'] ?? 'import.xlsx'),
            'rows'       => $result['rows'] ?? [],
            'errors'     => $result['errors'] ?? [],
            'fileErrors' => $result['fileErrors'] ?? [],
            'columns'    => $result['columns'] ?? [],
            'counts'     => $result['counts'] ?? [],
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
     * Re-runs validation over the (edited) staged rows and returns an updated review
     * result: refreshed rows, errors (file-level errors re-merged) and counts.
     *
     * @param array $result the staged review result
     * @param array $rows   the current row set (possibly edited)
     */
    private function revalidate(array $result, array $rows): array
    {
        $importer       = new FamilyExcelImporter();
        $existingHeads  = $importer->existingHeadsForRows($rows);
        $existingPeople = $importer->existingPeopleForRows($rows);
        $built          = $importer->validateAndBuild($rows, $existingHeads, $existingPeople);
        $fileErrors     = is_array($result['fileErrors'] ?? null) ? $result['fileErrors'] : [];
        $errors         = array_merge($fileErrors, $built['errors']);
        $counts         = $importer->summarize($built['families'], $errors, $built['appends']);

        $result['rows']    = $rows;
        $result['errors']  = $errors;
        $result['counts']  = $counts;
        $result['members'] = (int) ($counts['members'] ?? 0);
        $result['phase']   = 'review';

        return $result;
    }
}
