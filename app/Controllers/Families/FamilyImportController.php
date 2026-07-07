<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyExcelTemplate;
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
            return $this->jsonError('The accesscard database is missing required tables from accesscardV1.4.sql.', 422);
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
                ['storedPath' => $storedPath, 'originalName' => $originalName],
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
            'message'   => 'Your file is queued and importing in the background.',
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

        return $this->response->setJSON([
            'status'   => $status,
            'finished' => in_array($status, ['done', 'partial', 'failed'], true),
            'message'  => (string) ($job['message'] ?? ''),
            'progress' => [
                'total'     => $total,
                'processed' => $done,
                'imported'  => $imported,
                'failed'    => $failed,
                'skipped'   => $skipped,
                'members'   => $members,
                'percent'   => $total > 0 ? (int) floor($done * 100 / $total) : 0,
            ],
            'errors'   => array_slice($errors, 0, 200),
            'summary'  => [
                'families' => $imported,
                'members'  => $members,
            ],
            'csrf'     => csrf_hash(),
        ]);
    }
}
