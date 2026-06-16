<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SectorIds;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Lookups\ServiceModel;
use App\Support\FamilyProfilingFormV2;
use App\Support\FamilyRecordPresenter;
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

        $memberModel->beginTransaction();

        $headId = $memberModel->createHead($this->memberPayload('head_'));

        if ($headId === false) {
            $memberModel->rollbackTransaction();

            $message = 'Head of family could not be saved. Please check required fields.';

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

        foreach ($members as $member) {
            if (! is_array($member) || ! $this->hasMemberData($member)) {
                continue;
            }

            $memberId = $memberModel->addFamilyMember($headId, $this->memberPayloadFromArray($member));

            if ($memberId === false) {
                $memberModel->rollbackTransaction();

                $message = 'One family member could not be saved.';

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

            $memberServiceIds = $member['service_ids'] ?? [];

            if (! is_array($memberServiceIds)) {
                $memberServiceIds = [];
            }

            foreach ($memberServiceIds as $memberServiceId) {
                $memberServiceId = (int) $memberServiceId;

                if ($memberServiceId <= 0 || ! $serviceModel->existsById($memberServiceId)) {
                    continue;
                }

                if ($memberServiceModel->assignService($memberId, $memberServiceId) === false) {
                    $memberModel->rollbackTransaction();

                    $message = 'A selected service could not be assigned to one family member.';

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
            }
        }

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId < 0 || ! $serviceModel->existsById($serviceId)) {
                continue;
            }

            if ($memberServiceModel->assignService($headId, $serviceId) === false) {
                $memberModel->rollbackTransaction();

                $message = 'A selected service could not be assigned to the head of family.';

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
        }

        if ($auditModel->hasTable()) {
            $headName = trim(trim((string) $this->request->getPost('head_firstname')) . ' ' . trim((string) $this->request->getPost('head_lastname')));
            $memberCount = is_array($members) ? count($members) : 0;
            $serviceCount = is_array($serviceIds) ? count($serviceIds) : 0;
            // Tracks the creating operator plus client IP and browser agent.
            $auditModel->logAction(
                $userId,
                $headId,
                'FAMILY_CREATED',
                'Created family profile for ' . $headName . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString(),
                'Head of family: ' . $headName . '; added ' . $memberCount . ' additional member(s); '
                    . $serviceCount . ' service(s) assigned to the head'
            );
        }

        $memberModel->completeTransaction();

        if (! $memberModel->transactionStatus()) {
            $message = 'The family form was not saved.';

            if ($this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $message,
                        'csrf' => csrf_hash(),
                    ]);
            }

            return redirect()->back()->withInput()->with('error', $message);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => $successMessage,
                'csrf' => csrf_hash(),
            ]);
        }

        // family_record_saved signals the manage-records page to clear the
        // client-side "Add Record" draft (see family-form.js). Set on a new
        // record save only — never on edit/update.
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
     * GET `{admin|employee}/manage-family/edit/{id}`: returns the family form
     * prefilled for editing, as a modal fragment. Reuses the same
     * `Family/form` template as Add Family by passing the head
     * row, its members, and selected services, with the form pointed at update().
     */
    public function editFamily(int $headId): string|RedirectResponse
    {
        $guard = $this->requireFamilyEntryAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to edit family records.');
        }

        $memberModel = new MemberModel();
        $rows = $memberModel->getFamilyMembers($headId, 'all');
        [$head, $members] = $this->splitHeadAndMembers($rows, $headId);

        if ($head === null) {
            return $this->recordMissing();
        }

        // Address stores "address, barangay" combined; split it so the edit form
        // can prefill the separate Address and Barangay inputs.
        $addressParts = $this->splitAddressBarangay($head['address'] ?? '');
        $head['address'] = $addressParts['address'];
        $head['barangay'] = $addressParts['barangay'];

        $serviceIdsByMember = (new MemberServiceModel())
            ->getServiceIdsByMemberIds(array_map(static fn (array $row): int => (int) $row['memberID'], $rows));

        // Gather every sector/service the family currently holds so the edit form can
        // keep showing (and re-posting) any that have since been archived — otherwise
        // saving would silently drop those grandfathered benefits.
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

        return view('Family/form', array_merge(
            (new FamilyFormOptionsModel())->getViewDataForEdit(
                array_values(array_unique($assignedSectorIds)),
                array_values(array_unique($assignedServiceIds))
            ),
            [
                'familyRecord'      => $head,
                'existingMembers'   => $this->shapeExistingMembers($members, $serviceIdsByMember),
                'headServiceIds'    => $serviceIdsByMember[$headId] ?? [],
                'formAction'        => site_url($this->currentRouteBase() . '/update/' . $headId),
                'submitButtonLabel' => 'Update Record',
                'embeddedInModal'   => true,
                'canCreateFamily'   => true,
            ]
        ));
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
        return str_starts_with(trim((string) $this->request->getUri()->getPath(), '/'), 'employee/');
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

    /** JSON error body (with a fresh CSRF hash) used by the AJAX update responses. */
    private function jsonError(string $message, int $statusCode)
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
                'csrf' => csrf_hash(),
            ]);
    }

    /**
     * Update-failure response: JSON error for AJAX, otherwise a redirect back with
     * the input preserved and an error flash. Used throughout update().
     */
    private function failUpdate(string $message, int $statusCode)
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
        $address = $this->cleanAddress($address);
        $barangay = $this->cleanAddress($barangay);
        $combined = trim($address . ($address !== '' && $barangay !== '' ? ', ' : '') . $barangay);

        return $combined === '' ? null : $combined;
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
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * Trims a value to a string, returning null when empty so optional columns
     * store NULL rather than ''. Used throughout the payload builders.
     */
    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
        $value = preg_replace("/[^\\p{L}\\s.'-]/u", '', (string) $value);
        $value = trim((string) preg_replace('/\\s+/u', ' ', (string) $value));

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Cleans an address/barangay field on save/update: address-safe allowlist of
     * letters, digits, spaces and # , . - / ' ( ) & (so house/block numbers survive),
     * collapses repeated whitespace, then applies Title Case. Strips odd symbols such
     * as < > | \ " : ] [.
     */
    private function cleanAddress(mixed $value): string
    {
        $value = preg_replace("/[^\\p{L}\\p{N}\\s#,.\\-\\/'()&]/u", '', (string) $value);
        $value = trim((string) preg_replace('/\\s+/u', ' ', (string) $value));

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

}