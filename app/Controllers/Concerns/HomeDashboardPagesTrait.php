<?php

namespace App\Controllers\Concerns;

use App\Models\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\SearchModel;
use App\Models\SectorModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Builds the admin and employee workspace data used by the Home controller.
 */
trait HomeDashboardPagesTrait
{
    private function renderAdminPage(string $activePage): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if ($activePage === 'accounts' && $this->normalizeRole((string) session()->get('role')) !== 'Developer') {
            return redirect()->to(site_url('admin/dashboard'))
                ->with('error', 'Developer access is required for account management.');
        }

        return view('Dashboard/admin', $this->buildAdminViewData($activePage));
    }

    private function buildAdminViewData(string $activePage): array
    {
        $layoutModel = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);
        $isDeveloper = $this->normalizeRole((string) session()->get('role')) === 'Developer';
        $userModel = new UserModel();
        $users = $isDeveloper && $activePage === 'accounts'
            ? $searchModel->staffAccounts($searchTerm, $searchFilters)
            : $userModel->getStaffAccounts();
        $memberModel = new MemberModel();
        $sectorModel = new SectorModel();
        $serviceModel = new ServiceModel();
        $memberServiceModel = new MemberServiceModel();

        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();
        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);
        $recentAudits = $activePage === 'audit-trails'
            ? $searchModel->auditTrails($searchTerm, $searchFilters, 50)
            : (new AuditTrailsModel())->getRecent(10);

        return [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->pageTitle($activePage),
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            'canManageAccounts' => $isDeveloper,
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'accounts' => $layoutModel->navActive($activePage, 'accounts'),
                'family-entry' => $layoutModel->navActive($activePage, 'family-entry'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'audit-trails' => $layoutModel->navActive($activePage, 'audit-trails'),
                'sectors' => $layoutModel->navActive($activePage, 'sectors'),
                'services' => $layoutModel->navActive($activePage, 'services'),
            ],
            'adminAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'Admin')),
            'employeeAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'User')),
            'linkableMembers' => $isDeveloper ? $userModel->getLinkableMembers() : [],
            'familyFormViewData' => $familyFormViewData,
            'sectors' => $this->fetchVisibleSectors($sectorModel),
            'services' => $this->fetchVisibleServices($serviceModel),
            'recentFamilies' => $recentFamilies,
            'recentAudits' => $recentAudits,
            'searchTerm' => $searchTerm,
            'searchFilters' => $searchFilters,
            'auditActionOptions' => $searchModel->auditActions(),
            'stats' => $dashboardModel->stats(),
            'canCreateFamily' => true,
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
        ];
    }

    private function fetchVisibleSectors(SectorModel $sectorModel): array
    {
        if (! $sectorModel->hasTable()) {
            return [];
        }

        return $sectorModel
            ->orderBy('sectorID', 'ASC')
            ->findAll();
    }

    private function fetchVisibleServices(ServiceModel $serviceModel): array
    {
        if (! $serviceModel->hasTable()) {
            return [];
        }

        return $serviceModel
            ->orderBy('serviceID', 'ASC')
            ->findAll();
    }

    private function renderEmployeePage(string $activePage): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $layoutModel = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);
        $userId = (int) session()->get('user_id');
        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();
        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);
        $myAudits = $activePage === 'activity'
            ? $searchModel->auditTrailsByUser($userId, $searchTerm, $searchFilters, 50)
            : (new AuditTrailsModel())->getByUser($userId, 10);

        return view('Employee/index', [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->employeePageTitle($activePage),
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'family-entry' => $layoutModel->navActive($activePage, 'family-entry'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'activity' => $layoutModel->navActive($activePage, 'activity'),
            ],
            'canCreateFamily' => true,
            'familyFormViewData' => $familyFormViewData,
            'recentFamilies' => $recentFamilies,
            'myAudits' => $myAudits,
            'stats' => $dashboardModel->stats(),
            'searchTerm' => $searchTerm,
            'searchFilters' => $searchFilters,
            'auditActionOptions' => $searchModel->auditActions(),
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
        ]);
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
