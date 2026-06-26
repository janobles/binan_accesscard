<?php
/**
 * Employee workspace shell.
 * Uses the same dashboard frame as Admin, limited to Dashboard, Manage Records,
 * and My Activity.
 */
$username = $user['username'] ?? 'Employee';
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? ($activePage === 'dashboard' ? 'Workspace' : ucwords(str_replace('-', ' ', $activePage)));
$navActive = $navActive ?? [];
$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$myAudits = $myAudits ?? [];
$recordListData = $recordListData ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$hasSearchFilters = $hasSearchFilters ?? false;
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('employee')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion employee" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('employee/workspace') ?>">
                <img class="sidebar-brand-icon" src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS</span>
            </a>
        </li>
        <li><hr class="sidebar-divider my-0"></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a>
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
                        <a href="#" class="nav-link topbar-user js-open-my-account-modal" data-modal-url="<?= site_url('account/profile') ?>" data-modal-title="My Account"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?> &middot; Encoder</span></a>
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
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm overview-table">
                                    <thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($myAudits as $audit): ?>
                                            <tr>
                                                <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                                <td><?= esc(isset($formatAuditMember) ? $formatAuditMember($audit) : '') ?></td>
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

                <?php if ($activePage === 'family-manage'): ?>
                    <?= view('Family/list', $recordListData) ?>
                <?php endif; ?>

                <?php if ($activePage === 'activity'): ?>
                    <?php
                    $auditListData = $auditListData ?? [];
                    $listRoute = (string) ($auditListData['listRoute'] ?? 'employee/activity');
                    $auditAction = trim((string) ($searchFilters['action'] ?? ''));
                    $perPage = (int) ($auditListData['perPage'] ?? 50);
                    $perPageOptions = ($auditListData['perPageOptions'] ?? []) ?: [10, 25, 50, 100];
                    $page = (int) ($auditListData['page'] ?? 1);
                    $totalPages = (int) ($auditListData['totalPages'] ?? 1);
                    $totalRows = (int) ($auditListData['totalRows'] ?? count($myAudits));
                    $fromRecord = (int) ($auditListData['fromRecord'] ?? 0);
                    $toRecord = (int) ($auditListData['toRecord'] ?? 0);

                    $auditPageUrl = static function (int $targetPage) use ($listRoute, $searchTerm, $auditAction, $perPage): string {
                        $params = array_filter([
                            'q' => $searchTerm,
                            'action' => $auditAction,
                            'per_page' => $perPage !== 50 ? (string) $perPage : '',
                            'page' => $targetPage > 1 ? (string) $targetPage : '',
                        ], static fn ($value): bool => $value !== '');

                        return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
                    };
    
                    $auditClearUrl = static function () use ($listRoute, $auditAction, $perPage): string {
                        $params = array_filter([
                            'action' => $auditAction,
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
                                <button class="btn btn-primary records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
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
                                <thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead>
                                <tbody>
                                    <?php foreach ($myAudits as $audit): ?>
                                        <tr>
                                            <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                            <td><?= esc(isset($formatAuditMember) ? $formatAuditMember($audit) : '') ?></td>
                                            <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($myAudits === []): ?>
                                        <tr><td colspan="3" class="text-center text-muted audit-empty-state"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalRows > 0): ?>
                            <div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span class="text-muted small">Showing <?= esc((string) $fromRecord) ?>-<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?></span>
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

<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-label="Details" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyModalLabel">Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<?= view('Family/action-confirm-modal') ?>

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('employee')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-home-url="<?= site_url('/') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
</body>
</html>
