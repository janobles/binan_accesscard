<?php
/**
 * Shared dashboard sidebar, role-aware.
 *
 * Admin/Developer get the FULL admin nav (all sections). Scanner-role users
 * get an isolated two-tab sidebar (Scan + Manage Distributions) — this
 * isolation is a security boundary, not just a UI preference.
 *
 * Variables (all defaulted defensively):
 * - $sidebarScannerOnly bool   true => render scanner-only variant
 * - $navActive          array  active-state map for full admin nav
 * - $canManageAccounts  bool   shows Account Management link
 * - $sidebarRoleClass   string 'developer' | 'admin' | 'scanner'
 * - $sidebarUserUrl     string brand link target (full nav)
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
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('scanner/scan') ?>">
                <img class="sidebar-brand-icon" src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS</span>
            </a>
        </li>
        <li><hr class="sidebar-divider my-0"></li>
        <li><div class="sidebar-heading">QR Code</div></li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'scan' ? 'active' : '' ?>" href="<?= site_url('scanner/scan') ?>"><i class="bi bi-upc-scan" aria-hidden="true"></i><span>Scan</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'manage' ? 'active' : '' ?>" href="<?= site_url('scanner/manage') ?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Management</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'reports' ? 'active' : '' ?>" href="<?= site_url('scanner/reports') ?>"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Reports</span></a>
        </li>
        <li><hr class="sidebar-divider d-none d-md-block"></li>
        <li class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle" type="button" aria-label="Collapse sidebar" aria-controls="dashboard-sidebar" aria-expanded="true"></button>
        </li>
    </ul>
<?php else: ?>
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
        <li><div class="sidebar-heading">QR Code</div></li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['cards'] ?? '') ?>" href="<?= site_url('admin/cards') ?>"><i class="bi bi-qr-code" aria-hidden="true"></i><span>Generate</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['scanner'] ?? '') ?>" href="<?= site_url('scanner/scan') ?>"><i class="bi bi-upc-scan" aria-hidden="true"></i><span>Scan</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= esc($navActive['scanner-manage'] ?? '') ?>" href="<?= site_url('scanner/manage') ?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Management</span></a>
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
<?php endif; ?>
