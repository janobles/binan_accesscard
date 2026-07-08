<?php
/**
 * Admin distribution hub: aid-type catalogue + batch control, moved from the
 * scanner kiosk (Scanner/manage-*-body.php) into the admin server shell.
 * Rendered directly by Admin\DistributionController::index() (mirrors
 * Scanner/layout.php's SB-Admin frame; Admin/layout.php's activePage switch is
 * not used here since this page is not part of the DashboardPageBuilder set).
 *
 * Vars: pageTitle, username, user, accountLevelLabel, aidTypes, activeAidTypes,
 * distributions, batches, activeBatch, currentRole, canManageAccounts,
 * sidebarRoleClass, navActive.
 */
$aidTypes       = $aidTypes ?? [];
$activeAidTypes = $activeAidTypes ?? [];
$distributions  = $distributions ?? [];
$batches        = $batches ?? [];
$activeBatch    = $activeBatch ?? null;
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link rel="icon" type="image/png" href="<?= asset_url('assets/image/binan.png') ?>">
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body class="sb-nav-fixed">
<?= view('Partials/dashboard-topnav', [
    'brandUrl' => site_url('admin/dashboard'),
    'user' => $user,
    'username' => $username,
    'accountLevelLabel' => $accountLevelLabel,
]) ?>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <?= view('components/dashboard_sidebar', [
            'navActive' => $navActive,
            'canManageAccounts' => $canManageAccounts,
            'sidebarRoleClass' => $sidebarRoleClass,
            'sidebarUserUrl' => site_url('admin/dashboard'),
            'sidebarScannerOnly' => false,
        ]) ?>
    </div>
    <div id="layoutSidenav_content">
        <main class="container-fluid px-4 dashboard-content">
        <h1 class="mt-4" id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" data-auto-dismiss-alert><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" data-auto-dismiss-alert><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs manage-tabs mb-0" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-types" type="button" role="tab">Aid Types</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-batches" type="button" role="tab">Batches</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dist" type="button" role="tab">All Distributions</button></li>
        </ul>

        <div class="tab-content">
          <!-- Aid types CRUD -->
          <div class="tab-pane fade show active" id="tab-types" role="tabpanel">
            <?= view('components/card', [
                'icon' => 'tags-fill',
                'title' => 'Aid Types',
                'cardClass' => 'sector-management records-scroll-panel',
                'bodyView' => 'Admin/distribution-aidtypes-body',
                'bodyData' => ['aidTypes' => $aidTypes],
            ]) ?>
          </div>

          <!-- Distribution batches -->
          <div class="tab-pane fade" id="tab-batches" role="tabpanel">
            <?= view('components/card', [
                'icon' => 'collection',
                'title' => 'Distribution Batches',
                'cardClass' => 'sector-management records-scroll-panel',
                'bodyView' => 'Admin/distribution-batches-body',
                'bodyData' => [
                    'batches' => $batches,
                    'activeBatch' => $activeBatch,
                    'activeAidTypes' => $activeAidTypes,
                    'currentRole' => $currentRole,
                ],
            ]) ?>
          </div>

          <!-- All distributions -->
          <div class="tab-pane fade" id="tab-dist" role="tabpanel">
            <?= view('components/card', [
                'icon' => 'clipboard-check-fill',
                'title' => 'All Distributions',
                'cardClass' => 'sector-management records-scroll-panel',
                'bodyView' => 'Admin/distribution-distributions-body',
                'bodyData' => ['distributions' => $distributions, 'aidTypes' => $aidTypes],
                'footer' => '<span id="distCount"></span>',
            ]) ?>
          </div>
        </div>

        <!-- Add aid type modal -->
        <div class="modal fade" id="addAidTypeModal" tabindex="-1">
          <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= site_url('admin/aid-types/create') ?>">
              <?= csrf_field() ?>
              <div class="modal-header">
                <h5 class="modal-title">Add Aid Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <label for="aidTypeName" class="form-label">Name</label>
                <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="100">
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add</button>
              </div>
            </form>
          </div>
        </div>
        </main>
    </div>
</div>

<?= view('components/modal', [
    'id' => 'familyModal',
    'modalClass' => 'floating-family-modal',
    'attrs' => 'aria-label="Account settings" data-bs-backdrop="static" data-bs-keyboard="false"',
    'size' => 'modal-xl',
    'title' => 'My Account',
    'titleId' => 'familyModalLabel',
    'bodyId' => 'familyModalBody',
    'bodyHtml' => '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
    'footerHtml' => '<button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button>',
]) ?>

<?php foreach (array_merge(asset_scripts('core'), asset_scripts('admin')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const table   = document.getElementById('distTable');
  const search  = document.getElementById('distSearch');
  const filter  = document.getElementById('distAidFilter');
  const clear   = document.getElementById('distClear');
  const perPage = document.getElementById('distPerPage');
  const local   = document.getElementById('distLocalSearch');
  const count   = document.getElementById('distCount');
  if (!table) return;
  const rows = Array.from(table.tBodies[0].rows).filter(r => !r.querySelector('.sector-empty-state'));

  const render = () => {
    const q     = (search.value || '').trim().toLowerCase();
    const q2    = (local.value || '').trim().toLowerCase();
    const aid   = filter.value || '';
    const limit = parseInt(perPage.value, 10) || 0;
    let matched = 0;
    let shown = 0;
    rows.forEach(r => {
      const text = r.textContent.toLowerCase();
      const ok = (q === '' || text.includes(q))
              && (q2 === '' || text.includes(q2))
              && (aid === '' || (r.getAttribute('data-aidtype') || '') === aid);
      let visible = ok;
      if (ok) {
        matched++;
        if (limit > 0 && matched > limit) visible = false;
      }
      r.hidden = !visible;
      if (visible) shown++;
    });
    if (count) count.textContent = 'Showing ' + shown + ' of ' + rows.length + ' distribution' + (rows.length === 1 ? '' : 's');
  };

  [search, filter, local, perPage].forEach(el => el && el.addEventListener('input', render));
  if (perPage) perPage.addEventListener('change', render);
  if (clear) clear.addEventListener('click', () => { search.value = ''; local.value = ''; filter.value = ''; perPage.value = '50'; render(); });
  render();
});
</script>
</body>
</html>
