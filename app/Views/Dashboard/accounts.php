<?php
$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
?>

<div class="panel mb-3">
    <div class="section-title mt-0"><span>Account Management</span></div>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="border rounded p-3 h-100 bg-light">
                <h6 class="mb-3">Create Admin Account</h6>
                <form class="account-form" method="post" action="<?= site_url('developer/accounts') ?>">
                    <input type="hidden" name="role" value="Admin">
                    <div>
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" placeholder="admin_maria01" required minlength="4">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required minlength="8">
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
                <form class="account-form account-form-employee" method="post" action="<?= site_url('developer/accounts') ?>">
                    <input type="hidden" name="role" value="User">
                    <div>
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" placeholder="emp_juan01" required minlength="4">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required minlength="8">
                    </div>
                    <div class="account-action">
                        <button class="btn btn-primary w-100" type="submit">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel">
                <div class="section-title mt-0"><span>Admin Accounts</span></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Username</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($adminAccounts as $account): ?>
                                <tr>
                                    <td><?= esc((string) ($account['username'] ?? '')) ?></td>
                                    <td><?= esc((string) ($account['isactive'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($adminAccounts === []): ?>
                                <tr><td colspan="2" class="text-center text-muted">No admin accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel">
                <div class="section-title mt-0"><span>Employee Accounts</span></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Username</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($employeeAccounts as $account): ?>
                                <tr>
                                    <td><?= esc((string) ($account['username'] ?? '')) ?></td>
                                    <td><?= esc((string) ($account['isactive'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($employeeAccounts === []): ?>
                                <tr><td colspan="2" class="text-center text-muted">No employee accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
