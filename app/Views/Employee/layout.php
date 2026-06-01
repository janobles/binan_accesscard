<?php
$username = $username ?? ($user['username'] ?? 'Employee');
$pageTitle = $pageTitle ?? 'Workspace';
$navActive = $navActive ?? [];
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/admin.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar employee">
        <div>
            <div class="brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small class="d-block">Employee Workspace</small>
                </div>
            </div>
            <nav class="nav flex-column sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-label">Overview</div>
                    <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>">Workspace</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Records</div>
                    <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('employee/manage-records') ?>">Manage Records</a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Activity</div>
                    <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>">My Activity</a>
                </div>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user"><?= esc($username) ?> &middot; Employee</div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100 js-logout-link">Logout</a>
        </div>
    </aside>
    <main class="content">
        <div class="topbar">
            <div class="topbar-brand"><img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo"></div>
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
<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-labelledby="familyModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="familyModalLabel">Manage Record</h5></div>
            <div class="modal-body" id="familyModalBody"><div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/dashboard/family-form-ui.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form-ui.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-form.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-list.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-list.js') ?>"></script>
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/dashboard-modal-loader.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/dashboard-modal-loader.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/manage-family-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/view-interactions.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/view-interactions.js') ?>"></script>
</body>
</html>
