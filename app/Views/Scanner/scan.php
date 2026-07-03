<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm rounded-3 mb-3">
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
  <div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body">
      <div class="fw-bold mb-2">Family Head</div>
      <div id="headBody"></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="fw-bold px-3 pt-3 pb-2">Members</div>
    <ul class="list-group list-group-flush" id="membersList"></ul>
  </div>

  <div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="fw-bold px-3 pt-3 pb-2">Aid History</div>
    <ul class="list-group list-group-flush" id="historyList"></ul>
  </div>

  <div class="card border-0 shadow-sm rounded-3 mb-3" id="logPanel">
    <div class="card-body">
      <div class="fw-bold mb-2">Log Distribution</div>
      <div id="logAlert" class="alert alert-success mb-3" hidden></div>
      <form id="logForm">
        <input type="hidden" id="control_no" name="control_no">
        <div class="mb-3">
          <label for="claim_date" class="form-label">Date</label>
          <input type="date" class="form-control" id="claim_date" name="claim_date" required>
        </div>
        <div class="mb-3">
          <label for="aid_type_id" class="form-label">Aid Type</label>
          <select class="form-select" id="aid_type_id" name="aid_type_id" required>
            <option value="">-- Select aid type --</option>
            <?php foreach ($aidTypes as $type): ?>
              <option value="<?= esc($type['aid_type_id']) ?>"><?= esc($type['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="memberID" class="form-label">Claimant</label>
          <select class="form-select" id="memberID" name="memberID" required>
            <option value="">-- Select claimant --</option>
          </select>
        </div>
        <div id="fieldErrors" class="text-danger small mb-3"></div>
        <button class="btn btn-success w-100" id="submitBtn" type="submit">Log Distribution</button>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = '<?= rtrim(base_url(), '/') ?>';
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

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
    `<div class="fw-bold">${esc(h.firstname)} ${esc(h.lastname)}</div>` +
    `<div class="text-muted small">${esc(h.address)}</div>`;
  $('membersList').innerHTML = data.members
    .map(m => `<li class="list-group-item">
        <div>${esc(m.firstname)} ${esc(m.lastname)} <span class="text-muted">(${esc(m.relationship || 'Member')})</span></div>
        <div class="small text-muted">${esc(m.sex || '—')} · ${esc(m.birthday || '—')}</div>
      </li>`).join('');
  renderHistory(data.history);
  $('memberID').innerHTML = '<option value="">-- Select claimant --</option>' +
    data.members.map(m => `<option value="${esc(m.memberID)}">${esc(m.firstname)} ${esc(m.lastname)} (${esc(m.relationship || 'Member')})</option>`).join('');
  $('control_no').value = data.control_no;
  if (!$('claim_date').value) {
    $('claim_date').value = new Date().toISOString().slice(0, 10);
  }
  $('familyPanel').hidden = false;
}

function renderHistory(rows) {
  $('historyList').innerHTML = rows.length
    ? rows.map(r => `<li class="list-group-item d-flex justify-content-between">
        <span><span class="badge bg-light text-dark border me-1">${esc(r.aid_type)}</span>${esc(r.claimant)}</span><span class="text-muted">${esc(r.claim_date)}</span></li>`).join('')
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
  $('logAlert').hidden = true;
  $('fieldErrors').innerHTML = '';
  $('submitBtn').disabled = true;
  const fd = new FormData($('logForm'));
  try {
    const res = await fetch(`${BASE}/scanner/log`, { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok) {
      const errs = data.errors || {};
      $('fieldErrors').innerHTML = Object.values(errs).map(m => `<div>${esc(m)}</div>`).join('');
      return;
    }
    $('logAlert').textContent = 'Distribution logged successfully.';
    $('logAlert').hidden = false;
    renderHistory(data.history);
  } finally {
    $('submitBtn').disabled = false;
  }
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
