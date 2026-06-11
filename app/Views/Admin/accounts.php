<?php
use App\Libraries\ViewFormatter;

$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$canCreateAccounts = (bool) ($canCreateAccounts ?? false);
$currentRole = (string) ($currentRole ?? '');
$isDeveloper = $currentRole === 'Developer';
$isAdmin = $currentRole === 'Admin';
$accounts = $isDeveloper ? array_merge($adminAccounts, $employeeAccounts) : $employeeAccounts;
?>

<div class="account-management-page">
    <?php if ($canCreateAccounts): ?>
        <section class="account-card account-create-card" aria-labelledby="create-account-title">
            <div class="account-card-header">
                <div>
                    <h2 id="create-account-title">Create Account</h2>
                </div>
            </div>

            <form method="post" action="<?= site_url('developer/accounts') ?>">
                <?= csrf_field() ?>
                <div class="account-create-grid">
                    <div class="account-field-group" aria-label="Personal information">
                        <div class="account-field">
                            <label class="form-label" for="account-last-name">Last Name</label>
                            <input class="form-control" id="account-last-name" name="last_name" type="text" value="<?= esc((string) old('last_name')) ?>" placeholder="Enter last name" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-first-name">First Name</label>
                            <input class="form-control" id="account-first-name" name="first_name" type="text" value="<?= esc((string) old('first_name')) ?>" placeholder="Enter first name" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-middle-name">Middle Name</label>
                            <input class="form-control" id="account-middle-name" name="middle_name" type="text" value="<?= esc((string) old('middle_name')) ?>" placeholder="Enter middle name" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-suffix">Suffix <span class="text-muted">(optional)</span></label>
                            <input class="form-control" id="account-suffix" name="suffix" type="text" value="<?= esc((string) old('suffix')) ?>" placeholder="e.g. Jr, Sr, III">
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-address">Address</label>
                            <input class="form-control" id="account-address" name="address" type="text" value="<?= esc((string) old('address')) ?>" placeholder="Enter address" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-contact-no">Contact No.</label>
                            <input class="form-control" id="account-contact-no" name="contact_no" type="text" value="<?= esc((string) old('contact_no')) ?>" placeholder="Enter contact number" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-birthday">Birthday</label>
                            <input class="form-control" id="account-birthday" name="birthday" type="date" value="<?= esc((string) old('birthday')) ?>" required>
                        </div>
                    </div>

                    <div class="account-field-group" aria-label="Login information">
                        <div class="account-field">
                            <label class="form-label" for="account-username">Username</label>
                            <input class="form-control" id="account-username" name="username" type="text" value="<?= esc((string) old('username')) ?>" placeholder="Enter username" required minlength="4">
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-password">Password</label>
                            <input class="form-control" id="account-password" name="password" type="password" placeholder="Enter password" required minlength="8">
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-confirm-password">Confirm Password</label>
                            <input class="form-control" id="account-confirm-password" name="password_confirm" type="password" placeholder="Confirm password" required minlength="8">
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="account-role">Account Level</label>
                            <select class="form-select" id="account-role" name="role" required>
                                <option value="">Choose account level</option>
                                <option value="administrator" <?= old('role') === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                                <option value="encoder" <?= old('role') === 'encoder' ? 'selected' : '' ?>>Encoder</option>
                                <option value="viewer" <?= old('role') === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                            </select>
                        </div>
                        <div class="account-create-actions">
                            <button class="btn btn-success" type="submit">Create</button>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="account-card" aria-labelledby="accounts-title">
        <div class="account-card-header">
            <div>
                <h2 id="accounts-title">Accounts</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table account-table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php
                        $userId = (int) ($account['userID'] ?? 0);
                        $rawRole = (string) ($account['role'] ?? '');
                        $roleLabel = \App\Libraries\RoleAccess::normalizeRole($rawRole) ?? $rawRole;
                        $isActive = ViewFormatter::isActiveStatus($account['isactive'] ?? null);
                        $nextStatus = $isActive ? 'Disabled' : 'Enable';
                        $statusLabel = $isActive ? 'Enable' : 'Disabled';
                        $statusClass = $isActive ? 'is-active' : 'is-disabled';
                        ?>
                        <tr>
                            <td><strong><?= esc((string) ($account['username'] ?? '')) ?></strong></td>
                            <td><?= esc($roleLabel) ?></td>
                            <td><span class="account-status-badge <?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span></td>
                            <td>
                                <?php if ($isDeveloper && in_array($rawRole, ['administrator', 'encoder'], true)): ?>
                                    <form class="js-account-status-form" method="post" action="<?= site_url('developer/accounts/status') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' ' . $roleLabel . ' account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                        <input type="hidden" name="status" value="<?= esc($nextStatus) ?>">
                                        <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                                            <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i><?= $isActive ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                <?php elseif ($isAdmin && $rawRole === 'encoder'): ?>
                                    <form class="js-account-status-form" method="post" action="<?= site_url($isActive ? 'admin/accounts/disable' : 'admin/accounts/enable') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' employee account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                        <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                                            <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i><?= $isActive ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($accounts === []): ?>
                        <tr>
                            <td colspan="4" class="account-empty-state">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
