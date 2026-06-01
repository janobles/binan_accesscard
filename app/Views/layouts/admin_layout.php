<?php
helper('assets');
$user = $user ?? [];
$username = $user['username'] ?? 'Admin';
$pageTitle = $pageTitle ?? 'Dashboard';
$modeLabel = $modeLabel ?? 'Admin Console';
$navActive = $navActive ?? [];
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
$currentRole = (string) ($user['role'] ?? session()->get('role'));
$canManageAccounts = in_array($currentRole, ['Developer', 'Admin'], true);
$sectorLookupActive = (string) ($navActive['sectors'] ?? '');
$servicesLookupActive = (string) ($navActive['services'] ?? '');
$currentUrl = (string) current_url();

if ($sectorLookupActive === '' && (str_contains($currentUrl, '/admin/sectors') || str_contains($currentUrl, '/admin/lookups/sectors'))) {
    $sectorLookupActive = 'active';
}

if ($servicesLookupActive === '' && (str_contains($currentUrl, '/admin/services') || str_contains($currentUrl, '/admin/lookups/services'))) {
    $servicesLookupActive = 'active';
}
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
    <?= $this->renderSection('styles') ?>
</head>
<body data-session-timeout-ms="60000" data-session-timeout-redirect="<?= site_url('logout') ?>">
<div class="app-shell">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small><?= esc($modeLabel) ?></small>
                </div>
            </div>
            <nav class="nav flex-column mt-3">
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a>
                <?php if ($canManageAccounts): ?>
                    <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Account Management</span></a>
                <?php endif; ?>
                <a class="nav-link <?= esc($navActive['family-entry'] ?? '') ?>" href="<?= site_url('admin/manage-family') ?>"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Add Family</span></a>
                <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-family/list') ?>"><i class="bi bi-people" aria-hidden="true"></i><span>Manage Family</span></a>
                <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Audit Trails</span></a>
                <div class="mt-3 small text-uppercase text-muted">Administration</div>
                <?php if (in_array($currentRole, ['Admin', 'Developer'], true)): ?>
                    <div class="mt-3 small text-uppercase text-muted">Reference Data</div>
                    <a class="nav-link <?= esc($sectorLookupActive) ?>" href="<?= site_url('admin/sectors') ?>">
                        <i class="bi bi-grid-3x3-gap me-2" aria-hidden="true"></i>
                        Sector Management
                    </a>
                    <a class="nav-link <?= esc($servicesLookupActive) ?>" href="<?= site_url('admin/services') ?>">
                        <i class="bi bi-briefcase me-2" aria-hidden="true"></i>
                        Services and Programs Management
                    </a>
                <?php endif; ?>
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

            <?= $this->renderSection('content') ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
