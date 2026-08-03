<?php
/**
 * Login page (the site root and /login).
 *
 * Rendered by Auth\AuthController for both the GET form and the redisplay after a
 * failed POST, so the fields repopulate from old(). Standalone: this is one of the
 * few views with its own doctype rather than a dashboard layout. Styling is
 * public/css/login.css; login.js resets the idle timer.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Binan Access Card Portal</title>
    <link rel="icon" type="image/png" href="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>">
    <?php foreach (asset_styles('login') as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
    <main class="login-page">
        <div class="container position-relative z-1">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-5 col-xl-4">
                    <section class="login-card">
                        <div class="login-card-body">
                <div class="login-heading">
                    <img src="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>" alt="City of Binan Logo" class="login-logo">
                    <h1>Binan Access Card Portal</h1>
                    <p>Sign in to continue</p>
                </div>

                <?= view('components/toast') ?>
                <?= view('Partials/flash-toasts') ?>

                <form method="post" action="<?= site_url('login') ?>" autocomplete="on">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            value="<?= esc(old('username')) ?>"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="password" class="form-label mb-1">Password</label>
                            <a href="#" class="text-decoration-none small text-muted mb-1">Forgot password?</a>
                        </div>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn login-submit">Login</button>
                </form>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <?php foreach (asset_scripts('login') as $scriptPath): ?>
    <script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
    <?php endforeach; ?>
</body>
</html>
