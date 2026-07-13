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
<?php
/*
 * SB Admin-style shell: the layout keeps the existing data, routes, modal
 * target, and page switch while using a Bootstrap 5-safe responsive frame.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link rel="icon" type="image/png" href="<?= asset_url('assets/image/binan.png') ?>">
    <?php
    // The reports page reuses the scanner reports styles (KPI tiles, chart cards,
    // barangay chart) that live in the scanner asset group.
    $layoutStyles = array_merge(asset_styles('head'), asset_styles('admin'));
    if (($activePage ?? '') === 'reports') {
        $layoutStyles = array_merge($layoutStyles, asset_styles('scanner'));
    }
    ?>
    <?php foreach ($layoutStyles as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<<<<<<< HEAD
<body>
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('admin/dashboard') ?>">
                <img class="sidebar-brand-icon" src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
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
                    <li class="nav-item">
                        <a href="#" class="nav-link topbar-user js-open-my-account-modal" data-modal-url="<?= site_url('account/profile') ?>" data-modal-title="My Account"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?> &middot; <?= ($currentRole ?? '') === 'Developer' ? 'Developer' : 'Administrator' ?></span></a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= site_url('logout') ?>" class="nav-link js-logout-link"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span></a>
                    </li>
                </ul>
            </nav>

            <main class="container-fluid dashboard-content">
=======
<body class="sb-nav-fixed">
<?= view('Partials/dashboard-topnav', [
    'brandUrl' => $sidebarUserUrl,
    'user' => $user,
    'username' => $username,
    'accountLevelLabel' => $accountLevelLabel,
]) ?>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <?= view('components/dashboard_sidebar', [
            'navActive' => $navActive,
            'canManageAccounts' => $canManageAccounts,
            'sidebarRoleClass' => $sidebarRoleClass,
            'sidebarUserUrl' => $sidebarUserUrl,
        ]) ?>
    </div>
    <div id="layoutSidenav_content">
            <main class="container-fluid px-4 dashboard-content">
            <h1 class="mt-4" id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a
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
                            esc(trim(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))),
                            esc((string) ($family['sector_name'] ?? '-')),
                        ];
                    }
                    $recentAuditRows = [];
                    foreach ($recentAudits as $audit) {
                        $recentAuditRows[] = [
                            esc($formatAuditUser($audit)),
                            esc($formatAuditMember($audit)),
                            '<span class="badge bg-light text-dark border">' . esc((string) ($audit['user_action'] ?? '')) . '</span>',
                            esc((string) ($audit['description'] ?? '')),
                        ];
                    }
                    ?>
                    <?= view('components/data_table', [
                        'icon' => 'table',
                        'title' => 'Recent Records',
                        'columns' => ['Name (Head)', 'Sector'],
                        'rows' => $recentFamilyRows,
                        'emptyMessage' => 'No records yet.',
                        'tableClass' => 'table overview-table mb-0',
                        'cardClass' => 'dashboard-table-panel',
                    ]) ?>

<<<<<<< HEAD
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
=======
                    <?= view('components/data_table', [
                        'icon' => 'clock-history',
                        'title' => 'Recent Activity',
                        'headerActions' => '<a class="btn btn-sm panel-action" href="' . site_url('admin/audit-trails') . '"><i class="bi bi-arrow-right" aria-hidden="true"></i><span>View All</span></a>',
                        'columns' => ['User', 'Member', 'Action', 'Description'],
                        'rows' => $recentAuditRows,
                        'emptyMessage' => 'No activity yet.',
                        'tableClass' => 'table overview-table mb-0',
                        'cardClass' => 'dashboard-table-panel',
                    ]) ?>
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'accounts' && $canManageAccounts): ?>
                <?= view('Admin/accounts', [
                    'adminAccounts' => $adminAccounts,
                    'employeeAccounts' => $employeeAccounts,
                    'viewerAccounts' => $viewerAccounts ?? [],
                    'scannerAccounts' => $scannerAccounts ?? [],
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

            <?php if ($activePage === 'cards'): ?>
                <?= view('Cards/batch_form') ?>
            <?php endif; ?>

            <?php if ($activePage === 'distribution'): ?>
                <ul class="nav nav-tabs manage-tabs mb-0" role="tablist">
                  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-types" type="button" role="tab">Aid Types</button></li>
                  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-batches" type="button" role="tab">Batches</button></li>
                  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dist" type="button" role="tab">All Distributions</button></li>
                </ul>

                <div class="tab-content">
                  <div class="tab-pane fade show active" id="tab-types" role="tabpanel">
                    <?= view('components/card', [
                        'icon' => 'tags-fill',
                        'title' => 'Aid Types',
                        'cardClass' => 'sector-management',
                        'bodyView' => 'Admin/distribution-aidtypes-body',
                        'bodyData' => ['aidTypes' => $aidTypes],
                    ]) ?>
                  </div>

                  <div class="tab-pane fade" id="tab-batches" role="tabpanel">
                    <?= view('components/card', [
                        'icon' => 'collection',
                        'title' => 'Distribution Batches',
                        'cardClass' => 'sector-management',
                        'bodyView' => 'Admin/distribution-batches-body',
                        'bodyData' => [
                            'batches' => $batches,
                            'activeBatch' => $activeBatch,
                            'activeAidTypes' => $activeAidTypes,
                            'currentRole' => $currentRole,
                        ],
                    ]) ?>
                  </div>

                  <div class="tab-pane fade" id="tab-dist" role="tabpanel">
                    <?= view('components/card', [
                        'icon' => 'clipboard-check-fill',
                        'title' => 'All Distributions',
                        'cardClass' => 'sector-management',
                        'bodyView' => 'Admin/distribution-distributions-body',
                        'bodyData' => ['distributions' => $distributions, 'aidTypes' => $aidTypes],
                        'footer' => '<span id="distCount"></span>',
                    ]) ?>
                  </div>
                </div>

                <div class="modal fade" id="addAidTypeModal" tabindex="-1">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="<?= site_url('admin/aid-types/create') ?>">
                      <?= csrf_field() ?>
                      <div class="modal-header">
                        <h5 class="modal-title">Add Aid Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <label for="aidTypeName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="100">
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add</button>
                      </div>
                    </form>
                  </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', () => {
                  const table   = document.getElementById('distTable');
                  const search  = document.getElementById('distSearch');
                  const filter  = document.getElementById('distAidFilter');
                  const clear   = document.getElementById('distClear');
                  const perPage = document.getElementById('distPerPage');
                  const local   = document.getElementById('distLocalSearch');
                  const count   = document.getElementById('distCount');
                  if (!table) return;
                  const rows = Array.from(table.tBodies[0].rows).filter(r => !r.querySelector('.sector-empty-state'));

                  const render = () => {
                    const q     = (search.value || '').trim().toLowerCase();
                    const q2    = (local.value || '').trim().toLowerCase();
                    const aid   = filter.value || '';
                    const limit = parseInt(perPage.value, 10) || 0;
                    let matched = 0;
                    let shown = 0;
                    rows.forEach(r => {
                      const text = r.textContent.toLowerCase();
                      const ok = (q === '' || text.includes(q))
                              && (q2 === '' || text.includes(q2))
                              && (aid === '' || (r.getAttribute('data-aidtype') || '') === aid);
                      let visible = ok;
                      if (ok) {
                        matched++;
                        if (limit > 0 && matched > limit) visible = false;
                      }
                      r.hidden = !visible;
                      if (visible) shown++;
                    });
                    if (count) count.textContent = 'Showing ' + shown + ' of ' + rows.length + ' distribution' + (rows.length === 1 ? '' : 's');
                  };

                  [search, filter, local, perPage].forEach(el => el && el.addEventListener('input', render));
                  if (perPage) perPage.addEventListener('change', render);
                  if (clear) clear.addEventListener('click', () => { search.value = ''; local.value = ''; filter.value = ''; perPage.value = '25'; render(); });
                  render();
                });
                </script>
            <?php endif; ?>

            <?php if ($activePage === 'reports'): ?>
                <?= view('Admin/reports-body') ?>
            <?php endif; ?>
            </main>
    </div>
</div>

<?php /* Shared modal target. The *-modal.js loaders fetch ?partial=1 fragments
<<<<<<< HEAD
         (record details, accounts, sectors, services, audit) into #familyModalBody. */ ?>
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
=======
         (add/edit record, accounts, sectors, services, audit) into #familyModalBody. */ ?>
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
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a

<?= view('Family/action-confirm-modal') ?>

<?= view('Accounts/status-confirm-modal') ?>

<?php /* Per-row audit detail modal, populated client-side by audit-detail-modal.js
         from the clicked row's data-* attributes (no AJAX). */ ?>
<?= view('components/modal', [
    'id' => 'auditDetailModal',
    'modalClass' => 'audit-detail-modal',
    'attrs' => 'aria-labelledby="auditDetailTitle"',
    'size' => 'modal-lg',
    'title' => 'Audit Entry Details',
    'titleId' => 'auditDetailTitle',
    'bodyHtml' => '<p class="audit-detail-full" id="auditDetailFull">&mdash;</p>',
    'footerHtml' => '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>',
]) ?>

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('admin')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
</body>
</html>

