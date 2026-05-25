<?php
$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$linkableMembers = $linkableMembers ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$hasSearchFilters = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
};
$formatTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '' : date('h:i A', $timestamp);
};
$formatMemberName = static function (array $member): string {
    $memberName = trim((string) ($member['member_name'] ?? ''));

    if ($memberName !== '') {
        return $memberName;
    }

    $memberName = trim(implode(' ', array_filter([
        (string) ($member['firstname'] ?? ''),
        (string) ($member['middlename'] ?? ''),
        (string) ($member['lastname'] ?? ''),
        (string) ($member['suffix'] ?? ''),
    ], static fn (string $value): bool => trim($value) !== '')));

    return $memberName === '' ? '-' : $memberName;
};
?>

<div class="panel mb-3">
    <div class="section-title mt-0"><span>Account Management</span></div>
    <form class="row g-2 mb-3" method="get" action="<?= site_url('admin/accounts') ?>">
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
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
        <?php if ($hasSearchFilters): ?>
            <div class="col-auto">
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/accounts') ?>">Clear</a>
            </div>
        <?php endif; ?>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="border rounded p-3 h-100 bg-light">
                <h6 class="mb-3">Create Admin Account</h6>
                <form class="account-form" method="post" action="<?= site_url('developer/accounts') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="Admin">
                    <div>
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" placeholder="admin_maria01" required minlength="4">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required minlength="8">
                    </div>
                    <div>
                        <label class="form-label">Linked Member</label>
                        <select class="form-select" name="memberID">
                            <option value="">None</option>
                            <?php foreach ($linkableMembers as $member): ?>
                                <option value="<?= esc((string) ($member['memberID'] ?? '')) ?>"><?= esc($formatMemberName($member)) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="User">
                    <div>
                        <label class="form-label">Username</label>
                        <input class="form-control" name="username" placeholder="emp_juan01" required minlength="4">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required minlength="8">
                    </div>
                    <div>
                        <label class="form-label">Linked Member</label>
                        <select class="form-select" name="memberID">
                            <option value="">None</option>
                            <?php foreach ($linkableMembers as $member): ?>
                                <option value="<?= esc((string) ($member['memberID'] ?? '')) ?>"><?= esc($formatMemberName($member)) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <thead><tr><th>Username</th><th>Linked Member</th><th>Status</th><th>Date</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($adminAccounts as $account): ?>
                                <tr>
                                    <td><?= esc((string) ($account['username'] ?? '')) ?></td>
                                    <td><?= esc($formatMemberName($account)) ?></td>
                                    <td><?= esc((string) ($account['isactive'] ?? '')) ?></td>
                                    <td><?= esc($formatDate($account['dt_created'] ?? '')) ?></td>
                                    <td><?= esc($formatTime($account['dt_created'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($adminAccounts === []): ?>
                                <tr><td colspan="5" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching admin accounts found.' : 'No admin accounts found.' ?></td></tr>
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
                        <thead><tr><th>Username</th><th>Linked Member</th><th>Status</th><th>Date</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($employeeAccounts as $account): ?>
                                <tr>
                                    <td><?= esc((string) ($account['username'] ?? '')) ?></td>
                                    <td><?= esc($formatMemberName($account)) ?></td>
                                    <td><?= esc((string) ($account['isactive'] ?? '')) ?></td>
                                    <td><?= esc($formatDate($account['dt_created'] ?? '')) ?></td>
                                    <td><?= esc($formatTime($account['dt_created'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($employeeAccounts === []): ?>
                                <tr><td colspan="5" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching employee accounts found.' : 'No employee accounts found.' ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
