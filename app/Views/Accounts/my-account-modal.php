<?php
/**
 * "My Account" modal fragment (loaded by my-account-modal.js into #familyModalBody).
 * Lets the logged-in Admin / Encoder / Viewer edit their own username and personal
 * details and optionally change their password. Account level is read-only here;
 * it can only be changed by an Admin/Developer from Account Management.
 *
 * @var array  $account   userID, username, role (account_level), full_description, isactive
 * @var array  $details   parsed name/address fields (ViewFormatter::parseFullDescription)
 * @var string $roleLabel display label for the account level (Admin/Employee/Viewer)
 */
$details = $details ?? [];
$username = (string) ($account['username'] ?? '');
?>
<div class="accounts-page my-account-modal">
    <header class="account-card-header" style="border:0;background:transparent;padding:0 0 0.5rem;">
        <div>
            <h2>My Account</h2>
            <p class="text-muted mb-0">Update your profile details and password.</p>
        </div>
    </header>

    <form method="post" action="<?= site_url('account/profile/update') ?>">
        <?= csrf_field() ?>

        <section class="account-card" aria-labelledby="my-account-profile-title">
            <div class="account-card-header">
                <div>
                    <h2 id="my-account-profile-title">Profile Information</h2>
                    <p class="account-card-kicker mb-0" style="margin-top:0.3rem;">This information appears in logs and account activity.</p>
                </div>
            </div>

            <div class="account-create-grid">
                <div class="account-field-group" aria-label="Account">
                    <div class="account-field">
                        <label class="form-label" for="my-account-username">Username</label>
                        <input class="form-control" id="my-account-username" name="username" type="text" value="<?= esc($username, 'attr') ?>" placeholder="Enter username" required minlength="4">
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-level">Account Level</label>
                        <input class="form-control" id="my-account-level" type="text" value="<?= esc($roleLabel) ?>" readonly disabled>
                    </div>
                </div>

                <div class="account-field-group" aria-label="Personal information">
                    <div class="account-field">
                        <label class="form-label" for="my-account-first-name">First Name</label>
                        <input class="form-control" id="my-account-first-name" name="first_name" type="text" value="<?= esc((string) ($details['first_name'] ?? ''), 'attr') ?>" placeholder="Enter first name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-middle-name">Middle Name</label>
                        <input class="form-control" id="my-account-middle-name" name="middle_name" type="text" value="<?= esc((string) ($details['middle_name'] ?? ''), 'attr') ?>" placeholder="Enter middle name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-last-name">Last Name</label>
                        <input class="form-control" id="my-account-last-name" name="last_name" type="text" value="<?= esc((string) ($details['last_name'] ?? ''), 'attr') ?>" placeholder="Enter last name" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-suffix">Suffix <span class="text-muted">(optional)</span></label>
                        <input class="form-control" id="my-account-suffix" name="suffix" type="text" value="<?= esc((string) ($details['suffix'] ?? ''), 'attr') ?>" placeholder="e.g. Jr, Sr, III">
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-address">Address</label>
                        <input class="form-control" id="my-account-address" name="address" type="text" value="<?= esc((string) ($details['address'] ?? ''), 'attr') ?>" placeholder="Enter address" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-contact-no">Contact No.</label>
                        <input class="form-control" id="my-account-contact-no" name="contact_no" type="text" value="<?= esc((string) ($details['contact_no'] ?? ''), 'attr') ?>" placeholder="Enter contact number" required>
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-birthday">Birthday</label>
                        <input class="form-control" id="my-account-birthday" name="birthday" type="date" value="<?= esc((string) ($details['birthday'] ?? ''), 'attr') ?>" required>
                    </div>
                </div>
            </div>
        </section>

        <section class="account-card mt-3" aria-labelledby="my-account-password-title">
            <div class="account-card-header">
                <div>
                    <h2 id="my-account-password-title">Change Password</h2>
                    <p class="account-card-kicker mb-0" style="margin-top:0.3rem;">Leave these fields blank if you do not want to change your password.</p>
                </div>
            </div>

            <div class="account-create-grid">
                <div class="account-field-group" aria-label="Password">
                    <div class="account-field">
                        <label class="form-label" for="my-account-current-password">Current Password</label>
                        <input class="form-control" id="my-account-current-password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password">
                    </div>
                </div>
                <div class="account-field-group" aria-label="New password">
                    <div class="account-field">
                        <label class="form-label" for="my-account-new-password">New Password</label>
                        <input class="form-control" id="my-account-new-password" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8">
                    </div>
                    <div class="account-field">
                        <label class="form-label" for="my-account-confirm-password">Confirm Password</label>
                        <input class="form-control" id="my-account-confirm-password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter new password">
                    </div>
                </div>
            </div>
        </section>

        <div class="account-create-actions mt-3">
            <button class="btn btn-outline-secondary me-2" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" type="submit">Save Account</button>
        </div>
    </form>
</div>
