<?php
/**
 * Scanner performance page (Scanner kiosk > Performance).
 *
 * Per-batch throughput figures for the operator running the kiosk, scoped by the
 * batch selector at the top. Numbers are fetched from the stats endpoint rather than
 * rendered server side, so the page can refresh without reloading mid-distribution.
 *
 * An Admin or Developer can open one station from the dashboard's Stations
 * grid, in which case the figures belong to that scanner and not to the
 * account signed in. The heading names whose station this is; the topbar
 * account menu keeps naming the signed-in account. Data comes from
 * Scanner\ScanController::performance().
 */

$stationName = (string) ($stationName ?? 'Scanner');
$viewingOther = (bool) ($viewingOther ?? false);
?>
<?= $this->extend('Scanner/kiosk-layout') ?>
<?= $this->section('content') ?>

<h2 class="dashboard-zone-title"><?= $viewingOther ? 'Station ' . esc($stationName) : 'My performance' ?></h2>

<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-6">
        <label for="batchSelect" class="form-label fw-bold">Batch</label>
        <select class="form-select" id="batchSelect">
          <?php foreach ($batches as $b): ?>
            <option value="<?= esc((string) $b['batch_id'], 'attr') ?>" <?= (int) $b['batch_id'] === $batchId ? 'selected' : '' ?>>
              <?= esc($b['name']) ?><?= $b['closed_at'] === null ? ' (open)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 text-md-end">
        <div class="text-muted small mb-2">Last updated <span id="lastUpdated">-</span></div>
        <button class="btn btn-outline-secondary px-4" id="refreshNow" type="button" style="min-width: 120px; min-height: 44px;">Refresh</button>
      </div>
    </div>
  </div>
</div>

<section class="reports-stats mb-3" aria-label="Kiosk performance">
  <div class="row row-cols-2 row-cols-md-4 g-3 kpi-row">
    <div class="col stat-card--records">
      <div class="card kpi-card h-100">
        <div class="card-body">
          <p class="kpi-label">Families served</p>
          <p class="kpi-value"><?= esc((string) (int) ($mine['families'] ?? 0)) ?></p>
        </div>
      </div>
    </div>
    <div class="col stat-card--members">
      <div class="card kpi-card h-100">
        <div class="card-body">
          <p class="kpi-label">Handouts logged</p>
          <p class="kpi-value"><?= esc((string) (int) ($mine['handouts'] ?? 0)) ?></p>
        </div>
      </div>
    </div>
    <div class="col stat-card--sectors">
      <div class="card kpi-card h-100">
        <div class="card-body">
          <p class="kpi-label">Families / hour</p>
          <p class="kpi-value"><?= esc((string) (int) ($pace['perHour'] ?? 0)) ?></p>
        </div>
      </div>
    </div>
    <div class="col stat-card--services">
      <div class="card kpi-card h-100">
        <div class="card-body">
          <p class="kpi-label">Busiest window</p>
          <p class="kpi-value"><?= esc(($pace['busiest'] ?? '') !== '' ? $pace['busiest'] : '-') ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="fw-bold mb-2">Throughput - families served per 15 minutes</div>
    <div style="position:relative;height:260px"><canvas id="chartThroughput"></canvas></div>
    <p class="text-muted small mb-0" id="throughputEmpty" hidden>No scans logged yet for this batch.</p>
  </div>
</div>

<script type="application/json" id="kioskTimeline"><?= json_encode($timeline ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var url = '<?= site_url('scanner/stats') ?>';
  // Set only when an Admin/Developer has drilled into a station (see
  // ScanController::resolveViewedScanner()); carried into the poll and the
  // batch selector so both stay pinned on that station instead of quietly
  // falling back to the viewer's own figures.
  var viewedScannerId = <?= $viewedScannerId === null ? 'null' : (int) $viewedScannerId ?>;
  // The batch has to ride along too. The endpoint otherwise answers for
  // whichever batch is open, so a station square opened on a closed batch
  // renders the right figures and then has them overwritten five seconds
  // later, or zeroed when no batch is open at all.
  var pollBatchId = <?= (int) $batchId ?>;
  var params = [];
  if (viewedScannerId !== null) { params.push('scanner=' + encodeURIComponent(viewedScannerId)); }
  if (pollBatchId > 0) { params.push('batch=' + encodeURIComponent(pollBatchId)); }
  if (params.length) { url += '?' + params.join('&'); }

  function cssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name);
    return (v && v.trim()) || fallback;
  }
  var barColor = cssVar('--chart-color-1', '#4e73df');
  var gridColor = cssVar('--chart-grid', '#eaecf4');

  var timelineEl = document.getElementById('kioskTimeline');
  var timeline = [];
  try { timeline = JSON.parse(timelineEl.textContent || '[]'); } catch (e) { timeline = []; }

  var empty = document.getElementById('throughputEmpty');
  var chart = null;
  var canvas = document.getElementById('chartThroughput');

  function labels(rows) { return rows.map(function (r) { return r.label; }); }
  function values(rows) { return rows.map(function (r) { return r.families; }); }

  function draw(rows) {
    if (empty) { empty.hidden = rows.length > 0; }
    if (!canvas || !window.Chart) { return; }
    if (chart) {
      chart.data.labels = labels(rows);
      chart.data.datasets[0].data = values(rows);
      chart.update();
      return;
    }
    chart = new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels(rows),
        datasets: [{ label: 'Families', data: values(rows), backgroundColor: barColor, borderRadius: 4, maxBarThickness: 48 }]
      },
      options: {
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 }, grid: { color: gridColor } },
          x: { grid: { display: false } }
        },
        plugins: { legend: { display: false } }
      }
    });
  }

  function setTile(variant, value) {
    var el = document.querySelector('.' + variant + ' .kpi-value');
    if (el) { el.textContent = value; }
  }

  function paint(d) {
    setTile('stat-card--records', d.families);
    setTile('stat-card--members', d.handouts);
    if (d.pace) {
      setTile('stat-card--sectors', d.pace.perHour);
      setTile('stat-card--services', d.pace.busiest || '-');
    }
    if (Array.isArray(d.timeline)) { draw(d.timeline); }
    document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
  }

  function poll() {
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(paint)
      .catch(function () {});
  }

  draw(timeline);
  document.getElementById('refreshNow').addEventListener('click', poll);
  document.getElementById('batchSelect').addEventListener('change', function () {
    var target = '<?= site_url('scanner/performance') ?>?batch=' + encodeURIComponent(this.value);
    if (viewedScannerId !== null) { target += '&scanner=' + encodeURIComponent(viewedScannerId); }
    window.location.href = target;
  });
  document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
  // A closed batch cannot change, so only an open one is worth polling. The
  // Refresh button still works either way.
  if (<?= ($batchOpen ?? false) ? 'true' : 'false' ?>) {
    setInterval(function () {
      if (document.visibilityState === 'visible') { poll(); }
    }, 5000);
  }
})();
</script>
<?= $this->endSection() ?>
