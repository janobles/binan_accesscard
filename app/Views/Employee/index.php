<?php
/**
 * Employee workspace shell. (The Employee role is stored in the DB as the legacy
 * enum value 'User' but is referred to as "Employee" throughout the app.)
 *
 * Rendered by App\Libraries\DashboardPageBuilder::renderEmployeePage(), which
 * passes every variable used below. Like the admin shell, this is one layout
 * that swaps its main section on $activePage (dashboard / family-entry /
 * family-manage / activity). Controller entry points live in
 * App\Controllers\Employee\WorkspaceController
 * (dashboard, familyEntry, manageRecords, activity).
 *
 * Employees only ever see ACTIVE records and their OWN activity (no archive,
 * no account/sector/service management). The formatDate/formatTime/
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
$sectorOptions = $familyFormViewData['sectorOptions'] ?? [];
$hasSearchFilters = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];
$canCreateFamily = $canCreateFamily ?? false;
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;

/*
 * Jade-style reskin: employee shell now uses jadebranch's dashboard-shell /
 * sidebar / header visual structure (matching the admin shell). Content keeps
 * melbranch's classes and is themed by css/melbranch-bridge.css. All data,
 * routes, JS hooks (#familyModal, .js-logout-link, .js-audit-filter-form) and
 * scripts are unchanged.
 */
$cssVersion = static function (string $relativeCssPath): string {
    $absolute = FCPATH . ltrim($relativeCssPath, '/');
    $version  = is_file($absolute) ? (string) filemtime($absolute) : (string) time();

    return base_url($relativeCssPath) . '?v=' . $version;
};
$jadeStyles = [
    'css/dashboard.css',
    'css/mainlayout.css',
    'css/managerecord.css',
    'css/searchbar.css',
    'css/audittrails.css',
    'css/familymodal.css',
    'assets/css/session-timeout.css',
    'css/melbranch-bridge.css',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php foreach ($jadeStyles as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc($cssVersion($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="dashboard-shell">
    <aside class="dashboard-sidebar employee">
        <a class="sidebar-brand" href="<?= site_url('employee/workspace') ?>">
            <img class="sidebar-logo" src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
            <span class="sidebar-brand-text">Bi&ntilde;an Access Card MIS<br><small class="sidebar-brand-mode">Employee Workspace</small></span>
        </a>
        <nav aria-label="Employee navigation">
            <section class="sidebar-section">
                <h2 class="sidebar-heading">Overview</h2>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Workspace</span></a>
                    </li>
                </ul>
            </section>
            <section class="sidebar-section">
                <h2 class="sidebar-heading">Records</h2>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('employee/manage-records') ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Manage Records</span></a>
                    </li>
                </ul>
            </section>
            <section class="sidebar-section">
                <h2 class="sidebar-heading">Activity</h2>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>"><i class="bi bi-clock-history" aria-hidden="true"></i><span>My Activity</span></a>
                    </li>
                </ul>
            </section>
            <section class="sidebar-section sidebar-account">
                <div class="sidebar-user"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?> &middot; Employee</span></div>
                <a href="<?= site_url('logout') ?>" class="nav-link js-logout-link"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span></a>
            </section>
        </nav>
    </aside>

    <div class="dashboard-workspace">
        <header class="dashboard-header">
            <img class="header-logo" src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
            <div>
                <h1 id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
                <p>Bi&ntilde;an Access Card MIS</p>
            </div>
        </header>

        <main class="dashboard-content">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <?php /* Main content swaps on $activePage: dashboard overview, add-record
                     form, the shared records list, and the employee's own activity. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="panel stat-panel"><small>Total Records</small><div class="stat-value"><?= esc((string) ($stats['families'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel stat-panel"><small>Registered Members</small><div class="stat-value"><?= esc((string) ($stats['members'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel stat-panel"><small>Active Sectors</small><div class="stat-value"><?= esc((string) ($stats['sectors'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel stat-panel"><small>Services and Programs</small><div class="stat-value"><?= esc((string) ($stats['assistance'] ?? 0)) ?></div></div></div>
                </div>

                <div class="panel mb-3">
                    <div class="section-title mt-0">
                        <span>Recently Added Records</span>
                    </div>
                    <?= view('Dashboard/partials/search-bar', [
                        'searchTerm'       => $searchTerm,
                        'sectorOptions'    => $sectorOptions,
                        'selectedSectorId' => (string) ($searchFilters['sectorID'] ?? ''),
                        'searchAction'     => site_url('employee/workspace'),
                        'searchAllAction'  => site_url('employee/manage-records'),
                    ]) ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Name (Head)</th><th>Sector</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentFamilies as $family): ?>
                                    <tr>
                                        <td><span class="entity-title"><?= esc(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')) ?></span></td>
                                        <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentFamilies === []): ?>
                                    <tr><td colspan="2" class="text-center text-muted"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching records found.' : 'No records yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Recent Activity</span>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('employee/activity') ?>"><i class="bi bi-arrow-right" aria-hidden="true"></i>View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
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
            <?php endif; ?>

            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Add Record</span>
                    </div>
                    <?= view('Dashboard/familyform', array_merge(
                        $familyFormViewData,
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-manage'): ?>
                <?= view('Dashboard/familyform/family-list', $recordListData) ?>
            <?php endif; ?>

            <?php if ($activePage === 'activity'): ?>
                <div class="panel">
                    <div class="section-title mt-0"><span>My Recent Activity</span></div>
                    <form class="row g-2 filter-bar js-audit-filter-form" method="get" action="<?= site_url('employee/activity') ?>">
                        <div class="col-md-6 col-lg-4">
                            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search activity by action or description">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <select class="form-select js-audit-action-filter" name="action">
                                <option value="">All actions</option>
                                <?php foreach ($auditActionOptions as $action): ?>
                                    <?php $action = trim((string) $action); ?>
                                    <option value="<?= esc($action) ?>" <?= trim((string) ($searchFilters['action'] ?? '')) === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Search</button>
                        </div>
                        <?php if ($hasSearchFilters): ?>
                            <div class="col-auto">
                                <a class="btn btn-outline-secondary" href="<?= site_url('employee/activity') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i>Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm">
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
                                    <tr><td colspan="3" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php /* Shared modal target for the add/edit/view record fragments loaded by
         assets/js/dashboard/manage-family-modal.js (?partial=1 fetch). */ ?>
<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-labelledby="familyModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyModalLabel">Manage Record</h5>
            </div>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/dashboard/family-form-ui.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form-ui.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-form.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-list.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-list.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/audit-filters.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/audit-filters.js') ?>"></script>
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-home-url="<?= site_url('/') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/dashboard-modal-loader.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/dashboard-modal-loader.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/manage-family-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-modal.js') ?>"></script>
</body>
</html>
