<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Binan Access Card Portal</title>
    <link rel="stylesheet" href="<?= base_url('bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/login.css') ?>">
</head>
<body>
    <main class="login-page">
        <section class="login-card">
            <div class="login-card-body">
                <div class="login-heading">
                    <img
                        src="<?= base_url('assets/image/binan.png') ?>"
                        alt="City of Binan Logo"
                        class="login-logo"
                    >
                    <h1>Binan Access Card Portal</h1>
                    <p>Sign in to continue</p>
                </div>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= esc(session()->getFlashdata('error')) ?>
                    </div>
                <?php endif; ?>

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
                        <label for="password" class="form-label">Password</label>
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
    </main>
</body>
</html>
