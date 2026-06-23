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
helper('asset');

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
$recordListData = $recordListData ?? [];
$categories = $categories ?? [];
$sectorShortcodeOptions = $sectorShortcodeOptions ?? [];
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
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
// Developers get the "developer" sidebar accent; plain admins get "admin".
$sidebarRoleClass = $canManageAccounts ? 'developer' : 'admin';
$sidebarUserUrl = $canManageAccounts ? site_url('admin/accounts') : site_url('admin/dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?= asset_tags('dashboard-core-css') ?>
    <?= asset_tags('admin-dashboard-css') ?>
</head>
<body>
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('admin/dashboard') ?>">
                <img class="sidebar-brand-icon" src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS</span>
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
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['categories'] ?? '') ?>" href="<?= site_url('admin/categories') ?>"><i class="bi bi-tags" aria-hidden="true"></i><span>Manage Categories</span></a>
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
                </div>
                <ul class="navbar-nav ms-auto">
                    <?= view('Partials/topbar-account-menu', [
                        'user' => $user,
                        'accountLevelLabel' => (string) ($currentRole ?: 'Admin'),
                        'accountSettingsUrl' => ($currentRole ?? '') === 'Developer' ? $sidebarUserUrl : site_url('account/profile'),
                        'accountSettingsMode' => ($currentRole ?? '') === 'Developer' ? 'link' : 'modal',
                    ]) ?>
                </ul>
            </nav>

            <main class="container-fluid dashboard-content">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success" data-auto-dismiss-alert><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if ($resetInfo = session()->getFlashdata('reset_password')): ?>
                <div class="reset-password-callout" role="alert">
                    <div class="reset-password-callout__head">
                        <i class="bi bi-key-fill" aria-hidden="true"></i>
                        <span>New password for <strong><?= esc((string) ($resetInfo['username'] ?? '')) ?></strong></span>
                    </div>
                    <div class="reset-password-callout__body">
                        <code class="reset-password-callout__value" id="resetPasswordValue"><?= esc((string) ($resetInfo['password'] ?? '')) ?></code>
                        <button type="button" class="btn btn-sm btn-outline-success js-copy-password" data-copy-target="#resetPasswordValue">
                            <span>Copy</span>
                        </button>
                    </div>
                    <p class="reset-password-callout__hint">Share it with the user and ask them to change it in My Account.</p>
                </div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger" data-auto-dismiss-alert><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>
            <?php /* Main content swaps on $activePage. "dashboard" is inline (stats +
                     recent records/activity); the rest delegate to sub-views below. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <div class="dashboard-overview" data-dashboard-overview>
                    <section class="overview-stats" aria-label="Dashboard statistics">
                        <article class="stat-card"><p>Total Records</p><strong><?= esc((string) ($stats['families'] ?? 0)) ?></strong></article>
                        <article class="stat-card"><p>Registered Members</p><strong><?= esc((string) ($stats['members'] ?? 0)) ?></strong></article>
                        <article class="stat-card"><p>Active Sectors</p><strong><?= esc((string) ($stats['sectors'] ?? 0)) ?></strong></article>
                        <article class="stat-card"><p>Services and Programs</p><strong><?= esc((string) ($stats['assistance'] ?? 0)) ?></strong></article>
                    </section>

                    <section class="overview-panel dashboard-table-panel">
                        <header class="panel-header">
                            <h2>Recent Records</h2>
                        </header>
                        <div class="table-responsive">
                            <table class="table overview-table">
                                <thead><tr><th scope="col">Name (Head)</th><th scope="col">Sector</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentFamilies as $family): ?>
                                        <tr>
                                            <td><?= esc(trim(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></td>
                                            <td><?= esc((string) ($family['sector_name'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($recentFamilies === []): ?>
                                        <tr><td colspan="2" class="empty-state">No records yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overview-panel dashboard-table-panel">
                        <header class="panel-header">
                            <h2>Recent Activity</h2>
                            <a class="btn btn-sm panel-action" href="<?= site_url('admin/audit-trails') ?>"><span>View All</span></a>
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
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'accounts' && $canManageAccounts): ?>
                <?= view('Admin/accounts', [
                    'adminAccounts' => $adminAccounts,
                    'employeeAccounts' => $employeeAccounts,
                    'viewerAccounts' => $viewerAccounts ?? [],
                    'searchTerm' => $searchTerm,
                    'searchFilters' => $searchFilters,
                    'canCreateAccounts' => $canCreateAccounts,
                    'canEditAccounts' => $canEditAccounts ?? false,
                    'currentRole' => $currentRole,
                ]) ?>
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
                    'auditListData' => $auditListData ?? [],
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

            <?php if ($activePage === 'categories'): ?>
                <?= view('Lookups/categories', [
                    'categories' => $categories ?? [],
                    'lookupStatus' => $lookupStatus ?? 'active',
                    'canRestore' => $canRestoreLookups ?? false,
                ]) ?>
            <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php /* Shared modal target. The *-modal.js loaders fetch ?partial=1 fragments
         (record details, accounts, sectors, services, audit) into #familyModalBody. */ ?>
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

<?= view('Family/action-confirm-modal') ?>

<?= view('Accounts/status-confirm-modal') ?>

<?php /* Per-row audit detail modal, populated client-side by audit-detail-modal.js
         from the clicked row's data-* attributes (no AJAX). */ ?>
<div class="modal fade audit-detail-modal" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="auditDetailTitle">Audit Entry Details</h5>
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

<?= asset_tags('dashboard-vendor-js') ?>
<?= asset_tags('admin-dashboard-js') ?>
<?= asset_script_tag('assets/js/session-timeout.js', [
    'data-timeout-seconds' => (string) $idleTimeoutSeconds,
    'data-logout-url' => site_url('logout?timeout=1'),
    'data-keep-alive-url' => site_url('session/keep-alive'),
]) ?>
</body>
</html>

