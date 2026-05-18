<?php
$username = $user['username'] ?? 'Employee';
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? ($activePage === 'dashboard' ? 'Workspace' : ucwords(str_replace('-', ' ', $activePage)));
$navActive = $navActive ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/mis.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/mis.css') ?>">
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
            <nav class="nav flex-column mt-3">
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>">Workspace</a>
                <a class="nav-link <?= esc($navActive['family-entry'] ?? '') ?>" href="<?= site_url('employee/manage-family') ?>">Manage Family</a>
                <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>">My Activity</a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user"><?= esc($username) ?> &middot; Employee</div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="topbar">
            <div>
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

            <?php if ($activePage === 'dashboard'): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Recently Added Families</span>
                        <a class="btn btn-primary btn-sm" href="<?= site_url('employee/manage-family') ?>">Manage Family</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Head</th><th>Barangay</th><th>Sector</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentFamilies as $family): ?>
                                    <tr>
                                        <td><?= esc($family['firstname'] . ' ' . $family['lastname']) ?></td>
                                        <td><?= esc($family['barangay']) ?></td>
                                        <td><?= esc($family['sector_name'] ?? '') ?></td>
                                        <td><?= esc($family['dt_created'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentFamilies === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No family records yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Family / Member Data Entry</span>
                    </div>
                    <?= view('Dashboard/form', array_merge(
                        $familyFormViewData ?? [],
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'activity'): ?>
                <div class="panel">
                    <div class="section-title mt-0"><span>My Recent Activity</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Action</th><th>Description</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($myAudits as $audit): ?>
                                    <tr>
                                        <td><?= esc($audit['user_action']) ?></td>
                                        <td><?= esc($audit['description']) ?></td>
                                        <td><?= esc($audit['dt_created'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($myAudits === []): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No activity yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/mis.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/mis.js') ?>"></script>
</body>
</html>
