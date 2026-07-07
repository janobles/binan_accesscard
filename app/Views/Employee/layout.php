<?php
/**
 * Employee workspace shell.
 * Uses the same dashboard frame as Admin, limited to Dashboard, Manage Records,
 * and My Activity.
 */
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
                    <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('employee/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people" aria-hidden="true"></i></div>Manage Records</a>
                    <div class="sb-sidenav-menu-heading">Activity</div>
                    <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>My Activity</a>
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
                <?php if (session()->getFlashdata('family_record_saved')): ?>
                    <span id="familyDraftSavedMarker" hidden></span>
                <?php endif; ?>

                <?php if ($activePage === 'dashboard'): ?>
                    <div class="dashboard-overview" data-dashboard-overview>
                        <section class="overview-stats" aria-label="Dashboard statistics">
                            <article class="stat-card stat-card--records card h-100 py-2">
                                <div class="card-body">
                                    <div class="stat-card-content">
                                        <div><p>Total Records</p><strong><?= esc((string) ($stats['families'] ?? 0)) ?></strong></div>
                                        <i class="bi bi-folder2-open stat-card-icon" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </article>
                            <article class="stat-card stat-card--members card h-100 py-2">
                                <div class="card-body">
                                    <div class="stat-card-content">
                                        <div><p>Registered Members</p><strong><?= esc((string) ($stats['members'] ?? 0)) ?></strong></div>
                                        <i class="bi bi-people stat-card-icon" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </article>
                            <article class="stat-card stat-card--sectors card h-100 py-2">
                                <div class="card-body">
                                    <div class="stat-card-content">
                                        <div><p>Active Sectors</p><strong><?= esc((string) ($stats['sectors'] ?? 0)) ?></strong></div>
                                        <i class="bi bi-diagram-3 stat-card-icon" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </article>
                            <article class="stat-card stat-card--services card h-100 py-2">
                                <div class="card-body">
                                    <div class="stat-card-content">
                                        <div><p>Services and Programs</p><strong><?= esc((string) ($stats['assistance'] ?? 0)) ?></strong></div>
                                        <i class="bi bi-grid stat-card-icon" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </article>
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
                            'tableClass' => 'table table-sm overview-table mb-0',
                            'cardClass' => 'dashboard-table-panel',
                        ]) ?>

                        <?= view('components/data_table', [
                            'icon' => 'clock-history',
                            'title' => 'Recent Activity',
                            'columns' => ['Action', 'Member', 'Description'],
                            'rows' => $myAuditRows,
                            'emptyMessage' => 'No activity yet.',
                            'tableClass' => 'table table-sm overview-table mb-0',
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
                <?php endif; ?>
            </main>
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
