<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <label for="controlInput" class="form-label fw-bold">Scan or enter QR control number</label>
    <div class="input-group">
      <input type="text" inputmode="numeric" autocomplete="off" class="form-control form-control-lg"
             id="controlInput" placeholder="e.g. 42" autofocus>
      <button class="btn btn-primary" id="lookupBtn" type="button">Go</button>
    </div>
    <button class="btn btn-outline-secondary w-100 mt-2" id="cameraBtn" type="button">
      <i class="bi bi-camera me-1" aria-hidden="true"></i> Scan with camera
    </button>
    <div id="reader" class="mt-2" hidden></div>
    <div id="lookupAlert" class="alert alert-warning mt-2 mb-0" hidden></div>
  </div>
</div>

<div id="familyPanel" hidden>
  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Family Head</div>
    <div class="card-body" id="headBody"></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Aid History</div>
    <ul class="list-group list-group-flush" id="historyList"></ul>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Log Aid Distribution</div>
    <div class="card-body">
      <form id="logForm">
        <input type="hidden" name="control_no" id="logControl">
        <div class="mb-2">
          <label class="form-label">Date</label>
          <input type="date" class="form-control" name="claim_date" id="claimDate" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Aid type</label>
          <select class="form-select" name="aid_type_id" required>
            <?php foreach ($aidTypes as $t): ?>
              <option value="<?= esc($t['aid_type_id'], 'attr') ?>"><?= esc($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Claimed by</label>
          <select class="form-select" name="memberID" id="claimantSelect" required></select>
        </div>
        <button type="submit" class="btn btn-success w-100">Log distribution</button>
        <div id="logAlert" class="alert mt-2 mb-0" hidden></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = '<?= rtrim(base_url(), '/') ?>';
const $ = (id) => document.getElementById(id);
$('claimDate').value = new Date().toISOString().slice(0, 10);

async function lookup(control) {
  $('lookupAlert').hidden = true;
  const res = await fetch(`${BASE}/scanner/lookup/${encodeURIComponent(control)}`);
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    $('lookupAlert').textContent = err.error || 'Lookup failed.';
    $('lookupAlert').hidden = false;
    $('familyPanel').hidden = true;
    return;
  }
  const data = await res.json();
  const h = data.head;
  $('headBody').innerHTML =
    `<div class="fw-bold">${h.firstname ?? ''} ${h.lastname ?? ''}</div>` +
    `<div class="text-muted small">${h.address ?? ''}</div>`;
  $('claimantSelect').innerHTML = data.members
    .map(m => `<option value="${m.memberID}">${m.firstname} ${m.lastname} (${m.relationship || 'Member'})</option>`).join('');
  renderHistory(data.history);
  $('logControl').value = data.control_no;
  $('familyPanel').hidden = false;
}

function renderHistory(rows) {
  $('historyList').innerHTML = rows.length
    ? rows.map(r => `<li class="list-group-item d-flex justify-content-between">
        <span>${r.aid_type ?? ''} - ${r.claimant ?? ''}</span><span class="text-muted">${r.claim_date}</span></li>`).join('')
    : '<li class="list-group-item text-muted">No aid received yet.</li>';
}

$('lookupBtn').addEventListener('click', () => {
  const v = $('controlInput').value.trim();
  if (v) lookup(v);
});
$('controlInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); $('lookupBtn').click(); }
});

$('logForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const res = await fetch(`${BASE}/scanner/log`, { method: 'POST', body: new FormData(e.target) });
  const data = await res.json().catch(() => ({}));
  const alert = $('logAlert');
  if (res.ok && data.ok) {
    renderHistory(data.history);
    alert.className = 'alert alert-success mt-2 mb-0';
    alert.textContent = 'Aid logged.';
  } else {
    alert.className = 'alert alert-danger mt-2 mb-0';
    alert.textContent = data.errors ? Object.values(data.errors).join(' ') : (data.error || 'Failed to log.');
  }
  alert.hidden = false;
});

let scanner;
$('cameraBtn').addEventListener('click', () => {
  const reader = $('reader');
  reader.hidden = false;
  scanner = new Html5Qrcode('reader');
  scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 200 },
    (text) => {
      scanner.stop().then(() => { reader.hidden = true; });
      $('controlInput').value = text.trim();
      lookup(text.trim());
    }, () => {});
});
</script>
<?= $this->endSection() ?>
