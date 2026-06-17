<?php
/**
 * Employee workspace shell. (The Employee role is stored in the DB as the legacy
 * enum value 'User' but is referred to as "Employee" throughout the app.)
 *
 * Rendered by App\Libraries\DashboardPageBuilder::renderEmployeePage(), which
 * passes every variable used below. Like the admin shell, this is one layout
 * that swaps its main section on $activePage (dashboard / family-entry /
 * family-manage / activity). Controller entry points live in
 * App\Controllers\Employee\DashboardController
 * (dashboard, familyEntry, manageRecords, activity).
 *
 * Employees can view and edit family records only. Archive, restore, and
 * delete are restricted to Admin and Developer roles. The formatDate/formatTime/
 * formatAuditMember helpers are provided by the builder.
 */
$username = $user['username'] ?? 'Employee';
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? ($activePage === 'dashboard' ? 'Workspace' : ucwords(str_replace('-', ' ', $activePage)));
$navActive = $navActive ?? [];
$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$myAudits = $myAudits ?? [];
$familyFormViewData = $familyFormViewData ?? [];
$recordListData = $recordListData ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$hasSearchFilters = $searchTerm !== '' || array_filter($searchFilters, static function ($value): bool {
    if (is_array($value)) {
        return array_filter($value, static fn ($item): bool => trim((string) $item) !== '' && trim((string) $item) !== '__all') !== [];
    }

    $normalized = trim((string) $value);

    return $normalized !== '' && $normalized !== '__all';
}) !== [];
$canCreateFamily = $canCreateFamily ?? false;
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;

/*
 * SB Admin-style shell: keep employee data, routes, modal target, and page
 * switch while using the same responsive frame as the admin workspace.
 */
$cssVersion = static function (string $relativeCssPath): string {
    $absolute = FCPATH . ltrim($relativeCssPath, '/');
    $version  = is_file($absolute) ? (string) filemtime($absolute) : (string) time();

    return base_url($relativeCssPath) . '?v=' . $version;
};
$jadeStyles = [
    'css/sb-admin-adapter.css',
    'css/managerecord.css',
    'css/audittrails.css',
    'css/familymodal.css',
    'css/accounts.css',
    'css/session-timeout.css',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="<?= esc($cssVersion('assets/bootstrap/css/bootstrap.min.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc($cssVersion('assets/bootstrap-icons/font/bootstrap-icons.min.css'), 'attr') ?>" rel="stylesheet">
    <?php foreach ($jadeStyles as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc($cssVersion($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion employee" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('employee/workspace') ?>">
                <img class="sidebar-brand-icon" src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS<small>Employee Workspace</small></span>
            </a>
        </li>
        <li><hr class="sidebar-divider my-0"></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Workspace</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Records</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('employee/manage-records') ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Manage Records</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Activity</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>"><i class="bi bi-clock-history" aria-hidden="true"></i><span>My Activity</span></a>
        </li>
        <li><hr class="sidebar-divider d-none d-md-block"></li>
        <li class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle" type="button" aria-label="Collapse sidebar" aria-controls="dashboard-sidebar" aria-expanded="true"></button>
        </li>
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3" type="button" aria-label="Toggle navigation menu" aria-controls="dashboard-sidebar" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="topbar-title">
                    <div>
                        <h1 id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
                    </div>
                </div>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="#" class="nav-link topbar-user js-open-my-account-modal" data-modal-url="<?= site_url('account/profile') ?>" data-modal-title="My Account"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?> &middot; Employee</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= site_url('logout') ?>" class="nav-link js-logout-link"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span></a>
                    </li>
                </ul>
            </nav>

            <main class="container-fluid dashboard-content">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('family_record_saved')): ?>
                <span id="familyDraftSavedMarker" hidden></span>
            <?php endif; ?>

            <?php /* Main content swaps on $activePage: dashboard overview, add-record
                     form, the shared records list, and the employee's own activity. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <div class="dashboard-overview" data-dashboard-overview>
                    <section class="overview-stats" aria-label="Dashboard statistics">
                        <article class="stat-card"><p>Total Records</p><strong><?= esc((string) ($stats['families'] ?? 0)) ?></strong></article>
                        <article class="stat-card"><p>Registered Members</p><strong><?= esc((string) ($stats['members'] ?? 0)) ?></strong></article>
                        <article class="stat-card"><p>Active Sectors</p><strong><?= esc((string) ($stats['sectors'] ?? 0)) ?></strong></article>
                        <article class="stat-card"><p>Services and Programs</p><strong><?= esc((string) ($stats['assistance'] ?? 0)) ?></strong></article>
                    </section>

                    <div class="panel dashboard-table-panel">
                        <div class="section-title mt-0">
                            <span>Recently Added Records</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm overview-table">
                                <thead><tr><th>Name (Head)</th><th>Sector</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentFamilies as $family): ?>
                                        <tr>
                                            <td><span class="entity-title"><?= esc(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')) ?></span></td>
                                            <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($recentFamilies === []): ?>
                                        <tr><td colspan="2" class="text-center text-muted">No records yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="panel dashboard-table-panel">
                        <div class="section-title mt-0">
                            <span>Recent Activity</span>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('employee/activity') ?>"><i class="bi bi-arrow-right" aria-hidden="true"></i>View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm overview-table">
                                <thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead>
                                <tbody>
                                    <?php foreach ($myAudits as $audit): ?>
                                        <tr>
                                            <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                            <td><?= esc($formatAuditMember($audit)) ?></td>
                                            <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($myAudits === []): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No activity yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Add Record</span>
                    </div>
                    <?= view('Family/entry', array_merge(
                        $familyFormViewData,
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-manage'): ?>
                <?= view('Family/list', $recordListData) ?>
            <?php endif; ?>

            <?php if ($activePage === 'activity'): ?>
                <?php
                // Dual-search layout mirroring the Lookups pages (records-* classes), scoped to the
                // logged-in employee's own audit rows. Bar 1 = database search (server GET) keeping
                // .js-audit-filter-form + .js-audit-action-filter; Bar 2 = page-size + client-side
                // local "Search:" filter via data-lookup-search (lookup-search.js / data-audit-management-root).
                $auditListData  = $auditListData ?? [];
                $listRoute      = (string) ($auditListData['listRoute'] ?? 'employee/activity');
                $auditAction    = trim((string) ($searchFilters['action'] ?? ''));
                $perPage        = (int) ($auditListData['perPage'] ?? 50);
                $perPageOptions = ($auditListData['perPageOptions'] ?? []) ?: [10, 25, 50, 100];
                $page           = (int) ($auditListData['page'] ?? 1);
                $totalPages     = (int) ($auditListData['totalPages'] ?? 1);
                $totalRows      = (int) ($auditListData['totalRows'] ?? count($myAudits));
                $fromRecord     = (int) ($auditListData['fromRecord'] ?? 0);
                $toRecord       = (int) ($auditListData['toRecord'] ?? 0);

                $auditPageUrl = static function (int $targetPage) use ($listRoute, $searchTerm, $auditAction, $perPage): string {
                    $params = array_filter([
                        'q'        => $searchTerm,
                        'action'   => $auditAction,
                        'per_page' => $perPage !== 50 ? (string) $perPage : '',
                        'page'     => $targetPage > 1 ? (string) $targetPage : '',
                    ], static fn ($value): bool => $value !== '');

                    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
                };

                $auditClearUrl = static function () use ($listRoute, $auditAction, $perPage): string {
                    $params = array_filter([
                        'action'   => $auditAction,
                        'per_page' => $perPage !== 50 ? (string) $perPage : '',
                    ], static fn ($value): bool => $value !== '');

                    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
                };
                ?>
                <div class="panel" data-audit-management-root>
                    <div class="section-title mt-0"><span>My Recent Activity</span></div>

                    <div class="records-search-panel">
                        <form class="records-search-row records-lookup-search js-audit-filter-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search my activity">
                            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm, 'attr') ?>" placeholder="Search my activity by action or description" aria-label="Search my activity" autocomplete="off">
                            <select class="form-select records-status-select js-audit-action-filter" name="action" aria-label="Filter by action">
                                <option value="">All actions</option>
                                <?php foreach ($auditActionOptions as $action): ?>
                                    <?php $action = trim((string) $action); ?>
                                    <option value="<?= esc($action) ?>" <?= $auditAction === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
                            <a class="btn btn-outline-secondary records-search-action" href="<?= esc($auditClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
                            <button class="btn btn-primary records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
                        </form>
                    </div>

                    <div class="table-meta">
                        <div class="records-table-controls">
                            <form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
                                <?php if ($searchTerm !== ''): ?><input type="hidden" name="q" value="<?= esc($searchTerm, 'attr') ?>"><?php endif; ?>
                                <?php if ($auditAction !== ''): ?><input type="hidden" name="action" value="<?= esc($auditAction, 'attr') ?>"><?php endif; ?>
                                <label for="activityPerPage">Show</label>
                                <select class="form-select form-select-sm" id="activityPerPage" name="per_page" onchange="this.form.submit()">
                                    <?php foreach ($perPageOptions as $option): ?>
                                        <option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span>entries</span>
                            </form>
                            <form class="records-table-search-form" role="search" data-lookup-search aria-label="Filter shown activity">
                                <label for="activityLocalSearch">Search:</label>
                                <input class="form-control form-control-sm" type="search" id="activityLocalSearch" data-lookup-search-input placeholder="Type to filter..." autocomplete="off" aria-label="Filter shown activity">
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Action</th><th>Member</th><th>Description</th><th class="text-end">Details</th></tr></thead>
                            <tbody>
                                <?php foreach ($myAudits as $audit): ?>
                                    <tr>
                                        <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                        <td><?= esc($formatAuditMember($audit)) ?></td>
                                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary js-audit-detail"
                                                data-full="<?= esc((string) ($audit['full_description'] ?? ''), 'attr') ?>">
                                                <i class="bi bi-eye" aria-hidden="true"></i>View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($myAudits === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted audit-empty-state"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalRows > 0): ?>
                        <div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="text-muted small">Showing <?= esc((string) $fromRecord) ?>–<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?></span>
                            <?php if ($totalPages > 1): ?>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= esc($auditPageUrl(max(1, $page - 1)), 'attr') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
                                    <span class="btn btn-sm disabled">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
                                    <a class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= esc($auditPageUrl(min($totalPages, $page + 1)), 'attr') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php /* Shared modal target for the add/edit/view record fragments loaded by
         assets/js/dashboard/manage-family-modal.js (?partial=1 fetch). */ ?>
<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-label="Record details" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-body" id="familyModalBody">
                <div class="family-modal-loading" role="status" aria-live="polite">
                    <div class="spinner-border text-primary" aria-hidden="true"></div>
                    <span>Loading...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php /* Per-row audit detail modal, populated client-side by audit-detail-modal.js. */ ?>
<div class="modal fade audit-detail-modal" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="auditDetailTitle">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="audit-detail-full" id="auditDetailFull">—</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/jquery/jquery-3.7.1.min.js') ?>?v=<?= filemtime(FCPATH . 'assets/jquery/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= base_url('assets/bootstrap/js/bootstrap.bundle.min.js') ?>?v=<?= filemtime(FCPATH . 'assets/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/view-interactions.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/view-interactions.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-form-ui.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form-ui.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-form.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-list.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-list.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/audit-filters.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/audit-filters.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/lookup-search.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/lookup-search.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/audit-detail-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/audit-detail-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-home-url="<?= site_url('/') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/dashboard-modal-loader.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/dashboard-modal-loader.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/manage-family-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/my-account-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/my-account-modal.js') ?>"></script>
</body>
</html>
