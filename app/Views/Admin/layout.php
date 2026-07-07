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
$canCreateFamily = $canCreateFamily ?? false;
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
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
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
            'sidebarScannerOnly' => false,
        ]) ?>
    </div>
    <div id="layoutSidenav_content">
            <main class="container-fluid px-4 dashboard-content">
            <h1 class="mt-4" id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
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
                            <i class="bi bi-clipboard" aria-hidden="true"></i><span>Copy</span>
                        </button>
                    </div>
                    <p class="reset-password-callout__hint">Share it with the user and ask them to change it in My Account.</p>
                </div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger" data-auto-dismiss-alert><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('family_record_saved')): ?>
                <span id="familyDraftSavedMarker" hidden></span>
            <?php endif; ?>

            <?php /* Main content swaps on $activePage. "dashboard" is inline (stats +
                     recent records/activity); the rest delegate to sub-views below. */ ?>
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
            </main>
    </div>
</div>

<?php /* Shared modal target. The *-modal.js loaders fetch ?partial=1 fragments
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

