<?php
/**
 * Shared dashboard sidebar (SB Admin 1 sb-sidenav), role-aware.
 *
 * Admin/Developer get the FULL admin nav (all sections). Scanner-role users
 * get an isolated two-tab sidebar (Scan + Manage Distributions) — this
 * isolation is a security boundary, not just a UI preference.
 *
 * Layouts must render this inside #layoutSidenav_nav. The brand link lives in
 * the topnav partial (Partials/dashboard-topnav), not here.
 *
 * Variables (all defaulted defensively):
 * - $sidebarScannerOnly bool   true => render scanner-only variant
 * - $navActive          array  active-state map for full admin nav
 * - $canManageAccounts  bool   shows Account Management link
 * - $sidebarRoleClass   string 'developer' | 'admin' | 'scanner'
 * - $sidebarUserUrl     string accepted for backward compatibility (brand
 *                              moved to the topnav; unused here)
 * - $activeTab           string 'scan' | 'manage' (scanner-only variant)
 */
$sidebarScannerOnly = $sidebarScannerOnly ?? false;
$navActive = $navActive ?? [];
$canManageAccounts = $canManageAccounts ?? false;
$sidebarRoleClass = $sidebarRoleClass ?? ($sidebarScannerOnly ? 'scanner' : 'admin');
$sidebarUserUrl = $sidebarUserUrl ?? site_url('admin/dashboard');
$activeTab = $activeTab ?? '';
?>
<?php if ($sidebarScannerOnly): ?>
    <nav class="sb-sidenav accordion sb-sidenav-dark <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">QR Code</div>
                <a class="nav-link <?= $activeTab === 'scan' ? 'active' : '' ?>" href="<?= site_url('scanner/scan') ?>"><div class="sb-nav-link-icon"><i class="bi bi-upc-scan" aria-hidden="true"></i></div>Scan</a>
                <a class="nav-link <?= $activeTab === 'manage' ? 'active' : '' ?>" href="<?= site_url('scanner/manage') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></div>Management</a>
                <a class="nav-link <?= $activeTab === 'reports' ? 'active' : '' ?>" href="<?= site_url('scanner/reports') ?>"><div class="sb-nav-link-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></div>Reports</a>
            </div>
        </div>
    </nav>
<?php else: ?>
    <nav class="sb-sidenav accordion sb-sidenav-dark <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><div class="sb-nav-link-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>Dashboard</a>
                <div class="sb-sidenav-menu-heading">Records</div>
                <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people" aria-hidden="true"></i></div>Manage Records</a>
                <div class="sb-sidenav-menu-heading">Reference Data</div>
                <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('admin/sectors') ?>"><div class="sb-nav-link-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>Sector Management</a>
                <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('admin/services') ?>"><div class="sb-nav-link-icon"><i class="bi bi-grid" aria-hidden="true"></i></div>Services and Programs</a>
                <a class="nav-link <?= esc($navActive['categories'] ?? '') ?>" href="<?= site_url('admin/categories') ?>"><div class="sb-nav-link-icon"><i class="bi bi-tags" aria-hidden="true"></i></div>Manage Categories</a>
                <div class="sb-sidenav-menu-heading">QR Code</div>
                <a class="nav-link <?= esc($navActive['cards'] ?? '') ?>" href="<?= site_url('admin/cards') ?>"><div class="sb-nav-link-icon"><i class="bi bi-qr-code" aria-hidden="true"></i></div>Generate</a>
                <a class="nav-link <?= esc($navActive['scanner'] ?? '') ?>" href="<?= site_url('scanner/scan') ?>"><div class="sb-nav-link-icon"><i class="bi bi-upc-scan" aria-hidden="true"></i></div>Scan</a>
                <a class="nav-link <?= esc($navActive['scanner-manage'] ?? '') ?>" href="<?= site_url('scanner/manage') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></div>Management</a>
                <a class="nav-link <?= esc($navActive['scanner-reports'] ?? '') ?>" href="<?= site_url('scanner/reports') ?>"><div class="sb-nav-link-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></div>Reports</a>
                <div class="sb-sidenav-menu-heading">Administration</div>
                <?php if ($canManageAccounts): ?>
                <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><div class="sb-nav-link-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></div>Account Management</a>
                <?php endif; ?>
                <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>Audit Trails</a>
            </div>
        </div>
    </nav>
<?php endif; ?>
