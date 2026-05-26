<?php

namespace App\Libraries;

use App\Models\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\SearchModel;
use App\Models\SectorModel;
use App\Models\ServiceModel;
use App\Models\Auth\UserModel;
use App\Libraries\RoleAccess;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

class DashboardPageBuilder
{
    public function __construct(private IncomingRequest $request) {}

    public function renderAdminPage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $currentRole = $this->normalizeRole((string) session()->get('role'));

        if ($activePage === 'accounts' && ! in_array($currentRole, ['Developer', 'Admin'], true)) {
            return redirect()->to(site_url('admin/dashboard'))
            ->with('error', 'Developer or Admin access is required for account management.');
        }

        helper('assets');

        return view('Dashboard/admin', $this->buildAdminViewData($activePage));
    }

    public function buildAdminViewData(string $activePage): array
    {
        $layoutModel    = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);
        $currentRole = $this->normalizeRole((string) session()->get('role'));
        $isDeveloper = $currentRole === 'Developer';
        $isAdmin = $currentRole === 'Admin';
        $userModel = new UserModel();
        $users = $isDeveloper && $activePage === 'accounts'
            ? $searchModel->staffAccounts($searchTerm, $searchFilters)
            : $userModel->getStaffAccounts();
        $memberModel = new MemberModel();
        $sectorModel = new SectorModel();
        $serviceModel = new ServiceModel();
        $userModel    = new UserModel();
        $users        = $userModel->getStaffAccounts();

        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();

        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);

        $recentAudits = $activePage === 'audit-trails'
            ? $searchModel->auditTrails($searchTerm, $searchFilters, 50)
            : (new AuditTrailsModel())->getRecent(10);

        $isActiveStatus = static function (mixed $value): bool {
            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (int) $value === 1;
            }

            $normalized = strtolower(trim((string) $value));

            return in_array($normalized, ['enable', 'enabled', 'active', '1', 'true', 'yes', 'on'], true);
        };

        return [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->pageTitle($activePage),
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            // Developers can manage all staff; admins can only disable employees.
            'canManageAccounts' => $isDeveloper || $isAdmin,
            'canCreateAccounts' => $isDeveloper,
            'currentRole' => $currentRole,
            'navActive' => [
                'dashboard'    => $layoutModel->navActive($activePage, 'dashboard'),
                'accounts'     => $layoutModel->navActive($activePage, 'accounts'),
                'family-entry' => $layoutModel->navActive($activePage, 'family-entry'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'audit-trails' => $layoutModel->navActive($activePage, 'audit-trails'),
                'sectors'      => $layoutModel->navActive($activePage, 'sectors'),
                'services'     => $layoutModel->navActive($activePage, 'services'),
            ],
            'adminAccounts'      => array_values(array_filter($users, static fn ($account) => $account['role'] === 'Admin')),
            'employeeAccounts'   => array_values(array_filter($users, static fn ($account) => $account['role'] === 'User')),
            'familyFormViewData' => $familyFormViewData,
            'recentFamilies'     => $recentFamilies,
            'recentAudits'       => $recentAudits,
            'sectors'            => $this->fetchVisibleSectors($sectorModel),
            'services'           => $this->fetchVisibleServices($serviceModel),
            'stats'              => $dashboardModel->stats(),
            'canCreateFamily'    => true,
            'username'           => (string) (session()->get('username') ?? 'Admin'),
            'searchTerm'         => $searchTerm,
            'searchFilters'      => $searchFilters,
            'hasSearchFilters'   => $hasSearchFilters,
            'selectedFilterDate' => (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? ''),
            'sectorOptions'      => $familyFormViewData['sectorOptions'] ?? [],
            'auditActionOptions' => $searchModel->auditActions(),
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
            'isDeveloper'        => $isDeveloper,
            'isAdmin'            => $isAdmin,
            'showAdminActions'   => $isDeveloper,
            'showEmployeeActions' => $isDeveloper || $isAdmin,
            'adminColspan'       => $isDeveloper ? 5 : 4,
            'employeeColspan'    => ($isDeveloper || $isAdmin) ? 5 : 4,
            'adminColumnClass'   => $isDeveloper ? 'col-lg-6' : 'col-lg-12',
            'employeeColumnClass' => $isDeveloper ? 'col-lg-6' : 'col-lg-12',
            'isActiveStatus'     => $isActiveStatus,
            'formatStatus'       => static function (mixed $value) use ($isActiveStatus): string {
                return $isActiveStatus($value) ? 'Enable' : 'Disabled';
            },
            'formatDate'         => static function (mixed $value): string {
                $timestamp = strtotime((string) $value);

                return $timestamp === false ? '' : date('Y-m-d', $timestamp);
            },
            'formatTime'         => static function (mixed $value): string {
                $timestamp = strtotime((string) $value);

                return $timestamp === false ? '' : date('h:i A', $timestamp);
            },
            'formatAuditMember'  => static function (array $audit): string {
                $memberName = trim((string) ($audit['member_name'] ?? ''));

                if ($memberName === '') {
                    $memberName = trim((string) ($audit['firstname'] ?? '') . ' ' . (string) ($audit['lastname'] ?? ''));
                }

                return $memberName === '' ? '-' : $memberName;
            },
            'formatAuditUser'    => static function (array $audit): string {
                $username = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
                $role     = trim((string) ($audit['user_role'] ?? ''));

                if ($role === 'User') {
                    $role = 'Employee';
                }

                return $role === '' ? $username : $username . ' (' . $role . ')';
            },
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

    public function renderEmployeePage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'User']);

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
            'canCreateFamily'    => true,
            'familyFormViewData' => $familyFormViewData,
            'recentFamilies'     => $recentFamilies,
            'myAudits'           => $myAudits,
            'stats'              => array_merge(['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0], $dashboardModel->stats()),
            'searchTerm'         => $searchTerm,
            'searchFilters'      => $searchFilters,
            'auditActionOptions' => $searchModel->auditActions(),
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
            'username'           => (string) (session()->get('username') ?? 'Employee'),
            'sectorOptions'      => $familyFormViewData['sectorOptions'] ?? [],
            'selectedFilterDate' => (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? ''),
            'hasSearchFilters'   => $hasSearchFilters,
            'formatDate'         => static function (mixed $value): string {
                $timestamp = strtotime((string) $value);

                return $timestamp === false ? '' : date('Y-m-d', $timestamp);
            },
            'formatTime'         => static function (mixed $value): string {
                $timestamp = strtotime((string) $value);

                return $timestamp === false ? '' : date('h:i A', $timestamp);
            },
            'formatAuditMember'  => static function (array $audit): string {
                $memberName = trim((string) ($audit['member_name'] ?? ''));

                if ($memberName === '') {
                    $memberName = trim((string) ($audit['firstname'] ?? '') . ' ' . (string) ($audit['lastname'] ?? ''));
                }

                return $memberName === '' ? '-' : $memberName;
            },
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
