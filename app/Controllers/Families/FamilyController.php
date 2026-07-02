<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyExcelTemplate;
use App\Libraries\FamilyRecordWriteException;
use App\Libraries\FamilyRecordWriter;
use App\Libraries\RoleAccess;
use App\Libraries\SectorIds;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Jobs\JobQueueModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\SearchModel;
use App\Support\FamilyProfilingFormV2;
use App\Support\FamilyRecordPresenter;
use App\Support\MemberFieldNormalizer;
use CodeIgniter\HTTP\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Handles family records for the admin and employee Manage Family screens:
 * creating (store), viewing, editing/updating, and archiving/restoring/deleting.
 *
 * The controller validates the request and delegates database writes to models
 * (MemberModel, MemberServiceModel). The view/edit screens are loaded into the
 * dashboard modal as `?partial=1` HTML fragments by
 * assets/js/dashboard/manage-family-modal.js; the archive/restore/delete forms in
 * `Family/list` post here and redirect back to the list.
 */
class FamilyController extends BaseController
{
    /**
     * Saves a family registration submitted to POST `families` from the family
     * form (admin or employee). Runs in one DB transaction: creates the head in
     * `member`, adds each family member, links chosen services in
     * `member_services`, and logs a FAMILY_CREATED audit row. Frontend: the form
     * usually posts via AJAX, so errors/success are returned as JSON (with a
     * fresh CSRF hash); a non-AJAX fallback redirects back with a flash message.
     */
    public function store()
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'You do not have permission to add family records.',
                        'csrf' => csrf_hash(),
                    ]);
            }

            return $guard;
        }

        $memberModel = new MemberModel();
        $memberServiceModel = new MemberServiceModel();
        $serviceModel = new ServiceModel();
        $auditModel = new AuditTrailsModel();

        if (! $memberModel->hasRequiredFamilyTables()) {
            $message = 'The accesscard database is missing required tables from accesscardV1.4.sql.';

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        if ($this->submissionWasTruncated()) {
            $message = 'The form was too large and some member data was cut off before it reached the server, so nothing was saved. Please add fewer members at a time (or ask an administrator to raise the server\'s max_input_vars) and try again.';

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'code' => 'FORM_TRUNCATED',
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        $entryType = $this->entryType();
        $rules = $this->rulesForEntryType($entryType);

        if (! $this->validate($rules)) {
            $message = implode(' ', $this->validator->getErrors());

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $message);
        }

        $serviceIds = $this->request->getPost('service_ids');

        if (! is_array($serviceIds)) {
            $serviceIds = [];
        }

        $members = $this->request->getPost('members');

        if (! is_array($members)) {
            $members = [];
        }

        $userId = (int) session()->get('user_id');
        $successMessage = 'Family record saved successfully.';

        // Shape the additional members (skipping the form's empty rows) into the
        // [payload + serviceIds] entries FamilyRecordWriter expects.
        $memberPayloads = [];

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberServiceIds = $member['service_ids'] ?? [];

            $memberPayloads[] = [
                'payload' => $this->memberPayloadFromArray($member),
                'serviceIds' => is_array($memberServiceIds) ? array_map('intval', $memberServiceIds) : [],
            ];
        }

        // One family = one transaction. The persistence itself lives in
        // FamilyRecordWriter so the Excel importer reuses the exact same write path.
        $writer = new FamilyRecordWriter($memberModel, $memberServiceModel, $serviceModel, $auditModel);

        $memberModel->beginTransaction();

        try {
            $writer->persistFamily(
                $this->memberPayload('head_'),
                $memberPayloads,
                array_map('intval', $serviceIds),
                $userId,
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (FamilyRecordWriteException $exception) {
            $memberModel->rollbackTransaction();

            return $this->storeError($exception->getMessage());
        }

        $memberModel->completeTransaction();

        if (! $memberModel->transactionStatus()) {
            return $this->storeError('The family form was not saved.', 500);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => $successMessage,
                'csrf' => csrf_hash(),
            ]);
        }

        // Set on a new record save only, never on edit/update.
        return redirect()->back()
            ->with('family_record_saved', '1')
            ->with('success', $successMessage);
    }

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

        if (strtolower((string) $file->getClientExtension()) !== 'xlsx') {
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
        $jobs->ensureTable();

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

    /**
     * GET `{admin|employee}/manage-family/list`: the "Manage Family" sidebar entry.
     * The list itself (with search/filter/pagination) is rendered by the manage-records
     * page, so this redirects to that canonical URL for the caller's role context.
     */
    public function listFamilies(): RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return redirect()->to(site_url($this->isEmployeeContext() ? 'employee/manage-records' : 'admin/manage-records'));
    }

    /**
     * GET `{admin|employee}/manage-family/view/{id}`: returns the read-only family
     * detail fragment for the dashboard modal. Loaded via AJAX with `?partial=1` by
     * manage-family-modal.js; renders `Family/view`.
     */
    public function viewFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyViewAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to view family records.');
        }

        $memberModel = new MemberModel();
        $rows = $memberModel->getFamilyMembers($headId, 'all');
        [$head, $members] = $this->splitHeadAndMembers($rows, $headId);

        if ($head === null) {
            return $this->recordMissing();
        }

        $serviceIdsByMember = (new MemberServiceModel())
            ->getServiceIdsByMemberIds(array_map(static fn (array $row): int => (int) $row['memberID'], $rows));
        $serviceNames = $this->serviceNameMap($serviceIdsByMember);
        $incomeLabels = $this->incomeLabelMap();

        $namesFor = static fn (int $memberId): array => array_values(array_filter(array_map(
            static fn (int $id): string => $serviceNames[$id] ?? '',
            $serviceIdsByMember[$memberId] ?? []
        )));

        $memberViews = [];

        foreach ($members as $member) {
            $memberViews[] = FamilyRecordPresenter::member($member, $namesFor((int) $member['memberID']), $incomeLabels);
        }

        return view('Family/view', [
            'headView'    => FamilyRecordPresenter::head($head, $namesFor($headId), $incomeLabels),
            'memberViews' => $memberViews,
        ]);
    }

    /**
     * GET `{admin|employee}/manage-family/edit/{id}`: returns the family record
     * modal prefilled for editing. Delegates to renderFamilyModal(), the same
     * Bootstrap modal served by createFamily() in update mode.
     */
    public function editFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to edit family records.');
        }

        return $this->renderFamilyModal('update', $headId);
    }

    /**
     * POST `{admin|employee}/manage-family/update/{id}`: saves edits to a family.
     * Runs in one transaction: updates the head, replaces the member list, re-syncs
     * service assignments, and logs a FAMILY_UPDATED audit row. Mirrors store()'s
     * AJAX/non-AJAX response handling.
     */
    public function update(int $headId)
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->request->isAJAX()
                ? $this->jsonError('You do not have permission to edit family records.', 403)
                : $guard;
        }

        $memberModel = new MemberModel();
        $memberServiceModel = new MemberServiceModel();
        $serviceModel = new ServiceModel();
        $auditModel = new AuditTrailsModel();

        if (! $memberModel->hasRequiredFamilyTables()) {
            return $this->failUpdate('The accesscard database is missing required tables from accesscardV1.4.sql.', 422);
        }

        if ($this->submissionWasTruncated()) {
            return $this->failUpdate('The form was too large and some member data was cut off before it reached the server, so nothing was saved. Please edit fewer members at a time (or ask an administrator to raise the server\'s max_input_vars) and try again.', 422, 'FORM_TRUNCATED');
        }

        $existingHead = $memberModel->find($headId);

        if ($existingHead === null || (int) ($existingHead['headID'] ?? 0) !== $headId) {
            return $this->failUpdate('That family record no longer exists.', 404);
        }

        $rules = $this->rulesForEntryType('head');

        if (! $this->validate($rules)) {
            return $this->failUpdate(implode(' ', $this->validator->getErrors()), 422);
        }

        $serviceIds = $this->request->getPost('service_ids');
        $serviceIds = is_array($serviceIds) ? $serviceIds : [];
        $members = $this->request->getPost('members');
        $members = is_array($members) ? $members : [];

        $userId = (int) session()->get('user_id');

        $memberModel->beginTransaction();

        // Snapshot the family's current service IDs before clearing, so archived-but-
        // already-assigned services survive the re-save (they fail the active-only
        // existsById() check, so they must be grandfathered through assignServices()).
        $familyMemberIds = $memberModel->getFamilyMemberIds($headId);
        $grandfatheredServiceIds = $this->collectAssignedServiceIds($memberServiceModel, $familyMemberIds);

        // Clear the family's existing service links and relatives, then rebuild both
        // from the submission so the edit fully replaces the prior member list.
        $memberServiceModel->deleteByMemberIds($familyMemberIds);

        if (! $memberModel->updateHead($headId, $this->memberPayload('head_'))) {
            $memberModel->rollbackTransaction();

            return $this->failUpdate('Head of family could not be updated. Please check required fields.', 422);
        }

        $memberModel->deleteFamilyMembersExceptHead($headId);

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                return $this->failUpdate('One family member could not be saved.', 422);
            }

            if (! $this->assignServices($memberServiceModel, $serviceModel, $memberId, $member['service_ids'] ?? [], $grandfatheredServiceIds)) {
                $memberModel->rollbackTransaction();

                return $this->failUpdate('A selected service could not be assigned to one family member.', 422);
            }
        }

        if (! $this->assignServices($memberServiceModel, $serviceModel, $headId, $serviceIds, $grandfatheredServiceIds)) {
            $memberModel->rollbackTransaction();

            return $this->failUpdate('A selected service could not be assigned to the head of family.', 422);
        }

        if ($auditModel->hasTable()) {
            $headName = trim(trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')));
            $memberCount = is_array($members) ? count($members) : 0;
            $serviceCount = is_array($serviceIds) ? count($serviceIds) : 0;
            $auditModel->logAction(
                $userId,
                $headId,
                'FAMILY_UPDATED',
                'Updated family profile for ' . $headName . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString(),
                'Head of family: ' . $headName . '; ' . $memberCount . ' member(s) in household; '
                    . $serviceCount . ' service(s) on the head after update'
            );
        }

        $memberModel->completeTransaction();

        if (! $memberModel->transactionStatus()) {
            return $this->failUpdate('The family record was not updated.', 500);
        }

        $successMessage = 'Family record updated successfully.';

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => $successMessage,
                'csrf' => csrf_hash(),
            ]);
        }

        return redirect()->to(site_url($this->isEmployeeContext() ? 'employee/manage-records' : 'admin/manage-records'))
            ->with('success', $successMessage);
    }

    /**
     * POST `{admin|employee}/manage-family/archive/{id}`: soft-archives an entire
     * family (Developer/Admin/Employee) and audits it. Frontend: the "Archive"
     * action in the records list; redirects back with a flash message.
     */
    public function archive(int $headId): RedirectResponse
    {
        return $this->changeFamilyState(
            $headId,
            ['Developer', 'Admin'],
            static fn (MemberModel $model): bool => $model->archiveFamily($headId),
            'FAMILY_ARCHIVE',
            'Archived',
            'Record archived successfully.',
            'Unable to archive record.'
        );
    }

    /**
     * POST `{admin|employee}/manage-family/restore/{id}`: restores a soft-archived
     * family (Developer/Admin/Employee) and audits it. Frontend: the "Restore"
     * action on the archived records view.
     */
    public function restore(int $headId): RedirectResponse
    {
        return $this->changeFamilyState(
            $headId,
            ['Developer', 'Admin'],
            static fn (MemberModel $model): bool => $model->restoreFamily($headId),
            'FAMILY_RESTORE',
            'Restored',
            'Record restored successfully.',
            'Unable to restore record.'
        );
    }

    /**
     * Shared flow for the archive/restore/delete actions: role-guards the request,
     * runs the supplied state change on MemberModel, audits it, and redirects back
     * with a success/error flash message.
     *
     * @param list<string> $roles
     */
    private function changeFamilyState(int $headId, array $roles, callable $action, string $auditAction, string $auditVerb, string $successMessage, string $errorMessage): RedirectResponse
    {
        $guard = RoleAccess::requireRole($roles);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new MemberModel();

        if (! $model->hasTable()) {
            return redirect()->to($this->listUrlWithoutDeepSearch())->with('error', 'The family records table is not available.');
        }

        $name = $this->familyHeadName($model, $headId);

        try {
            $changed = $action($model);
        } catch (Throwable $exception) {
            $this->auditSystemError(strtolower($auditVerb) . ' family record #' . $headId, $exception);

            return redirect()->to($this->listUrlWithoutDeepSearch())->with('error', $errorMessage);
        }

        if (! $changed) {
            return redirect()->to($this->listUrlWithoutDeepSearch())->with('error', $errorMessage);
        }

        $auditModel = new AuditTrailsModel();

        if ($auditModel->hasTable()) {
            $auditModel->logAction(
                (int) session()->get('user_id'),
                $headId,
                $auditAction,
                $auditVerb . ' family record ' . $name . ' #' . $headId . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString(),
                $auditVerb . ' the entire family record headed by ' . $name . ' (head #' . $headId . ')'
            );
        }

        return redirect()->to($this->listUrlWithoutDeepSearch())->with('success', $successMessage);
    }

    /**
     * Records a SYSTEM_ERROR audit row for an unexpected failure during a family
     * action, so it surfaces on the audit page (visible to admins). Best-effort —
     * a failure here must never mask the original error.
     */
    private function auditSystemError(string $context, Throwable $exception): void
    {
        try {
            $auditModel = new AuditTrailsModel();

            if (! $auditModel->hasTable()) {
                return;
            }

            $auditModel->logAction(
                (int) session()->get('user_id'),
                null,
                'SYSTEM_ERROR',
                'System error during ' . $context . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString(),
                $exception->getMessage()
            );
        } catch (Throwable $ignored) {
            log_message('error', 'Audit SYSTEM_ERROR skipped: ' . $ignored->getMessage());
        }
    }

    /**
     * Builds a redirect URL from the HTTP Referer but strips the deep-search
     * parameters (`search_scope`, `deep_q`, `deep_page`) so that archiving or
     * restoring a record never lands back on the database-search results panel.
     * Falls back to the clean manage-records page when the Referer is absent or
     * points to a different host.
     */
    private function listUrlWithoutDeepSearch(): string
    {
        $fallback = site_url($this->isEmployeeContext() ? 'employee/manage-records' : 'admin/manage-records');
        $referer  = (string) ($this->request->getServer('HTTP_REFERER') ?? '');

        if ($referer === '') {
            return $fallback;
        }

        $parsed = parse_url($referer);

        if (($parsed['host'] ?? '') !== (string) ($this->request->getServer('HTTP_HOST') ?? '')) {
            return $fallback;
        }

        parse_str($parsed['query'] ?? '', $params);
        unset($params['search_scope'], $params['deep_q'], $params['deep_page']);

        $query = http_build_query($params);

        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '/') . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Splits a family's rows (head + relatives) into [head, members]. The head is
     * the row whose memberID equals its headID; everything else is a relative.
     *
     * @return array{0: ?array, 1: list<array>}
     */
    private function splitHeadAndMembers(array $rows, int $headId): array
    {
        $head = null;
        $members = [];

        foreach ($rows as $row) {
            if ((int) ($row['memberID'] ?? 0) === $headId) {
                $head = $row;
            } else {
                $members[] = $row;
            }
        }

        return [$head, $members];
    }

    /**
     * Maps family-member rows into the per-member array the family form's member
     * template expects (data-name keys), including each member's selected service
     * and sector IDs so the edit form pre-checks them.
     *
     * @param array<int, list<int>> $serviceIdsByMember
     * @return list<array<string, mixed>>
     */
    private function shapeExistingMembers(array $members, array $serviceIdsByMember): array
    {
        return array_map(function (array $member) use ($serviceIdsByMember): array {
            $memberId = (int) ($member['memberID'] ?? 0);

            return [
                'firstname'     => (string) ($member['firstname'] ?? ''),
                'middlename'    => (string) ($member['middlename'] ?? ''),
                'lastname'      => (string) ($member['lastname'] ?? ''),
                'suffix'        => (string) ($member['suffix'] ?? ''),
                'birthday'      => (string) ($member['birthday'] ?? ''),
                'sex'           => (string) ($member['sex'] ?? ''),
                'civilstatus'   => (string) ($member['civilstatus'] ?? ''),
                'contactnumber' => (string) ($member['contactnumber'] ?? ''),
                'religion'      => (string) ($member['religion'] ?? ''),
                'education'     => (string) ($member['education'] ?? ''),
                'job'           => (string) ($member['job'] ?? ''),
                'salary'        => (string) ($member['Salary'] ?? ''),
                'relationship'  => (string) ($member['relationship'] ?? ''),
                'sector_ids'    => SectorIds::normalize($member['sectorID'] ?? null),
                'service_ids'   => $serviceIdsByMember[$memberId] ?? [],
            ];
        }, $members);
    }

    /**
     * Validates and links a set of selected service IDs to one member inside the
     * update transaction. A service is accepted when it is an active service, OR it
     * is in $grandfatheredServiceIds — the set the family already held before this
     * edit — so archived-but-assigned services are preserved rather than dropped.
     * Other invalid/non-existent services are skipped; returns false only when a
     * valid service fails to link (so the caller can roll back).
     *
     * @param list<int> $grandfatheredServiceIds
     */
    private function assignServices(MemberServiceModel $memberServiceModel, ServiceModel $serviceModel, int $memberId, mixed $serviceIds, array $grandfatheredServiceIds = []): bool
    {
        if (! is_array($serviceIds)) {
            return true;
        }

        $grandfathered = array_flip(array_map('intval', $grandfatheredServiceIds));

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId < 0) {
                continue;
            }

            if (! isset($grandfathered[$serviceId]) && ! $serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($memberServiceModel->assignService($memberId, $serviceId) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Flat list of distinct service IDs currently assigned across the given members.
     * Used to grandfather archived-but-assigned services through an update re-save.
     *
     * @param list<int> $memberIds
     * @return list<int>
     */
    private function collectAssignedServiceIds(MemberServiceModel $memberServiceModel, array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $ids = [];

        foreach ($memberServiceModel->getServiceIdsByMemberIds($memberIds) as $serviceIds) {
            foreach ($serviceIds as $serviceId) {
                $ids[] = (int) $serviceId;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Resolves [serviceID => name] across every service assigned to the family. */
    private function serviceNameMap(array $serviceIdsByMember): array
    {
        $allServiceIds = [];

        foreach ($serviceIdsByMember as $ids) {
            foreach ($ids as $id) {
                $allServiceIds[] = (int) $id;
            }
        }

        if ($allServiceIds === []) {
            return [];
        }

        return (new ServiceModel())->getNameMapByIds(array_values(array_unique($allServiceIds)));
    }

    /** Builds an [income bracket value => label] map for the family detail view. */
    private function incomeLabelMap(): array
    {
        $map = [];

        foreach ((new FamilyFormOptionsModel())->getOptions()['income_ranges'] ?? [] as $range) {
            $value = (string) ($range['value'] ?? '');

            if ($value === '') {
                continue;
            }

            $map[$value] = (string) ($range['label'] ?? $value);
        }

        return $map;
    }

    /** First + last name of a family head, for audit descriptions ('record' if missing). */
    private function familyHeadName(MemberModel $model, int $headId): string
    {
        $head = $model->find($headId);

        if ($head === null) {
            return 'record';
        }

        $name = trim((string) ($head['firstname'] ?? '') . ' ' . (string) ($head['lastname'] ?? ''));

        return $name === '' ? 'record' : $name;
    }

    /** True when the current request is under the `employee/` route group. */
    private function isEmployeeContext(): bool
    {
        // uri_string() returns the path relative to baseURL (e.g. "employee/manage-family/
        // update/5"). Using the URI's getPath() here would include the subfolder the app
        // is installed in (e.g. "/binan_accesscard/employee/..."), so the str_starts_with
        // check would fail and an encoder's save would redirect to the admin-only
        // manage-records page ("You do not have access to that page.").
        return str_starts_with(uri_string(), 'employee/');
    }

    /** Route base (`admin/manage-family` or `employee/manage-family`) for the request. */
    private function currentRouteBase(): string
    {
        return $this->isEmployeeContext() ? 'employee/manage-family' : 'admin/manage-family';
    }

    /**
     * For a partial (modal) request whose access guard failed, returns an inline
     * alert fragment so the modal shows the reason; otherwise returns the redirect.
     */
    private function partialGuard(RedirectResponse $guard, string $message): string|RedirectResponse
    {
        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return '<div class="alert alert-danger mb-0">' . esc($message) . '</div>';
        }

        return $guard;
    }

    /** Inline alert fragment shown in the modal when a family record can't be found. */
    private function recordMissing(): string
    {
        return '<div class="alert alert-warning mb-0">That family record could not be found. It may have been removed.</div>';
    }

    /**
     * JSON error body (with a fresh CSRF hash) used by the AJAX update responses.
     * Optional $code adds a machine-readable tag (e.g. 'FORM_TRUNCATED') the
     * frontend can branch on instead of matching the human message text.
     */
    private function jsonError(string $message, int $statusCode, ?string $code = null)
    {
        $body = [
            'status' => 'error',
            'message' => $message,
            'csrf' => csrf_hash(),
        ];

        if ($code !== null) {
            $body['code'] = $code;
        }

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON($body);
    }

    /**
     * Update-failure response: JSON error for AJAX, otherwise a redirect back with
     * the input preserved and an error flash. Used throughout update(). Optional
     * $code is forwarded to the JSON body for the AJAX path.
     */
    private function failUpdate(string $message, int $statusCode, ?string $code = null)
    {
        if ($this->request->isAJAX()) {
            return $this->jsonError($message, $statusCode, $code);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    /**
     * Create-failure response for store(): JSON error for AJAX (status defaults to
     * 422), otherwise a redirect back with input preserved and an error flash.
     * Mirrors the original per-step error handling that lived inline in store().
     */
    private function storeError(string $message, int $statusCode = 422)
    {
        if ($this->request->isAJAX()) {
            return $this->jsonError($message, $statusCode);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    /**
     * Access guard for family entry: allows Developer/Admin/User, otherwise
     * returns a redirect. store() converts this to a 403 JSON for AJAX requests.
     */
    private function requireFamilyEntryAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if (in_array($role, ['Developer', 'Admin', 'Employee'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to add family records.');
    }

    /**
     * Access guard for the READ-ONLY family detail fragment (viewFamily). Same as
     * requireFamilyEntryAccess but also permits the Viewer role — viewers may look
     * at a record but never reach the edit/update/archive/restore actions, which
     * keep the stricter requireFamilyEntryAccess guard.
     */
    private function requireFamilyViewAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if (in_array($role, ['Developer', 'Admin', 'Employee', 'Viewer'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to view family records.');
    }

    /**
     * Builds a `member` table row from prefixed POST fields (e.g. `head_`),
     * normalizing money, optional text, and the multi-select sector IDs. Used for
     * the head of family. Maps form field names to DB column names.
     */
    private function memberPayload(string $prefix): array
    {
        return [
            'firstname' => $this->cleanName($this->request->getPost($prefix . 'firstname')),
            'middlename' => $this->cleanName($this->request->getPost($prefix . 'middlename')),
            'lastname' => $this->cleanName($this->request->getPost($prefix . 'lastname')),
            'suffix' => $this->nullableText($this->request->getPost($prefix . 'suffix')),
            'birthday' => $this->request->getPost($prefix . 'birthday'),
            'civilstatus' => $this->nullableText($this->request->getPost($prefix . 'civilstatus')),
            'sex' => $this->nullableText($this->request->getPost($prefix . 'sex')),
            'education' => $this->nullableText($this->request->getPost($prefix . 'education')),
            'job' => $this->nullableText($this->request->getPost($prefix . 'job')),
            'Salary' => $this->moneyOrNull($this->request->getPost($prefix . 'salary')),
            'contactnumber' => $this->nullableText($this->request->getPost($prefix . 'contactnumber')),
            'religion' => $this->nullableText($this->request->getPost($prefix . 'religion')),
            'address' => $this->combineAddressBarangay(
                $this->request->getPost($prefix . 'address'),
                $this->request->getPost($prefix . 'barangay')
            ),
            'relationship' => $prefix === 'head_' ? 'Head' : $this->nullableText($this->request->getPost($prefix . 'relationship')),
            'sectorID' => SectorIds::normalize($this->request->getPost('sector_ids')),
        ];
    }

    /**
     * Combines the separate Address and Barangay form inputs into the single
     * `member.address` column ("address, barangay"). The schema has no barangay
     * column; barangay is kept only as a form field for entry/editing.
     */
    private function combineAddressBarangay(mixed $address, mixed $barangay): ?string
    {
        return MemberFieldNormalizer::combineAddressBarangay($address, $barangay);
    }

    /**
     * Inverse of combineAddressBarangay(): splits a stored address back into its
     * address + barangay parts so the edit form can prefill both inputs. Matches the
     * trailing barangay against the canonical list (longest match first so
     * "Binan (Poblacion)" wins over "Poblacion").
     *
     * @return array{address: string, barangay: string}
     */
    private function splitAddressBarangay(mixed $combined): array
    {
        $combined = trim((string) $combined);
        $barangays = FamilyProfilingFormV2::barangays();
        usort($barangays, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($barangays as $barangay) {
            $suffix = ', ' . $barangay;

            if (mb_strlen($combined) >= mb_strlen($suffix)
                && strcasecmp(mb_substr($combined, -mb_strlen($suffix)), $suffix) === 0) {
                return [
                    'address' => rtrim(mb_substr($combined, 0, mb_strlen($combined) - mb_strlen($suffix))),
                    'barangay' => $barangay,
                ];
            }

            if (strcasecmp($combined, $barangay) === 0) {
                return ['address' => '', 'barangay' => $barangay];
            }
        }

        return ['address' => $combined, 'barangay' => ''];
    }

    /**
     * Detects a POST silently truncated by PHP's max_input_vars. The family form
     * posts a trailing `_form_end` sentinel (the first field dropped when the limit
     * is hit, since it is last in the body) and an early `members_meta_count` the
     * client sets to its live member-row count. If the sentinel is missing, or fewer
     * member rows arrived than the client promised, the submission was cut short —
     * the caller must reject it so no partial family record is ever saved.
     */
    private function submissionWasTruncated(): bool
    {
        if (strtolower((string) $this->request->getMethod()) !== 'post') {
            return false;
        }

        if ((string) $this->request->getPost('_form_end') !== '1') {
            return true;
        }

        $expected = (int) $this->request->getPost('members_meta_count');
        $members = $this->request->getPost('members');
        $received = is_array($members) ? count($members) : 0;

        return $received < $expected;
    }

    /**
     * Reads the `entry_type` POST flag to decide whether this submission is a new
     * head ('head') or an added member ('member'); drives which rules apply.
     */
    private function entryType(): string
    {
        return (string) $this->request->getPost('entry_type') === 'member' ? 'member' : 'head';
    }

    /**
     * Returns the validation ruleset for the given entry type. Members require a
     * parent head id and member name fields; heads require name/birthday/sex plus
     * civil status, education, job, monthly income, address and barangay. Sectors are
     * optional, but any IDs supplied must be well-formed.
     */
    private function rulesForEntryType(string $entryType): array
    {
        $rules = [
            'sector_ids' => 'permit_empty|valid_sector_array',
        ];

        if ($entryType === 'member') {
            return $rules + [
                'family_head_id' => 'required|is_natural_no_zero',
                'member_firstname' => 'required|max_length[100]',
                'member_lastname' => 'required|max_length[100]',
                'member_middlename' => 'permit_empty|max_length[50]',
                'member_birthday' => 'permit_empty|valid_date[Y-m-d]',
                'member_sex' => 'permit_empty|in_list[Male,Female]',
            ];
        }

        return $rules + [
            'head_firstname' => 'required|max_length[100]',
            'head_middlename' => 'permit_empty|max_length[50]',
            'head_lastname' => 'required|max_length[100]',
            'head_birthday' => 'required|valid_date[Y-m-d]',
            'head_sex' => 'required|in_list[Male,Female]',
            'head_civilstatus' => 'required',
            'head_education' => 'required',
            'head_job' => 'required',
            'head_salary' => 'required',
            'head_address' => 'required|max_length[255]',
            'head_barangay' => 'required|max_length[100]',
        ];
    }

    /**
     * Like memberPayload() but builds a `member` row from one entry of the
     * repeated `members[]` array (additional family members) instead of prefixed
     * POST fields. Falls back to the form's sector selection when a member has none.
     */
    private function memberPayloadFromArray(array $member): array
    {
        return [
            'firstname' => $this->cleanName($member['firstname'] ?? ''),
            'middlename' => $this->cleanName($member['middlename'] ?? ''),
            'lastname' => $this->cleanName($member['lastname'] ?? ''),
            'suffix' => $this->nullableText($member['suffix'] ?? null),
            'birthday' => $member['birthday'] ?? null,
            'civilstatus' => $this->nullableText($member['civilstatus'] ?? null),
            'sex' => $this->nullableText($member['sex'] ?? null),
            'education' => $this->nullableText($member['education'] ?? null),
            'job' => $this->nullableText($member['job'] ?? null),
            'Salary' => $this->moneyOrNull($member['salary'] ?? null),
            'contactnumber' => $this->nullableText($member['contactnumber'] ?? null),
            'religion' => $this->nullableText($member['religion'] ?? null),
            'address' => $this->combineAddressBarangay(
                $this->request->getPost('head_address'),
                $this->request->getPost('head_barangay')
            ),
            'relationship' => $this->nullableText($member['relationship'] ?? 'Member'),
            'sectorID' => SectorIds::normalize($member['sector_ids'] ?? $this->request->getPost('sector_ids')),
        ];
    }

    /**
     * True only when a `members[]` row has at least a first and last name, so the
     * form's empty extra-member rows are skipped instead of saved.
     */
    private function hasMemberData(array $member): bool
    {
        return trim((string) ($member['firstname'] ?? '')) !== ''
            && trim((string) ($member['lastname'] ?? '')) !== '';
    }

    /**
     * Parses a salary input into a float, stripping thousands separators, or null
     * when blank. Keeps the `Salary` column numeric/nullable.
     */
    private function moneyOrNull(mixed $value): ?float
    {
        return MemberFieldNormalizer::moneyOrNull($value);
    }

    /**
     * Trims a value to a string, returning null when empty so optional columns
     * store NULL rather than ''. Used throughout the payload builders.
     */
    private function nullableText(mixed $value): ?string
    {
        return MemberFieldNormalizer::nullableText($value);
    }

    /**
     * Cleans a person-name field on save/update: keeps only letters (incl. ñ/Ñ and
     * accents), spaces and the - ' . punctuation real names use, collapses repeated
     * whitespace, then applies Title Case (first letter of each word capitalized).
     * Workers may type freely; the stored value is normalized here. Used for
     * first/middle/last names of head and members.
     */
    private function cleanName(mixed $value): string
    {
        return MemberFieldNormalizer::cleanName($value);
    }

    /**
     * Cleans an address/barangay field on save/update: address-safe allowlist of
     * letters, digits, spaces and # , . - / ' ( ) & (so house/block numbers survive),
     * collapses repeated whitespace, then applies Title Case. Strips odd symbols such
     * as < > | \ " : ] [.
     */
    private function cleanAddress(mixed $value): string
    {
        return MemberFieldNormalizer::cleanAddress($value);
    }

    // ---------------------------------------------------------------------------
    // Server-side DataTables list (GET {role}/manage-family/data)
    //
    // Powers the Manage Records DataTable (assets/js/dashboard/family-datatable.js).
    // Reuses the existing, untouched search models: MemberModel::searchFamilies()
    // for the family-heads scope and SearchModel::allMembers() for the whole-database
    // scope. Both are called with the optional, append-only $orderKey/$orderDirection
    // arguments for column sorting; everything else is the same query used elsewhere.
    // ---------------------------------------------------------------------------

    /** Returns the server-side DataTables payload for Manage Records. */
    public function dataTable()
    {
        $draw = max(0, (int) $this->request->getGet('draw'));
        $guard = $this->requireFamilyViewAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON($this->dataTablePayload($draw, 0, 0, [], 'You do not have permission to view family records.'));
        }

        $start = max(0, (int) $this->request->getGet('start'));
        $requestedLength = (int) $this->request->getGet('length');
        $length = in_array($requestedLength, [10, 25, 50, 100], true) ? $requestedLength : 25;
        $scope = strtolower(trim((string) $this->request->getGet('scope'))) === 'all' ? 'all' : 'heads';
        $keyword = trim((string) $this->request->getGet('q'));
        $dataTablesSearch = $this->request->getGet('search');

        if ($keyword === '' && is_array($dataTablesSearch)) {
            $keyword = trim((string) ($dataTablesSearch['value'] ?? ''));
        }

        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['all', 'active', 'archived'], true) ? $status : 'all';
        $filters = [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
        ];
        [$orderKey, $orderDirection] = $this->dataTableOrder();

        try {
            if ($scope === 'all') {
                $searchModel = new SearchModel();
                $searchFilters = array_merge(['status' => $status], $filters);
                $total = $searchModel->countAllMembers('', ['status' => 'all']);
                $filtered = $searchModel->countAllMembers($keyword, $searchFilters);
                $rows = $searchModel->allMembers($keyword, $searchFilters, $length, $start, $orderKey, $orderDirection);
            } else {
                $memberModel = new MemberModel();
                $searchKeyword = $keyword === '' ? null : $keyword;
                $total = $memberModel->countSearchFamilies(null, 'all');
                $filtered = $memberModel->countSearchFamilies($searchKeyword, $status, $filters);
                $rows = $memberModel->searchFamilies($searchKeyword, $length, $start, $status, $filters, $orderKey, $orderDirection);
            }

            $sectorShortcodes = $this->dataTableSectorShortcodes();
            $data = array_map(
                fn (array $row): array => $this->dataTableRow($row, $scope === 'all', $sectorShortcodes),
                $rows
            );

            return $this->response->setJSON($this->dataTablePayload($draw, $total, $filtered, $data));
        } catch (Throwable $exception) {
            $this->auditSystemError('loading the family records table', $exception);

            return $this->response
                ->setStatusCode(500)
                ->setJSON($this->dataTablePayload($draw, 0, 0, [], 'Unable to load family records.'));
        }
    }

    /**
     * Reads the DataTables order[] request into a [columnKey, direction] pair.
     * Only the name/address/birthday columns are sortable; everything else falls
     * back to the name column. The `date` parameter is intentionally NOT consulted.
     *
     * @return array{0: string, 1: string}
     */
    private function dataTableOrder(): array
    {
        $order = $this->request->getGet('order');

        // No column sort requested (the table's default) -> newest records first, so
        // a just-added or just-imported family is visible at the top of the list
        // instead of being sorted by surname into a large dataset. 'newest' is
        // unrecognized by applyMemberOrder(), which falls back to memberID DESC.
        if (! is_array($order) || ! isset($order[0]) || ! is_array($order[0])) {
            return ['newest', 'desc'];
        }

        $firstOrder = $order[0];
        $column = (int) ($firstOrder['column'] ?? 0);
        $direction = strtolower((string) ($firstOrder['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderKey = match ($column) {
            2 => 'address',
            3 => 'birthday',
            default => 'name',
        };

        return [$orderKey, $direction];
    }

    /** [sectorID => SHORTCODE] map for rendering the DataTable's Sector column. */
    private function dataTableSectorShortcodes(): array
    {
        $map = [];

        foreach ((new SectorModel())->getSectorOptions() as $sector) {
            $sectorId = (int) ($sector['sectorID'] ?? $sector['id'] ?? 0);
            $shortcode = trim((string) ($sector['shortcode'] ?? ''));

            if ($sectorId > 0 && $shortcode !== '') {
                $map[$sectorId] = mb_strtoupper($shortcode);
            }
        }

        return $map;
    }

    /**
     * Shapes one member row into the DataTables cell map the client expects
     * (name HTML, sector shortcodes, address, birthday, actions dropdown).
     *
     * @param array<int, string> $sectorShortcodes
     */
    private function dataTableRow(array $row, bool $allMembersScope, array $sectorShortcodes): array
    {
        $memberId = (int) ($row['memberID'] ?? 0);
        $headId = $allMembersScope ? (int) ($row['headID'] ?? $memberId) : $memberId;
        $name = $this->dataTableDisplayName($row);
        $relationship = trim((string) ($row['relationship'] ?? ''));
        $nameHtml = '<span class="entity-title">' . esc(mb_strtoupper($name)) . '</span>';

        if ($allMembersScope && $relationship !== '') {
            $nameHtml .= '<small class="text-muted d-block">' . esc(mb_strtoupper($relationship)) . '</small>';
        }

        $sectors = [];

        foreach (SectorIds::normalize($row['sectorID'] ?? null) as $sectorId) {
            if (isset($sectorShortcodes[$sectorId])) {
                $sectors[] = $sectorShortcodes[$sectorId];
            }
        }

        $birthday = strtotime((string) ($row['birthday'] ?? ''));

        return [
            'name' => $nameHtml,
            'sector' => esc(implode(', ', array_values(array_unique($sectors)))),
            'address' => esc(mb_strtoupper((string) ($row['address'] ?? ''))),
            'birthday' => $birthday === false ? '-' : date('Y-m-d', $birthday),
            'actions' => $this->dataTableActions($row, $headId, $name),
        ];
    }

    /** "Surname Suffix, Firstname M." display name for a member row. */
    private function dataTableDisplayName(array $row): string
    {
        $lastName = trim((string) ($row['lastname'] ?? ''));
        $suffix = trim((string) ($row['suffix'] ?? ''));
        $firstName = trim((string) ($row['firstname'] ?? ''));
        $middleName = trim((string) ($row['middlename'] ?? ''));
        $surname = trim($lastName . ($suffix !== '' ? ' ' . $suffix : ''));
        $givenName = trim($firstName . ($middleName !== '' ? ' ' . mb_substr($middleName, 0, 1) . '.' : ''));

        return $surname !== '' && $givenName !== '' ? $surname . ', ' . $givenName : trim($surname . ' ' . $givenName);
    }

    /**
     * Builds the per-row Actions dropdown HTML for the DataTable. View is shown to
     * any viewer; Update only to entry-access roles (Developer/Admin/Employee);
     * Archive/Restore only to Developer/Admin. Empty string hides the menu.
     */
    private function dataTableActions(array $row, int $headId, string $displayName): string
    {
        if ($headId <= 0) {
            return '';
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));
        $canEdit = in_array($role, ['Developer', 'Admin', 'Employee'], true);
        $canArchive = in_array($role, ['Developer', 'Admin'], true);
        $archived = trim((string) ($row['dt_deleted'] ?? '')) !== '';

        if ($archived && ! $canArchive) {
            return '';
        }

        $routeBase = $this->dataTableRouteBase();

        // The trigger markup (modal callers + archive/restore form) lives in the
        // view; this controller only supplies the permission flags and URLs.
        return view('Family/row-actions', [
            'archived'       => $archived,
            'canEdit'        => $canEdit,
            'canArchive'     => $canArchive,
            'displayName'    => $displayName,
            'viewUrl'        => $archived ? '' : site_url($routeBase . '/view/' . $headId . '?partial=1'),
            'updateUrl'      => (! $archived && $canEdit) ? site_url($routeBase . '/create?partial=1&mode=update&id=' . $headId) : '',
            'formAction'     => $canArchive ? site_url($routeBase . '/' . ($archived ? 'restore' : 'archive') . '/' . $headId) : '',
            'actionLabel'    => $archived ? 'Restore' : 'Archive',
            'actionPast'     => $archived ? 'restored' : 'archived',
            'confirmMessage' => $archived
                ? 'Restore this record to the active list?'
                : 'Archive this record? This keeps the record in the database, marks it as archived, and hides it from active lists.',
        ]);
    }

    /** Role-aware route base for the DataTable action URLs. */
    private function dataTableRouteBase(): string
    {
        if (str_starts_with(uri_string(), 'employee/')) {
            return 'employee/manage-family';
        }

        if (str_starts_with(uri_string(), 'viewer/')) {
            return 'viewer/manage-family';
        }

        return 'admin/manage-family';
    }

    /** Standard DataTables JSON envelope (+ optional error message). */
    private function dataTablePayload(int $draw, int $total, int $filtered, array $data, ?string $error = null): array
    {
        $payload = [
            'draw' => $draw,
            'recordsTotal' => max(0, $total),
            'recordsFiltered' => max(0, $filtered),
            'data' => $data,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        return $payload;
    }

    // ---------------------------------------------------------------------------
    // Bootstrap Add / Update modal (GET {role}/manage-family/create[?mode=update&id=])
    // ---------------------------------------------------------------------------

    /**
     * GET `{admin|employee}/manage-family/create`: returns the Bootstrap family
     * record modal fragment loaded by manage-family-modal.js. `?mode=update&id=`
     * prefills it for editing; otherwise it is a blank Add form. The form posts to
     * the existing, untouched store()/update() endpoints.
     */
    public function createFamily(): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to open the family record form.');
        }

        $isUpdateMode = strtolower(trim((string) $this->request->getGet('mode'))) === 'update';
        $headId = max(0, (int) $this->request->getGet('id'));

        return $this->renderFamilyModal($isUpdateMode ? 'update' : 'create', $headId);
    }

    /**
     * Shared renderer for the Add/Update family modal. In create mode it serves a
     * blank form pointed at `families` (store). In update mode it prefills the head,
     * existing members, and selected (incl. grandfathered/archived) sectors/services,
     * pointed at the role's update/{id} endpoint.
     */
    private function renderFamilyModal(string $mode, int $headId): string|RedirectResponse
    {
        if ($mode === 'update') {
            $memberModel = new MemberModel();
            $rows = $memberModel->getFamilyMembers($headId, 'all');
            [$head, $members] = $this->splitHeadAndMembers($rows, $headId);

            if ($head === null) {
                return $this->recordMissing();
            }

            $serviceIdsByMember = (new MemberServiceModel())
                ->getServiceIdsByMemberIds(array_map(static fn (array $row): int => (int) $row['memberID'], $rows));

            // Gather assigned sectors/services so archived-but-assigned items stay
            // visible/checked on the form (grandfathering), matching the old editFamily().
            $assignedSectorIds = [];
            foreach ($rows as $row) {
                foreach (SectorIds::normalize($row['sectorID'] ?? null) as $sectorId) {
                    $assignedSectorIds[] = (int) $sectorId;
                }
            }

            $assignedServiceIds = [];
            foreach ($serviceIdsByMember as $ids) {
                foreach ($ids as $id) {
                    $assignedServiceIds[] = (int) $id;
                }
            }

            $viewData = (new FamilyFormOptionsModel())->getViewDataForEdit(
                array_values(array_unique($assignedSectorIds)),
                array_values(array_unique($assignedServiceIds))
            );

            return view('Family/family-modal', array_merge(
                $viewData,
                $this->familyModalUpdateData($head, $serviceIdsByMember[$headId] ?? []),
                [
                    'action' => site_url($this->currentRouteBase() . '/update/' . $headId),
                    'fieldPrefix' => 'family-update',
                    'modalTitle' => 'Update Family Record',
                    'modalMode' => 'update',
                    'submitLabel' => 'Update',
                    'saveDisabled' => false,
                    'existingMembers' => $this->shapeModalMembers($members, $serviceIdsByMember),
                ]
            ));
        }

        $viewData = (new FamilyFormOptionsModel())->getViewData();

        return view('Family/family-modal', array_merge(
            $viewData,
            [
                'action' => site_url('families'),
                'fieldPrefix' => 'family-add',
                'modalTitle' => 'New Family Record',
                'modalMode' => 'create',
                'submitLabel' => 'Save',
                'headId' => 0,
                'saveDisabled' => false,
                'existingMembers' => [],
            ]
        ));
    }

    /**
     * Builds the head prefill block (formValues + selected sector/service IDs) for
     * the Update modal. Splits the stored "address, barangay" back into the two
     * separate inputs via splitAddressBarangay().
     *
     * @param list<int> $headServiceIds
     */
    private function familyModalUpdateData(array $head, array $headServiceIds): array
    {
        $headId = (int) ($head['memberID'] ?? 0);
        $addressParts = $this->splitAddressBarangay($head['address'] ?? '');

        return [
            'headId' => $headId,
            'formValues' => [
                'head_lastname' => (string) ($head['lastname'] ?? ''),
                'head_firstname' => (string) ($head['firstname'] ?? ''),
                'head_middlename' => (string) ($head['middlename'] ?? ''),
                'head_suffix' => (string) ($head['suffix'] ?? ''),
                'head_birthday' => (string) ($head['birthday'] ?? ''),
                'head_sex' => (string) ($head['sex'] ?? ''),
                'head_civilstatus' => (string) ($head['civilstatus'] ?? ''),
                'head_contactnumber' => (string) ($head['contactnumber'] ?? ''),
                'head_religion' => (string) ($head['religion'] ?? ''),
                'head_education' => (string) ($head['education'] ?? ''),
                'head_job' => (string) ($head['job'] ?? ''),
                'head_salary' => (string) ($head['Salary'] ?? ''),
                'head_address' => $addressParts['address'],
                'head_barangay' => $addressParts['barangay'],
            ],
            'selectedSectorIds' => array_map('strval', SectorIds::normalize($head['sectorID'] ?? null)),
            'selectedServiceIds' => array_map('strval', $headServiceIds),
        ];
    }

    /**
     * Shapes existing family-member rows for the Update modal so they pre-render
     * (and re-post) — otherwise update()'s member replace would drop them.
     *
     * @param array<int, list<int>> $serviceIdsByMember
     * @return list<array<string, mixed>>
     */
    private function shapeModalMembers(array $members, array $serviceIdsByMember): array
    {
        return array_map(function (array $member) use ($serviceIdsByMember): array {
            $memberId = (int) ($member['memberID'] ?? 0);

            return [
                'lastname' => (string) ($member['lastname'] ?? ''),
                'firstname' => (string) ($member['firstname'] ?? ''),
                'middlename' => (string) ($member['middlename'] ?? ''),
                'suffix' => (string) ($member['suffix'] ?? ''),
                'birthday' => (string) ($member['birthday'] ?? ''),
                'sex' => (string) ($member['sex'] ?? ''),
                'civilstatus' => (string) ($member['civilstatus'] ?? ''),
                'contactnumber' => (string) ($member['contactnumber'] ?? ''),
                'religion' => (string) ($member['religion'] ?? ''),
                'education' => (string) ($member['education'] ?? ''),
                'job' => (string) ($member['job'] ?? ''),
                'salary' => (string) ($member['Salary'] ?? ''),
                'relationship' => (string) ($member['relationship'] ?? ''),
                'sector_ids' => array_map('strval', SectorIds::normalize($member['sectorID'] ?? null)),
                'service_ids' => array_map('strval', $serviceIdsByMember[$memberId] ?? []),
            ];
        }, $members);
    }

}
