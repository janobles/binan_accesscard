<?php
/**
 * Scanner performance page (Scanner kiosk > Performance).
 *
 * Per-batch throughput figures for the operator running the kiosk, scoped by the
 * batch selector at the top. Numbers are fetched from the stats endpoint rather than
 * rendered server side, so the page can refresh without reloading mid-distribution.
 */
?>
<?= $this->extend('Scanner/kiosk-layout') ?>
<?= $this->section('content') ?>

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
        <div class="text-muted small">Last updated <span id="lastUpdated">-</span></div>
        <button class="btn btn-outline-secondary btn-sm" id="refreshNow" type="button">Refresh</button>
      </div>
    </div>
  </div>
</div>

<!-- KPI tiles: house stat-card component (same as the admin reports page). -->
<section class="reports-stats mb-3" aria-label="Kiosk performance">
  <?= view('components/stat_card', [
      'label' => 'Families served',
      'value' => (string) (int) ($mine['families'] ?? 0),
      'icon' => 'people-fill',
      'variant' => 'stat-card--records',
  ]) ?>
  <?= view('components/stat_card', [
      'label' => 'Handouts logged',
      'value' => (string) (int) ($mine['handouts'] ?? 0),
      'icon' => 'box-seam',
      'variant' => 'stat-card--members',
  ]) ?>
  <?= view('components/stat_card', [
      'label' => 'Families / hour',
      'value' => (string) (int) ($pace['perHour'] ?? 0),
      'icon' => 'speedometer2',
      'variant' => 'stat-card--sectors',
  ]) ?>
  <?= view('components/stat_card', [
      'label' => 'Busiest window',
      'value' => ($pace['busiest'] ?? '') !== '' ? $pace['busiest'] : '-',
      'icon' => 'clock-history',
      'variant' => 'stat-card--services',
  ]) ?>
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
  if (viewedScannerId !== null) { url += '?scanner=' + encodeURIComponent(viewedScannerId); }

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
    var el = document.querySelector('.' + variant + ' strong');
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
  setInterval(function () {
    if (document.visibilityState === 'visible') { poll(); }
  }, 5000);
})();
</script>
<?= $this->endSection() ?>
