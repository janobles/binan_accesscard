<?php
/* Aid Distribution section of the admin dashboard (no doctype/html/head shell,
   rendered inline by Admin/layout.php's dashboard block). Section header carries
   the batch selector + Refresh/PDF actions (global actions live outside the
   cards, house style). KPI numbers sit in the dashboard's unified top tile row;
   data comes from DashboardPageBuilder::buildReportsData(). All server data
   esc()'d. */

$reportsBatches    = $reportsBatches ?? [];
$reportsBatchId    = $reportsBatchId ?? null;
$reportsBatchName  = $reportsBatchName ?? null;
$reportsBatchOpen  = $reportsBatchOpen ?? false;
$reportsSummary    = $reportsSummary ?? ['total' => 0, 'received' => 0, 'notReceived' => 0, 'coverage' => 0];
$reportsByBarangay = $reportsByBarangay ?? [];
$reportsByAidType  = $reportsByAidType ?? [];
$reportsPerScanner = $reportsPerScanner ?? [];

$rangeLabel = $reportsBatchName !== null
    ? 'Showing batch: ' . esc($reportsBatchName)
    : 'Showing all batches';

// Charts and tables only earn their space once the batch has scans (or is
// live and filling up); a closed empty batch collapses to one line.
$hasScanData = ((int) $reportsSummary['received']) > 0 || $reportsPerScanner !== [];
$showDistDetail = $reportsBatchOpen || $hasScanData;
?>

<div class="dashboard-section-head">
  <h2><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i>Aid Distribution</h2>
  <div class="section-actions">
    <form class="reports-filter" method="get" action="<?= site_url('admin/dashboard') ?>">
      <label for="batchPick" class="form-label mb-0 visually-hidden">Batch</label>
      <select class="form-select" id="batchPick" name="batch" onchange="this.form.submit()">
        <?php foreach ($reportsBatches as $b): ?>
          <option value="<?= esc($b['batch_id'], 'attr') ?>" <?= $reportsBatchId === (int) $b['batch_id'] ? 'selected' : '' ?>>
            <?= esc($b['name']) ?><?= $b['closed_at'] === null ? ' (open)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <button type="button" class="btn btn-outline-secondary" id="refreshNow"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>Refresh</span></button>
    <a class="btn btn-primary reports-download-btn" href="<?= site_url('admin/reports/pdf') . '?batch=' . (int) $reportsBatchId ?>"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span>Download Report</span></a>
  </div>
</div>
<p class="text-muted small mb-3"><?= $rangeLabel ?> &middot; Last updated <span id="lastUpdated">-</span></p>

<?php if (! $showDistDetail): ?>
<p class="text-muted mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i>
  No scans were logged in this batch. Pick another batch above to see its breakdown.</p>
<?php else: ?>

<!-- Barangay chart + aid-type/kiosk column (standard card anatomy) -->
<div class="row g-3 reports-charts">
  <div class="col-lg-8">
    <?= view('components/card', [
        'icon' => 'bar-chart-fill',
        'title' => 'Coverage by barangay (percent)',
        'bodyHtml' => '<div class="reports-barangay-chart"><canvas id="chartBarangay"></canvas></div>',
        'footer' => view('components/table_footer', ['leftContent' => $rangeLabel]),
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-lg-4 d-flex flex-column gap-3">
    <?php
    $aidTypeRows = [];
    foreach ($reportsByAidType as $t) {
        $aidTypeRows[] = [
            esc((string) $t['aid_type']),
            esc((string) $t['count']),
        ];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'box-seam',
        'title' => 'Handouts by aid type',
        'columns' => ['Aid type', 'Handouts'],
        'rows' => $aidTypeRows,
        'emptyMessage' => 'No handouts in this batch yet.',
        'tableClass' => 'table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback mb-0',
        'footer' => null,
    ]) ?>
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
        'title' => 'Per-kiosk performance',
        'columns' => ['Scanner', 'Families', 'Handouts'],
        'rows' => $scannerRows,
        'emptyMessage' => 'No scans in this batch yet.',
        'tableClass' => 'table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback mb-0',
        'footer' => null,
    ]) ?>
  </div>
</div>

<!-- No-JS / print fallback summary table (duplicates the chart, so screen hides it) -->
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
<div class="d-none d-print-block">
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
</div>

<?php endif; ?>

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
  // Live poll: fetch fresh stats for the selected batch and repaint the chart +
  // KPI tiles in place (no page reload, so the batch selector and scroll stay put).
  var statsUrl = '<?= site_url('admin/reports/stats') ?>';
  var batchId = <?= (int) ($reportsBatchId ?? 0) ?>;
  var batchOpen = <?= $reportsBatchOpen ? 'true' : 'false' ?>;
  if (batchId > 0) { statsUrl += '?batch=' + batchId; }

  function setTile(variant, value) {
    // Distribution tiles sit in the dashboard's unified top row; the sectors/
    // services variants are unique to them on this page.
    var el = document.querySelector('.overview-stats .' + variant + ' strong');
    if (el) { el.textContent = value; }
  }

  function apply(d) {
    if (d.received) {
      setTile('stat-card--sectors', (d.received.received || 0) + ' of ' + (d.received.total || 0));
      setTile('stat-card--services', (d.received.coverage || 0) + '%');
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

  // Live-poll only while the selected batch is open (closed batches are
  // static) and the tab is visible; browsers throttle hidden-tab timers anyway.
  if (batchOpen) {
    setInterval(function () {
      if (document.visibilityState === 'visible') { poll(); }
    }, 5000);
  }
})();
</script>
