<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\AuditTrailsModel;
use App\Models\Employee\WorkspaceModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\SearchModel;
use App\Models\SectorModel;
use App\Models\ServicesModel;
use CodeIgniter\HTTP\RedirectResponse;

class EmployeeWorkspaceController extends BaseController
{
    public function workspace(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $data = $this->workspaceData('dashboard');

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/mainlayout', $data);
        }

        return view('Admin/dashboard', [
            'activePage' => 'dashboard',
            'dashboardMode' => 'employee',
            'pageTitle' => 'Workspace',
            'workspaceUrl' => site_url('employee/workspace'),
        ]);
    }

    public function manageRecords(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $state = $this->recordsState();

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/managerecord', $this->recordsViewData($state));
        }

        return view('Admin/dashboard', [
            'activePage' => 'family-manage',
            'dashboardMode' => 'employee',
            'pageTitle' => 'Manage Records',
            'workspaceUrl' => $this->recordsUrl($state),
        ]);
    }

    public function newRecord(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = array_merge($this->familyModalOptions(), [
            'mode' => 'new',
            'windowTitle' => 'New Family Record',
            'submitLabel' => 'Save',
            'recordHead' => [],
            'familyMembers' => [],
            'memberServiceIds' => [],
        ]);

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            helper('family_modal');

            return view('Admin/familymodal', $viewData);
        }

        return view('Admin/dashboard', [
            'activePage' => 'family-manage',
            'dashboardMode' => 'employee',
            'pageTitle' => 'New Record',
            'workspaceUrl' => site_url('employee/family-record/new'),
        ]);
    }

    public function editRecord(int $memberId): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $memberModel = new MemberModel();
        $selectedMember = $memberModel->findWithSector($memberId);

        if ($selectedMember === null) {
            return redirect()->to(site_url('employee/manage-records'));
        }

        $headId = (int) ($selectedMember['headID'] ?? $selectedMember['memberID'] ?? $memberId);
        $familyMembers = $memberModel->getFamilyMembers($headId, 'all');
        $recordHead = null;

        foreach ($familyMembers as $familyMember) {
            if ((int) ($familyMember['memberID'] ?? 0) === $headId) {
                $recordHead = $familyMember;
                break;
            }
        }

        $recordHead ??= $memberModel->findWithSector($headId) ?? $selectedMember;
        $familyMembers = array_values(array_filter(
            $familyMembers,
            static fn (array $familyMember): bool => (int) ($familyMember['memberID'] ?? 0) !== $headId
        ));
        $memberIds = array_values(array_filter(array_map(
            static fn (array $familyMember): int => (int) ($familyMember['memberID'] ?? 0),
            array_merge([$recordHead], $familyMembers)
        )));

        $viewData = array_merge($this->familyModalOptions(), [
            'mode' => 'update',
            'windowTitle' => 'Update Family Record',
            'submitLabel' => 'Update',
            'recordHead' => $recordHead,
            'familyMembers' => $familyMembers,
            'memberServiceIds' => (new MemberServiceModel())->getServiceIdsByMemberIds($memberIds),
        ]);

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            helper('family_modal');

            return view('Admin/familymodal', $viewData);
        }

        return view('Admin/dashboard', [
            'activePage' => 'family-manage',
            'dashboardMode' => 'employee',
            'pageTitle' => 'Update Record',
            'workspaceUrl' => site_url('employee/family-record/' . $headId . '/edit'),
        ]);
    }

    public function activity(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) session()->get('user_id');
        $data = [
            'auditTrails' => array_map(
                fn (array $audit): array => $this->formatAudit($audit),
                (new AuditTrailsModel())->auditTrailsByUser($userId, '', [], 50)
            ),
        ];

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Logs/audit_trails', $data);
        }

        return view('Admin/dashboard', [
            'activePage' => 'activity',
            'dashboardMode' => 'employee',
            'pageTitle' => 'My Trails',
            'workspaceUrl' => site_url('employee/activity'),
        ]);
    }

    private function workspaceData(string $activePage): array
    {
        $data = (new WorkspaceModel($this->request))->pageData($activePage);
        $data['dashboardMode'] = 'employee';
        $data['manageRecordsUrl'] = site_url('employee/manage-records');
        $data['activityUrl'] = site_url('employee/activity');
        $data['activityLinkLabel'] = 'My Trails';
        $data['recentFamilies'] = array_map(fn (array $family): array => $this->formatRecord($family), $data['recentFamilies'] ?? []);
        $data['recentAudits'] = array_map(fn (array $audit): array => $this->formatAudit($audit), $data['myAudits'] ?? []);

        return $data;
    }

    private function recordsState(): array
    {
        $searchScope = strtolower(trim((string) $this->request->getGet('search_scope'))) === 'all'
            ? 'all'
            : 'heads';
        $date = trim((string) $this->request->getGet('date'));

        return [
            'status' => 'active',
            'searchScope' => $searchScope,
            'keyword' => trim((string) $this->request->getGet('q')),
            'sectorId' => trim((string) $this->request->getGet('sectorID')),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '',
            'page' => max(1, (int) $this->request->getGet('page')),
        ];
    }

    private function recordsViewData(array $state): array
    {
        $keyword = $state['keyword'];
        $searchScope = $state['searchScope'];
        $page = $state['page'];
        $perPage = 25;
        $filters = [
            'status' => 'active',
            'sectorID' => $state['sectorId'],
            'date' => $state['date'],
        ];

        if ($searchScope === 'all') {
            $model = new SearchModel();
            $totalRecords = $model->countAllMembers($keyword, $filters);
            $totalPages = max(1, (int) ceil($totalRecords / $perPage));
            $page = min($page, $totalPages);
            $records = $model->allMembers($keyword, $filters, $perPage, ($page - 1) * $perPage);
        } else {
            $model = new MemberModel();
            $searchKeyword = $keyword === '' ? null : $keyword;
            $totalRecords = $model->countSearchFamilies($searchKeyword, false, $filters);
            $totalPages = max(1, (int) ceil($totalRecords / $perPage));
            $page = min($page, $totalPages);
            $records = $model->searchFamilies($searchKeyword, $perPage, ($page - 1) * $perPage, false, $filters);
        }

        $viewData = [
            'status' => 'active',
            'searchScope' => $searchScope,
            'keyword' => $keyword,
            'sectorId' => $state['sectorId'],
            'date' => $state['date'],
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'records' => array_map(fn (array $record): array => $this->formatRecord($record), $records),
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'fromRecord' => $totalRecords === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'toRecord' => min($totalRecords, $page * $perPage),
            'canCreateFamily' => true,
            'canEditFamily' => true,
            'canDeleteFamily' => false,
            'canRestoreArchived' => false,
            'showArchivedTab' => false,
            'newRecordUrl' => site_url('employee/family-record/new'),
            'editRecordBaseUrl' => site_url('employee/family-record'),
            'searchRouteAction' => site_url('employee/manage-records'),
        ];

        return array_merge($viewData, [
            'activeUrl' => $this->recordsUrl(array_merge($viewData, ['page' => 1])),
            'previousUrl' => $page > 1
                ? $this->recordsUrl(array_merge($viewData, ['page' => $page - 1]))
                : null,
            'nextUrl' => $page < $totalPages
                ? $this->recordsUrl(array_merge($viewData, ['page' => $page + 1]))
                : null,
        ]);
    }

    private function recordsUrl(array $state): string
    {
        $query = array_filter([
            'q' => trim((string) ($state['keyword'] ?? '')) ?: null,
            'sectorID' => trim((string) ($state['sectorId'] ?? '')) ?: null,
            'date' => trim((string) ($state['date'] ?? '')) ?: null,
            'search_scope' => ($state['searchScope'] ?? 'heads') === 'all' ? 'all' : null,
            'page' => (int) ($state['page'] ?? 1) > 1 ? (int) $state['page'] : null,
        ], static fn (mixed $value): bool => $value !== null);

        return site_url('employee/manage-records') . ($query === [] ? '' : '?' . http_build_query($query));
    }

    private function familyModalOptions(): array
    {
        $sectorOptions = (new SectorModel())->getAll();
        $serviceOptions = (new ServicesModel())->getAll();
        $sectorsByCode = [];
        $servicesByCategory = [];

        foreach ($sectorOptions as $sector) {
            $code = strtoupper(trim((string) ($sector['shortcode'] ?? 'OTHER')));
            $groupCode = $code === '' ? 'OTHER' : $code;

            if (preg_match('/^[A-Z]+/', $code, $matches) === 1) {
                $groupCode = $matches[0];
            }

            $sectorsByCode[$groupCode][] = $sector;
        }

        foreach ($serviceOptions as $service) {
            $category = trim((string) ($service['category'] ?? 'Other'));
            $servicesByCategory[$category === '' ? 'Other' : $category][] = $service;
        }

        return [
            'sectorsByCode' => $sectorsByCode,
            'servicesByCategory' => $servicesByCategory,
        ];
    }

    private function formatRecord(array $record): array
    {
        return array_merge($record, [
            'display_name' => $this->valueOrDash(trim((string) ($record['firstname'] ?? '') . ' ' . (string) ($record['lastname'] ?? ''))),
            'display_sector' => $this->valueOrDash($record['sector_name'] ?? ''),
            'display_barangay' => $this->valueOrDash($record['barangay'] ?? ''),
            'display_birthday' => $this->formatDate($record['birthday'] ?? ''),
            'display_date' => $this->formatDate($record['dt_created'] ?? ''),
            'display_time' => $this->formatTime($record['dt_created'] ?? ''),
        ]);
    }

    private function formatAudit(array $audit): array
    {
        return array_merge($audit, [
            'display_username' => $this->valueOrDash($audit['username'] ?? ''),
            'display_member' => $this->formatName($audit, 'member_name'),
            'display_action' => $this->valueOrDash($audit['user_action'] ?? ''),
            'display_description' => $this->valueOrDash($audit['description'] ?? ''),
        ]);
    }

    private function formatName(array $row, string $fallbackField): string
    {
        $name = trim((string) ($row[$fallbackField] ?? ''));

        if ($name === '') {
            $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? ''));
        }

        return $this->valueOrDash($name);
    }

    private function formatDate(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '-' : date('Y-m-d', $timestamp);
    }

    private function formatTime(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '-' : date('h:i A', $timestamp);
    }

    private function valueOrDash(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }
}
