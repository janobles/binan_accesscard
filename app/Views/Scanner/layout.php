<?php
/**
 * Mobile-first shell for the Scanner module (aid distribution at the point of
 * scan). Kept deliberately separate from Admin/Employee layout.php — this is a
 * single-purpose, phone-sized page, not a sidebar dashboard.
 *
 * Only "Scan" is live; Reports/History/Aid Types are disabled stubs reserved
 * for a later spec.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Scanner - Binan Access Card MIS</title>
    <?php foreach (asset_styles('head') as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1"><i class="bi bi-qr-code-scan me-2" aria-hidden="true"></i>Scanner</span>
            <a href="<?= site_url('logout') ?>" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </nav>
    <ul class="nav nav-pills nav-justified bg-white shadow-sm px-2 py-1">
        <li class="nav-item"><a class="nav-link active" href="<?= site_url('scanner/scan') ?>">Scan</a></li>
        <li class="nav-item"><span class="nav-link disabled" title="Later spec">Reports</span></li>
        <li class="nav-item"><span class="nav-link disabled" title="Later spec">History</span></li>
        <li class="nav-item"><span class="nav-link disabled" title="Later spec">Aid Types</span></li>
    </ul>
    <main class="container-fluid py-3" style="max-width:640px;">
        <?= $this->renderSection('content') ?>
    </main>
    <?php foreach (asset_scripts('core') as $scriptPath): ?>
    <script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
    <?php endforeach; ?>
    <script src="<?= esc(base_url('vendor/html5-qrcode/html5-qrcode.min.js'), 'attr') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
