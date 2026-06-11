<?php
/**
 * Admin / Developer dashboard shell (the ONLY live admin layout).
 *
 * Rendered by App\Libraries\DashboardPageBuilder::renderAdminPage(), which
 * passes every variable used below (see buildAdminViewData()). The page is a
 * single layout that swaps its main section based on $activePage; each section
 * either renders inline (the "dashboard" overview) or delegates to a sub-view
 * under Views/Dashboard/. Controller entry points live in App\Controllers\Admin\DashboardController.
 *
 * The formatDate/formatTime/formatAuditMember/formatAuditUser helpers are
 * provided by the builder (do not redefine them here).
 */
// Defensive defaults so the layout still renders if a value is ever missing.
$user = $user ?? [];
$username = $user['username'] ?? 'Admin';
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';
$modeLabel = $modeLabel ?? 'Admin Console';
$canManageAccounts = $canManageAccounts ?? false;
$currentRole = $currentRole ?? '';
$navActive = $navActive ?? [];
$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$recentAudits = $recentAudits ?? [];
$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$familyFormViewData = $familyFormViewData ?? [];
$recordListData = $recordListData ?? [];
$sectorShortcodeOptions = $sectorShortcodeOptions ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$sectorOptions = $familyFormViewData['sectorOptions'] ?? [];
$hasSearchFilters = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];
$canCreateFamily = $canCreateFamily ?? false;
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
// Developers get the "developer" sidebar accent; plain admins get "admin".
$sidebarRoleClass = $canManageAccounts ? 'developer' : 'admin';
$sidebarUserUrl = $canManageAccounts ? site_url('admin/accounts') : site_url('admin/dashboard');
$showTopbarBranding = $activePage !== 'audit-trails';
?>
<?php
/*
 * SB Admin-style shell: the layout keeps the existing data, routes, modal
 * target, and page switch while using a Bootstrap 5-safe responsive frame.
 */
$cssVersion = static function (string $relativeCssPath): string {
    $absolute = FCPATH . ltrim($relativeCssPath, '/');
    $version  = is_file($absolute) ? (string) filemtime($absolute) : (string) time();

    return base_url($relativeCssPath) . '?v=' . $version;
};
$jadeStyles = [
    'css/sb-admin-adapter.css',
    'css/managerecord.css',
    'css/searchbar.css',
    'css/sector.css',
    'css/service.css',
    'css/audittrails.css',
    'css/accountmanagement.css',
    'css/familymodal.css',
    'css/session-timeout.css',
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
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('admin/dashboard') ?>">
                <img class="sidebar-brand-icon" src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS<small><?= esc($modeLabel) ?></small></span>
            </a>
        </li>
        <li><hr class="sidebar-divider my-0"></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Records</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-records') ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Manage Records</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Reference Data</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('admin/sectors') ?>"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Sector Management</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('admin/services') ?>"><i class="bi bi-grid" aria-hidden="true"></i><span>Services and Programs</span></a>
        </li>
        <li><hr class="sidebar-divider"></li>
        <li><div class="sidebar-heading">Administration</div></li>
        <?php if ($canManageAccounts): ?>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Account Management</span></a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Audit Trails</span></a>
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
                    <?php if ($showTopbarBranding): ?>
                    <img class="topbar-logo" src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                    <?php endif; ?>
                    <div>
                        <h1 id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
                        <?php if ($showTopbarBranding): ?>
                        <p>Bi&ntilde;an Access Card MIS</p>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="<?= esc($sidebarUserUrl, 'attr') ?>" class="nav-link topbar-user"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?></span></a>
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

            <?php /* Main content swaps on $activePage. "dashboard" is inline (stats +
                     recent records/activity); the rest delegate to sub-views below. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <section class="overview-stats" aria-label="Dashboard statistics">
                    <article class="stat-card"><p>Total Records</p><strong><?= esc((string) ($stats['families'] ?? 0)) ?></strong></article>
                    <article class="stat-card"><p>Registered Members</p><strong><?= esc((string) ($stats['members'] ?? 0)) ?></strong></article>
                    <article class="stat-card"><p>Active Sectors</p><strong><?= esc((string) ($stats['sectors'] ?? 0)) ?></strong></article>
                    <article class="stat-card"><p>Services and Programs</p><strong><?= esc((string) ($stats['assistance'] ?? 0)) ?></strong></article>
                </section>

                <section class="overview-panel" data-dashboard-search-panel>
                    <header class="panel-header">
                        <h2>Recent Records</h2>
                    </header>
                    <?= view('components/search-bar', [
                        'searchTerm'       => $searchTerm,
                        'sectorOptions'    => $sectorOptions,
                        'selectedSectorId' => (string) ($searchFilters['sectorID'] ?? ''),
                        'searchAction'     => site_url('admin/dashboard'),
                        'searchAllAction'  => site_url('admin/manage-records'),
                    ]) ?>
                    <div class="table-responsive">
                        <table class="table overview-table">
                            <thead><tr><th scope="col">Name (Head)</th><th scope="col">Sector</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentFamilies as $family): ?>
                                    <tr data-record-row data-sector-ids="<?= esc((string) ($family['sectorID'] ?? '[]'), 'attr') ?>">
                                        <td data-record-name><?= esc(trim(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></td>
                                        <td data-record-sector><?= esc((string) ($family['sector_name'] ?? '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentFamilies === []): ?>
                                    <tr><td colspan="2" class="empty-state"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching records found.' : 'No records yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="overview-panel">
                    <header class="panel-header">
                        <h2>Recent Activity</h2>
                        <a class="btn btn-sm panel-action" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-arrow-right" aria-hidden="true"></i><span>View All</span></a>
                    </header>
                    <div class="table-responsive">
                        <table class="table overview-table">
                            <thead><tr><th scope="col">User</th><th scope="col">Member</th><th scope="col">Action</th><th scope="col">Description</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentAudits as $audit): ?>
                                    <tr>
                                        <td><?= esc($formatAuditUser($audit)) ?></td>
                                        <td><?= esc($formatAuditMember($audit)) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentAudits === []): ?>
                                    <tr><td colspan="4" class="empty-state">No activity yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activePage === 'accounts' && $canManageAccounts): ?>
                <?= view('Admin/accounts', [
                    'adminAccounts' => $adminAccounts,
                    'employeeAccounts' => $employeeAccounts,
                    'searchTerm' => $searchTerm,
                    'searchFilters' => $searchFilters,
                    'canCreateAccounts' => $canCreateAccounts,
                    'currentRole' => $currentRole,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel mb-3">
                    <div class="section-title mt-0"><span>Add Record</span></div>
                    <?= view('Family/entry', array_merge(
                        $familyFormViewData,
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-manage'): ?>
                <?= view('Family/list', $recordListData) ?>
            <?php endif; ?>

            <?php if ($activePage === 'audit-trails'): ?>
                <?= view('Admin/audit-trails', [
                    'recentAudits' => $recentAudits,
                    'searchTerm' => $searchTerm,
                    'searchFilters' => $searchFilters,
                    'auditActionOptions' => $auditActionOptions,
                    'auditPage' => $auditPage,
                    'auditPerPage' => $auditPerPage,
                    'auditTotal' => $auditTotal,
                    'auditTotalPages' => $auditTotalPages,
                    'auditFromRecord' => $auditFromRecord,
                    'auditToRecord' => $auditToRecord,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'sectors'): ?>
                <?= view('Lookups/sectors', [
                    'sectors' => $sectors ?? [],
                    'sectorShortcodeOptions' => $sectorShortcodeOptions,
                    'lookupStatus' => $lookupStatus ?? 'active',
                    'canRestore' => $canRestoreLookups ?? false,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'services'): ?>
                <?= view('Lookups/services', [
                    'services' => $services ?? [],
                    'lookupStatus' => $lookupStatus ?? 'active',
                    'canRestore' => $canRestoreLookups ?? false,
                ]) ?>
            <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php /* Shared modal target. The *-modal.js loaders fetch ?partial=1 fragments
         (add/edit record, accounts, sectors, services, audit) into #familyModalBody. */ ?>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$versionedAssetUrl = static function (string $relativePath): ?string {
    $fullPath = FCPATH . ltrim($relativePath, '/');

    if (! is_file($fullPath)) {
        return null;
    }

    $url = base_url($relativePath);
    $mtime = filemtime($fullPath);

    if ($mtime !== false) {
        $url .= '?v=' . $mtime;
    }

    return $url;
};

$dashboardScripts = [
    'assets/js/dashboard/view-interactions.js',
    'assets/js/dashboard/family-form-ui.js',
    'assets/js/dashboard/family-form.js',
    'assets/js/dashboard/family-list.js',
    'assets/js/dashboard/management-forms.js',
    'assets/js/dashboard/lookup-search.js',
    'assets/js/dashboard/audit-filters.js',
    'assets/js/dashboard/dashboard-modal-loader.js',
    'assets/js/dashboard/manage-family-modal.js',
    'assets/js/dashboard/accounts-modal.js',
    'assets/js/dashboard/sectors-modal.js',
    'assets/js/dashboard/services-modal.js',
    'assets/js/dashboard/audit-trails-modal.js',
];

$sessionTimeoutScript = $versionedAssetUrl('assets/js/session-timeout.js');
?>
<?php foreach ($dashboardScripts as $scriptPath): ?>
    <?php $scriptUrl = $versionedAssetUrl($scriptPath); ?>
    <?php if ($scriptUrl !== null): ?>
<script src="<?= esc($scriptUrl, 'attr') ?>"></script>
    <?php endif; ?>
<?php endforeach; ?>
<?php if ($sessionTimeoutScript !== null): ?>
<script src="<?= esc($sessionTimeoutScript, 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?php endif; ?>
</body>
</html>

