<?= $this->extend('Scanner/kiosk-layout') ?>
<?= $this->section('content') ?>

<?php if ($activeBatch === null): ?>
<div class="alert alert-warning" role="alert">
  No active distribution batch. Ask an administrator to start one.
</div>
<?php else: ?>

<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-12">
        <label for="controlInput" class="form-label fw-bold">Scan or enter QR control number</label>
        <div class="input-group input-group-lg">
          <input type="text" inputmode="numeric" autocomplete="off" class="form-control"
                 id="controlInput" placeholder="e.g. 42" autofocus>
          <button class="btn btn-outline-secondary" id="cameraBtn" type="button" title="Scan with camera" aria-label="Scan with camera">
            <i class="bi bi-camera" aria-hidden="true"></i>
          </button>
          <button class="btn btn-primary" id="lookupBtn" type="button">Go</button>
        </div>
      </div>
    </div>
    <div id="reader" class="mt-2" hidden></div>
  </div>
</div>

<?php /* One-action result banner: scan = log. alert-success when the handout
         was recorded, alert-danger when the family was already logged in
         this batch. Big text so it reads from arm's length. */ ?>
<div id="resultBanner" class="alert d-none align-items-center gap-3 fs-4 py-4" role="alert" aria-live="assertive">
  <i id="resultIcon" class="bi display-6" aria-hidden="true"></i>
  <div>
    <div id="resultTitle" class="fw-bold"></div>
    <div id="resultText" class="fs-6"></div>
  </div>
</div>
<template id="dupTitleText">Duplicate Entry</template>

<div id="emptyState" class="text-center py-5">
  <i id="emptyIcon" class="bi bi-qr-code-scan display-3 text-secondary" aria-hidden="true"></i>
  <div id="emptyTitle" class="fw-bold mt-3">No family loaded</div>
  <div id="emptyText" class="text-muted small">Scan a QR card to log a distribution.</div>
</div>

<div id="familyPanel" hidden>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 rounded-3 mb-3">
        <div class="card-body">
          <div class="fw-bold mb-2">Family Head</div>
          <div id="headBody"></div>
        </div>
      </div>
      <div class="card border-0 rounded-3 mb-3">
        <div class="d-flex justify-content-between align-items-center px-3 pt-3 pb-2">
          <span class="fw-bold">Members</span>
          <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse"
                  data-bs-target="#membersCollapse" aria-expanded="false" aria-controls="membersCollapse">
            Show members
          </button>
        </div>
        <div class="collapse" id="membersCollapse">
          <ul class="list-group list-group-flush" id="membersList"></ul>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 rounded-3 mb-3">
        <div class="fw-bold px-3 pt-3 pb-2">Aid History</div>
        <ul class="list-group list-group-flush" id="historyList"></ul>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($activeBatch !== null): ?>
<script>
const BASE = '<?= rtrim(base_url(), '/') ?>';
const AID_TYPE_NAME = <?= json_encode((string) $aidType['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// Empty-state zone doubles as the error surface: idle prompt by default,
// warning icon + message when a scan fails.
function showEmpty(error) {
  $('emptyState').hidden = false;
  $('familyPanel').hidden = true;
  $('resultBanner').classList.add('d-none');
  $('resultBanner').classList.remove('d-flex');
  if (error) {
    $('emptyIcon').className = 'bi bi-exclamation-triangle display-3 text-warning';
    $('emptyTitle').textContent = 'Scan problem';
    $('emptyText').textContent = error;
  } else {
    $('emptyIcon').className = 'bi bi-qr-code-scan display-3 text-secondary';
    $('emptyTitle').textContent = 'No family loaded';
    $('emptyText').textContent = 'Scan a QR card to log a distribution.';
  }
}

function showBanner(logged, text) {
  const banner = $('resultBanner');
  banner.classList.remove('d-none', 'alert-success', 'alert-danger');
  banner.classList.add('d-flex', logged ? 'alert-success' : 'alert-danger');
  $('resultIcon').className = 'bi display-6 ' + (logged ? 'bi-check-circle-fill' : 'bi-x-octagon-fill');
  $('resultTitle').textContent = logged ? 'Logged' : $('dupTitleText').content.textContent;
  $('resultText').textContent = text;
}

function badgeList(items) {
  return (items || []).map(b => `<span class="badge bg-light text-dark border me-1">${esc(b)}</span>`).join('');
}

function renderFamily(data) {
  const h = data.head;
  $('headBody').innerHTML =
    `<div class="fw-bold">${esc(h.firstname)} ${esc(h.lastname)}</div>` +
    `<div class="text-muted small">${esc(h.address)}</div>` +
    `<div class="mt-2">${badgeList(h.badges)}</div>`;
  $('membersList').innerHTML = data.members
    .map(m => `<li class="list-group-item">
        <div>${esc(m.firstname)} ${esc(m.lastname)} <span class="text-muted">(${esc(m.relationship || 'Member')})</span></div>
        <div class="small text-muted">${esc(m.sex || '—')} · ${esc(m.birthday || '—')}</div>
        <div class="mt-1">${badgeList(m.badges)}</div>
      </li>`).join('');
  renderHistory(data.history);
  $('emptyState').hidden = true;
  $('familyPanel').hidden = false;
}

function renderHistory(rows) {
  $('historyList').innerHTML = rows.length
    ? rows.map(r => `<li class="list-group-item d-flex justify-content-between">
        <span><span class="badge bg-light text-dark border me-1">${esc(r.aid_type)}</span>${esc(r.claimant)}</span><span class="text-muted">${esc(r.claim_date)}</span></li>`).join('')
    : '<li class="list-group-item text-muted">No aid received yet.</li>';
}

// One action: scan = log. The server resolves the family, refuses in-batch
// duplicates, and returns everything the panel needs in one round trip.
async function scanLog(control) {
  // Auto-clear: hardware scanners type code + Enter but never clear the
  // field; clearing here keeps consecutive scans from concatenating.
  $('controlInput').value = '';
  $('controlInput').focus();
  $('controlInput').classList.remove('is-invalid');
  const fd = new FormData();
  fd.append('control_no', control);
  let res, data;
  try {
    res = await fetch(`${BASE}/scanner/log`, { method: 'POST', body: fd });
    data = await res.json();
  } catch (err) {
    scanFailed(control, 'Network error. Please check your connection and try again.');
    return;
  }
  if (!res.ok) {
    scanFailed(control, data.error || Object.values(data.errors || {}).join(' ') || 'Scan failed.');
    return;
  }

  renderFamily(data);
  const headName = `${data.head.firstname} ${data.head.lastname}`;
  if (data.logged) {
    showBanner(true, `${AID_TYPE_NAME} → ${headName} (Family #${data.control_no})`);
  } else {
    const d = data.duplicate || {};
    const by = d.scanned_by ? ` by ${d.scanned_by}` : '';
    showBanner(false, `Already gave out to Family #${data.control_no} in this batch (${d.dt_created || d.claim_date || ''}${by}). Nothing was logged.`);
  }
  const countEl = document.getElementById('myBatchCount');
  if (countEl && typeof data.myBatchCount === 'number') {
    countEl.textContent = String(data.myBatchCount);
  }
}

function scanFailed(control, message) {
  showEmpty(message);
  // Restore the scanned value selected so the user can see/correct it;
  // the next gun scan overwrites the selection.
  $('controlInput').value = control;
  $('controlInput').classList.add('is-invalid');
  $('controlInput').select();
}

$('lookupBtn').addEventListener('click', () => {
  const v = $('controlInput').value.trim();
  if (v) scanLog(v);
});
$('controlInput').addEventListener('keydown', (e) => {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const v = $('controlInput').value.trim();
  if (v) scanLog(v);
});
// Focus guard: a stray click must never break the gun flow. Any printable
// key typed outside a form control re-arms the scan input.
window.addEventListener('keydown', (e) => {
  const t = e.target;
  const inField = t instanceof HTMLElement && ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A'].includes(t.tagName);
  if (!inField && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
    $('controlInput').focus();
  }
});

let scanner = null;

function stopCamera() {
  const s = scanner;
  scanner = null;
  $('cameraBtn').classList.remove('active');
  return (s ? s.stop() : Promise.resolve()).finally(() => { $('reader').hidden = true; });
}

$('cameraBtn').addEventListener('click', () => {
  if (scanner) { stopCamera(); return; }
  const reader = $('reader');
  reader.hidden = false;
  $('cameraBtn').classList.add('active');
  scanner = new Html5Qrcode('reader');
  scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } },
    (text) => {
      stopCamera();
      scanLog(text.trim());
    }, () => {}).catch(() => {
      stopCamera();
      showEmpty('Camera unavailable. Check permissions or use manual entry.');
    });
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>
