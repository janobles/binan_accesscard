<?php
use App\Libraries\ViewFormatter;

$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$viewerAccounts = $viewerAccounts ?? [];
$canCreateAccounts = (bool) ($canCreateAccounts ?? false);
$canEditAccounts = (bool) ($canEditAccounts ?? false);
$currentRole = (string) ($currentRole ?? '');
$isDeveloper = $currentRole === 'Developer';
$isAdmin = $currentRole === 'Admin';
$currentUserId = (int) session()->get('user_id');
// Admins and developers both manage every non-developer account now.
$accounts = array_merge($adminAccounts, $employeeAccounts, $viewerAccounts);
?>

<div class="accounts-page">
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
                        $statusLabel = $isActive ? 'Active' : 'Inactive';
                        $statusClass = $isActive ? 'is-active' : 'is-disabled';
                        ?>
                        <tr>
                            <td><strong><?= esc((string) ($account['username'] ?? '')) ?></strong></td>
                            <td><?= esc($roleLabel) ?></td>
                            <td><span class="account-status-badge <?= esc($statusClass) ?>"><?= esc($statusLabel) ?></span></td>
                            <td>
                                <div class="account-actions">
                                    <?php if ($canEditAccounts && in_array($rawRole, ['administrator', 'encoder', 'viewer'], true)): ?>
                                        <button class="btn btn-sm btn-outline-primary js-open-account-edit-modal" type="button"
                                                data-modal-url="<?= site_url('accounts/edit/' . $userId) ?>"
                                                data-modal-title="Edit Account">
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canEditAccounts && $userId !== $currentUserId && in_array($rawRole, ['administrator', 'encoder', 'viewer'], true)): ?>
                                        <form class="js-account-status-form" method="post" action="<?= site_url('accounts/reset-password') ?>" data-confirm-message="<?= esc('Generate a new random password for "' . (string) ($account['username'] ?? '') . '"? The current password will stop working.', 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">
                                                <i class="bi bi-key" aria-hidden="true"></i>Reset Password
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isDeveloper && in_array($rawRole, ['administrator', 'encoder', 'viewer'], true)): ?>
                                        <form class="js-account-status-form" method="post" action="<?= site_url('developer/accounts/status') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' ' . $roleLabel . ' account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                            <input type="hidden" name="status" value="<?= esc($nextStatus) ?>">
                                            <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                                                <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i><?= $isActive ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                    <?php elseif ($isAdmin && in_array($rawRole, ['encoder', 'viewer'], true)): ?>
                                        <form class="js-account-status-form" method="post" action="<?= site_url($isActive ? 'admin/accounts/disable' : 'admin/accounts/enable') ?>" data-confirm-message="<?= esc(($isActive ? 'Disable' : 'Enable') . ' ' . $roleLabel . ' account "' . (string) ($account['username'] ?? '') . '"?', 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="userID" value="<?= esc((string) $userId) ?>">
                                            <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit">
                                                <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>" aria-hidden="true"></i><?= $isActive ? 'Disable' : 'Enable' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (! $canEditAccounts && ! ($isDeveloper && in_array($rawRole, ['administrator', 'encoder', 'viewer'], true)) && ! ($isAdmin && in_array($rawRole, ['encoder', 'viewer'], true))): ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
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
