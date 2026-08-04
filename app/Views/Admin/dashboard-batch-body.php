<?php
/**
 * Dashboard Zone 2, "this batch": the batch selector, progress block, coverage
 * and cumulative charts, and the Barangay/Stations/Remaining table. A
 * fragment, not a page: rendered inline by Pages/dashboard.php for
 * Developer/Admin only. Data comes from
 * DashboardPageBuilder::buildReportsData() (batches, batchRow, batchOpen,
 * batchSnapshot, remaining, batchBodyTab). All server data is escaped.
 */

$batches = $batches ?? [];
$batchRow = $batchRow ?? null;
$batchOpen = (bool) ($batchOpen ?? false);
$batchSnapshot = $batchSnapshot ?? ['coverage' => ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0], 'byBarangay' => [], 'perScanner' => [], 'timeline' => []];
$remaining = $remaining ?? [];
$batchBodyTab = $batchBodyTab ?? 'barangay';

$c = $batchSnapshot['coverage'];
$byBarangay = $batchSnapshot['byBarangay'];
$perScanner = $batchSnapshot['perScanner'];
$timeline = $batchSnapshot['timeline'];

$batchId = (int) ($batchRow['batch_id'] ?? 0);
$rangeLabel = $batchRow !== null ? 'Showing batch: ' . esc($batchRow['name']) : 'No batch selected';
$noBatch = $batchRow === null;
$noEligible = ! $noBatch && $c['eligible'] === 0;
?>

<div class="dashboard-section-head dashboard-section-head--subtitled">
  <div class="dashboard-section-head-title">
    <h2><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i>This batch</h2>
    <p class="text-muted small mb-0"><?= $rangeLabel ?><?php if ($batchOpen): ?> &middot; Last updated <span id="lastUpdated">-</span><?php endif; ?></p>
  </div>
  <div class="section-actions">
    <?php if ($batches !== []): ?>
    <form class="reports-filter" method="get" action="<?= site_url('dashboard') ?>">
      <label for="batchPick" class="form-label mb-0 visually-hidden">Batch</label>
      <select class="form-select" id="batchPick" name="batch" onchange="this.form.submit()">
        <?php foreach ($batches as $b): ?>
          <option value="<?= esc($b['batch_id'], 'attr') ?>" <?= $batchId === (int) $b['batch_id'] ? 'selected' : '' ?>>
            <?= esc($b['name']) ?><?= $b['closed_at'] === null ? ' (open)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
    <?php if (! $noBatch): ?>
    <a class="btn btn-primary reports-download-btn" href="<?= site_url('distribution/reports/pdf') . '?batch=' . (int) $batchId ?>"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span>Download Report</span></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($noBatch): ?>
<p class="text-muted mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i> No distribution batch exists yet. Open one from the Distribution page to see its coverage here.</p>
<?php elseif ($noEligible): ?>
<p class="text-muted mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i> This batch has no eligible families. Check its barangay and sector filters.</p>
<?php else: ?>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <?php /* Progress block: one unit, because remaining and percent are both
             derived from served and eligible. */ ?>
    <div class="card h-100"><div class="card-body">
      <p class="stat-label">This batch</p>
      <strong class="progress-headline">
        <?= esc((string) $c['served']) ?> of <?= esc((string) $c['eligible']) ?> served,
        <?= esc((string) $c['coverage']) ?>%
      </strong>
      <div class="progress" role="progressbar"
           aria-valuenow="<?= esc((string) $c['coverage'], 'attr') ?>"
           aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: <?= esc((string) $c['coverage'], 'attr') ?>%"></div>
      </div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <?= view('components/stat_card', [
        'label' => $batchOpen ? 'Remaining' : 'Not claimed',
        'value' => (string) $c['remaining'],
        'icon' => 'hourglass-split',
        'variant' => 'stat-card--records',
    ]) ?>
  </div>
  <?php if ($c['voided'] > 0): ?>
  <div class="col-6 col-lg-3">
    <?= view('components/stat_card', [
        'label' => 'Voided',
        'value' => (string) $c['voided'],
        'icon' => 'x-circle',
        'variant' => 'stat-card--members',
    ]) ?>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3 reports-charts mt-1">
  <div class="col-12 col-lg-8">
    <?= view('components/card', [
        'icon' => 'bar-chart-fill',
        'title' => 'Coverage by barangay (percent, worst first)',
        'bodyHtml' => '<div class="reports-barangay-chart"><canvas id="chartBarangay"></canvas></div>',
        'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-12 col-lg-4">
    <?php if ($batchOpen): ?>
    <?= view('components/card', [
        'icon' => 'graph-up',
        'title' => 'Families served over time',
        'bodyHtml' => '<div class="batch-timeline-chart"><canvas id="chartTimeline"></canvas></div>',
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
    <?php else: ?>
    <?php
    $scannerSummaryRows = [];
    foreach ($perScanner as $p) {
        $scannerSummaryRows[] = [esc($p['scanner']), esc((string) $p['families'])];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'person-badge',
        'title' => 'Per-kiosk families served',
        'columns' => ['Scanner', 'Families'],
        'rows' => $scannerSummaryRows,
        'emptyMessage' => 'No scans in this batch.',
        'tableClass' => 'table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback h-100 mb-0',
        'footer' => null,
    ]) ?>
    <?php endif; ?>
  </div>
</div>

<?= view('components/page_tabs', [
    'tabs' => [
        ['key' => 'barangay', 'label' => 'Barangay'],
        ['key' => 'stations', 'label' => 'Stations'],
        ['key' => 'remaining', 'label' => 'Remaining'],
    ],
    'active' => $batchBodyTab,
    'baseUrl' => 'dashboard',
    // Carries the batch selection through the sub-tab switch: without this,
    // ?tab= alone silently reverts an explicitly-picked batch back to the
    // default (active, or most recent) on every Barangay/Stations/Remaining
    // click.
    'queryParams' => $noBatch ? [] : ['batch' => $batchId],
]) ?>

<?php if ($batchBodyTab === 'barangay'):
    $barangayRows = [];
    foreach ($byBarangay as $b) {
        $barangayRows[] = [
            esc($b['barangay']),
            esc((string) $b['total']),
            esc((string) $b['received']),
            '<span class="badge bg-light text-dark border">' . esc((string) $b['coverage']) . '%</span>',
        ];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'table',
        'title' => 'Coverage by barangay',
        'columns' => ['Barangay', 'Eligible', 'Served', 'Coverage'],
        'rows' => $barangayRows,
        'emptyMessage' => 'No data for this batch.',
        'tableClass' => 'table manage-record-table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback',
        'footer' => null,
    ]) ?>
<?php elseif ($batchBodyTab === 'stations'):
    // Plain text, not a link: scanner/performance reads $userId from the
    // session, not a query param, so a link here would take an admin to their
    // own (usually zero) numbers under a station's name - a silent wrong
    // answer, worse than no link at all. Extending that route to accept a
    // target user is Task 12's scope, not this one's.
    $stationRows = [];
    foreach ($perScanner as $p) {
        $stationRows[] = [
            esc($p['scanner']),
            esc((string) $p['families']),
            esc((string) $p['handouts']),
        ];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'person-badge',
        'title' => 'Per-station performance',
        'columns' => ['Scanner', 'Families', 'Handouts'],
        'rows' => $stationRows,
        'emptyMessage' => 'No scans in this batch yet.',
        'tableClass' => 'table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback',
        'footer' => null,
    ]) ?>
<?php else: ?>
    <?php /* remaining() can return hundreds of rows: same client-side pagination
             pattern as the Distribution Batches / Distribution Log tables
             (assets/js/dashboard/table-paginate.js), not a data_table. */ ?>
    <?= view('components/card', [
        'icon' => 'hourglass-split',
        'title' => $batchOpen ? 'Remaining families' : 'Families not claimed',
        'cardClass' => 'reports-fallback',
        'attrs' => 'data-table-paginate data-paginate-key="remaining" data-paginate-label="families"',
        'bodyView' => 'Admin/dashboard-remaining-body',
        'bodyData' => ['remaining' => $remaining],
        'footer' => view('components/table_footer', ['clientKey' => 'remaining', 'entityLabel' => 'families']),
    ]) ?>
<?php endif; ?>

<script id="reportsData" type="application/json"><?= json_encode(
    [
        'coverage'  => $c,
        'barangay'  => $byBarangay,
        'timeline'  => $timeline,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?></script>

<script src="<?= esc(asset_url('vendor/chart.js/chart.umd.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/scanner-reports.js'), 'attr') ?>"></script>
<script>
(function () {
  // Live poll: fetch fresh stats for the selected batch and repaint the charts
  // in place (no page reload, so the batch selector, tab and scroll stay put).
  var statsUrl = '<?= site_url('distribution/reports/stats') ?>';
  var batchId = <?= (int) $batchId ?>;
  var batchOpen = <?= $batchOpen ? 'true' : 'false' ?>;
  if (batchId > 0) { statsUrl += '?batch=' + batchId; }

  function apply(d) {
    if (window.ReportsCharts) { window.ReportsCharts.update(d); }
    var stamp = document.getElementById('lastUpdated');
    if (stamp) { stamp.textContent = new Date().toLocaleTimeString(); }
  }

  function poll() {
    fetch(statsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) { apply(d); } })
      .catch(function () {});
  }

  var stamp = document.getElementById('lastUpdated');
  if (stamp) { stamp.textContent = new Date().toLocaleTimeString(); }

  // Live-poll only while the selected batch is open (closed batches are
  // static) and the tab is visible; browsers throttle hidden-tab timers anyway.
  if (batchOpen) {
    setInterval(function () {
      if (document.visibilityState === 'visible') { poll(); }
    }, 5000);
  }
})();
</script>
<?php endif; ?>
