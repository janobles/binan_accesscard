<?php
/**
 * Shared dashboard sidebar (SB Admin 1 sb-sidenav), role-aware.
 *
 * Admin/Developer get the FULL admin nav (all sections). Kiosk pages render
 * via Scanner/kiosk-layout (no sidebar) and never use this component.
 *
 * Layouts must render this inside #layoutSidenav_nav. The brand link lives in
 * the topnav partial (Partials/dashboard-topnav), not here.
 *
 * Variables (all defaulted defensively):
 * - $navActive          array  active-state map for full admin nav
 * - $canManageAccounts  bool   shows Account Management link
 * - $sidebarRoleClass   string 'developer' | 'admin' | 'scanner'
 * - $sidebarUserUrl     string accepted for backward compatibility (brand
 *                              moved to the topnav; unused here)
 */
$navActive = $navActive ?? [];
$canManageAccounts = $canManageAccounts ?? false;
$sidebarRoleClass = $sidebarRoleClass ?? 'admin';
$sidebarUserUrl = $sidebarUserUrl ?? site_url('admin/dashboard');
?>
    <nav class="sb-sidenav accordion sb-sidenav-dark <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><div class="sb-nav-link-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>Dashboard</a>
                <div class="sb-sidenav-menu-heading">Records</div>
                <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>Manage Records</a>
                <div class="sb-sidenav-menu-heading">Reference Data</div>
                <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('admin/sectors') ?>"><div class="sb-nav-link-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></div>Sector Management</a>
                <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('admin/services') ?>"><div class="sb-nav-link-icon"><i class="bi bi-grid-fill" aria-hidden="true"></i></div>Services and Programs</a>
                <a class="nav-link <?= esc($navActive['categories'] ?? '') ?>" href="<?= site_url('admin/categories') ?>"><div class="sb-nav-link-icon"><i class="bi bi-tags-fill" aria-hidden="true"></i></div>Manage Categories</a>
                <div class="sb-sidenav-menu-heading">QR Code</div>
                <a class="nav-link <?= esc($navActive['cards'] ?? '') ?>" href="<?= site_url('admin/cards') ?>"><div class="sb-nav-link-icon"><i class="bi bi-qr-code" aria-hidden="true"></i></div>Generate</a>
                <a class="nav-link <?= esc($navActive['batches'] ?? '') ?>" href="<?= site_url('admin/batches') ?>"><div class="sb-nav-link-icon"><i class="bi bi-collection" aria-hidden="true"></i></div>Batches</a>
                <a class="nav-link <?= esc($navActive['distributions'] ?? '') ?>" href="<?= site_url('admin/distributions') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></div>Distributions</a>
                <a class="nav-link <?= esc($navActive['reports'] ?? '') ?>" href="<?= site_url('admin/reports') ?>"><div class="sb-nav-link-icon"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></div>Reports</a>
                <div class="sb-sidenav-menu-heading">Administration</div>
                <?php if ($canManageAccounts): ?>
                <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><div class="sb-nav-link-icon"><i class="bi bi-person-fill-gear" aria-hidden="true"></i></div>Account Management</a>
                <?php endif; ?>
                <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>Audit Trails</a>
            </div>
        </div>
    </nav>
