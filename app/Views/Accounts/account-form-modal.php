<?php
/**
 * Shared create/edit account modal fragment.
 *
 * Rendered by AccountController::createForm/editForm and
 * ProfileController::myAccount. Stored values come from the users row; `old`
 * and `errors` come from AccountFormRetry when the previous submission was
 * rejected, and take priority so the form shows what was typed.
 */
$mode = (string) ($mode ?? 'create');
$old = is_array($old ?? null) ? $old : [];
$oldValue = static function (string $key) use ($old): ?string {
    return array_key_exists($key, $old) ? (string) $old[$key] : null;
};
$isEdit = $mode === 'edit';
$isSelfProfile = $mode === 'self';
$details = $details ?? [];
$account = $account ?? [];
$userId = (int) ($account['userID'] ?? 0);
$username = $oldValue('username') ?? (($isEdit || $isSelfProfile) ? (string) ($account['username'] ?? '') : '');
$role = $oldValue('role') ?? (($isEdit || $isSelfProfile) ? (string) ($account['role'] ?? '') : '');
$isSelf = (bool) ($isSelf ?? false);
// True when an admin edits another administrator: personal details and account
// level are locked, while username and password reset remain available.
$personalLocked = (bool) ($personalLocked ?? false);
$lockAttr = $personalLocked ? ' disabled' : '';
$roleLabel = (string) ($roleLabel ?? $role);
$isRoleReadOnly = $personalLocked || ($isEdit && $isSelf) || $isSelfProfile;
$displayRoleLabel = $roleLabel !== '' ? $roleLabel : match ($role) {
    'administrator' => 'Administrator',
    'developer' => 'Developer',
    'encoder' => 'Encoder',
    'viewer' => 'Viewer',
    'scanner' => 'Scanner',
    default => $role,
};
$fieldPrefix = $isEdit ? 'edit-account' : ($isSelfProfile ? 'my-account' : 'account');
$action = $isEdit ? site_url('accounts/update') : ($isSelfProfile ? site_url('account/profile/update') : site_url('accounts'));
$submitLabel = $isEdit ? 'Save Changes' : ($isSelfProfile ? 'Save Account' : 'Create Account');
$value = static function (array $details, string $key, bool $useStored) use ($oldValue): string {
    return $oldValue($key) ?? ($useStored ? (string) ($details[$key] ?? '') : '');
};
$errors = is_array($errors ?? null) ? $errors : [];
$hasError = static function (string $field) use ($errors): bool {
    return isset($errors[$field]);
};
$getError = static function (string $field) use ($errors): string {
    return $errors[$field] ?? '';
};
?>
<div class="edit-account-modal d-flex flex-column h-100">
    <form method="post" action="<?= esc($action, 'attr') ?>">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="userID" value="<?= esc((string) $userId, 'attr') ?>">
        <?php endif; ?>

        <section class="account-card" aria-labelledby="<?= esc($fieldPrefix, 'attr') ?>-profile-title">
            <div class="account-create-grid account-create-grid--stacked">
                <div class="account-field-group" aria-label="Profile Information">
                    <h3 class="account-field-group-title" id="<?= esc($fieldPrefix, 'attr') ?>-profile-title">Profile Information</h3>
                    <?php if ($personalLocked): ?>
                        <p class="text-muted account-field small mb-0">Personal details and account level are read-only. As an administrator you can only update this account's username and password.</p>
                    <?php endif; ?>
                    <div class="account-fields-row account-fields-row--requirements">
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-last-name">Last Name <span class="account-required-marker text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control <?= $hasError('last_name') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-last-name" name="last_name" type="text" value="<?= esc($value($details, 'last_name', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter last name" required<?= $lockAttr ?>>
                            <?php if ($hasError('last_name')): ?><div class="invalid-feedback"><?= esc($getError('last_name')) ?></div><?php endif; ?>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-first-name">First Name <span class="account-required-marker text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control <?= $hasError('first_name') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-first-name" name="first_name" type="text" value="<?= esc($value($details, 'first_name', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter first name" required<?= $lockAttr ?>>
                            <?php if ($hasError('first_name')): ?><div class="invalid-feedback"><?= esc($getError('first_name')) ?></div><?php endif; ?>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-middle-name">Middle Name <span class="text-muted">(optional)</span></label>
                            <input class="form-control <?= $hasError('middle_name') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-middle-name" name="middle_name" type="text" value="<?= esc($value($details, 'middle_name', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter middle name"<?= $lockAttr ?>>
                            <?php if ($hasError('middle_name')): ?><div class="invalid-feedback"><?= esc($getError('middle_name')) ?></div><?php endif; ?>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-suffix">Suffix <span class="text-muted">(optional)</span></label>
                            <input class="form-control <?= $hasError('suffix') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-suffix" name="suffix" type="text" value="<?= esc($value($details, 'suffix', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="e.g. Jr, Sr, III"<?= $lockAttr ?>>
                            <?php if ($hasError('suffix')): ?><div class="invalid-feedback"><?= esc($getError('suffix')) ?></div><?php endif; ?>
                        </div>
                        <div class="account-field account-field--wide">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-address">Address <span class="account-required-marker text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control <?= $hasError('address') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-address" name="address" type="text" value="<?= esc($value($details, 'address', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter address" required<?= $lockAttr ?>>
                            <?php if ($hasError('address')): ?><div class="invalid-feedback"><?= esc($getError('address')) ?></div><?php endif; ?>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-contact-no">Contact No. <span class="account-required-marker text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control <?= $hasError('contact_no') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-contact-no" name="contact_no" type="text" value="<?= esc($value($details, 'contact_no', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter contact number" required<?= $lockAttr ?>>
                            <?php if ($hasError('contact_no')): ?><div class="invalid-feedback"><?= esc($getError('contact_no')) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="account-field-group" aria-label="Login information">
                    <h3 class="account-field-group-title">Login Information</h3>
                    <div class="account-fields-row account-fields-row--requirements">
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-username">Username <span class="account-required-marker text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control <?= $hasError('username') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-username" name="username" type="text" value="<?= esc($username, 'attr') ?>" placeholder="Enter username" required minlength="4">
                            <?php if ($hasError('username')): ?><div class="invalid-feedback"><?= esc($getError('username')) ?></div><?php endif; ?>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-role">Account Level <span class="account-required-marker text-danger" aria-hidden="true">*</span></label>
                            <?php if ($isRoleReadOnly): ?>
                                <input class="form-control account-role-readonly <?= $hasError('role') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-role" type="text" value="<?= esc($displayRoleLabel, 'attr') ?>" disabled>
                                <small class="text-muted"><?= $isSelfProfile ? '' : 'You cannot change this account level.' ?></small>
                                <input type="hidden" name="role" value="<?= esc($role, 'attr') ?>">
                            <?php else: ?>
                                <select class="form-select <?= $hasError('role') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-role" name="role" required>
                                    <?php if (! $isEdit && ! $isSelfProfile): ?>
                                        <option value="">Choose account level</option>
                                    <?php endif; ?>
                                    <option value="administrator" <?= $role === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                                    <option value="encoder" <?= $role === 'encoder' ? 'selected' : '' ?>>Encoder</option>
                                    <option value="viewer" <?= $role === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                    <option value="scanner" <?= $role === 'scanner' ? 'selected' : '' ?>>Scanner</option>
                                </select>
                            <?php endif; ?>
                            <?php if ($hasError('role')): ?><div class="invalid-feedback d-block"><?= esc($getError('role')) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php if (! $isEdit && ! $isSelfProfile): ?>
                        <div class="account-fields-row account-fields-row--requirements">
                            <div class="account-field">
                                <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-password">Password</label>
                                <input class="form-control <?= $hasError('password') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-password" name="password" type="password" placeholder="Leave blank for temp password" minlength="8">
                                <?php if ($hasError('password')): ?><div class="invalid-feedback"><?= esc($getError('password')) ?></div><?php endif; ?>
                            </div>
                            <div class="account-field account-field--wide">
                                <label class="form-label invisible" aria-hidden="true">&nbsp;</label>
                                <span class="text-muted small">Leave blank to set a temporary password (<code>password123</code>).</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($isEdit && ! $isSelf): ?>
                        <div class="account-fields-row account-fields-row--requirements">
                            <div class="account-field">
                                <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-new-password">New Password</label>
                                <input class="form-control <?= $hasError('new_password') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-new-password" name="new_password" type="password" autocomplete="new-password" placeholder="Leave blank for temp password" minlength="8">
                                <?php if ($hasError('new_password')): ?><div class="invalid-feedback"><?= esc($getError('new_password')) ?></div><?php endif; ?>
                            </div>
                            <div class="account-field account-field--wide">
                                <label class="form-label invisible" aria-hidden="true">&nbsp;</label>
                                <span class="text-muted small">Leave blank to set a temporary password (<code>password123</code>).</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($isSelfProfile): ?>
                        <div class="account-fields-row account-fields-row--credentials">
                            <div class="account-field">
                                <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-current-password">Current Password</label>
                                <input class="form-control <?= $hasError('current_password') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-current-password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password">
                                <?php if ($hasError('current_password')): ?><div class="invalid-feedback"><?= esc($getError('current_password')) ?></div><?php endif; ?>
                            </div>
                            <div class="account-field">
                                <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-new-password">New Password</label>
                                <input class="form-control <?= $hasError('new_password') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-new-password" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8">
                                <?php if ($hasError('new_password')): ?><div class="invalid-feedback"><?= esc($getError('new_password')) ?></div><?php endif; ?>
                            </div>
                            <div class="account-field">
                                <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-confirm-password">Confirm Password</label>
                                <input class="form-control <?= $hasError('confirm_password') ? 'is-invalid' : '' ?>" id="<?= esc($fieldPrefix, 'attr') ?>-confirm-password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter new password">
                                <?php if ($hasError('confirm_password')): ?><div class="invalid-feedback"><?= esc($getError('confirm_password')) ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="<?= btn('add') ?>"><?= esc($submitLabel) ?></button>
        </div>
    </form>
</div>
