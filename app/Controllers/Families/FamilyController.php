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

        return redirect()->to(site_url($this->isEmployeeContext() ? 'employee/manage-records' : 'admin/manage-records'));
    }

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

        if (! $archived) {
            $viewUrl = site_url($routeBase . '/view/' . $headId . '?partial=1');
            $updateUrl = site_url($routeBase . '/create?partial=1&mode=update&id=' . $headId);
            $items .= '<button type="button" class="dropdown-item js-open-family-view-modal" data-modal-url="'
                . esc($viewUrl, 'attr') . '" data-modal-title="View Record">VIEW</button>';
            $items .= '<button type="button" class="dropdown-item js-open-family-add-modal" data-modal-url="'
                . esc($updateUrl, 'attr') . '" data-modal-title="Update Family Record">UPDATE</button>';
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

    /** Access guard for DataTables and read-only family detail fragments. */
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
}
