<?php
/**
 * Admin / Developer dashboard shell (the ONLY live admin layout).
 *
 * Rendered by App\Libraries\DashboardPageBuilder::renderAdminPage(), which
 * passes every variable used below (see buildAdminViewData()). The page is a
 * single layout that swaps its main section based on $activePage; each section
 * either renders inline (the "dashboard" overview) or delegates to a sub-view
 * under Views/Dashboard/. Controller entry points live in App\Controllers\Home.
 *
 * The formatDate/formatTime/formatAuditMember/formatAuditUser helpers are
 * provided by the builder (do not redefine them here).
 */
helper('assets');

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
$selectedFilterDate = (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?= admin_dashboard_style_links() ?>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar <?= esc($sidebarRoleClass) ?>">
        <div>
            <div class="brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small><?= esc($modeLabel) ?></small>
                </div>
            </div>
            <nav class="nav flex-column sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-label">Overview</div>
                    <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Records</div>
                    <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-records') ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Manage Records</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Reference Data</div>
                    <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('admin/sectors') ?>"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Sector Management</span></a>
                    <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('admin/services') ?>"><i class="bi bi-ui-checks-grid" aria-hidden="true"></i><span>Services and Programs</span></a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Administration</div>
                    <?php if ($canManageAccounts): ?>
                        <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Account Management</span></a>
                    <?php endif; ?>
                    <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Audit Trails</span></a>
                </div>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user"><?= esc($username) ?> &middot; <?= esc($modeLabel) ?></div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100 js-logout-link">Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="topbar">
            <div class="topbar-brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
            </div>
            <div class="topbar-text">
                <div class="fw-bold"><?= esc($pageTitle) ?></div>
                <small class="text-muted">Bi&ntilde;an Access Card MIS</small>
            </div>
        </div>

        <div class="container-fluid py-4">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <?php /* Main content swaps on $activePage. "dashboard" is inline (stats +
                     recent records/activity); the rest delegate to sub-views below. */ ?>
            <?php if ($activePage === 'dashboard'): ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="panel stat-panel"><small>Total Records</small><div class="stat-value"><?= esc((string) ($stats['families'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel stat-panel"><small>Registered Members</small><div class="stat-value"><?= esc((string) ($stats['members'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel stat-panel"><small>Active Sectors</small><div class="stat-value"><?= esc((string) ($stats['sectors'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel stat-panel"><small>Services and Programs</small><div class="stat-value"><?= esc((string) ($stats['assistance'] ?? 0)) ?></div></div></div>
                </div>

                <div class="panel mb-3">
                    <div class="section-title mt-0">
                        <span>Recent Records</span>
                    </div>
                    <form class="row g-2 filter-bar" method="get" action="<?= site_url('admin/dashboard') ?>">
                        <div class="col-md-6 col-lg-4">
                            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search records by name, contact number, or sector">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <select class="form-select" name="sectorID">
                                <option value="">All sectors</option>
                                <?php foreach ($sectorOptions as $sector): ?>
                                    <?php $sectorId = (string) ($sector['sectorID'] ?? ''); ?>
                                    <option value="<?= esc($sectorId) ?>" <?= (string) ($searchFilters['sectorID'] ?? '') === $sectorId ? 'selected' : '' ?>><?= esc((string) ($sector['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <input class="form-control" type="date" name="date" value="<?= esc($selectedFilterDate) ?>" aria-label="Filter by date">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Search</button>
                        </div>
                        <?php if ($hasSearchFilters): ?>
                            <div class="col-auto">
                                <a class="btn btn-outline-secondary" href="<?= site_url('admin/dashboard') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i>Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Head</th><th>Sector</th><th>Date</th><th>Time</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentFamilies as $family): ?>
                                    <tr>
                                        <td><span class="entity-title"><?= esc(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')) ?></span></td>
                                        <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($family['dt_created'] ?? '')) ?></td>
                                        <td><?= esc($formatTime($family['dt_created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentFamilies === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching records found.' : 'No records yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel mb-3">
                    <div class="section-title mt-0">
                        <span>Recent Activity</span>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-arrow-right" aria-hidden="true"></i>View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>User</th><th>Member</th><th>Action</th><th>Description</th><th>Date</th><th>Time</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentAudits as $audit): ?>
                                    <tr>
                                        <td><?= esc($formatAuditUser($audit)) ?></td>
                                        <td><?= esc($formatAuditMember($audit)) ?></td>
                                        <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($audit['dt_created'] ?? '')) ?></td>
                                        <td><?= esc($formatTime($audit['dt_created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentAudits === []): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No activity yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'accounts' && $canManageAccounts): ?>
                <?= view('Dashboard/Manage/accounts', [
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
                    <?= view('Dashboard/familyform', array_merge(
                        $familyFormViewData,
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-manage'): ?>
                <?= view('Dashboard/familyform/family-list', $recordListData) ?>
            <?php endif; ?>

            <?php if ($activePage === 'audit-trails'): ?>
                <?= view('Dashboard/Manage/audit-trails', [
                    'recentAudits' => $recentAudits,
                    'searchTerm' => $searchTerm,
                    'searchFilters' => $searchFilters,
                    'auditActionOptions' => $auditActionOptions,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'sectors'): ?>
                <?= view('Dashboard/Sectors and Services/sector', [
                    'sectors' => $sectors ?? [],
                    'sectorShortcodeOptions' => $sectorShortcodeOptions,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'services'): ?>
                <?= view('Dashboard/Sectors and Services/services', [
                    'services' => $services ?? [],
                ]) ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php /* Shared modal target. The *-modal.js loaders fetch ?partial=1 fragments
         (add/edit record, accounts, sectors, services, audit) into #familyModalBody. */ ?>
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
<script src="<?= base_url('assets/js/dashboard/management-forms.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/management-forms.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/audit-filters.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/audit-filters.js') ?>"></script>
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/dashboard-modal-loader.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/dashboard-modal-loader.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/manage-family-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/accounts-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/accounts-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/sectors-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/sectors-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/services-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/services-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/audit-trails-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/audit-trails-modal.js') ?>"></script>
</body>
</html>

