<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyModalDataBuilder;
use App\Libraries\FamilyRecordWriteException;
use App\Libraries\FamilyRecordWriter;
use App\Libraries\RoleAccess;
use App\Libraries\SectorIds;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Support\FamilyRecordPresenter;
use App\Support\MemberFieldNormalizer;
use CodeIgniter\HTTP\RedirectResponse;
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
    use FamilyRequestContext;

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
            $message = 'The accesscard database is missing required tables from accesscardV14.sql.';

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

        if ($incomplete = $this->firstIncompleteMember($members)) {
            return $this->storeError($incomplete);
        }

        $userId = (int) session()->get('user_id');
        $successMessage = 'Family record saved successfully.';

        $controlNo = (int) $this->request->getPost('qr_control_no');

        if (model(\App\Models\Scanner\QrControlModel::class)->takenByOtherHead($controlNo, 0)) {
            return $this->storeError('QR Number ' . $controlNo . ' is already assigned to another family.');
        }

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
                $this->request->getUserAgent()->getAgentString(),
                '',
                $controlNo
            );
        } catch (Throwable $exception) {
            $memberModel->rollbackTransaction();

            // persistFamily can also throw beyond FamilyRecordWriteException (QR
            // assignment, audit, or an unexpected DB error). Catch them all so the
            // transaction is always rolled back and the request fails gracefully.
            if (! $exception instanceof FamilyRecordWriteException) {
                // Unexpected failure — record it like import()/changeFamilyState()
                // do, so silent write failures surface on the audit page.
                $this->auditSystemError('saving a family record', $exception);
            }

            return $this->storeError(
                $exception instanceof FamilyRecordWriteException
                    ? $exception->getMessage()
                    : 'The family record was not saved.'
            );
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
        $modalData = new FamilyModalDataBuilder();
        $serviceNames = $modalData->serviceNameMap($serviceIdsByMember);
        $incomeLabels = $modalData->incomeLabelMap();

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
            return $this->failUpdate('The accesscard database is missing required tables from accesscardV14.sql.', 422);
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

        if ($incomplete = $this->firstIncompleteMember($members)) {
            return $this->failUpdate($incomplete, 422);
        }

        $userId = (int) session()->get('user_id');

        $qrModel        = model(\App\Models\Scanner\QrControlModel::class);
        $currentControl = $qrModel->controlForHead($headId);
        $locked         = $currentControl !== null
            && model(\App\Models\Scanner\AidDistributionModel::class)->hasClaims($currentControl);

        // Locked heads keep their number: ignore any submitted change (defense in
        // depth in case the readonly field was tampered with).
        $controlNo = $locked ? (int) $currentControl : (int) $this->request->getPost('qr_control_no');

        if (! $locked) {
            if ($controlNo <= 0) {
                return $this->failUpdate('QR Number is required.', 422);
            }
            if ($qrModel->takenByOtherHead($controlNo, $headId)) {
                return $this->failUpdate('QR Number ' . $controlNo . ' is already assigned to another family.', 422);
            }
        }

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

        if (! $locked) {
            try {
                $qrModel->upsertForHead($controlNo, $headId);
            } catch (\Throwable $e) {
                $memberModel->rollbackTransaction();

                return $this->failUpdate($e->getMessage(), 422);
            }
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
        return MemberFieldNormalizer::splitAddressBarangay($combined);
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
            'head_birthday' => 'required|valid_date[Y-m-d]|not_future_date',
            'head_sex' => 'required|in_list[Male,Female]',
            'head_civilstatus' => 'required|min_length[2]',
            'head_education' => 'required|min_length[2]',
            'head_job' => 'required|min_length[2]',
            'head_salary' => 'required',
            'head_address' => 'required|min_length[2]|max_length[255]',
            'head_barangay' => 'required|max_length[100]',
            'qr_control_no' => 'required|is_natural_no_zero',
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
     * The first "this member is incomplete" message, or null when every real member row
     * carries the required personal fields. Members need the same profile fields as the head
     * (Date of birth, Sex, Civil status, Education, Job, Monthly income); Address/Barangay are
     * the head's and inherited. Empty template rows (no name) are skipped via hasMemberData().
     */
    private function firstIncompleteMember(array $members): ?string
    {
        $required = [
            'birthday'    => 'Date of birth',
            'sex'         => 'Sex',
            'civilstatus' => 'Civil status',
            'education'   => 'Education',
            'job'         => 'Job',
            'salary'      => 'Monthly income',
        ];

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            foreach ($required as $key => $label) {
                if (trim((string) ($member[$key] ?? '')) === '') {
                    $name = trim((string) ($member['firstname'] ?? '') . ' ' . (string) ($member['lastname'] ?? ''));

                    return $label . ' is required for member ' . ($name !== '' ? '"' . $name . '"' : '(unnamed)') . '.';
                }
            }
        }

        return null;
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

            $currentControl = model(\App\Models\Scanner\QrControlModel::class)->controlForHead($headId);
            $qrLocked = $currentControl !== null
                && model(\App\Models\Scanner\AidDistributionModel::class)->hasClaims($currentControl);

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

            $modalData = new FamilyModalDataBuilder();

            return view('Family/family-modal', array_merge(
                $viewData,
                $modalData->updateData($head, $serviceIdsByMember[$headId] ?? []),
                [
                    'action' => site_url($this->currentRouteBase() . '/update/' . $headId),
                    'fieldPrefix' => 'family-update',
                    'modalTitle' => 'Update Family Record',
                    'modalMode' => 'update',
                    'submitLabel' => 'Update',
                    'saveDisabled' => false,
                    'qrLocked' => $qrLocked,
                    'existingMembers' => $modalData->shapeMembers($members, $serviceIdsByMember),
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
                'qrLocked' => false,
                'existingMembers' => [],
            ]
        ));
    }

}
