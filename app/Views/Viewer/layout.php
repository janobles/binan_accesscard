<?php
/**
 * Viewer workspace shell (read-only).
 *
 * Rendered by App\Libraries\DashboardPageBuilder::renderViewerPage(), which passes
 * every variable used below. Like the admin/employee shells, this is one layout
 * that swaps its main section on $activePage (dashboard / family-manage / sectors /
 * services). Controller entry points live in App\Controllers\Viewer\DashboardController.
 *
 * A Viewer can only LOOK: the family records list shows "View" (no Add/Update/
 * Archive), and the sector/service lists render without Add/Edit/Archive. The
 * read-only family detail modal is served by FamilyController::viewFamily, which
 * permits the Viewer role. The formatDate/formatTime helpers come from the builder.
 */
$user = $user ?? [];
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? ($activePage === 'dashboard' ? 'Dashboard' : ucwords(str_replace('-', ' ', $activePage)));
$navActive = $navActive ?? [];
$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$recordListData = $recordListData ?? [];
$sectorListData = $sectorListData ?? [];
$serviceListData = $serviceListData ?? [];
$sectors = $sectors ?? [];
$services = $services ?? [];
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('viewer')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body class="sb-nav-fixed">
<?= view('Partials/dashboard-topnav', [
    'brandUrl' => site_url('viewer/dashboard'),
    'user' => $user,
    'username' => $username,
    'accountLevelLabel' => $accountLevelLabel,
]) ?>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion sb-sidenav-dark viewer" id="dashboard-sidebar">
            <div class="sb-sidenav-menu">
                <div class="nav">
                    <div class="sb-sidenav-menu-heading">Core</div>
                    <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('viewer/dashboard') ?>"><div class="sb-nav-link-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>Dashboard</a>
                    <div class="sb-sidenav-menu-heading">Records</div>
                    <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('viewer/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people" aria-hidden="true"></i></div>Manage Records</a>
                    <div class="sb-sidenav-menu-heading">Reference Data</div>
                    <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('viewer/sectors') ?>"><div class="sb-nav-link-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>Sectors</a>
                    <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('viewer/services') ?>"><div class="sb-nav-link-icon"><i class="bi bi-grid" aria-hidden="true"></i></div>Services and Programs</a>
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

            <?php /* Main content swaps on $activePage: read-only overview, the shared
                     records list (view-only), and the read-only sector/service lists. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <div class="dashboard-overview" data-dashboard-overview>
                    <section class="overview-stats" aria-label="Dashboard statistics">
                        <?= view('components/stat_card', [
                            'label' => 'Total Records',
                            'value' => (string) ($stats['families'] ?? 0),
                            'icon' => 'folder2-open',
                            'variant' => 'stat-card--records',
                        ]) ?>
                        <?= view('components/stat_card', [
                            'label' => 'Registered Members',
                            'value' => (string) ($stats['members'] ?? 0),
                            'icon' => 'people',
                            'variant' => 'stat-card--members',
                        ]) ?>
                        <?= view('components/stat_card', [
                            'label' => 'Active Sectors',
                            'value' => (string) ($stats['sectors'] ?? 0),
                            'icon' => 'diagram-3',
                            'variant' => 'stat-card--sectors',
                        ]) ?>
                        <?= view('components/stat_card', [
                            'label' => 'Services and Programs',
                            'value' => (string) ($stats['assistance'] ?? 0),
                            'icon' => 'grid',
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
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-manage'): ?>
                <?= view('Family/list', $recordListData) ?>
            <?php endif; ?>

            <?php if ($activePage === 'sectors'): ?>
                <?= view('Lookups/sectors', [
                    'sectorListData' => $sectorListData,
                    'sectors' => $sectors,
                    'canManage' => false,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'services'): ?>
                <?= view('Lookups/services', [
                    'serviceListData' => $serviceListData,
                    'services' => $services,
                    'canManage' => false,
                ]) ?>
            <?php endif; ?>
            </main>
    </div>
</div>

<?php /* Shared modal target for the read-only "View Record" fragment loaded by
         assets/js/dashboard/manage-family-modal.js (?partial=1 fetch). */ ?>
<?= view('components/modal', [
    'id' => 'familyModal',
    'modalClass' => 'floating-family-modal',
    'attrs' => 'aria-label="Record details" data-bs-backdrop="static" data-bs-keyboard="false"',
    'size' => 'modal-xl',
    'title' => 'Record',
    'titleId' => 'familyModalLabel',
    'bodyId' => 'familyModalBody',
    'bodyHtml' => '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
    'footerHtml' => '<button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button>',
]) ?>

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('viewer')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-home-url="<?= site_url('/') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
</body>
</html>
