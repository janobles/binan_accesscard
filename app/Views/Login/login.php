<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/login.css') ?>">
</head>
<body>
    <main class="login-page">
        <section class="login-card">
            <div class="logo-stack">
                <div class="logo-container">
                    <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo" class="logo-image">
                </div>
            </div>

            <h1 class="login-title">Binan Access Card Portal</h1>

            <?php if (session()->getFlashdata('error')): ?>
                <p class="login-error"><?= esc(session()->getFlashdata('error')) ?></p>
            <?php endif; ?>

            <form method="post" action="<?= site_url('login') ?>" class="login-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= esc(old('username')) ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="login-button">Login</button>
            </form>
        </section>
    </main>
    <script src="<?= base_url('assets/js/login.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/login.js') ?>"></script>
</body>
</html>
