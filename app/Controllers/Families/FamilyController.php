<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SectorIds;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Families\MemberModel;
use App\Models\Families\MemberServiceModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\SearchModel;
use App\Support\FamilyProfilingFormV2;
use App\Support\FamilyRecordPresenter;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Manage Records family controller.
 *
 * Add/Edit persistence is intentionally unavailable while that frontend is being
 * rebuilt. This controller only serves DataTables rows, the read-only View Record
 * modal, and the Archive/Restore row actions.
 */
class FamilyController extends BaseController
{
    /**
     * GET `{admin|employee}/manage-family/list`: legacy convenience entrypoint.
     * The real list is rendered by the role dashboard's `manage-records` page.
     */
    public function listFamilies(): RedirectResponse
    {
        $guard = $this->requireFamilyViewAccess();

        if ($guard instanceof RedirectResponse) {
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
     * GET `{admin|employee|viewer}/manage-family/create`: Bootstrap Add Record
     * frontend fragment. The create POST route remains intentionally unavailable
     * while the Add/Edit flow is being rebuilt.
     */
    public function createFamily(): string|RedirectResponse
    {
        $guard = $this->requireFamilyViewAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->partialGuard($guard, 'You do not have permission to open the family record form.');
        }

        $viewData = (new FamilyFormOptionsModel())->getViewData();
        $isUpdateMode = strtolower(trim((string) $this->request->getGet('mode'))) === 'update';
        $headId = max(0, (int) $this->request->getGet('id'));

        if ($isUpdateMode) {
            $updateData = $this->familyModalUpdateData($headId, (array) ($viewData['barangayOptions'] ?? []));

            if ($updateData === null) {
                return $this->recordMissing();
            }

            $viewData = array_merge($viewData, $updateData);
        }

        $viewData['action'] = '#';
        $viewData['fieldPrefix'] = $isUpdateMode ? 'family-update' : 'family-add';
        $viewData['modalTitle'] = $isUpdateMode ? 'Update Family Record' : 'New Family Record';
        $viewData['modalMode'] = $isUpdateMode ? 'update' : 'create';
        $viewData['submitLabel'] = $isUpdateMode ? 'Update' : 'Save';
        $viewData['saveDisabled'] = true;
        $viewData['saveDisabledMessage'] = $isUpdateMode
            ? 'Saving is not connected yet. The Update frontend is ready for the Bootstrap rebuild, but the update POST route is still disabled.'
            : 'Saving is not connected yet. The Add frontend is ready for the Bootstrap rebuild, but the create POST route is still disabled.';

        return view('Family/family-modal', $viewData);
    }

    /** Returns the existing family-head values used to prefill the Update modal. */
    private function familyModalUpdateData(int $headId, array $barangayOptions = []): ?array
    {
        if ($headId <= 0) {
            return null;
        }

        $rows = (new MemberModel())->getFamilyMembers($headId, 'all');
        $head = null;

        foreach ($rows as $row) {
            if ((int) ($row['memberID'] ?? 0) === $headId) {
                $head = $row;
                break;
            }
        }

        if ($head === null) {
            return null;
        }

        $serviceIdsByMember = (new MemberServiceModel())->getServiceIdsByMemberIds([$headId]);
        [$address, $barangay] = $this->splitAddressBarangayForModal(
            (string) ($head['address'] ?? ''),
            (string) ($head['barangay'] ?? ''),
            $barangayOptions
        );

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
                'head_address' => $address,
                'head_barangay' => $barangay,
            ],
            'selectedSectorIds' => SectorIds::normalize($head['sectorID'] ?? null),
            'selectedServiceIds' => array_map('strval', $serviceIdsByMember[$headId] ?? []),
        ];
    }

    /**
     * Older records may store barangay as the trailing part of the combined
     * address. Split it for the Bootstrap modal so the Barangay dropdown is
     * preselected while the Address field keeps only the street/subdivision text.
     *
     * @return array{0: string, 1: string}
     */
    private function splitAddressBarangayForModal(string $address, string $barangay, array $barangayOptions): array
    {
        $address = trim($address);
        $barangay = trim($barangay);
        $matches = array_values(array_filter(array_map('strval', $barangayOptions), static fn (string $option): bool => trim($option) !== ''));

        usort($matches, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($matches as $option) {
            $option = trim($option);
            $suffix = ', ' . $option;

            if ($barangay === '' && str_ends_with(mb_strtolower($address), mb_strtolower($suffix))) {
                return [trim(mb_substr($address, 0, -mb_strlen($suffix))), $option];
            }

            if ($barangay !== '' && strcasecmp($barangay, $option) === 0 && str_ends_with(mb_strtolower($address), mb_strtolower($suffix))) {
                return [trim(mb_substr($address, 0, -mb_strlen($suffix))), $barangay];
            }
        }

        return [$address, $barangay];
    }

    /**
     * GET `{admin|employee|viewer}/manage-family/view/{id}`: read-only detail
     * fragment loaded into the dashboard modal by manage-family-modal.js.
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
     * modal prefilled for editing. Delegates to renderFamilyModal() — the same
     * Bootstrap modal served by createFamily() in update mode — so the legacy
     * edit route stays functional after the old `Family/form` wizard was retired.
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
     * family. Role enforcement is Developer/Admin only.
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
     * family. Role enforcement is Developer/Admin only.
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
     * Shared flow for archive/restore row actions.
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
     * Records a SYSTEM_ERROR audit row for unexpected family-table failures.
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
     * Builds a redirect URL from Referer while removing obsolete deep-search
     * parameters. Falls back to the role's Manage Records page.
     */
    private function listUrlWithoutDeepSearch(): string
    {
        $fallback = site_url($this->isEmployeeContext() ? 'employee/manage-records' : 'admin/manage-records');
        $referer = (string) ($this->request->getServer('HTTP_REFERER') ?? '');

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

    /** @return array{0: string, 1: string} */
    private function dataTableOrder(): array
    {
        $order = $this->request->getGet('order');
        $firstOrder = is_array($order) && isset($order[0]) && is_array($order[0]) ? $order[0] : [];
        $column = (int) ($firstOrder['column'] ?? 0);
        $direction = strtolower((string) ($firstOrder['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderKey = match ($column) {
            2 => 'address',
            3 => 'birthday',
            default => 'name',
        };

        return [$orderKey, $direction];
    }

    /** @return array<int, string> */
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

    /** @param array<int, string> $sectorShortcodes */
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

    private function dataTableActions(array $row, int $headId, string $displayName): string
    {
        if ($headId <= 0) {
            return '';
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));
        $canArchive = in_array($role, ['Developer', 'Admin'], true);
        $archived = trim((string) ($row['dt_deleted'] ?? '')) !== '';

        if ($archived && ! $canArchive) {
            return '';
        }

        $routeBase = $this->dataTableRouteBase();
        $items = '';
        helper('family_modal');

        if (! $archived) {
            $viewUrl = site_url($routeBase . '/view/' . $headId . '?partial=1');
            $items .= '<button type="button" class="dropdown-item js-open-family-view-modal" data-modal-url="'
                . esc($viewUrl, 'attr') . '" data-modal-title="View Record">VIEW</button>';
            $items .= '<button type="button" class="dropdown-item js-open-family-add-modal" '
                . family_modal_trigger_attrs('Update Family Record', ['mode' => 'update', 'id' => $headId], $routeBase)
                . '>UPDATE</button>';
        }

        if ($canArchive) {
            $action = $archived ? 'restore' : 'archive';
            $label = $archived ? 'Restore' : 'Archive';
            $past = $archived ? 'restored' : 'archived';
            $message = $archived
                ? 'Restore this record to the active list?'
                : 'Archive this record? This keeps the record in the database, marks it as archived, and hides it from active lists.';
            $formAction = site_url($routeBase . '/' . $action . '/' . $headId);
            $items .= '<form class="js-family-record-action-form" method="post" action="' . esc($formAction, 'attr')
                . '" data-confirm-message="' . esc($message, 'attr') . '" data-action-label="' . esc($label, 'attr')
                . '" data-action-past="' . esc($past, 'attr') . '" data-family-name="' . esc($displayName, 'attr') . '">'
                . csrf_field() . '<button type="submit" class="dropdown-item ' . ($archived ? 'text-success' : 'text-danger')
                . '">' . mb_strtoupper($label) . '</button></form>';
        }

        if ($items === '') {
            return '';
        }

        return '<div class="dropdown actions-menu"><button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"'
            . ' data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false"'
            . ' aria-label="Record actions">Actions</button>'
            . '<div class="dropdown-menu dropdown-menu-end">' . $items . '</div></div>';
    }

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

    /**
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
        return [
            '0' => 'No regular income',
            '8000' => 'Below PHP 8,000',
            '13000' => 'PHP 8,000 - 13,000',
            '18000' => 'PHP 13,001 - 18,000',
            '25000' => 'PHP 18,001 - 25,000',
            '40000' => 'PHP 25,001 - 40,000',
            '65000' => 'PHP 40,001 - 65,000',
            '100000' => 'PHP 65,001 - 100,000',
            '150000' => 'PHP 100,001 - 150,000',
            '250000' => 'PHP 150,001 - 250,000',
            '250001' => 'Above PHP 250,000',
        ];
    }

    /** First + last name of a family head, for audit descriptions. */
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
        return str_starts_with(uri_string(), 'employee/');
    }

    /**
     * For a partial/modal request whose access guard failed, returns an inline
     * alert fragment so the modal shows the reason.
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
        $firstOrder = is_array($order) && isset($order[0]) && is_array($order[0]) ? $order[0] : [];
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