<?php
/**
 * Scanner dashboard shell — mirrors Admin/layout.php's SB-Admin frame exactly
 * (#wrapper sidebar + #content-wrapper topbar + main.dashboard-content), but
 * with a scanner-only sidebar. Scanner-role users can never reach admin tabs;
 * this role isolation is a security boundary, not just a UI preference.
 */
$activeTab           = $activeTab ?? 'scan';
$username            = $username ?? 'Scanner';
$pageTitle           = $pageTitle ?? 'Scan';
$idleTimeoutSeconds  = $idleTimeoutSeconds ?? 900;
$currentRole         = $currentRole ?? (string) (session()->get('role') ?? '');
$isScannerRole       = $currentRole === 'Scanner';
$canManageAccounts   = $canManageAccounts ?? false;
$sidebarRoleClass    = $sidebarRoleClass ?? ($canManageAccounts ? 'developer' : ($isScannerRole ? 'scanner' : 'admin'));
$sidebarUserUrl      = $sidebarUserUrl ?? site_url('admin/dashboard');
$navActive           = $navActive ?? ['scanner' => 'active'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin'), asset_styles('scanner')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<div id="wrapper">
    <?= view('components/dashboard_sidebar', [
        'sidebarScannerOnly' => $isScannerRole,
        'activeTab' => $activeTab,
        'navActive' => $navActive,
        'canManageAccounts' => $canManageAccounts,
        'sidebarRoleClass' => $sidebarRoleClass,
        'sidebarUserUrl' => $sidebarUserUrl,
    ]) ?>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3" type="button" aria-label="Toggle navigation menu" aria-controls="dashboard-sidebar" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="topbar-title">
                    <div>
                        <h1 id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
                    </div>
                </div>
                <ul class="navbar-nav ms-auto">
                    <?= view('Partials/topbar-account-menu', ['user' => $user, 'username' => $username, 'accountLevelLabel' => $accountLevelLabel]) ?>
                </ul>
            </nav>

            <main class="container-fluid dashboard-content">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
</div>

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('admin'), asset_scripts('scanner')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(base_url('vendor/html5-qrcode/html5-qrcode.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
