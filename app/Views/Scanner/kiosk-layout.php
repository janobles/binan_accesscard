<?php
/**
 * Kiosk shell for the scan flow (setting + scan pages): full-viewport, no
 * sidebar/topbar. Deliberately minimal for time-and-motion - one slim header
 * bar (batch · subsidy type · live personal counter · logout) and the page
 * content. Reports and stats stay in the admin dashboard shell.
 */
$pageTitle          = $pageTitle ?? 'Scan';
$username           = $username ?? 'Scanner';
$activeBatch        = $activeBatch ?? null;
$subsidyType        = $subsidyType ?? null;
$myBatchCount       = (int) ($myBatchCount ?? 0);
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link rel="icon" type="image/png" href="<?= asset_url('assets/image/binan.png') ?>">
    <link rel="stylesheet" href="<?= esc(asset_url('css/design-tokens.css'), 'attr') ?>">
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin'), asset_styles('scanner')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body class="app-body bg-white">
<?php
$accountMenuData = [
    'user' => $user ?? [],
    'username' => $username ?? 'Scanner',
    'accountLevelLabel' => $accountLevelLabel ?? 'Scanner'
];
$currentUri = uri_string();
$isPerformance = strpos($currentUri, 'scanner/performance') !== false;
?>
<header class="navbar navbar-expand bg-white border-bottom px-3 py-2 app-topbar">
  <div class="d-flex align-items-center gap-3">
      <div class="navbar-brand d-flex align-items-center m-0 pe-2">
          <img src="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>" alt="City of Binan Logo" height="32" class="me-2">
          <span class="fw-normal d-none d-sm-inline">Bi&ntilde;an Access Card MIS</span>
      </div>
      <div class="vr bg-secondary opacity-25" style="width: 1px;"></div>
      <div class="d-flex flex-column lh-1 justify-content-center">
          <span class="fw-bold mb-1" style="font-size: 0.95rem;"><?= $activeBatch !== null ? esc($activeBatch['name']) : 'No active batch' ?></span>
          <?php if ($subsidyType !== null): ?>
            <span class="badge bg-light text-dark align-self-start border fw-normal" style="font-size: 0.75rem;"><?= esc($subsidyType['name']) ?></span>
          <?php endif; ?>
      </div>
  </div>
  <ul class="navbar-nav ms-auto">
      <?= view('Partials/topbar-account-menu', $accountMenuData) ?>
  </ul>
</header>

<main class="container-fluid px-4 py-4 dashboard-content flex-grow-1">

  <ul class="nav nav-pills segmented-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link <?= !$isPerformance ? 'active' : '' ?>" <?= !$isPerformance ? 'aria-current="page"' : '' ?> href="<?= site_url('scanner/scan') ?>">
        Scan Handout
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $isPerformance ? 'active' : '' ?>" <?= $isPerformance ? 'aria-current="page"' : '' ?> href="<?= site_url('scanner/performance') ?>">
        My Performance
      </a>
    </li>
  </ul>

  <?= view('components/toast') ?>
  <?= view('Partials/flash-toasts') ?>
  <?= $this->renderSection('content') ?>
</main>

<?php /* Plain-sized modal (no floating-family-modal - that class is tuned
         for the admin/employee record-edit view's rich layout, way oversized
         for the scan panel's simple head + members read-only popup). */ ?>
<?= view('components/modal', [
    'id' => 'familyModal',
    'attrs' => 'aria-label="Family details"',
    'size' => 'modal-lg',
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
