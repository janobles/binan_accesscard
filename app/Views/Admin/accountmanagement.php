<?= account_management_styles() ?>

<?php
$adminAccounts = is_array($adminAccounts ?? null) ? $adminAccounts : [];
$employeeAccounts = is_array($employeeAccounts ?? null) ? $employeeAccounts : [];
$formatDate = static fn (string $value): string => $value === '' ? '-' : date('M d, Y', strtotime($value));
$formatTime = static fn (string $value): string => $value === '' ? '-' : date('h:i A', strtotime($value));
$statusLabel = static function (mixed $value): string {
    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'yes', 'enable', 'enabled'], true) ? 'Enable' : 'Disabled';
};
?>

<section class="account-management-panel" aria-labelledby="account-management-title">
    <div class="account-management-inner">
        <h2 id="account-management-title" class="account-management-title">Account Management</h2>
        <div class="account-management-divider" aria-hidden="true"></div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" role="alert">
                <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" role="alert">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <div data-account-create-alert></div>

        <div class="account-filter-bar" aria-label="Account filters">
            <input
                class="form-control"
                type="search"
                aria-label="Search accounts"
                placeholder="Search accounts by username, role, or status"
            >

            <select class="form-select" aria-label="Filter by role">
                <option selected>Roles</option>
            </select>

            <select class="form-select" aria-label="Filter by status">
                <option selected>Status</option>
            </select>
    
            <button class="btn btn-success px-4" type="button">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>
        </div>

        <div class="account-grid">
            <section class="account-card" aria-labelledby="create-admin-account-title">
                <h3 id="create-admin-account-title" class="account-card-title">Create Admin Account</h3>

                <form
                    class="account-create-grid"
                    method="post"
                    action="<?= site_url('admin/accounts/create') ?>"
                    data-account-create-form
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="Admin">

                    <div>
                        <label class="form-label" for="admin-account-username">Username</label>
                        <input class="form-control" type="text" id="admin-account-username" name="username" autocomplete="username" required>
                    </div>

                    <div>
                        <label class="form-label" for="admin-account-password">Password</label>
                        <input class="form-control" type="password" id="admin-account-password" name="password" autocomplete="new-password" required>
                    </div>

                    <button class="btn btn-success px-4" type="submit" data-account-create-button>
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        <span>Create</span>
                    </button>
                </form>
            </section>

            <section class="account-card" aria-labelledby="create-employee-account-title">
                <h3 id="create-employee-account-title" class="account-card-title">Create Employee Account</h3>

                <form
                    class="account-create-grid"
                    method="post"
                    action="<?= site_url('admin/accounts/create') ?>"
                    data-account-create-form
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="User">

                    <div>
                        <label class="form-label" for="employee-account-username">Username</label>
                        <input class="form-control" type="text" id="employee-account-username" name="username" autocomplete="username" required>
                    </div>

                    <div>
                        <label class="form-label" for="employee-account-password">Password</label>
                        <input class="form-control" type="password" id="employee-account-password" name="password" autocomplete="new-password" required>
                    </div>

                    <button class="btn btn-success px-4" type="submit" data-account-create-button>
                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                        <span>Create</span>
                    </button>
                </form>
            </section>
        </div>

        <div class="account-grid">
            <section class="account-card account-table-card" aria-labelledby="admin-accounts-title">
                <h3 id="admin-accounts-title" class="account-card-title">Admin Accounts</h3>
                <div class="account-management-divider" aria-hidden="true"></div>

                <div class="table-responsive">
                    <table class="table account-table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Username</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($adminAccounts === []): ?>
                                <tr>
                                    <td class="account-empty-state" colspan="5">No admin accounts yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($adminAccounts as $account): ?>
                                    <?php $createdAt = (string) ($account['dt_created'] ?? ''); ?>
                                    <tr>
                                        <td><?= esc((string) ($account['username'] ?? '-')) ?></td>
                                        <td><?= esc($statusLabel($account['isactive'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($createdAt)) ?></td>
                                        <td><?= esc($formatTime($createdAt)) ?></td>
                                        <td>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Manage</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="account-card account-table-card" aria-labelledby="employee-accounts-title">
                <h3 id="employee-accounts-title" class="account-card-title">Employee Accounts</h3>
                <div class="account-management-divider" aria-hidden="true"></div>

                <div class="table-responsive">
                    <table class="table account-table align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Username</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($employeeAccounts === []): ?>
                                <tr>
                                    <td class="account-empty-state" colspan="5">No employee accounts yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employeeAccounts as $account): ?>
                                    <?php $createdAt = (string) ($account['dt_created'] ?? ''); ?>
                                    <tr>
                                        <td><?= esc((string) ($account['username'] ?? '-')) ?></td>
                                        <td><?= esc($statusLabel($account['isactive'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($createdAt)) ?></td>
                                        <td><?= esc($formatTime($createdAt)) ?></td>
                                        <td>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Manage</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>
