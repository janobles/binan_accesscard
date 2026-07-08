<?php
/**
 * SB Admin 1 top navigation bar, shared by all dashboard shells.
 *
 * Variables:
 * - $brandUrl            string brand link target
 * - $user, $username, $accountLevelLabel  passed through to Partials/topbar-account-menu
 * - $accountSettingsUrl, $accountSettingsMode  optional passthrough (see topbar-account-menu)
 */
$brandUrl = $brandUrl ?? site_url('admin/dashboard');
$accountMenuData = ['user' => $user ?? [], 'username' => $username ?? 'User', 'accountLevelLabel' => $accountLevelLabel ?? 'Account'];
if (isset($accountSettingsUrl)) {
    $accountMenuData['accountSettingsUrl'] = $accountSettingsUrl;
}
if (isset($accountSettingsMode)) {
    $accountMenuData['accountSettingsMode'] = $accountSettingsMode;
}
?>
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="<?= esc($brandUrl, 'attr') ?>">
        <img src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo" height="24" class="me-2">Bi&ntilde;an Access Card MIS
    </a>
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-controls="dashboard-sidebar">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <ul class="navbar-nav ms-auto me-3 me-lg-4">
        <?= view('Partials/topbar-account-menu', $accountMenuData) ?>
    </ul>
</nav>
