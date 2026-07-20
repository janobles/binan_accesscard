<?php
/**
 * Kiosk shell for the scan flow (setting + scan pages): full-viewport, no
 * sidebar/topbar. Deliberately minimal for time-and-motion — one slim header
 * bar (batch · aid type · live personal counter · logout) and the page
 * content. Reports and stats stay in the admin dashboard shell.
 */
$pageTitle          = $pageTitle ?? 'Scan';
$username           = $username ?? 'Scanner';
$activeBatch        = $activeBatch ?? null;
$aidType            = $aidType ?? null;
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
<?php
$accountMenuData = [
    'user' => $user ?? [],
    'username' => $username ?? 'Scanner',
    'accountLevelLabel' => $accountLevelLabel ?? 'Scanner'
];
?>
<nav class="sb-topnav navbar navbar-expand navbar-dark" style="background-color: var(--binan-green);">
  <div class="navbar-brand ps-3 d-flex align-items-center pe-3" style="white-space: nowrap;">
      <img src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo" height="24" class="me-2">
      <span class="d-none d-sm-inline">Bi&ntilde;an Access Card MIS</span>
      <div class="mx-3" style="border-left: 1px solid rgba(255,255,255,0.3); height: 20px;"></div>
      <span class="me-2" style="font-size: 0.95rem;"><?= $activeBatch !== null ? esc($activeBatch['name']) : 'No active batch' ?></span>
      <?php if ($aidType !== null): ?>
        <span class="badge bg-light text-dark"><?= esc($aidType['name']) ?></span>
      <?php endif; ?>
  </div>
  <ul class="navbar-nav ms-auto me-3 me-lg-4">
      <?= view('Partials/topbar-account-menu', $accountMenuData) ?>
  </ul>
</nav>
<main class="container-fluid px-4 py-3">

  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
  <?php endif; ?>
  <?= $this->renderSection('content') ?>
</main>

<?= view('components/modal', [
    'id' => 'familyModal',
    'modalClass' => 'floating-family-modal',
    'attrs' => 'aria-label="Record details" data-bs-backdrop="static" data-bs-keyboard="false"',
    'size' => 'modal-xl',
    'title' => 'Record',
    'titleId' => 'familyModalLabel',
    'bodyId' => 'familyModalBody',
    'bodyHtml' => '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
    'footerHtml' => '<button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button>',
]) ?>
<?php foreach (array_merge(asset_scripts('core'), asset_scripts('scanner')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(base_url('vendor/html5-qrcode/html5-qrcode.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
