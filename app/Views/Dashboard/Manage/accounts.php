<?php
use App\Libraries\ViewFormatter;

$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$canCreateAccounts = $canCreateAccounts ?? false;
$currentRole = $currentRole ?? '';
$isDeveloper = $currentRole === 'Developer';
$isAdmin = $currentRole === 'Admin';
$showAdminActions = $isDeveloper;
$showEmployeeActions = $isDeveloper || $isAdmin;
$adminColspan = $showAdminActions ? 5 : 4;
$employeeColspan = $showEmployeeActions ? 5 : 4;
$adminColumnClass = $isDeveloper ? 'col-lg-6' : 'col-lg-12';
$employeeColumnClass = $isDeveloper ? 'col-lg-6' : 'col-lg-12';
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$hasSearchFilters = ViewFormatter::hasSearchFilters($searchTerm, $searchFilters);
$formatDate = [ViewFormatter::class, 'formatDate'];
$formatTime = [ViewFormatter::class, 'formatTime'];
$isActiveStatus = [ViewFormatter::class, 'isActiveStatus'];
$formatStatus = [ViewFormatter::class, 'formatStatus'];
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span>Account Management</span>
    </div>
    <form class="row g-2 filter-bar" method="get" action="<?= site_url('admin/accounts') ?>">
        <div class="col-md-6 col-lg-4">
            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search accounts by username, role, or status">
        </div>
        <div class="col-md-3 col-lg-2">
            <select class="form-select" name="role">
                <option value="">All roles</option>
                <option value="Admin" <?= (string) ($searchFilters['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                <option value="User" <?= (string) ($searchFilters['role'] ?? '') === 'User' ? 'selected' : '' ?>>Employee</option>
            </select>
        </div>
        <div class="col-md-3 col-lg-2">
            <select class="form-select" name="status">
                <option value="">All statuses</option>
                <option value="Enable" <?= (string) ($searchFilters['status'] ?? '') === 'Enable' ? 'selected' : '' ?>>Enable</option>
                <option value="Disabled" <?= (string) ($searchFilters['status'] ?? '') === 'Disabled' ? 'selected' : '' ?>>Disabled</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Search</button>
        </div>
        <?php if ($hasSearchFilters): ?>
            <div class="col-auto">
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/accounts') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i>Clear</a>
            </div>
        <?php endif; ?>
    </form>

    <?php if ($canCreateAccounts): ?>
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="section-surface">
                    <h6 class="mb-3">Create Admin Account</h6>
                    <form class="account-form" method="post" action="<?= site_url('developer/accounts') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="role" value="Admin">
                        <div>
                            <label class="form-label">Username</label>
                            <input class="form-control" name="username" placeholder="" required minlength="4">
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                        </div>
                        <div class="account-action">
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Create</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-surface">
                    <h6 class="mb-3">Create Employee Account</h6>
                    <form class="account-form account-form-employee" method="post" action="<?= site_url('developer/accounts') ?>">
                        <?= csrf_field() ?>
                        <?php /* Creates an Employee account; value="User" is the DB enum value for that role. */ ?>
                        <input type="hidden" name="role" value="User">
                        <div>
                            <label class="form-label">Username</label>
                            <input class="form-control" name="username" placeholder="" required minlength="4">
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                        </div>
                        <div class="account-action">
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i>Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <?php if ($isDeveloper): ?>
            <div class="<?= esc($adminColumnClass) ?>">
                <div class="section-surface">
                    <div class="section-title mt-0"><span>Admin Accounts</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Username</th><th>Status</th><th>Date</th><th>Time</th><?= $showAdminActions ? '<th>Action</th>' : '' ?></tr></thead>
                            <tbody>
                                <?php foreach ($adminAccounts as $account): ?>
                                    <?php $isActive = $isActiveStatus($account['isactive'] ?? null); ?>
                                    <?php $nextStatus = $isActive ? 'Disabled' : 'Enable'; ?>
                                    <tr>
                                        <td><span class="entity-title"><?= esc((string) ($account['username'] ?? '')) ?></span></td>
                                        <td><span class="status-pill <?= $isActive ? 'is-active' : 'is-danger' ?>"><?= esc($formatStatus($account['isactive'] ?? '')) ?></span></td>
                                        <td><?= esc($formatDate($account['dt_created'] ?? '')) ?></td>
                                        <td><?= esc($formatTime($account['dt_created'] ?? '')) ?></td>
                                        <?php if ($showAdminActions): ?>
                                            <td>
                                                <!-- Developer-only: posts to AccountController::updateStatus (developer/accounts/status). -->
                                                <form class="js-account-status-form" method="post" action="<?= site_url('developer/accounts/status') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' admin account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="userID" value="<?= esc((string) ($account['userID'] ?? '')) ?>">
                                                    <input type="hidden" name="status" value="<?= esc($nextStatus) ?>">
                                                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                                                        <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i><?= $isActive ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($adminAccounts === []): ?>
                                    <tr><td colspan="<?= $adminColspan ?>" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching admin accounts found.' : 'No admin accounts found.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="<?= esc($employeeColumnClass) ?>">
            <div class="section-surface">
                <div class="section-title mt-0">
                    <span>Employee Accounts</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Username</th><th>Status</th><th>Date</th><th>Time</th><?= $showEmployeeActions ? '<th>Action</th>' : '' ?></tr></thead>
                        <tbody>
                            <?php foreach ($employeeAccounts as $account): ?>
                                <?php $isActive = $isActiveStatus($account['isactive'] ?? null); ?>
                                <?php $nextStatus = $isActive ? 'Disabled' : 'Enable'; ?>
                                <tr>
                                    <td><span class="entity-title"><?= esc((string) ($account['username'] ?? '')) ?></span></td>
                                    <td><span class="status-pill <?= $isActive ? 'is-active' : 'is-danger' ?>"><?= esc($formatStatus($account['isactive'] ?? '')) ?></span></td>
                                    <td><?= esc($formatDate($account['dt_created'] ?? '')) ?></td>
                                    <td><?= esc($formatTime($account['dt_created'] ?? '')) ?></td>
                                    <?php if ($showEmployeeActions): ?>
                                        <td>
                                            <?php if ($isDeveloper): ?>
                                                <!-- Developer-only: posts to AccountController::updateStatus (developer/accounts/status). -->
                                                <form class="js-account-status-form" method="post" action="<?= site_url('developer/accounts/status') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' employee account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="userID" value="<?= esc((string) ($account['userID'] ?? '')) ?>">
                                                    <input type="hidden" name="status" value="<?= esc($nextStatus) ?>">
                                                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                                                        <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i><?= $isActive ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>
                                            <?php elseif ($isAdmin): ?>
                                                <?php if ($isActive): ?>
                                                    <!-- Admin-only: posts to AccountController::disableEmployee (admin/accounts/disable). -->
                                                    <form class="js-account-status-form" method="post" action="<?= site_url('admin/accounts/disable') ?>" data-confirm-message="<?= esc('Disable employee account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="userID" value="<?= esc((string) ($account['userID'] ?? '')) ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-person-x" aria-hidden="true"></i>Disable</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="status-pill is-danger">Disabled</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($employeeAccounts === []): ?>
                                <tr><td colspan="<?= $employeeColspan ?>" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching employee accounts found.' : 'No employee accounts found.' ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
