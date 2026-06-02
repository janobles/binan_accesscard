<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Families\MemberModel;
use App\Models\SearchModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Auth\UserModel;
use App\Libraries\RoleAccess;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Central view-data assembler for the dashboard. Workspace\Home delegates here so
 * controllers only choose WHICH page to show while this class gathers all the
 * models' data and renders the admin/employee shell views. The main place to look
 * when debugging what a dashboard page displays.
 */
class DashboardPageBuilder
{
    /** Holds the current request so query params (search/filters/page) are available. */
    public function __construct(private IncomingRequest $request) {}

    /**
     * Guards Developer/Admin access, then renders the admin shell
     * (`Dashboard/Manage/admin`) on the given tab. Account management additionally
     * requires Developer/Admin. Frontend: returns the full admin page HTML.
     */
    public function renderAdminPage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $currentRole = RoleAccess::normalizeRole((string) session()->get('role'));

        if ($activePage === 'accounts' && ! in_array($currentRole, ['Developer', 'Admin'], true)) {
            return redirect()->to(site_url('admin/dashboard'))
            ->with('error', 'Developer or Admin access is required for account management.');
        }

        helper('assets');

        return view('Dashboard/Manage/admin', $this->buildAdminViewData($activePage));
    }

    /**
     * Assembles every variable the admin shell and its sub-views need: page title,
     * role flags/permissions, nav highlighting, account lists, family form options,
     * recent families/audits, member list (on Manage Records), sector/service
     * lists, dashboard stats, search term/filters, and view formatter closures
     * (formatDate/Status/etc.). Also reused to build AJAX partials. Frontend:
     * consumed directly by `Dashboard/Manage/*` views.
     */
    public function buildAdminViewData(string $activePage): array
    {
        $layoutModel    = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);
        $currentRole = RoleAccess::normalizeRole((string) session()->get('role'));
        $isDeveloper = $currentRole === 'Developer';
        $isAdmin = $currentRole === 'Admin';
        $userModel = new UserModel();
        $users = $isDeveloper && $activePage === 'accounts'
            ? $searchModel->staffAccounts($searchTerm, $searchFilters)
            : $userModel->getStaffAccounts();
        $sectorModel = new SectorModel();
        $serviceModel = new ServiceModel();

        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();

        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);

        $recentAudits = $activePage === 'audit-trails'
            ? $searchModel->auditTrails($searchTerm, $searchFilters, 50)
            : (new AuditTrailsModel())->getRecent(10);
        $memberListData = $activePage === 'family-manage'
            ? $this->buildMemberListData()
            : [];

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
            'recordListData'      => $memberListData,
            'memberListData'      => $memberListData,
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

    /** Public entry for the admin records-list AJAX partial (Home::renderAdminRecordListPartial). */
    public function buildAdminRecordListViewData(): array
    {
        return $this->buildMemberListData();
    }

    /** Public entry for the employee records-list AJAX partial. */
    public function buildEmployeeRecordListViewData(): array
    {
        return $this->buildEmployeeRecordListData();
    }

    /** All sectors (active + archived) ordered by ID, for the admin sectors view. */
    private function fetchVisibleSectors(SectorModel $sectorModel): array
    {
        if (! $sectorModel->hasTable()) {
            return [];
        }

        return $sectorModel
            ->orderBy('sectorID', 'ASC')
            ->findAll();
    }

    /** All services (active + archived) ordered by ID, for the admin services view. */
    private function fetchVisibleServices(ServiceModel $serviceModel): array
    {
        if (! $serviceModel->hasTable()) {
            return [];
        }

        return $serviceModel
            ->orderBy('serviceID', 'ASC')
            ->findAll();
    }

    /**
     * Builds the admin Manage Records list: reads the q/status/page/sector/date
     * query params, runs the paginated family-head search, and merges in the deep
     * (whole-database) search results. Frontend: the family-list view + its filter
     * and pagination controls.
     */
    private function buildMemberListData(): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $showArchived = $status === 'archived';
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = 50;

        // Manage Records FILTER controls (sector + date). Status (active/archived)
        // is handled separately above. Passed into MemberModel::searchFamilies().
        $filters = [
            'sectorID' => (string) $this->request->getGet('sectorID'),
            'date'     => (string) $this->request->getGet('date'),
        ];

        $memberModel = new MemberModel();
        $searchKeyword = $keyword === '' ? null : $keyword;
        $totalFamilies = $memberModel->countSearchFamilies($searchKeyword, $showArchived, $filters);
        $totalPages = max(1, (int) ceil($totalFamilies / $perPage));
        $page = min($page, $totalPages);
        $routeBase = 'admin/manage-family';

        return array_merge([
            'canRestoreArchived' => true,
            'families'          => $memberModel->searchFamilies($searchKeyword, $perPage, ($page - 1) * $perPage, $showArchived, $filters),
            'fromRecord'        => $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'isEmployeeList'    => false,
            'isFullPage'        => true,
            'keyword'           => $keyword,
            // Full-page route so both the filter form and deep-search form reload the
            // whole Manage Records page (not the modal/partial list endpoint).
            'listRoute'         => 'admin/manage-records',
            'page'              => $page,
            'perPage'           => $perPage,
            'routeBase'         => $routeBase,
            'status'            => $showArchived ? 'archived' : 'active',
            'toRecord'          => min($totalFamilies, $page * $perPage),
            'totalFamilies'     => $totalFamilies,
            'totalPages'        => $totalPages,
            // Filter UI data.
            'sectorOptions'     => (new SectorModel())->getSectorOptions(),
            'filters'           => $filters,
        ], $this->buildDeepSearchData($showArchived ? 'archived' : 'active'));
    }

    /**
     * Builds the SECOND ("search the whole database") results panel for Manage Records.
     * Only populated when the deep-search box (deep_q) is used; otherwise deepKeyword is
     * empty and the view hides the panel. Delegates to App\Models\SearchModel::allMembers().
     */
    private function buildDeepSearchData(string $status): array
    {
        $deepKeyword = trim((string) $this->request->getGet('deep_q'));

        if ($deepKeyword === '') {
            return [
                'deepKeyword'    => '',
                'deepResults'    => [],
                'deepPage'       => 1,
                'deepTotal'      => 0,
                'deepTotalPages' => 1,
                'deepFromRecord' => 0,
                'deepToRecord'   => 0,
            ];
        }

        $perPage = 50;
        $page = max(1, (int) $this->request->getGet('deep_page'));
        $filters = [
            'status'   => $status,
            'sectorID' => (string) $this->request->getGet('sectorID'),
            'date'     => (string) $this->request->getGet('date'),
        ];

        $searchModel = new SearchModel();
        $total = $searchModel->countAllMembers($deepKeyword, $filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        return [
            'deepKeyword'    => $deepKeyword,
            'deepResults'    => $searchModel->allMembers($deepKeyword, $filters, $perPage, ($page - 1) * $perPage),
            'deepPage'       => $page,
            'deepTotal'      => $total,
            'deepTotalPages' => $totalPages,
            'deepFromRecord' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'deepToRecord'   => min($total, $page * $perPage),
        ];
    }

    /**
     * Guards Developer/Admin/User access, then assembles the employee view data
     * (own activity instead of all audits, no account management) and renders the
     * employee shell (`Employee/index`). Frontend: returns the full employee page.
     */
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
        $recordListData = $activePage === 'family-manage'
            ? $this->buildEmployeeRecordListData()
            : [];
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
            'recordListData'     => $recordListData,
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

    /** Collects all supported search/filter query params into one array. */
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

    /** True if any search filter is set, used to decide search vs. default listing. */
    private function hasSearchFilters(array $filters): bool
    {
        foreach ($filters as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Employee counterpart of buildMemberListData(): same paginated family list but
     * always active-only and without restore controls. Frontend: the employee
     * Manage Records view.
     */
    private function buildEmployeeRecordListData(): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = 50;

        // Manage Records FILTER controls (sector + date). Employees only see active records.
        $filters = [
            'sectorID' => (string) $this->request->getGet('sectorID'),
            'date'     => (string) $this->request->getGet('date'),
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
            // Filter UI data.
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'filters' => $filters,
        ], $this->buildDeepSearchData('active'));
    }
}
