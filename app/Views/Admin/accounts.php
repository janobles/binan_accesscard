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

<?php
/*
 * Jade-style reskin (account-* classes from public/css/accountmanagement.css).
 * All melbranch forms, data, role-gating, JS hooks (.js-account-status-form +
 * data-confirm-message) and POST endpoints are preserved unchanged.
 */
$statusBadge = static function (bool $isActive, string $label): string {
    $class = $isActive ? 'bg-success' : 'bg-danger';

    return '<span class="badge ' . $class . '">' . esc($label) . '</span>';
};
?>
<section class="account-management-panel" aria-labelledby="account-management-title">
    <div class="account-management-inner">
        <h2 id="account-management-title" class="account-management-title">Account Management</h2>
        <div class="account-management-divider" aria-hidden="true"></div>

        <form class="account-filter-bar" method="get" action="<?= site_url('admin/accounts') ?>" aria-label="Account filters">
            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" aria-label="Search accounts" placeholder="Search accounts by username, role, or status">
            <select class="form-select" name="role" aria-label="Filter by role">
                <option value="">All roles</option>
                <option value="Admin" <?= (string) ($searchFilters['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                <option value="User" <?= (string) ($searchFilters['role'] ?? '') === 'User' ? 'selected' : '' ?>>Employee</option>
            </select>
            <select class="form-select" name="status" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="Enable" <?= (string) ($searchFilters['status'] ?? '') === 'Enable' ? 'selected' : '' ?>>Enable</option>
                <option value="Disabled" <?= (string) ($searchFilters['status'] ?? '') === 'Disabled' ? 'selected' : '' ?>>Disabled</option>
            </select>
            <div class="d-flex gap-2">
                <button class="btn btn-success px-4" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
                <?php if ($hasSearchFilters): ?>
                    <a class="btn btn-outline-secondary px-3" href="<?= site_url('admin/accounts') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($canCreateAccounts): ?>
            <div class="account-grid">
                <section class="account-card" aria-labelledby="create-admin-account-title">
                    <h3 id="create-admin-account-title" class="account-card-title">Create Admin Account</h3>
                    <form class="account-form account-create-grid" method="post" action="<?= site_url('developer/accounts') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="role" value="Admin">
                        <div>
                            <label class="form-label" for="admin-account-username">Username</label>
                            <input class="form-control" id="admin-account-username" name="username" required minlength="4">
                        </div>
                        <div>
                            <label class="form-label" for="admin-account-password">Password</label>
                            <input type="password" class="form-control" id="admin-account-password" name="password" required minlength="8">
                        </div>
                        <button class="btn btn-success px-4" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Create</span></button>
                    </form>
                </section>
                <section class="account-card" aria-labelledby="create-employee-account-title">
                    <h3 id="create-employee-account-title" class="account-card-title">Create Employee Account</h3>
                    <?php /* Creates an Employee account; value="User" is the DB enum value for that role. */ ?>
                    <form class="account-form account-form-employee account-create-grid" method="post" action="<?= site_url('developer/accounts') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="role" value="User">
                        <div>
                            <label class="form-label" for="employee-account-username">Username</label>
                            <input class="form-control" id="employee-account-username" name="username" required minlength="4">
                        </div>
                        <div>
                            <label class="form-label" for="employee-account-password">Password</label>
                            <input type="password" class="form-control" id="employee-account-password" name="password" required minlength="8">
                        </div>
                        <button class="btn btn-success px-4" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Create</span></button>
                    </form>
                </section>
            </div>
        <?php endif; ?>

        <div class="account-grid">
            <?php if ($isDeveloper): ?>
                <section class="account-card account-table-card" aria-labelledby="admin-accounts-title">
                    <h3 id="admin-accounts-title" class="account-card-title">Admin Accounts</h3>
                    <div class="account-management-divider" aria-hidden="true"></div>
                    <div class="table-responsive">
                        <table class="table account-table align-middle">
                            <thead><tr><th scope="col">Username</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Time</th><?= $showAdminActions ? '<th scope="col">Action</th>' : '' ?></tr></thead>
                            <tbody>
                                <?php foreach ($adminAccounts as $account): ?>
                                    <?php $isActive = $isActiveStatus($account['isactive'] ?? null); ?>
                                    <?php $nextStatus = $isActive ? 'Disabled' : 'Enable'; ?>
                                    <tr>
                                        <td><strong><?= esc((string) ($account['username'] ?? '')) ?></strong></td>
                                        <td><?= $statusBadge($isActive, $formatStatus($account['isactive'] ?? '')) ?></td>
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
                                    <tr><td colspan="<?= $adminColspan ?>" class="account-empty-state"><?= $hasSearchFilters ? 'No matching admin accounts found.' : 'No admin accounts found.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
            <section class="account-card account-table-card" aria-labelledby="employee-accounts-title">
                <h3 id="employee-accounts-title" class="account-card-title">Employee Accounts</h3>
                <div class="account-management-divider" aria-hidden="true"></div>
                <div class="table-responsive">
                    <table class="table account-table align-middle">
                        <thead><tr><th scope="col">Username</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Time</th><?= $showEmployeeActions ? '<th scope="col">Action</th>' : '' ?></tr></thead>
                        <tbody>
                            <?php foreach ($employeeAccounts as $account): ?>
                                <?php $isActive = $isActiveStatus($account['isactive'] ?? null); ?>
                                <?php $nextStatus = $isActive ? 'Disabled' : 'Enable'; ?>
                                <tr>
                                    <td><strong><?= esc((string) ($account['username'] ?? '')) ?></strong></td>
                                    <td><?= $statusBadge($isActive, $formatStatus($account['isactive'] ?? '')) ?></td>
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
                                                    <!-- Admin-only: posts to AccountController::enableEmployee (admin/accounts/enable). -->
                                                    <form class="js-account-status-form" method="post" action="<?= site_url('admin/accounts/enable') ?>" data-confirm-message="<?= esc('Enable employee account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="userID" value="<?= esc((string) ($account['userID'] ?? '')) ?>">
                                                        <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-person-check" aria-hidden="true"></i>Enable</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($employeeAccounts === []): ?>
                                <tr><td colspan="<?= $employeeColspan ?>" class="account-empty-state"><?= $hasSearchFilters ? 'No matching employee accounts found.' : 'No employee accounts found.' ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>
