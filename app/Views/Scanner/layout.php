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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion scanner" id="dashboard-sidebar">
        <li class="sidebar-brand-wrap">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('scanner/scan') ?>">
                <img class="sidebar-brand-icon" src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <span class="sidebar-brand-text mx-2">Bi&ntilde;an Access Card MIS</span>
            </a>
        </li>
        <li><hr class="sidebar-divider my-0"></li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'scan' ? 'active' : '' ?>" href="<?= site_url('scanner/scan') ?>"><i class="bi bi-upc-scan" aria-hidden="true"></i><span>Scan</span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'manage' ? 'active' : '' ?>" href="<?= site_url('scanner/manage') ?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Manage Distributions</span></a>
        </li>
        <li><hr class="sidebar-divider d-none d-md-block"></li>
        <li class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle" type="button" aria-label="Collapse sidebar" aria-controls="dashboard-sidebar" aria-expanded="true"></button>
        </li>
    </ul>

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
                    <li class="nav-item">
                        <a href="#" class="nav-link topbar-user"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?= esc($username) ?> &middot; Scanner</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= site_url('logout') ?>" class="nav-link js-logout-link"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span></a>
                    </li>
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

<?php foreach (asset_scripts('core') as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(base_url('vendor/html5-qrcode/html5-qrcode.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
