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
    <div class="card-header fw-bold">Members</div>
    <ul class="list-group list-group-flush" id="membersList"></ul>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Aid History</div>
    <ul class="list-group list-group-flush" id="historyList"></ul>
  </div>

  <a id="logDistLink" class="btn btn-success w-100 mb-3" href="#">
    <i class="bi bi-clipboard-plus me-1" aria-hidden="true"></i> Log distribution for this QR
  </a>
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
  $('logDistLink').href = `${BASE}/scanner/manage?control_no=${encodeURIComponent(data.control_no)}`;
  $('familyPanel').hidden = false;
}

function renderHistory(rows) {
  $('historyList').innerHTML = rows.length
    ? rows.map(r => `<li class="list-group-item d-flex justify-content-between">
        <span>${esc(r.aid_type)} - ${esc(r.claimant)}</span><span class="text-muted">${esc(r.claim_date)}</span></li>`).join('')
    : '<li class="list-group-item text-muted">No aid received yet.</li>';
}

$('lookupBtn').addEventListener('click', () => {
  const v = $('controlInput').value.trim();
  if (v) lookup(v);
});
$('controlInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); $('lookupBtn').click(); }
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
