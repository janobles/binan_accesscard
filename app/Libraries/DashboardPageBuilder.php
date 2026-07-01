<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\Families\MemberModel;
use App\Models\SearchModel;
use App\Models\Lookups\CategoryModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Auth\UserModel;
use App\Support\FamilyProfilingFormV2;
use App\Libraries\RoleAccess;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Central view-data assembler for the dashboard. Admin\DashboardController delegates here so
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
     * (`Admin/layout`) on the given tab. Account management additionally
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

        return view('Admin/layout', $this->buildAdminViewData($activePage));
    }

    /**
     * Assembles every variable the admin shell and its sub-views need: page title,
     * role flags/permissions, nav highlighting, account lists, record filters,
     * recent families/audits, member list (on Manage Records), sector/service
     * lists, dashboard stats, search term/filters, and view formatter closures
     * (formatDate/Status/etc.). Also reused to build AJAX partials. Frontend:
     * consumed directly by `Admin/*` views.
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

        $sectorOptions = $sectorModel->getActive();

        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);

        // Only the Developer may see Developer (NULL-userID) audit rows; admins must
        // never learn a Developer exists.
        $includeDeveloperAudits = $currentRole === 'Developer';
        $auditListData = $activePage === 'audit-trails'
            ? $this->buildAuditListData($includeDeveloperAudits, null, 'admin/audit-trails')
            : [];
        $recentAudits = $auditListData['rows'] ?? (new AuditTrailsModel())->getRecent(10, $includeDeveloperAudits);
        $memberListData = $activePage === 'family-manage'
            ? $this->buildMemberListData()
            : [];

        // Paginated server-side list bundles for the lookup-management pages (built
        // only for the active page; other pages keep the full fetchVisible* lists).
        $sectorListData = $activePage === 'sectors'
            ? $this->buildLookupListData($sectorModel, 'admin/sectors', 'sectorID')
            : [];
        $serviceListData = $activePage === 'services'
            ? $this->buildLookupListData($serviceModel, 'admin/services', 'serviceID')
            : [];
        $categoryListData = $activePage === 'categories'
            ? $this->buildLookupListData(new CategoryModel(), 'admin/categories', 'categoryID')
            : [];

        // Hide the logged-in user's own account from their Account Management list;
        // other admins/developers still see it. The Developer logs in from .env
        // (userID 0, no users row), so nothing is hidden for it.
        $currentUserId = (int) session()->get('user_id');
        $visibleAccounts = $currentUserId > 0
            ? array_values(array_filter($users, static fn ($account) => (int) ($account['userID'] ?? 0) !== $currentUserId))
            : $users;

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
            'user' => $this->currentSessionUser(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->pageTitle($activePage),
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            // Developers and admins both manage all non-developer staff accounts:
            // create, edit, reset password, and (for admin/encoder) enable/disable.
            'canManageAccounts' => $isDeveloper || $isAdmin,
            'canCreateAccounts' => $isDeveloper || $isAdmin,
            'canEditAccounts' => $isDeveloper || $isAdmin,
            'currentRole' => $currentRole,
            'navActive' => [
                'dashboard'    => $layoutModel->navActive($activePage, 'dashboard'),
                'accounts'     => $layoutModel->navActive($activePage, 'accounts'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'audit-trails' => $layoutModel->navActive($activePage, 'audit-trails'),
                'sectors'      => $layoutModel->navActive($activePage, 'sectors'),
                'services'     => $layoutModel->navActive($activePage, 'services'),
                'categories'   => $layoutModel->navActive($activePage, 'categories'),
            ],
            'adminAccounts'      => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'administrator')),
            // 'encoder' is the raw DB enum value for the Employee role (surfaced as
            // "Employee" in the UI); the rows here come straight from the users table
            // (account_level aliased back to `role` by UserModel::getStaffAccounts).
            'employeeAccounts'   => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'encoder')),
            'viewerAccounts'     => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'viewer')),
            'recentFamilies'     => $recentFamilies,
            'recentAudits'       => $recentAudits,
            'auditListData'      => $auditListData,
            'recordListData'      => $memberListData,
            'memberListData'      => $memberListData,
            'sectors'            => $sectorListData['rows'] ?? $this->fetchVisibleSectors($sectorModel),
            'services'           => $serviceListData['rows'] ?? $this->fetchVisibleServices($serviceModel),
            'categories'         => $categoryListData['rows'] ?? $this->fetchVisibleCategories(new CategoryModel()),
            'sectorListData'     => $sectorListData,
            'serviceListData'    => $serviceListData,
            'categoryListData'   => $categoryListData,
            'stats'              => $dashboardModel->stats(),
            'username'           => (string) (session()->get('username') ?? 'Admin'),
            'searchTerm'         => $searchTerm,
            'searchFilters'      => $searchFilters,
            'hasSearchFilters'   => $hasSearchFilters,
            'selectedFilterDate' => (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? ''),
            'sectorOptions'      => $sectorOptions,
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
                $role     = RoleAccess::normalizeRole($role) ?? $role;

                return $role === '' ? $username : $username . ' (' . $role . ')';
            },
        ];
    }

    /** Public entry for the admin records-list AJAX partial (Admin\DashboardController::renderRecordListPartial). */
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

    /** All categories (active + archived), official first, for the Manage Categories view. */
    private function fetchVisibleCategories(CategoryModel $categoryModel): array
    {
        if (! $categoryModel->hasTable()) {
            return [];
        }

        return $categoryModel->getAllIncluding();
    }

    /**
     * Builds a paginated lookup-management list (Sectors / Services / Categories).
     * Reads the q/status/page query params, runs the model's status-aware keyword
     * search (50/page), and returns the row page plus pagination + count metadata.
     * The model must expose searchLookup()/countLookup()/statusCounts() (all three
     * lookup models do). Frontend: the Lookups/* views + their database-search bar,
     * status dropdown and pagination controls.
     *
     * @param object $model     A lookup model (SectorModel|ServiceModel|CategoryModel).
     * @param string $listRoute Full-page route the search/pagination forms post to.
     * @param string $idField   Primary-key column name (unused in the query; kept for clarity).
     */
    private function buildLookupListData(object $model, string $listRoute, string $idField): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status  = strtolower(trim((string) $this->request->getGet('status')));
        $status  = in_array($status, ['active', 'archived', 'all'], true) ? $status : 'all';
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 50;

        $searchKeyword = $keyword === '' ? null : $keyword;
        $total      = $model->countLookup($searchKeyword, $status);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $counts     = $model->statusCounts();

        return [
            'rows'          => $model->searchLookup($searchKeyword, $status, $perPage, ($page - 1) * $perPage),
            'keyword'       => $keyword,
            'status'        => $status,
            'page'          => $page,
            'perPage'       => $perPage,
            'perPageOptions'=> $perPageOptions,
            'totalPages'    => $totalPages,
            'totalRows'     => $total,
            'fromRecord'    => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'toRecord'      => min($total, $page * $perPage),
            'activeCount'   => (int) ($counts['active'] ?? 0),
            'archivedCount' => (int) ($counts['archived'] ?? 0),
            'listRoute'     => $listRoute,
        ];
    }

    /**
     * Builds a paginated audit-trail list bundle for the admin Audit Trails page
     * and the employee Activity page. Mirrors buildLookupListData(): reads the
     * q/action/page/per_page query params, runs the keyword + action/date search
     * (paginated), and returns the row page plus pagination + count metadata. The
     * action filter reuses searchFilters(). Frontend: the audit views' database
     * search bar, show-entries selector and pagination controls.
     *
     * @param bool     $includeDeveloper Whether Developer (NULL-userID) rows are visible (admin only).
     * @param int|null $userId           Scopes to one user's own rows (employee Activity), or null for all users.
     * @param string   $listRoute        Full-page route the search/pagination forms post to.
     */
    private function buildAuditListData(bool $includeDeveloper, ?int $userId, string $listRoute): array
    {
        $searchModel = new SearchModel();
        $keyword = trim((string) $this->request->getGet('q'));
        $filters = $this->searchFilters();
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 50;

        $total = $userId === null
            ? $searchModel->countAuditTrails($keyword, $filters, $includeDeveloper)
            : $searchModel->countAuditTrailsByUser($userId, $keyword, $filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page   = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = $userId === null
            ? $searchModel->auditTrails($keyword, $filters, $perPage, $includeDeveloper, $offset)
            : $searchModel->auditTrailsByUser($userId, $keyword, $filters, $perPage, $offset);

        return [
            'rows'          => $rows,
            'keyword'       => $keyword,
            'page'          => $page,
            'perPage'       => $perPage,
            'perPageOptions'=> $perPageOptions,
            'totalPages'    => $totalPages,
            'totalRows'     => $total,
            'fromRecord'    => $total === 0 ? 0 : $offset + 1,
            'toRecord'      => min($total, $page * $perPage),
            'listRoute'     => $listRoute,
        ];
    }

    /** Session user plus stored profile details for topbar/account menus. */
    private function currentSessionUser(): array
    {
        $sessionUser = session()->get();
        $userId = (int) ($sessionUser['user_id'] ?? 0);

        if ($userId <= 0) {
            return $sessionUser;
        }

        $account = (new UserModel())->getAccountById($userId);

        if ($account === null) {
            return $sessionUser;
        }

        return array_merge($sessionUser, $account);
    }

    /** Builds the Admin DataTables shell and advanced-filter options. */
    private function buildMemberListData(): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['all', 'active', 'archived'], true) ? $status : 'all';
        $filters = [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
        ];

        return [
            'keyword' => $keyword,
            'routeBase' => 'admin/manage-family',
            'status' => $status,
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'barangayOptions' => FamilyProfilingFormV2::barangays(),
            'filters' => $filters,
        ];
    }

    /**
     * Guards Developer/Admin/User access, then assembles the employee view data
     * (own activity instead of all audits, no account management) and renders the
     * employee shell (`Employee/layout`). Frontend: returns the full employee page.
     */
    public function renderEmployeePage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'Employee']);

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
        $sectorOptions = (new SectorModel())->getActive();
        $recordListData = $activePage === 'family-manage'
            ? $this->buildEmployeeRecordListData()
            : [];
        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);
        $auditListData = $activePage === 'activity'
            ? $this->buildAuditListData(false, $userId, 'employee/activity')
            : [];
        $myAudits = $auditListData['rows'] ?? (new AuditTrailsModel())->getByUser($userId, 10);

        return view('Employee/layout', [
            'user' => $this->currentSessionUser(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->employeePageTitle($activePage),
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'activity' => $layoutModel->navActive($activePage, 'activity'),
            ],
            'recordListData'     => $recordListData,
            'recentFamilies'     => $recentFamilies,
            'myAudits'           => $myAudits,
            'auditListData'      => $auditListData,
            'stats'              => array_merge(['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0], $dashboardModel->stats()),
            'searchTerm'         => $searchTerm,
            'searchFilters'      => $searchFilters,
            'auditActionOptions' => $searchModel->auditActions(),
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
            'username'           => (string) (session()->get('username') ?? 'Employee'),
            'sectorOptions'      => $sectorOptions,
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
            'formatAuditUser'    => static function (array $audit): string {
                $username = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
                $role     = trim((string) ($audit['user_role'] ?? ''));
                $role     = RoleAccess::normalizeRole($role) ?? $role;

                return $role === '' ? $username : $username . ' (' . $role . ')';
            },
        ]);
    }

    /**
     * Read-only counterpart of renderAdminPage/renderEmployeePage for the Viewer
     * role. Guards Viewer access, then renders the viewer shell (`Viewer/layout`)
     * with view-only data: dashboard stats + recent records, the family records
     * list (no add/edit/archive), and the read-only Sector/Service lookup lists.
     * Frontend: returns the full viewer page HTML.
     */
    public function renderViewerPage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Viewer']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $layoutModel = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);

        $recordListData = $activePage === 'family-manage'
            ? $this->buildViewerRecordListData()
            : [];
        $recentFamilies = $activePage === 'dashboard' && ($searchTerm !== '' || $hasSearchFilters)
            ? $searchModel->families($searchTerm, $searchFilters, 25)
            : $dashboardModel->recentFamilies(10);
        $sectorListData = $activePage === 'sectors'
            ? $this->buildLookupListData(new SectorModel(), 'viewer/sectors', 'sectorID')
            : [];
        $serviceListData = $activePage === 'services'
            ? $this->buildLookupListData(new ServiceModel(), 'viewer/services', 'serviceID')
            : [];

        return view('Viewer/layout', [
            'user' => $this->currentSessionUser(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->pageTitle($activePage),
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'family-manage' => $layoutModel->navActive($activePage, 'family-manage'),
                'sectors' => $layoutModel->navActive($activePage, 'sectors'),
                'services' => $layoutModel->navActive($activePage, 'services'),
            ],
            'recordListData'     => $recordListData,
            'recentFamilies'     => $recentFamilies,
            'sectorListData'     => $sectorListData,
            'serviceListData'    => $serviceListData,
            'sectors'            => $sectorListData['rows'] ?? [],
            'services'           => $serviceListData['rows'] ?? [],
            'stats'              => array_merge(['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0], $dashboardModel->stats()),
            'searchTerm'         => $searchTerm,
            'searchFilters'      => $searchFilters,
            'hasSearchFilters'   => $hasSearchFilters,
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
            'username'           => (string) (session()->get('username') ?? 'Viewer'),
            'formatDate'         => static function (mixed $value): string {
                $timestamp = strtotime((string) $value);

                return $timestamp === false ? '' : date('Y-m-d', $timestamp);
            },
            'formatTime'         => static function (mixed $value): string {
                $timestamp = strtotime((string) $value);

                return $timestamp === false ? '' : date('h:i A', $timestamp);
            },
        ]);
    }

    /** Public entry for the viewer records-list AJAX partial (Viewer\DashboardController). */
    public function buildViewerRecordListViewData(): array
    {
        return $this->buildViewerRecordListData();
    }

    /** Builds the read-only Viewer DataTables shell and filter options. */
    private function buildViewerRecordListData(): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['all', 'active', 'archived'], true) ? $status : 'all';
        $filters = [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
        ];

        return [
            'keyword' => $keyword,
            'routeBase' => 'viewer/manage-family',
            'status' => $status,
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'barangayOptions' => FamilyProfilingFormV2::barangays(),
            'filters' => $filters,
        ];
    }

    /** Collects all supported search/filter query params into one array. */
    private function searchFilters(): array
    {
        return [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
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
            if (is_array($value)) {
                if ($this->hasSearchFilters($value)) {
                    return true;
                }

                continue;
            }

            $normalized = trim((string) $value);

            if ($normalized !== '' && $normalized !== '__all') {
                return true;
            }
        }

        return false;
    }

    /** Builds the Employee DataTables shell and advanced-filter options. */
    private function buildEmployeeRecordListData(): array
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['all', 'active', 'archived'], true) ? $status : 'all';
        $filters = [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
        ];

        return [
            'keyword' => $keyword,
            'routeBase' => 'employee/manage-family',
            'status' => $status,
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'barangayOptions' => FamilyProfilingFormV2::barangays(),
            'filters' => $filters,
        ];
    }
}
