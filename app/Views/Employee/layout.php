<?php
/**
 * Employee workspace shell.
 * Uses the same dashboard frame as Admin, limited to Dashboard, Manage Records,
 * and My Activity.
 */
helper('asset');

$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? ($activePage === 'dashboard' ? 'Dashboard' : ucwords(str_replace('-', ' ', $activePage)));
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
<<<<<<< HEAD
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
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3" type="button" aria-label="Toggle navigation menu" aria-controls="dashboard-sidebar" aria-expanded="false">
                    <span>Menu</span>
                </button>
                <div class="topbar-title">
                    <div>
                        <h1 id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
                    </div>
=======
<body class="sb-nav-fixed">
<?= view('Partials/dashboard-topnav', [
    'brandUrl' => site_url('employee/workspace'),
    'user' => $user,
    'username' => $username,
    'accountLevelLabel' => $accountLevelLabel,
]) ?>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion sb-sidenav-dark employee" id="dashboard-sidebar">
            <div class="sb-sidenav-menu">
                <div class="nav">
                    <div class="sb-sidenav-menu-heading">Core</div>
                    <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>"><div class="sb-nav-link-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>Dashboard</a>
                    <div class="sb-sidenav-menu-heading">Records</div>
                    <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('employee/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>Manage Records</a>
                    <div class="sb-sidenav-menu-heading">Activity</div>
                    <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>My Activity</a>
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a
                </div>
            </div>
        </nav>
    </div>
    <div id="layoutSidenav_content">
            <main class="container-fluid px-4 dashboard-content">
                <h1 class="mt-4" id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success" data-auto-dismiss-alert><?= esc(session()->getFlashdata('success')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger" data-auto-dismiss-alert><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <?php if ($activePage === 'dashboard'): ?>
                    <div class="dashboard-overview" data-dashboard-overview>
                        <section class="overview-stats" aria-label="Dashboard statistics">
                            <?= view('components/stat_card', [
                                'label' => 'Total Records',
                                'value' => (string) ($stats['families'] ?? 0),
                                'icon' => 'folder-fill',
                                'variant' => 'stat-card--records',
                            ]) ?>
                            <?= view('components/stat_card', [
                                'label' => 'Registered Members',
                                'value' => (string) ($stats['members'] ?? 0),
                                'icon' => 'people-fill',
                                'variant' => 'stat-card--members',
                            ]) ?>
                            <?= view('components/stat_card', [
                                'label' => 'Active Sectors',
                                'value' => (string) ($stats['sectors'] ?? 0),
                                'icon' => 'diagram-3-fill',
                                'variant' => 'stat-card--sectors',
                            ]) ?>
                            <?= view('components/stat_card', [
                                'label' => 'Services and Programs',
                                'value' => (string) ($stats['assistance'] ?? 0),
                                'icon' => 'grid-fill',
                                'variant' => 'stat-card--services',
                            ]) ?>
                        </section>

                        <?php
                        $recentFamilyRows = [];
                        foreach ($recentFamilies as $family) {
                            $recentFamilyRows[] = [
                                '<span class="entity-title">' . esc(mb_strtoupper(trim(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')), 'UTF-8')) . '</span>',
                                view('Partials/sector-label-list', ['sectorLabel' => mb_strtoupper((string) ($family['sector_name'] ?? ''), 'UTF-8')]),
                            ];
                        }
                        $myAuditRows = [];
                        foreach ($myAudits as $audit) {
                            $myAuditRows[] = [
                                '<span class="status-pill is-muted">' . esc((string) ($audit['user_action'] ?? '')) . '</span>',
                                esc(isset($formatAuditMember) ? $formatAuditMember($audit) : ''),
                                esc((string) ($audit['description'] ?? '')),
                            ];
                        }
                        ?>
                        <?= view('components/data_table', [
                            'icon' => 'table',
                            'title' => 'Recently Added Records',
                            'columns' => ['Name (Head)', 'Sector'],
                            'rows' => $recentFamilyRows,
                            'emptyMessage' => 'No records yet.',
                            'tableClass' => 'table overview-table mb-0',
                            'cardClass' => 'dashboard-table-panel',
                        ]) ?>

                        <?= view('components/data_table', [
                            'icon' => 'clock-history',
                            'title' => 'Recent Activity',
                            'columns' => ['Action', 'Member', 'Description'],
                            'rows' => $myAuditRows,
                            'emptyMessage' => 'No activity yet.',
                            'tableClass' => 'table overview-table mb-0',
                            'cardClass' => 'dashboard-table-panel',
                        ]) ?>
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
                    $perPage = (int) ($auditListData['perPage'] ?? 25);
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
                            'per_page' => $perPage !== 25 ? (string) $perPage : '',
                            'page' => $targetPage > 1 ? (string) $targetPage : '',
                        ], static fn ($value): bool => $value !== '');

                        return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
                    };
    
                    // "Clear" resets the whole toolbar (keyword + action filter, back to
                    // page 1) per the one-role-per-control rule; page size survives.
                    $auditClearUrl = static function () use ($listRoute, $perPage): string {
                        $params = $perPage !== 25 ? ['per_page' => (string) $perPage] : [];

                        return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
                    };
                    ?>
<<<<<<< HEAD
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
                                <div class="btn-toolbar" role="toolbar" aria-label="Activity actions">
                                    <div class="btn-group" role="group" aria-label="Search activity">
                                        <a class="btn btn-outline-secondary records-search-action" href="<?= esc($auditClearUrl(), 'attr') ?>"><span>Clear</span></a>
                                        <button class="btn btn-primary records-search-action" type="submit"><span>Search</span></button>
                                    </div>
                                </div>
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
=======
                    <?php
                    $activityActionRadios = [['value' => '', 'label' => 'All actions', 'checked' => $auditAction === '', 'default' => true]];
                    foreach ($auditActionOptions as $action) {
                        $action = trim((string) $action);
                        $activityActionRadios[] = ['value' => $action, 'label' => $action, 'pill' => $action, 'checked' => $auditAction === $action];
                    }
                    ?>
                    <?= view('components/records_toolbar_server', [
                        'formAction' => site_url($listRoute),
                        'formAria' => 'Search all my activity',
                        'searchPlaceholder' => 'Search all my activity...',
                        'keyword' => $searchTerm,
                        'clearUrl' => $auditClearUrl(),
                        'pillsId' => 'activityFilterPills',
                        'narrow' => true,
                        'hiddenHtml' => $perPage !== 25 ? '<input type="hidden" name="per_page" value="' . esc((string) $perPage, 'attr') . '">' : '',
                        'radioGroups' => [[
                            'name' => 'action',
                            'label' => 'Action',
                            'scroll' => true,
                            'options' => $activityActionRadios,
                        ]],
                    ]) ?>
                    <?php
                    $activityFooter = $totalRows > 0 ? view('components/table_footer', [
                        'fromRecord' => $fromRecord,
                        'toRecord' => $toRecord,
                        'totalRows' => $totalRows,
                        'page' => $page,
                        'totalPages' => $totalPages,
                        'prevUrl' => $auditPageUrl(max(1, $page - 1)),
                        'nextUrl' => $auditPageUrl(min($totalPages, $page + 1)),
                    ]) : null;
                    ?>
                    <?= view('components/card', [
                        'icon' => 'clock-history',
                        'title' => 'My Recent Activity',
                        'attrs' => 'data-audit-management-root',
                        'bodyView' => 'Employee/activity-body',
                        'bodyData' => [
                            'listRoute' => $listRoute,
                            'searchTerm' => $searchTerm,
                            'auditAction' => $auditAction,
                            'auditActionOptions' => $auditActionOptions,
                            'perPage' => $perPage,
                            'perPageOptions' => $perPageOptions,
                            'myAudits' => $myAudits,
                            'hasSearchFilters' => $hasSearchFilters,
                            'formatAuditMember' => $formatAuditMember ?? null,
                            'auditClearUrl' => $auditClearUrl,
                        ],
                        'footer' => $activityFooter,
                    ]) ?>
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a
                <?php endif; ?>
            </main>
    </div>
</div>

<?= view('components/modal', [
    'id' => 'familyModal',
    'modalClass' => 'floating-family-modal',
    'attrs' => 'aria-label="Details" data-bs-backdrop="static" data-bs-keyboard="false"',
    'size' => 'modal-xl',
    'title' => 'Record',
    'titleId' => 'familyModalLabel',
    'bodyId' => 'familyModalBody',
    'bodyHtml' => '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
    'footerHtml' => '<button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button>',
]) ?>

<?= view('Family/action-confirm-modal') ?>

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('employee')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-home-url="<?= site_url('/') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
</body>
</html>
