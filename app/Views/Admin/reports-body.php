<?php
/* Admin overall Reports body fragment (no doctype/html/head/nav/script-shell —
   rendered inline by Admin/layout.php's $activePage === 'reports' block, same
   pattern as Admin/distribution-*-body.php). Batch-scoped only (no date
   filter); admin always sees every kiosk's per-scanner row. Data comes from
   DashboardPageBuilder::buildReportsData(). All server data esc()'d. */

$reportsBatches    = $reportsBatches ?? [];
$reportsBatchId    = $reportsBatchId ?? null;
$reportsBatchName  = $reportsBatchName ?? null;
$reportsSummary    = $reportsSummary ?? ['total' => 0, 'received' => 0, 'notReceived' => 0, 'coverage' => 0];
$reportsByBarangay = $reportsByBarangay ?? [];
$reportsByAidType  = $reportsByAidType ?? [];
$reportsPerScanner = $reportsPerScanner ?? [];

$rangeLabel = $reportsBatchName !== null
    ? 'Showing batch: ' . esc($reportsBatchName)
    : 'Showing all batches';
?>

<!-- Batch selector + PDF export -->
<div class="reports-toolbar">
  <form class="reports-filter" method="get" action="<?= site_url('admin/reports') ?>">
    <label for="batchPick" class="form-label mb-0">Batch</label>
    <select class="form-select" id="batchPick" name="batch" onchange="this.form.submit()">
      <?php foreach ($reportsBatches as $b): ?>
        <option value="<?= esc($b['batch_id'], 'attr') ?>" <?= $reportsBatchId === (int) $b['batch_id'] ? 'selected' : '' ?>>
          <?= esc($b['name']) ?><?= $b['closed_at'] === null ? ' (open)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="reports-actions">
    <button type="button" class="btn btn-secondary" id="refreshNow"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>Refresh</span></button>
    <a class="btn btn-primary reports-download-btn" href="<?= site_url('admin/reports/pdf') . '?batch=' . (int) $reportsBatchId ?>"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span>Download Report</span></a>
  </div>
</div>
<p class="text-muted small mb-3"><?= $rangeLabel ?> &middot; Last updated <span id="lastUpdated">-</span></p>

<!-- KPI tiles: same house style as the admin dashboard stat cards -->
<section class="reports-stats" aria-label="Report statistics">
  <?= view('components/stat_card', [
      'label' => 'Families with a QR',
      'value' => (string) $reportsSummary['total'],
      'icon' => 'qr-code',
      'variant' => 'stat-card--records',
  ]) ?>
  <?= view('components/stat_card', [
      'label' => 'Received aid',
      'value' => (string) $reportsSummary['received'],
      'icon' => 'check-circle-fill',
      'variant' => 'stat-card--members',
  ]) ?>
  <?= view('components/stat_card', [
      'label' => 'Still waiting',
      'value' => (string) $reportsSummary['notReceived'],
      'icon' => 'hourglass-split',
      'variant' => 'stat-card--sectors',
  ]) ?>
  <?= view('components/stat_card', [
      'label' => 'Coverage',
      'value' => ((string) $reportsSummary['coverage']) . '%',
      'icon' => 'pie-chart-fill',
      'variant' => 'stat-card--services',
  ]) ?>
</section>

<!-- Per-kiosk performance: admin sees every scanner (no self-scoping). -->
<?php
$scannerRows = [];
foreach ($reportsPerScanner as $p) {
    $scannerRows[] = [
        esc($p['scanner']),
        esc((string) $p['families']),
        esc((string) $p['handouts']),
    ];
}
?>
<?= view('components/data_table', [
    'icon' => 'person-badge',
    'title' => 'Per-kiosk performance this batch',
    'columns' => ['Scanner', 'Families served', 'Handouts logged'],
    'rows' => $scannerRows,
    'emptyMessage' => 'No scans in this batch yet.',
    'tableClass' => 'table manage-record-table align-middle w-100 mb-0',
    'cardClass' => 'reports-fallback',
    'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
]) ?>

<!-- Charts: each in the standard card anatomy (components/card) -->
<div class="row g-3 reports-charts">
  <div class="col-lg-4">
    <?= view('components/card', [
        'icon' => 'pie-chart-fill',
        'title' => 'Families that received aid vs still waiting',
        'bodyHtml' => '<canvas id="chartReceived" height="220"></canvas>',
        'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-lg-8">
    <?= view('components/card', [
        'icon' => 'bar-chart-fill',
        'title' => 'Coverage by barangay (percent)',
        'bodyHtml' => '<div class="reports-barangay-chart"><canvas id="chartBarangay"></canvas></div>',
        'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-lg-12">
    <?= view('components/card', [
        'icon' => 'bar-chart-fill',
        'title' => 'Number of handouts by aid type',
        'bodyHtml' => '<div style="position:relative;height:260px"><canvas id="chartAidType"></canvas></div>',
        'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
        'cardClass' => 'reports-chart-card',
    ]) ?>
  </div>
</div>

<!-- No-JS / print fallback summary table -->
<?php
$barangayRows = [];
foreach ($reportsByBarangay as $b) {
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
    'columns' => ['Barangay', 'Families', 'Received', 'Coverage'],
    'rows' => $barangayRows,
    'emptyMessage' => 'No data for this batch.',
    'tableClass' => 'table manage-record-table align-middle w-100 mb-0',
    'cardClass' => 'reports-fallback',
    'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
]) ?>

<script id="reportsData" type="application/json"><?= json_encode(
    [
        'received' => $reportsSummary,
        'barangay'  => $reportsByBarangay,
        'byAidType' => $reportsByAidType,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?></script>

<script src="<?= esc(asset_url('vendor/chart.js/chart.umd.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/scanner-reports.js'), 'attr') ?>"></script>
<script>
(function () {
  // Live poll: fetch fresh stats for the selected batch and repaint charts +
  // KPI tiles in place (no page reload, so the batch selector and scroll stay put).
  var statsUrl = '<?= site_url('admin/reports/stats') ?>';
  var batchId = <?= (int) ($reportsBatchId ?? 0) ?>;
  if (batchId > 0) { statsUrl += '?batch=' + batchId; }

  function setTile(variant, value) {
    var el = document.querySelector('.' + variant + ' strong');
    if (el) { el.textContent = value; }
  }

  function apply(d) {
    if (d.received) {
      setTile('stat-card--records', d.received.total);
      setTile('stat-card--members', d.received.received);
      setTile('stat-card--sectors', d.received.notReceived);
      setTile('stat-card--services', d.received.coverage + '%');
    }
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

  var btn = document.getElementById('refreshNow');
  if (btn) { btn.addEventListener('click', poll); }
  var stamp = document.getElementById('lastUpdated');
  if (stamp) { stamp.textContent = new Date().toLocaleTimeString(); }

  // Only poll while the tab is visible; browsers throttle hidden-tab timers anyway.
  setInterval(function () {
    if (document.visibilityState === 'visible') { poll(); }
  }, 5000);
})();
</script>
