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
  <div class="col-md-6">
    <div class="card border-0 rounded-3 h-100">
      <div class="card-body text-center">
        <div class="text-muted small">Families served</div>
        <div class="display-4 fw-bold" id="statFamilies"><?= (int) ($mine['families'] ?? 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 rounded-3 h-100">
      <div class="card-body text-center">
        <div class="text-muted small">Handouts logged</div>
        <div class="display-4 fw-bold" id="statHandouts"><?= (int) ($mine['handouts'] ?? 0) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="fw-bold mb-2">Handouts by aid type (this batch)</div>
    <div style="position:relative;height:240px"><canvas id="chartAidType"></canvas></div>
  </div>
</div>

<script type="application/json" id="reportsData"><?= json_encode(['aidType' => $byAidType], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var url = '<?= site_url('scanner/stats') ?>';
  function paint(d) {
    document.getElementById('statFamilies').textContent = d.families;
    document.getElementById('statHandouts').textContent = d.handouts;
    document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
  }
  function poll() { fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(function (r) { return r.json(); }).then(paint).catch(function () {}); }
  document.getElementById('refreshNow').addEventListener('click', poll);
  document.getElementById('batchSelect').addEventListener('change', function () {
    window.location.href = '<?= site_url('scanner/performance') ?>?batch=' + encodeURIComponent(this.value);
  });
  poll();
  setInterval(poll, 5000);
})();
</script>
<?= $this->endSection() ?>
