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
        <div class="text-muted small">Last updated <span id="lastUpdated">&mdash;</span></div>
        <button class="btn btn-outline-secondary btn-sm" id="refreshNow" type="button">Refresh</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-3 h-100">
      <div class="card-body text-center">
        <div class="text-muted small">Families served</div>
        <div class="display-5 fw-bold" id="statFamilies"><?= (int) ($mine['families'] ?? 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-3 h-100">
      <div class="card-body text-center">
        <div class="text-muted small">Handouts logged</div>
        <div class="display-5 fw-bold" id="statHandouts"><?= (int) ($mine['handouts'] ?? 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-3 h-100">
      <div class="card-body text-center">
        <div class="text-muted small">Families / hour</div>
        <div class="display-5 fw-bold" id="statPerHour"><?= (int) ($pace['perHour'] ?? 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-3 h-100">
      <div class="card-body text-center">
        <div class="text-muted small">Busiest window</div>
        <div class="h3 fw-bold mb-0 mt-2" id="statBusiest"><?= esc(($pace['busiest'] ?? '') !== '' ? $pace['busiest'] : '—') ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="fw-bold mb-2">Throughput &mdash; families served per 15 minutes</div>
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

  function paint(d) {
    document.getElementById('statFamilies').textContent = d.families;
    document.getElementById('statHandouts').textContent = d.handouts;
    if (d.pace) {
      document.getElementById('statPerHour').textContent = d.pace.perHour;
      document.getElementById('statBusiest').textContent = d.pace.busiest || '—';
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
    window.location.href = '<?= site_url('scanner/performance') ?>?batch=' + encodeURIComponent(this.value);
  });
  document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
  setInterval(function () {
    if (document.visibilityState === 'visible') { poll(); }
  }, 5000);
})();
</script>
<?= $this->endSection() ?>
