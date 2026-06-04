<?php

namespace App\Models\Employee;

use App\Models\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\MemberModel;
use App\Models\SearchModel;
use App\Models\SectorModel;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\IncomingRequest;
use Config\IdleTimeout;

/**
 * Prepares read-only data for employee workspace pages.
 */
class WorkspaceModel
{
    public function __construct(private IncomingRequest $request) {}

    public function pageData(string $activePage): array
    {
        $layoutModel = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $auditModel = new AuditTrailsModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);
        $sectorOptions = (new SectorModel())->getSectorOptions();
        $familyFormViewData = [
            'sectorOptions' => $sectorOptions,
        ];
        $userId = (int) session()->get('user_id');

        return [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->employeePageTitle($activePage),
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'activity' => $layoutModel->navActive($activePage, 'activity'),
            ],
            'canCreateFamily' => true,
            'familyFormViewData' => $familyFormViewData,
            'recordListData' => $activePage === 'family-manage' ? $this->recordListData() : [],
            'recentFamilies' => $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
                ? $searchModel->families($searchTerm, $searchFilters, 25)
                : $dashboardModel->recentFamilies(10),
            'myAudits' => $activePage === 'activity'
                ? $auditModel->auditTrailsByUser($userId, $searchTerm, $searchFilters, 50)
                : $auditModel->getByUser($userId, 10),
            'stats' => array_merge(['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0], $dashboardModel->stats()),
            'searchTerm' => $searchTerm,
            'searchFilters' => $searchFilters,
            'auditActionOptions' => $auditModel->auditActions(),
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
            'username' => (string) (session()->get('username') ?? 'Employee'),
            'sectorOptions' => $sectorOptions,
            'selectedFilterDate' => (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? ''),
            'hasSearchFilters' => $hasSearchFilters,
        ];
    }

    public function recordListData(): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = 50;
        $filters = [
            'sectorID' => (string) $this->request->getGet('sectorID'),
            'date' => (string) $this->request->getGet('date'),
        ];
        $memberModel = new MemberModel();
        $searchKeyword = $keyword === '' ? null : $keyword;
        $totalFamilies = $memberModel->countSearchFamilies($searchKeyword, false, $filters);
        $totalPages = max(1, (int) ceil($totalFamilies / $perPage));
        $page = min($page, $totalPages);

        return array_merge([
            'canRestoreArchived' => false,
            'families' => $memberModel->searchFamilies($searchKeyword, $perPage, ($page - 1) * $perPage, false, $filters),
            'fromRecord' => $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'isEmployeeList' => true,
            'keyword' => $keyword,
            'listRoute' => 'employee/manage-records',
            'page' => $page,
            'perPage' => $perPage,
            'routeBase' => 'employee/manage-family',
            'status' => 'active',
            'toRecord' => min($totalFamilies, $page * $perPage),
            'totalFamilies' => $totalFamilies,
            'totalPages' => $totalPages,
            'useModalLinks' => false,
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'filters' => $filters,
        ], $this->deepSearchData());
    }

    private function deepSearchData(): array
    {
        $deepKeyword = trim((string) $this->request->getGet('deep_q'));

        if ($deepKeyword === '') {
            return [
                'deepKeyword' => '',
                'deepResults' => [],
                'deepPage' => 1,
                'deepTotal' => 0,
                'deepTotalPages' => 1,
                'deepFromRecord' => 0,
                'deepToRecord' => 0,
            ];
        }

        $perPage = 50;
        $page = max(1, (int) $this->request->getGet('deep_page'));
        $filters = [
            'status' => 'active',
            'sectorID' => (string) $this->request->getGet('sectorID'),
            'date' => (string) $this->request->getGet('date'),
        ];
        $searchModel = new SearchModel();
        $total = $searchModel->countAllMembers($deepKeyword, $filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        return [
            'deepKeyword' => $deepKeyword,
            'deepResults' => $searchModel->allMembers($deepKeyword, $filters, $perPage, ($page - 1) * $perPage),
            'deepPage' => $page,
            'deepTotal' => $total,
            'deepTotalPages' => $totalPages,
            'deepFromRecord' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'deepToRecord' => min($total, $page * $perPage),
        ];
    }

    private function searchFilters(): array
    {
        return [
            'sectorID' => (string) $this->request->getGet('sectorID'),
            'role' => (string) $this->request->getGet('role'),
            'status' => (string) $this->request->getGet('status'),
            'action' => (string) $this->request->getGet('action'),
            'date' => (string) $this->request->getGet('date'),
            'date_from' => (string) $this->request->getGet('date_from'),
            'date_to' => (string) $this->request->getGet('date_to'),
        ];
    }

    private function hasSearchFilters(array $filters): bool
    {
        foreach ($filters as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
