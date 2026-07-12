<?php
/**
 * Kiosk shell for the scan flow (setting + scan pages): full-viewport, no
 * sidebar/topbar. Deliberately minimal for time-and-motion — one slim header
 * bar (batch · service · live personal counter · logout) and the page
 * content. Reports and stats stay in the admin dashboard shell.
 */
$pageTitle          = $pageTitle ?? 'Scan';
$username           = $username ?? 'Scanner';
$activeBatch        = $activeBatch ?? null;
$service            = $service ?? null;
$myBatchCount       = (int) ($myBatchCount ?? 0);
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin'), asset_styles('scanner')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<nav class="navbar navbar-dark px-3" style="background-color: var(--binan-green);">
  <span class="navbar-brand mb-0 h1 d-flex align-items-center gap-2">
    <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
    <span><?= $activeBatch !== null ? esc($activeBatch['name']) : 'No active batch' ?></span>
    <?php if ($service !== null): ?>
      <span class="badge bg-info text-dark fs-6"><?= esc(($service['code'] ?? '') !== '' ? $service['code'] . ' — ' . $service['name'] : $service['name']) ?></span>
    <?php endif; ?>
  </span>
  <div class="d-flex align-items-center gap-3 text-white">
    <div class="btn-group btn-group-sm" role="group" aria-label="Kiosk navigation">
      <a class="btn <?= url_is('scanner/scan') ? 'btn-light' : 'btn-outline-light' ?>" href="<?= site_url('scanner/scan') ?>"><i class="bi bi-upc-scan me-1" aria-hidden="true"></i>Scan</a>
      <a class="btn <?= url_is('scanner/performance') ? 'btn-light' : 'btn-outline-light' ?>" href="<?= site_url('scanner/performance') ?>"><i class="bi bi-graph-up me-1" aria-hidden="true"></i>Performance</a>
    </div>
    <span class="small"><?= esc($username) ?></span>
    <a class="btn btn-outline-light btn-sm" href="<?= site_url('logout') ?>">Logout</a>
  </div>
</nav>
<main class="container-fluid px-4 py-3">
  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
  <?php endif; ?>
  <?= $this->renderSection('content') ?>
</main>
<?php foreach (array_merge(asset_scripts('core'), asset_scripts('scanner')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(base_url('vendor/html5-qrcode/html5-qrcode.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
