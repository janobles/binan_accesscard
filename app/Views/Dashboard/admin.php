<?php
$role = $user['role'] ?? '';
$username = $user['username'] ?? 'User';
$activePage = $activePage ?? 'dashboard';
$isDeveloper = $role === 'Developer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc(ucwords(str_replace('-', ' ', $activePage))) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/mis.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/mis.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small><?= esc($isDeveloper ? 'Developer Mode' : 'Admin Console') ?></small>
                </div>
            </div>
            <nav class="nav flex-column mt-3">
                <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= site_url('admin/dashboard') ?>">Dashboard</a>
                <?php if ($isDeveloper): ?>
                    <a class="nav-link <?= $activePage === 'accounts' ? 'active' : '' ?>" href="<?= site_url('admin/accounts') ?>">Account Management</a>
                <?php endif; ?>
                <a class="nav-link <?= $activePage === 'family-entry' ? 'active' : '' ?>" href="<?= site_url('admin/family-entry') ?>">Family Entry</a>
                <a class="nav-link <?= $activePage === 'audit-trails' ? 'active' : '' ?>" href="<?= site_url('admin/audit-trails') ?>">Audit Trails</a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user"><?= esc($username) ?> &middot; <?= esc($role) ?></div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="topbar">
            <div>
                <div class="fw-bold"><?= esc(ucwords(str_replace('-', ' ', $activePage))) ?></div>
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
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="panel"><small>Total Families</small><div class="stat-value"><?= esc($stats['families'] ?? 0) ?></div></div></div>
                    <div class="col-md-3"><div class="panel"><small>Registered Members</small><div class="stat-value"><?= esc($stats['members'] ?? 0) ?></div></div></div>
                    <div class="col-md-3"><div class="panel"><small>Active Sectors</small><div class="stat-value"><?= esc($stats['sectors'] ?? 0) ?></div></div></div>
                    <div class="col-md-3"><div class="panel"><small>Member Services</small><div class="stat-value"><?= esc($stats['assistance'] ?? 0) ?></div></div></div>
                </div>
                <div class="panel">
                    <div class="section-title mt-0"><span>Recent Families</span></div>
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

            <?php if ($activePage === 'accounts' && $isDeveloper): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Account Management</span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <h6 class="mb-3">Create Admin Account</h6>
                                <form method="post" action="<?= site_url('developer/accounts') ?>" class="account-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="role" value="Admin">
                                    <div>
                                        <label class="form-label">Username</label>
                                        <input class="form-control" name="username" placeholder="admin_maria01" required minlength="4">
                                        <div class="form-text">Must be unique. Examples: admin_maria01, admin_roberto02.</div>
                                    </div>
                                    <div>
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" required minlength="8">
                                        <div class="form-text">Minimum 8 characters.</div>
                                    </div>
                                    <div class="account-action">
                                        <button class="btn btn-primary w-100" type="submit">Create</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <h6 class="mb-3">Create Employee Account</h6>
                                <form method="post" action="<?= site_url('developer/accounts') ?>" class="account-form account-form-employee">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="role" value="User">
                                    <div>
                                        <label class="form-label">Username</label>
                                        <input class="form-control" name="username" placeholder="emp_juan01" required minlength="4">
                                        <div class="form-text">Must be unique. Examples: emp_juan01, emp_ana02.</div>
                                    </div>
                                    <div>
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" required minlength="8">
                                        <div class="form-text">Minimum 8 characters.</div>
                                    </div>
                                    <div class="account-action">
                                        <button class="btn btn-primary w-100" type="submit">Create</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-5">
                            <h6 class="mb-2">Admin Accounts</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>User</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($adminAccounts as $account): ?>
                                            <tr><td><?= esc($account['username']) ?></td><td><?= esc($account['isactive']) ?></td></tr>
                                        <?php endforeach; ?>
                                        <?php if ($adminAccounts === []): ?>
                                            <tr><td colspan="2" class="text-center text-muted">No admin accounts yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <h6 class="mb-2">Employee Accounts</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>User</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($employeeAccounts as $account): ?>
                                            <tr>
                                                <td><?= esc($account['username']) ?></td>
                                                <td><?= esc($account['isactive']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ($employeeAccounts === []): ?>
                                            <tr><td colspan="2" class="text-center text-muted">No employee accounts yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Family / Member Data Entry</span>
                    </div>
                    <?= view('Shared/family_form', [
                        'formOptions' => $formOptions,
                        'canCreateFamily' => $canCreateFamily,
                    ]) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'audit-trails'): ?>
                <div class="panel">
                    <div class="section-title mt-0"><span>Audit Trails</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>User</th><th>Action</th><th>Description</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentAudits as $audit): ?>
                                    <tr>
                                        <td><?= esc($audit['username'] ?? '') ?></td>
                                        <td><?= esc($audit['user_action']) ?></td>
                                        <td><?= esc($audit['description']) ?></td>
                                        <td><?= esc($audit['dt_created'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentAudits === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No audit logs yet.</td></tr>
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
<script src="<?= base_url('assets/js/mis.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/mis.js') ?>"></script>
</body>
</html>
