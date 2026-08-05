<?php

namespace App\Libraries;

use App\Models\Audit\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\Lookups\BarangayModel;
use App\Models\Families\MemberModel;
use App\Models\SearchModel;
use App\Models\Lookups\CategoryModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Scanner\SubsidyDistributionModel;
use App\Models\Scanner\SubsidyTypeModel;
use App\Models\Scanner\SubsidyStatsModel;
use App\Models\Scanner\DistributionBatchModel;
use App\Support\FamilyProfilingFormV2;
use App\Libraries\RoleAccess;
use App\Libraries\Scanner\BatchScope;
use App\Models\ViewLayoutModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use Config\IdleTimeout;
use Config\Navigation;

/**
 * Central view-data assembler for the dashboard. Admin\DashboardController delegates here so
 * controllers only choose WHICH page to show while this class gathers all the
 * models' data and renders the one shell view. The main place to look when
 * debugging what a dashboard page displays.
 */
class DashboardPageBuilder
{
    /**
     * Manifest page key => the view the shell renders in its main area. A key
     * with no entry here falls back to the dashboard body.
     *
     * @var array<string, string>
     */
    private const BODY_VIEWS = [
        'dashboard'      => 'Pages/dashboard',
        'records'        => 'Family/list',
        'reference-data' => 'Pages/reference-data',
        'cards'          => 'Cards/batch_form',
        'distribution'   => 'Pages/distribution',
        'accounts'       => 'Admin/accounts',
        'audit-trails'   => 'Admin/audit-trails',
    ];

    /** Holds the current request so query params (search/filters/page) are available. */
    public function __construct(private IncomingRequest $request) {}

    /**
     * The shell's account variables: who the topbar names, and what the sidebar's
     * user link may reach.
     *
     * A controller that renders `layout` directly (the import wizard, the family
     * entry and profile pages) never runs buildViewData(), so without this it
     * hands the shell no user at all and the topbar falls back to the word "User"
     * while the operator is signed in as themselves. Session-only, so it costs
     * nothing to call from a page that assembles the rest of its data itself.
     *
     * @return array{user: array, username: string, accountLevelLabel: string, canManageAccounts: bool}
     */
    public static function shellAccountData(): array
    {
        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        try {
            $user = SessionAccount::user();
        } catch (DatabaseException $e) {
            // The name in the topbar is not worth a 500. Fall back to the session,
            // which already carries the username the operator signed in with -
            // but only the display fields. Casting the whole session here would
            // hand auth_token and every other key to the views as $user.
            $user = [
                'username' => (string) (session()->get('username') ?? ''),
                'userID'   => (int) session()->get('user_id'),
            ];
        }

        return [
            'user'              => $user,
            'username'          => (string) (session()->get('username') ?? 'Admin'),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'canManageAccounts' => in_array($role, ['Developer', 'Admin'], true),
        ];
    }

    /** Normalized role label for the session, or null when the role is unknown. */
    private function currentRole(): ?string
    {
        return RoleAccess::normalizeRole((string) session()->get('role'));
    }

    /**
     * Manage Records row-action flags for a role: Add/Edit for the entry roles,
     * Archive/Restore for managers, none of it for a Viewer. Pulled out of
     * buildMemberListData() so it can be unit tested without a database. Row
     * actions are gated again server side in FamilyDataTablePresenter.
     *
     * @return array{0: bool, 1: bool, 2: bool} [canEdit, canArchive, canRestoreArchived]
     */
    private function recordListRoleFlags(?string $role): array
    {
        $canEdit = in_array($role, ['Developer', 'Admin', 'Encoder'], true);
        $canArchive = in_array($role, ['Developer', 'Admin'], true);

        return [$canEdit, $canArchive, $canArchive];
    }

    /**
     * Renders the dashboard shell (`layout`) on the given manifest page for
     * whatever role the session holds. Who may reach the page is the `roleNav`
     * route filter's decision (see app/Config/Navigation.php), not this class's.
     * Frontend: returns the full page HTML.
     */
    public function renderPage(string $activePage): string
    {
        return view('layout', $this->buildViewData($activePage));
    }

    /**
     * Assembles every variable the shell and the active page's body view need:
     * session user and role, the body view plus its data, account lists, recent
     * families/audits, sector/service lists, dashboard stats, search
     * term/filters, and view formatter closures (formatDate/Status/etc.). Also
     * reused to build AJAX partials.
     */
    public function buildViewData(string $activePage): array
    {
        $layoutModel    = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $searchFilters = $this->searchFilters();
        $hasSearchFilters = $this->hasSearchFilters($searchFilters);
        $currentRole = $this->currentRole();
        $isDeveloper = $currentRole === 'Developer';
        $isAdmin = $currentRole === 'Admin';
        $canManageAccounts = $isDeveloper || $isAdmin;
        $isAccounts = $activePage === 'accounts' && $canManageAccounts;
        $isDashboard = $activePage === 'dashboard';

        // Both manager roles read the same list through the search model, so the
        // page's search box and role/status filters work for an Admin too. Neither
        // query returns developer accounts.
        $users = $isAccounts ? $searchModel->staffAccounts($searchTerm, $searchFilters) : [];
        $sectorModel = new SectorModel();
        $serviceModel = new ServiceModel();

        $sectorOptions = $sectorModel->getSectorOptions();

        // Keep legacy file-backed Developer audit rows (NULL userID) visible only to
        // Developers. New Developer activity has a real userID like every DB account.
        $includeDeveloperAudits = $currentRole === 'Developer';
        $auditListData = $activePage === 'audit-trails'
            ? $this->buildAuditListData($includeDeveloperAudits, null, 'audit-trails')
            : [];
        // Only the Audit Trails page shows every user's audit rows. An Encoder has
        // no Audit Trails page, so their own recent activity rides the dashboard.
        $recentAudits = $auditListData['rows'] ?? [];
        $myAudits = $isDashboard && $currentRole === 'Encoder'
            ? (new AuditTrailsModel())->getByUser((int) session()->get('user_id'), 10)
            : [];
        $memberListData = $activePage === 'records'
            ? $this->buildMemberListData()
            : [];

        // Reference Data page: the lookup tables share one page, switched by ?tab=.
        // Only the active tab's list bundle is built (the tab strip is server-side
        // and only the active pane renders). Categories and Subsidy Types are
        // managers-only tabs; everyone else gets the two read-only lists.
        $referenceTabs = $canManageAccounts
            ? ['sectors', 'services', 'categories', 'subsidy-types']
            : ['sectors', 'services'];
        $referenceTab = (string) $this->request->getGet('tab');
        $referenceTab = in_array($referenceTab, $referenceTabs, true) ? $referenceTab : 'sectors';
        $isReference = $activePage === 'reference-data';

        $sectorListData = $isReference && $referenceTab === 'sectors'
            ? $this->buildLookupListData($sectorModel, 'reference-data', 'sectorID')
            : [];
        $serviceListData = $isReference && $referenceTab === 'services'
            ? $this->buildLookupListData($serviceModel, 'reference-data', 'serviceID')
            : [];
        $categoryListData = $isReference && $referenceTab === 'categories'
            ? $this->buildLookupListData(new CategoryModel(), 'reference-data', 'categoryID')
            : [];
        $subsidyTypeListData = $isReference && $referenceTab === 'subsidy-types'
            ? $this->buildLookupListData(model(SubsidyTypeModel::class), 'reference-data', 'subsidy_type_id')
            : [];

        // Distribution page: batches and the log share one page, switched by
        // ?tab=. Data gated so other pages don't run these queries.
        $distributionTab = (string) $this->request->getGet('tab');
        $distributionTab = in_array($distributionTab, ['batches', 'log'], true) ? $distributionTab : 'batches';
        $isBatches       = $activePage === 'distribution' && $distributionTab === 'batches';
        $isDistributions = $activePage === 'distribution' && $distributionTab === 'log';
        $batchModel      = model(DistributionBatchModel::class);

        // Dashboard batch zone's own table: Barangay / Stations / Remaining,
        // switched by ?tab= like the other tabbed pages. Not shared with
        // $distributionTab above: that one belongs to the Distribution page.
        $batchBodyTab = (string) $this->request->getGet('tab');
        $batchBodyTab = in_array($batchBodyTab, ['barangay', 'stations', 'remaining'], true) ? $batchBodyTab : 'barangay';

        // Two panes share the dashboard page. An unknown ?view= falls back to
        // Overview rather than erroring, matching how $batchBodyTab treats a
        // bad ?tab=.
        $dashboardView = (string) $this->request->getGet('view');
        $dashboardView = in_array($dashboardView, ['overview', 'distribution'], true) ? $dashboardView : 'overview';

        // The batch the reader explicitly asked for, straight off the query
        // rather than out of $reportsData: the outer tab strip has to carry the
        // selection onto the Overview pane, where no batch data is assembled at
        // all, and back again.
        $requestedBatch  = $this->request->getGet('batch');
        $selectedBatchId = is_scalar($requestedBatch) ? max(0, (int) $requestedBatch) : 0;

        // Distribution analytics now live on the dashboard (combined totals +
        // per-kiosk table), batch-scoped only (no date filter). Gated on the
        // page and the pane, not on the role: every role reaches the dashboard
        // and both panes, but neither pane pays for the other's queries.
        $reportsData = $isDashboard && $dashboardView === 'distribution'
            ? $this->buildReportsData($batchModel, $batchBodyTab)
            : [
                'batches'       => [],
                'batchRow'      => null,
                'batchOpen'     => false,
                'batchSnapshot' => $this->emptyBatchSnapshot(),
                'remainingPage' => $this->emptyRemainingPage(),
            ];

        // Hide the logged-in user's own account from Account Management; self-service
        // changes belong in My Account.
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

        $viewData = [
            'user' => $this->currentSessionUser(),
            'activePage' => $activePage,
            'role' => $currentRole ?? '',
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            // Developers and admins share account-management features. Developer
            // targets remain protected, and only Developers may toggle Administrators.
            'canManageAccounts' => $canManageAccounts,
            'canCreateAccounts' => $canManageAccounts,
            'canEditAccounts' => $canManageAccounts,
            'canManageLookups' => $canManageAccounts,
            'currentRole' => $currentRole,
            'referenceTabs'      => $referenceTabs,
            'myAudits'           => $myAudits,
            'adminAccounts'      => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'administrator')),
            // 'encoder' is the raw DB enum value for the Encoder role; the rows here
            // come straight from the users table (account_level aliased back to
            // `role` by SearchModel::staffAccounts()).
            'employeeAccounts'   => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'encoder')),
            'viewerAccounts'     => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'viewer')),
            'scannerAccounts'    => array_values(array_filter($visibleAccounts, static fn ($account) => $account['role'] === 'scanner')),
            'recentAudits'       => $recentAudits,
            'auditListData'      => $auditListData,
            'recordListData'      => $memberListData,
            'memberListData'      => $memberListData,
            'sectors'            => $sectorListData['rows'] ?? ($isReference ? $this->fetchVisibleSectors($sectorModel) : []),
            'services'           => $serviceListData['rows'] ?? ($isReference ? $this->fetchVisibleServices($serviceModel) : []),
            'categories'         => $categoryListData['rows'] ?? ($isReference ? $this->fetchVisibleCategories(new CategoryModel()) : []),
            'referenceTab'       => $referenceTab,
            'distributionTab'    => $distributionTab,
            'sectorListData'     => $sectorListData,
            'serviceListData'    => $serviceListData,
            'categoryListData'   => $categoryListData,
            // Same "all batches" list feeds the Distribution page's Batches tab
            // and the dashboard's batch selector; only one of the two gates is
            // ever true for a given page load.
            'batches'            => $isBatches ? $batchModel->allBatches() : ($reportsData['batches'] ?? []),
            'activeBatch'        => $isBatches ? $batchModel->activeBatch() : null,
            'activeSubsidyTypes' => $isBatches ? model(SubsidyTypeModel::class)->active() : [],
            // Barangay/sector option lists for the batch-open modal's eligibility
            // filters (Task 10). Only fetched on the batches tab.
            'barangayOptions'    => $isBatches ? model(BarangayModel::class)->activeList() : [],
            'batchSectorOptions' => $isBatches ? $sectorModel->getActive() : [],
            'subsidyTypes'       => $subsidyTypeListData['rows'] ?? [],
            'subsidyTypeListData' => $subsidyTypeListData,
            'distributions'      => $isDistributions ? model(SubsidyDistributionModel::class)->allDistributions() : [],
            'batchRow'           => $reportsData['batchRow'],
            'batchOpen'          => $reportsData['batchOpen'],
            'batchSnapshot'      => $reportsData['batchSnapshot'],
            'remainingPage'      => $reportsData['remainingPage'],
            'batchBodyTab'       => $batchBodyTab,
            'dashboardView'      => $dashboardView,
            'selectedBatchId'    => $selectedBatchId,
            'overviewStats'      => $isDashboard && $dashboardView === 'overview'
                ? $dashboardModel->programStats()
                : ['families' => 0, 'distributions' => 0, 'everServed' => 0, 'neverServed' => 0],
            'distributionRows'   => $isDashboard && $dashboardView === 'overview'
                ? $this->buildDistributionRows($batchModel)
                : [],
            'busiestDay'         => self::busiestDay($reportsData['batchSnapshot']['byDay'] ?? []),
            // Only the roles that may open the record-entry page get the Add and
            // Import buttons on the records list (Config\Navigation, records-entry).
            'canCreateFamily'    => in_array($currentRole, Navigation::pageRoles('records-entry'), true),
            'username'           => (string) (session()->get('username') ?? 'Admin'),
            'accountLevelLabel'  => SessionAccount::levelLabel(),
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

        // The shell renders one body view. Everything above is already shared view
        // data, so only the records list needs its own bundle handed over.
        $viewData['bodyView'] = self::BODY_VIEWS[$activePage] ?? 'Pages/dashboard';
        $viewData['bodyData'] = $activePage === 'records' ? $memberListData : [];

        return $viewData;
    }

    /** Public entry for the records-list AJAX partial (DashboardPartialsTrait). */
    public function buildRecordListViewData(): array
    {
        return $this->buildMemberListData();
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
     * search (25/page), and returns the row page plus pagination + count metadata.
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
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 25;

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
     * $includeDeveloper shows Developer (NULL-userID) rows (admin only); $userId scopes
     * to one user's own rows (employee Activity), or null for all users.
     */
    private function buildAuditListData(bool $includeDeveloper, ?int $userId, string $listRoute): array
    {
        $searchModel = new SearchModel();
        $keyword = trim((string) $this->request->getGet('q'));
        $filters = $this->searchFilters();
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 25;

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

    /**
     * Batch-scoped data for the dashboard's "this batch" zone: the cached
     * coverage/barangay/scanner/timeline snapshot plus one page of the
     * remaining-families list. The scoping batch defaults to the active batch,
     * else the most recent batch, and honors ?batch= when it matches a known
     * batch (BatchScope::resolve(), shared with the reports endpoint and the
     * scanner performance page).
     *
     * The remaining-families query only runs when $batchBodyTab is 'remaining':
     * against the 100k-family target, SubsidyStatsModel::remaining() lists
     * everyone in one response, so it is too large to fetch on every dashboard
     * load regardless of which sub-tab is showing. The PDF export keeps calling
     * remaining() directly (ReportsController::pdf()); the liquidation artifact
     * must stay whole.
     */
    private function buildReportsData(DistributionBatchModel $batchModel, string $batchBodyTab): array
    {
        $batches = $batchModel->allBatches();
        $active  = $batchModel->activeBatch();

        [$batchId, $batch] = BatchScope::resolve($batches, $active, (int) $this->request->getGet('batch'));

        $isOpen = $batch !== null && ($batch['closed_at'] ?? null) === null;
        $stats  = model(SubsidyStatsModel::class);

        return [
            'batches'       => $batches,
            'batchRow'      => $batch,
            'batchOpen'     => $isOpen,
            'batchSnapshot' => $batchId > 0 ? $stats->batchSnapshot($batchId, $isOpen) : $this->emptyBatchSnapshot(),
            'remainingPage' => $batchId > 0 && $batchBodyTab === 'remaining'
                ? $this->buildRemainingPageData($stats, $batchId)
                : $this->emptyRemainingPage(),
        ];
    }

    /**
     * Every distribution with its outcome, newest first, for the Overview
     * tab's table. Served comes from one grouped query rather than a call per
     * batch, so the table costs two queries regardless of how many batches
     * the city has run.
     *
     * @return list<array<string,mixed>>
     */
    private function buildDistributionRows(DistributionBatchModel $batchModel): array
    {
        $served = model(SubsidyStatsModel::class)->servedByBatch();

        return array_map(static function (array $batch) use ($served): array {
            $batchId  = (int) $batch['batch_id'];
            $eligible = (int) ($batch['eligible_count'] ?? 0);
            $count    = $served[$batchId] ?? 0;

            return $batch + [
                'eligible' => $eligible,
                'served'   => $count,
                'coverage' => $eligible === 0 ? 0 : (int) round($count / $eligible * 100),
            ];
        }, $batchModel->allBatches());
    }

    /**
     * The day carrying the largest served count, for the Busiest day card.
     * Null when the batch has no scans at all, which the view renders as a
     * dash rather than a zero that looks like a real reading.
     *
     * @param list<array{date:string,label:string,served:int}> $byDay
     * @return array{label:string,served:int}|null
     */
    private static function busiestDay(array $byDay): ?array
    {
        $best = null;
        foreach ($byDay as $day) {
            if ($best === null || $day['served'] > $best['served']) {
                $best = ['label' => $day['label'], 'served' => (int) $day['served']];
            }
        }

        return $best;
    }

    /**
     * Paginated remaining-families bundle for the dashboard's Remaining tab.
     * The 'q' query param (family name or barangay) routes through
     * SubsidyStatsModel::remainingBuilder() for both the count and the page,
     * so the footer total and the rows shown can never disagree.
     */
    private function buildRemainingPageData(SubsidyStatsModel $stats, int $batchId): array
    {
        $perPageOptions = [10, 25, 50, 100];
        $keyword = trim((string) $this->request->getGet('q'));
        $page    = max(1, (int) $this->request->getGet('page'));
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 25;

        $searchKeyword = $keyword === '' ? null : $keyword;
        $total      = $stats->remainingCount($batchId, $searchKeyword);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);

        return [
            'rows'          => $stats->remainingPage($batchId, $perPage, ($page - 1) * $perPage, $searchKeyword),
            'keyword'       => $keyword,
            'page'          => $page,
            'perPage'       => $perPage,
            'perPageOptions'=> $perPageOptions,
            'totalPages'    => $totalPages,
            'totalRows'     => $total,
            'fromRecord'    => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'toRecord'      => min($total, $page * $perPage),
        ];
    }

    /** The zero shape for buildRemainingPageData(), used when the tab isn't active or no batch is scoped. */
    private function emptyRemainingPage(): array
    {
        return [
            'rows' => [], 'keyword' => '', 'page' => 1, 'perPage' => 25, 'perPageOptions' => [10, 25, 50, 100],
            'totalPages' => 1, 'totalRows' => 0, 'fromRecord' => 0, 'toRecord' => 0,
        ];
    }

    /** The zero shape for SubsidyStatsModel::batchSnapshot(), used when no batch is scoped. */
    private function emptyBatchSnapshot(): array
    {
        return [
            'coverage'   => ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0],
            'byBarangay' => [],
            'perScanner' => [],
            'timeline'   => [],
            'byDay'      => [],
        ];
    }

    /** Session user plus stored profile details for topbar/account menus. */
    private function currentSessionUser(): array
    {
        return SessionAccount::user();
    }

    /**
     * Builds the Manage Records list for whatever role the session holds: reads
     * the q/status/page/sector/date query params, runs the paginated family-head
     * search, and merges in the deep (whole-database) search results. The role
     * only decides which controls the list may offer, never which rows it sees.
     * Frontend: the family-list view + its filter and pagination controls.
     */
    private function buildMemberListData(): array
    {
        $role = $this->currentRole();
        [$canEdit, $canArchive, $canRestoreArchived] = $this->recordListRoleFlags($role);

        $keyword = trim((string) $this->request->getGet('q'));
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['all', 'active', 'archived'], true) ? $status : 'all';
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = $this->recordsPerPage();

        // Manage Records FILTER controls (sector + date). Status (active/archived)
        // is handled separately above. Passed into MemberModel::searchFamilies().
        $filters = [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
            'date'     => (string) $this->request->getGet('date'),
        ];

        $memberModel = new MemberModel();
        $searchKeyword = $keyword === '' ? null : $keyword;
        $totalFamilies = $memberModel->countSearchFamilies($searchKeyword, $status, $filters);
        $totalPages = max(1, (int) ceil($totalFamilies / $perPage));
        $page = min($page, $totalPages);

        return array_merge([
            'canEdit'           => $canEdit,
            'canArchive'        => $canArchive,
            'canRestoreArchived' => $canRestoreArchived,
            'families'          => $memberModel->searchFamilies($searchKeyword, $perPage, ($page - 1) * $perPage, $status, $filters),
            'fromRecord'        => $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'isFullPage'        => true,
            'keyword'           => $keyword,
            // Full-page route so both the filter form and deep-search form reload the
            // whole Manage Records page (not the modal/partial list endpoint).
            'listRoute'         => 'records',
            'page'              => $page,
            'perPage'           => $perPage,
            'status'            => $status,
            'toRecord'          => min($totalFamilies, $page * $perPage),
            'totalFamilies'     => $totalFamilies,
            'totalPages'        => $totalPages,
            // Filter UI data.
            'sectorOptions'     => (new SectorModel())->getSectorOptions(),
            'barangayOptions'   => FamilyProfilingFormV2::barangays(),
            'filters'           => $filters,
        ], $this->buildDeepSearchData($status));
    }

    /**
     * Builds the SECOND ("search the whole database") results panel for Manage Records.
     * Only populated when the deep-search box (deep_q) is used; otherwise deepKeyword is
     * empty and the view hides the panel. Delegates to App\Models\SearchModel::allMembers().
     */
    private function buildDeepSearchData(string $status): array
    {
        $deepKeyword = trim((string) $this->request->getGet('deep_q'));
        $scopeAll = strtolower(trim((string) $this->request->getGet('search_scope'))) === 'all';

        // Some links/forms can still submit search_scope=all with the keyword in `q`.
        // Treat that value as the deep keyword so the whole-database results panel opens.
        if ($deepKeyword === '' && $scopeAll) {
            $deepKeyword = trim((string) $this->request->getGet('q'));
        }

        // Deep search is active when explicitly requested (search_scope=all) or when a
        // deep keyword is present. An empty keyword with scope=all lists everyone in the
        // database (filters still narrow it), matching the "show what's in the DB" intent.
        $deepActive = $scopeAll || $deepKeyword !== '';

        if (! $deepActive) {
            return [
                'deepActive'     => false,
                'deepKeyword'    => '',
                'deepResults'    => [],
                'deepPage'       => 1,
                'deepTotal'      => 0,
                'deepTotalPages' => 1,
                'deepFromRecord' => 0,
                'deepToRecord'   => 0,
            ];
        }

        $perPage = $this->recordsPerPage();
        $page = max(1, (int) $this->request->getGet('deep_page'));
        $filters = [
            'status'   => $status,
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
            'date'     => (string) $this->request->getGet('date'),
        ];

        $searchModel = new SearchModel();
        $total = $searchModel->countAllMembers($deepKeyword, $filters);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        return [
            'deepActive'     => true,
            'deepKeyword'    => $deepKeyword,
            'deepResults'    => $searchModel->allMembers($deepKeyword, $filters, $perPage, ($page - 1) * $perPage),
            'deepPage'       => $page,
            'deepTotal'      => $total,
            'deepTotalPages' => $totalPages,
            'deepFromRecord' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'deepToRecord'   => min($total, $page * $perPage),
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

    /** Whitelisted page sizes for Manage Records and deep-search pagination. */
    private function recordsPerPage(): int
    {
        $perPage = (int) $this->request->getGet('per_page');

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
    }
}
