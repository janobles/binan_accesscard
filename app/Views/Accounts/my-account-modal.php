<?php
/**
 * "My Account" modal fragment (loaded by my-account-modal.js into #familyModalBody).
 * Lets the logged-in Admin / Encoder / Viewer edit their own username and name and
 * optionally change their password. Account level is read-only here; address /
 * contact / birthday are set only at account creation and are preserved untouched
 * by ProfileController::update (they are intentionally not shown here).
 *
 * The Cancel / Save buttons are injected into the shared modal footer by
 * my-account-modal.js (so they stay visible without scrolling); this fragment
 * carries the form id `myAccountForm` those footer buttons target.
 *
 * @var array  $account   userID, username, role (account_level), full_description, isactive
 * @var array  $details   parsed name fields (ViewFormatter::parseFullDescription)
 * @var string $roleLabel display label for the account level (Admin/Employee/Viewer)
 */
$details = $details ?? [];
$username = (string) ($account['username'] ?? '');
?>
<div class="my-account-modal">
    <div class="my-account-hero">
        <span class="my-account-avatar"><i class="bi bi-person-circle" aria-hidden="true"></i></span>
        <div class="my-account-hero-text">
            <p class="my-account-hero-kicker">My Account</p>
            <h2 class="my-account-name"><?= esc($username) ?></h2>
            <span class="my-account-role-badge"><?= esc($roleLabel) ?></span>
        </div>
    </div>

    <form id="myAccountForm" method="post" action="<?= site_url('account/profile/update') ?>">
        <?= csrf_field() ?>

        <section class="my-account-section" aria-labelledby="my-account-profile-title">
            <header class="my-account-section-head">
                <h3 id="my-account-profile-title"><i class="bi bi-person-vcard" aria-hidden="true"></i> Profile Information</h3>
                <p>This information appears in logs and account activity.</p>
            </header>
            <div class="my-account-grid">
                <div class="account-field">
                    <label class="form-label" for="my-account-username">Username</label>
                    <input class="form-control" id="my-account-username" name="username" type="text" value="<?= esc($username, 'attr') ?>" placeholder="Enter username" required minlength="4">
                </div>
                <div class="account-field">
                    <label class="form-label" for="my-account-level">Account Level</label>
                    <input class="form-control my-account-readonly" id="my-account-level" type="text" value="<?= esc($roleLabel) ?>" readonly disabled>
                </div>
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
            </div>
        </section>

        <section class="my-account-section" aria-labelledby="my-account-password-title">
            <header class="my-account-section-head">
                <h3 id="my-account-password-title"><i class="bi bi-shield-lock" aria-hidden="true"></i> Change Password</h3>
                <p>Leave these fields blank to keep your current password.</p>
            </header>
            <div class="my-account-grid">
                <div class="account-field">
                    <label class="form-label" for="my-account-current-password">Current Password</label>
                    <input class="form-control" id="my-account-current-password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password">
                </div>
                <div class="account-field"></div>
                <div class="account-field">
                    <label class="form-label" for="my-account-new-password">New Password</label>
                    <input class="form-control" id="my-account-new-password" name="new_password" type="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8">
                </div>
                <div class="account-field">
                    <label class="form-label" for="my-account-confirm-password">Confirm Password</label>
                    <input class="form-control" id="my-account-confirm-password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter new password">
                </div>
            </div>
        </section>
    </form>
</div>
