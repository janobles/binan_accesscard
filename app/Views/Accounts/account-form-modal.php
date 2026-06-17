<?php
/**
 * Shared create/edit account modal fragment.
 *
 * @var string $mode create|edit|self
 * @var array  $account userID, username, role, full_description, isactive
 * @var array  $details parsed name/address fields
 * @var bool   $isSelf true when editing your own row
 */
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$isSelfProfile = $mode === 'self';
$details = $details ?? [];
$account = $account ?? [];
$userId = (int) ($account['userID'] ?? 0);
$username = ($isEdit || $isSelfProfile) ? (string) ($account['username'] ?? '') : (string) old('username');
$role = ($isEdit || $isSelfProfile) ? (string) ($account['role'] ?? '') : (string) old('role');
$isSelf = (bool) ($isSelf ?? false);
$roleLabel = (string) ($roleLabel ?? $role);
$fieldPrefix = $isEdit ? 'edit-account' : ($isSelfProfile ? 'my-account' : 'account');
$title = $isEdit ? 'Edit Account' : ($isSelfProfile ? 'My Account' : 'Create Account');
$subtitle = $isEdit ? "Update this account's profile details and access level." : ($isSelfProfile ? 'Update your profile details and password.' : 'Add a new system user and assign an access level.');
$action = $isEdit ? site_url('accounts/update') : ($isSelfProfile ? site_url('account/profile/update') : site_url('developer/accounts'));
$submitLabel = $isEdit ? 'Save Changes' : ($isSelfProfile ? 'Save Account' : 'Create');
$value = static function (array $details, string $key, bool $isEdit): string {
    return $isEdit ? (string) ($details[$key] ?? '') : (string) old($key);
};
?>
<div class="accounts-page edit-account-modal">
    <header class="account-card-header" style="border:0;background:transparent;padding:0 0 0.5rem;">
        <div>
            <h2><?= esc($title) ?></h2>
            <p class="text-muted mb-0"><?= esc($subtitle) ?></p>
        </div>
    </header>

    <form method="post" action="<?= esc($action, 'attr') ?>">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="userID" value="<?= esc((string) $userId, 'attr') ?>">
        <?php endif; ?>

        <section class="account-card" aria-labelledby="<?= esc($fieldPrefix, 'attr') ?>-profile-title">
            <div class="account-card-header">
                <div>
                    <h2 id="<?= esc($fieldPrefix, 'attr') ?>-profile-title">Profile Information</h2>
                </div>
            </div>

            <div class="account-create-grid">
                <div class="account-field-group" aria-label="Personal information">
                    <div class="account-field">
                        <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-last-name">Last Name</label>
                        <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-last-name" name="last_name" type="text" value="<?= esc($value($details, 'last_name', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter last name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-first-name">First Name</label>
                        <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-first-name" name="first_name" type="text" value="<?= esc($value($details, 'first_name', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter first name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-middle-name">Middle Name</label>
                        <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-middle-name" name="middle_name" type="text" value="<?= esc($value($details, 'middle_name', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="Enter middle name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-suffix">Suffix <span class="text-muted">(optional)</span></label>
                        <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-suffix" name="suffix" type="text" value="<?= esc($value($details, 'suffix', $isEdit || $isSelfProfile), 'attr') ?>" placeholder="e.g. Jr, Sr, III">
                    </div>
                    <?php if (! $isSelfProfile): ?>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-address">Address</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-address" name="address" type="text" value="<?= esc($value($details, 'address', $isEdit), 'attr') ?>" placeholder="Enter address" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-contact-no">Contact No.</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-contact-no" name="contact_no" type="text" value="<?= esc($value($details, 'contact_no', $isEdit), 'attr') ?>" placeholder="Enter contact number" required>
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-birthday">Birthday</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-birthday" name="birthday" type="date" value="<?= esc($value($details, 'birthday', $isEdit), 'attr') ?>" required>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="account-field-group" aria-label="Login information">
                    <div class="account-field">
                        <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-username">Username</label>
                        <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-username" name="username" type="text" value="<?= esc($username, 'attr') ?>" placeholder="Enter username" required minlength="4">
                    </div>
                    <?php if (! $isEdit && ! $isSelfProfile): ?>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-password">Password</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-password" name="password" type="password" placeholder="Enter password" required minlength="8">
                        </div>
                    <?php endif; ?>
                    <div class="account-field">
                        <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-role">Account Level</label>
                        <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>-role" name="role" required <?= ($isEdit && $isSelf) || $isSelfProfile ? 'disabled' : '' ?>>
                            <?php if (! $isEdit && ! $isSelfProfile): ?>
                                <option value="">Choose account level</option>
                            <?php endif; ?>
                            <option value="administrator" <?= $role === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                            <option value="encoder" <?= $role === 'encoder' ? 'selected' : '' ?>>Encoder</option>
                            <option value="viewer" <?= $role === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                        <?php if (($isEdit && $isSelf) || $isSelfProfile): ?>
                            <small class="text-muted"><?= $isSelfProfile ? 'Your account level is read-only.' : 'You cannot change your own account level.' ?></small>
                            <input type="hidden" name="role" value="<?= esc($role, 'attr') ?>">
                        <?php endif; ?>
                    </div>
                    <?php if ($isEdit): ?>
                        <div class="account-field">
                            <p class="text-muted mb-0" style="font-size:0.82rem;">To reset this user's password, use the <strong>Reset Password</strong> button in the accounts list.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($isSelfProfile): ?>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-current-password">Current Password</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-current-password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password">
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-new-password">New Password</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-new-password" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8">
                        </div>
                        <div class="account-field">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>-confirm-password">Confirm Password</label>
                            <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>-confirm-password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter new password">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="account-create-actions mt-3">
            <button class="btn btn-outline-secondary me-2" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="submit"><?= esc($submitLabel) ?></button>
        </div>
    </form>
</div>
