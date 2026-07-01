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
$username = $user['username'] ?? 'Viewer';
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
<body>
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion viewer" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('viewer/dashboard') ?>">
                <img class="sidebar-brand-icon" src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS<small>Viewer</small></span>
            </a>
        </li>
        <li><hr class="sidebar-divider my-0"></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('viewer/dashboard') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Records</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('viewer/manage-records') ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Manage Records</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Reference Data</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('viewer/sectors') ?>"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Sectors</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('viewer/services') ?>"><i class="bi bi-grid" aria-hidden="true"></i><span>Services and Programs</span></a>
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
                    <li class="nav-item topbar-divider d-none d-sm-block"></li>
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle topbar-user" href="#" id="viewerUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= esc($username) ?> &middot; Viewer</span>
                            <i class="bi bi-person-circle topbar-user-icon" aria-hidden="true"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="viewerUserDropdown">
                            <button type="button" class="dropdown-item js-open-my-account-modal" data-modal-url="<?= site_url('account/profile') ?>" data-modal-title="My Account">
                                <i class="bi bi-person me-2 text-gray-400" aria-hidden="true"></i> My Account
                            </button>
                            <div class="dropdown-divider"></div>
                            <a href="<?= site_url('logout') ?>" class="dropdown-item js-logout-link">
                                <i class="bi bi-box-arrow-right me-2 text-gray-400" aria-hidden="true"></i> Logout
                            </a>
                        </div>
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

            <?php /* Main content swaps on $activePage: read-only overview, the shared
                     records list (view-only), and the read-only sector/service lists. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <div class="dashboard-overview" data-dashboard-overview>
                    <section class="overview-stats" aria-label="Dashboard statistics">
                        <article class="stat-card stat-card--records card shadow-sm h-100 py-2">
                            <div class="card-body">
                                <div class="stat-card-content">
                                    <div><p>Total Records</p><strong><?= esc((string) ($stats['families'] ?? 0)) ?></strong></div>
                                    <i class="bi bi-folder2-open stat-card-icon" aria-hidden="true"></i>
                                </div>
                            </div>
                        </article>
                        <article class="stat-card stat-card--members card shadow-sm h-100 py-2">
                            <div class="card-body">
                                <div class="stat-card-content">
                                    <div><p>Registered Members</p><strong><?= esc((string) ($stats['members'] ?? 0)) ?></strong></div>
                                    <i class="bi bi-people stat-card-icon" aria-hidden="true"></i>
                                </div>
                            </div>
                        </article>
                        <article class="stat-card stat-card--sectors card shadow-sm h-100 py-2">
                            <div class="card-body">
                                <div class="stat-card-content">
                                    <div><p>Active Sectors</p><strong><?= esc((string) ($stats['sectors'] ?? 0)) ?></strong></div>
                                    <i class="bi bi-diagram-3 stat-card-icon" aria-hidden="true"></i>
                                </div>
                            </div>
                        </article>
                        <article class="stat-card stat-card--services card shadow-sm h-100 py-2">
                            <div class="card-body">
                                <div class="stat-card-content">
                                    <div><p>Services and Programs</p><strong><?= esc((string) ($stats['assistance'] ?? 0)) ?></strong></div>
                                    <i class="bi bi-grid stat-card-icon" aria-hidden="true"></i>
                                </div>
                            </div>
                        </article>
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
</div>

<?php /* Shared modal target for the read-only "View Record" fragment loaded by
         assets/js/dashboard/manage-family-modal.js (?partial=1 fetch). */ ?>
<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-label="Record details" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
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

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('viewer')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-home-url="<?= site_url('/') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
</body>
</html>
