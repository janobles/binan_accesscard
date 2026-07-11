<?php
/*
 * Shown by AuthController::login() when the credentials are valid but the same
 * account already holds an active session on another browser/device. Reuses the
 * login page's card styling. Both buttons POST to `login/confirm`, which reads the
 * server-side pending_login (no re-transmitted password): "Continue here" displaces
 * the other session; "Cancel" leaves it running.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Already Signed In | Binan Access Card Portal</title>
    <link rel="icon" type="image/png" href="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>">
    <?php foreach (asset_styles('login') as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
    <main class="login-page">
        <section class="login-card">
            <div class="login-card-body">
                <div class="login-heading">
                    <img src="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>" alt="City of Binan Logo" class="login-logo">
                    <h1>Already Signed In</h1>
                    <p>This account is active on another session</p>
                </div>

                <div class="alert alert-warning" role="alert">
                    Your account is already signed in on another session.
                </div>

                <p>Do you want to log out of that session and continue here?</p>

                <form method="post" action="<?= site_url('login/confirm') ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn login-submit">Log out the other session and continue</button>
                </form>

                <form method="post" action="<?= site_url('login/confirm') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" name="cancel" value="1" class="btn btn-outline-secondary w-100">Cancel</button>
                </form>
            </div>
        </section>
    </main>
    <?php foreach (asset_scripts('login') as $scriptPath): ?>
    <script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
    <?php endforeach; ?>
</body>
</html>
