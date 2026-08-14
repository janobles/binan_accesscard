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

<?php /* Eight label-over-value lines from the same partial the station modal
         renders, so the kiosk's own page and an admin's drill-in view of this
         same station can never disagree about what these figures are. See
         Scanner/_metrics-grid.php for the shape $metrics takes. */ ?>
<section class="mb-4" aria-label="Kiosk performance">
  <?= $this->include('Scanner/_metrics-grid', ['metrics' => $metrics ?? null]) ?>
</section>

<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="fw-bold mb-2">Throughput - families served per hour</div>
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

  // Matches ViewFormatter::duration() exactly, so a repaint prints the same
  // wording the server would have for the same figure. Duplicated rather than
  // shared with station-modal.js/scanner-reports.js: see those files' headers
  // for why each keeps its own copy.
  function duration(seconds) {
    if (seconds <= 0) { return '-'; }
    if (seconds < 60) { return seconds + ' s'; }
    if (seconds < 300) {
      var minutes = Math.floor(seconds / 60);
      var rest = seconds % 60;

      return rest === 0 ? minutes + ' m' : minutes + ' m ' + rest + ' s';
    }
    if (seconds < 3600) { return Math.floor(seconds / 60) + ' m'; }

    var hours = Math.floor(seconds / 3600);
    var restMinutes = Math.floor((seconds % 3600) / 60);

    return restMinutes === 0 ? hours + ' h' : hours + ' h ' + restMinutes + ' m';
  }

  // Matches PHP's date('ga', mktime($hour, 0)).
  function hourLabel(hour) {
    var normalized = ((hour % 24) + 24) % 24;
    var h = normalized % 12;
    if (h === 0) { h = 12; }

    return h + (normalized < 12 ? 'am' : 'pm');
  }

  // Reads the fetched metrics row by data-metric key, the same
  // Scanner/_metrics-grid.php partial the poll's markup already carries, so
  // a repaint never invents a field the server-rendered grid does not have.
  function paintMetrics(metrics) {
    var text = {
      families: metrics ? Number(metrics.families || 0).toLocaleString() : '-',
      handouts: metrics ? Number(metrics.handouts || 0).toLocaleString() : '-',
      pace: !metrics || metrics.pace === null || metrics.pace === undefined
        ? '-' : Number(metrics.pace).toFixed(0) + ' / hour',
      typical: !metrics || metrics.typicalSeconds === null || metrics.typicalSeconds === undefined
        ? '-' : duration(Number(metrics.typicalSeconds)),
      onStation: metrics ? duration(Number(metrics.onStationSeconds || 0)) : '-',
      idle: metrics ? duration(Number(metrics.idleSeconds || 0)) : '-',
      bestHour: !metrics || metrics.bestHour === null || metrics.bestHour === undefined
        ? '-' : hourLabel(Number(metrics.bestHour)) + ' - ' + hourLabel(Number(metrics.bestHour) + 1),
      share: metrics ? Number((metrics.share || 0) * 100).toFixed(0) + '%' : '-'
    };
    var sub = {
      bestHour: metrics && metrics.bestHour !== null && metrics.bestHour !== undefined
        ? Number(metrics.bestHourFamilies || 0).toLocaleString() + ' families' : ''
    };

    document.querySelectorAll('[data-metric]').forEach(function (el) {
      var key = el.getAttribute('data-metric');
      el.textContent = key in text ? text[key] : '-';
    });
    document.querySelectorAll('[data-metric-sub]').forEach(function (el) {
      var key = el.getAttribute('data-metric-sub');
      if (key in sub) {
        el.textContent = sub[key];
      }
    });
  }

  function paint(d) {
    paintMetrics(d.metrics || null);
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
