<?php
/**
 * Edit-account modal fragment (loaded by accounts-modal.js into #familyModalBody).
 * Admin/Developer edits another staff account's username, personal details, and
 * account level. Passwords are not changed here — use the "Reset Password" action
 * in the accounts list, then the user changes it in My Account.
 *
 * @var array $account userID, username, role (account_level), full_description, isactive
 * @var array $details parsed name/address fields (ViewFormatter::parseFullDescription)
 * @var bool  $isSelf  true when editing your own row (account level locked)
 */
$details = $details ?? [];
$userId = (int) ($account['userID'] ?? 0);
$username = (string) ($account['username'] ?? '');
$role = (string) ($account['role'] ?? '');
$isSelf = (bool) ($isSelf ?? false);
?>
<div class="accounts-page edit-account-modal">
    <header class="account-card-header" style="border:0;background:transparent;padding:0 0 0.5rem;">
        <div>
            <h2>Edit Account</h2>
            <p class="text-muted mb-0">Update this account's profile details and access level.</p>
        </div>
    </header>

    <form method="post" action="<?= site_url('accounts/update') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="userID" value="<?= esc((string) $userId, 'attr') ?>">

        <section class="account-card" aria-labelledby="edit-account-profile-title">
            <div class="account-card-header">
                <div>
                    <h2 id="edit-account-profile-title">Profile Information</h2>
                </div>
            </div>

            <div class="account-create-grid">
                <div class="account-field-group" aria-label="Personal information">
                    <div class="account-field">
                        <label class="form-label" for="edit-account-last-name">Last Name</label>
                        <input class="form-control" id="edit-account-last-name" name="last_name" type="text" value="<?= esc((string) ($details['last_name'] ?? ''), 'attr') ?>" placeholder="Enter last name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-first-name">First Name</label>
                        <input class="form-control" id="edit-account-first-name" name="first_name" type="text" value="<?= esc((string) ($details['first_name'] ?? ''), 'attr') ?>" placeholder="Enter first name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-middle-name">Middle Name</label>
                        <input class="form-control" id="edit-account-middle-name" name="middle_name" type="text" value="<?= esc((string) ($details['middle_name'] ?? ''), 'attr') ?>" placeholder="Enter middle name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-suffix">Suffix <span class="text-muted">(optional)</span></label>
                        <input class="form-control" id="edit-account-suffix" name="suffix" type="text" value="<?= esc((string) ($details['suffix'] ?? ''), 'attr') ?>" placeholder="e.g. Jr, Sr, III">
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-address">Address</label>
                        <input class="form-control" id="edit-account-address" name="address" type="text" value="<?= esc((string) ($details['address'] ?? ''), 'attr') ?>" placeholder="Enter address" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-contact-no">Contact No.</label>
                        <input class="form-control" id="edit-account-contact-no" name="contact_no" type="text" value="<?= esc((string) ($details['contact_no'] ?? ''), 'attr') ?>" placeholder="Enter contact number" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-birthday">Birthday</label>
                        <input class="form-control" id="edit-account-birthday" name="birthday" type="date" value="<?= esc((string) ($details['birthday'] ?? ''), 'attr') ?>" required>
                    </div>
                </div>

                <div class="account-field-group" aria-label="Login information">
                    <div class="account-field">
                        <label class="form-label" for="edit-account-username">Username</label>
                        <input class="form-control" id="edit-account-username" name="username" type="text" value="<?= esc($username, 'attr') ?>" placeholder="Enter username" required minlength="4">
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="edit-account-role">Account Level</label>
                        <select class="form-select" id="edit-account-role" name="role" required <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="administrator" <?= $role === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                            <option value="encoder" <?= $role === 'encoder' ? 'selected' : '' ?>>Encoder</option>
                            <option value="viewer" <?= $role === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                        <?php if ($isSelf): ?>
                            <small class="text-muted">You cannot change your own account level.</small>
                            <input type="hidden" name="role" value="<?= esc($role, 'attr') ?>">
                        <?php endif; ?>
                    </div>
                    <div class="account-field">
                        <p class="text-muted mb-0" style="font-size:0.82rem;">To reset this user's password, use the <strong>Reset Password</strong> button in the accounts list.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="account-create-actions mt-3">
            <button class="btn btn-outline-secondary me-2" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="submit">Save Changes</button>
        </div>
    </form>
</div>
