<?php
/**
 * Lightweight shell for Scanner pages that need a real data table (search,
 * filters, pagination) but aren't the kiosk scan flow: same topnav brand and
 * account menu as kiosk-layout, dashboard table CSS/JS loaded directly here
 * rather than going through the dashboard page builder, which is wired to one
 * big activePage switch a Scanner page cannot join without forking it.
 * Scanner\ScanController::history() is the only page that uses it.
 */
$pageTitle          = $pageTitle ?? 'Biñan Access Card MIS';
$username           = $username ?? 'Scanner';
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
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
  </div>
  <ul class="navbar-nav ms-auto me-3 me-lg-4">
      <?= view('Partials/topbar-account-menu', $accountMenuData) ?>
  </ul>
</nav>
<main class="container-fluid px-4 py-3">

  <?= view('components/toast') ?>
  <?= view('Partials/flash-toasts') ?>
  <?= $this->renderSection('content') ?>
</main>

<?php foreach (array_merge(asset_scripts('core'), ['assets/js/dashboard/lookup-search.js', 'assets/js/dashboard/records-filter-panel.js', 'assets/js/dashboard/table-paginate.js']) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
