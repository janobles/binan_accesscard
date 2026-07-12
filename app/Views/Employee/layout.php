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
                    <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('employee/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>Manage Records</a>
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
    
                    // "Clear" resets the whole toolbar (keyword + action filter, back to
                    // page 1) per the one-role-per-control rule; page size survives.
                    $auditClearUrl = static function () use ($listRoute, $perPage): string {
                        $params = $perPage !== 50 ? ['per_page' => (string) $perPage] : [];

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
