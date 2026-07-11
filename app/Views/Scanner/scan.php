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

<div id="emptyState" class="text-center py-5">
  <i id="emptyIcon" class="bi bi-qr-code-scan display-3 text-secondary" aria-hidden="true"></i>
  <div id="emptyTitle" class="fw-bold mt-3">No family loaded</div>
  <div id="emptyText" class="text-muted small">Scan a QR card to see the family and log a distribution.</div>
</div>

<div id="familyPanel" hidden>
  <div class="row g-3">
    <div class="col-lg-7" id="familyColumn">
      <div class="card border-0 rounded-3 mb-3">
        <div class="card-body">
          <div class="fw-bold mb-2">Family Head</div>
          <div id="headBody"></div>
        </div>
      </div>
      <div class="card border-0 rounded-3 mb-3">
        <div class="fw-bold px-3 pt-3 pb-2">Members</div>
        <ul class="list-group list-group-flush" id="membersList"></ul>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 rounded-3 mb-3" id="logPanel">
        <div class="card-body">
          <div class="fw-bold mb-2">Log Distribution</div>
          <form id="logForm">
            <input type="hidden" id="control_no" name="control_no">
            <div id="dupAlert" class="alert alert-warning" hidden></div>
            <div class="mb-3">
              <label for="claim_date" class="form-label">Date</label>
              <input type="date" class="form-control" id="claim_date" name="claim_date" required>
            </div>
            <div class="mb-3">
              <label for="memberID" class="form-label">Claimant</label>
              <select class="form-select" id="memberID" name="memberID" required>
                <option value="">Select claimant&hellip;</option>
              </select>
            </div>
            <div id="fieldErrors" class="text-danger small mb-3"></div>
            <button class="btn btn-success w-100" id="submitBtn" type="submit">
              <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Confirm (Enter)
            </button>
          </form>
        </div>
      </div>

      <div class="card border-0 rounded-3 scan-receipt mb-3" id="receiptPanel" hidden>
        <div class="card-body">
          <div class="fw-bold text-success mb-1">
            <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> Logged
          </div>
          <div id="receiptBody"></div>
          <div class="text-muted small mt-1">Ready for next scan&hellip;</div>
        </div>
      </div>

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
const AID_TYPE_ID = <?= (int) $aidType['aid_type_id'] ?>;
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

let lastHistory = [];
let lastLoggedAidId = null;

// Empty-state zone doubles as the error surface: idle prompt by default,
// warning icon + message when a lookup fails.
function showEmpty(error) {
  $('emptyState').hidden = false;
  $('familyPanel').hidden = true;
  if (error) {
    $('emptyIcon').className = 'bi bi-exclamation-triangle display-3 text-warning';
    $('emptyTitle').textContent = 'Scan problem';
    $('emptyText').textContent = error;
  } else {
    $('emptyIcon').className = 'bi bi-qr-code-scan display-3 text-secondary';
    $('emptyTitle').textContent = 'No family loaded';
    $('emptyText').textContent = 'Scan a QR card to see the family and log a distribution.';
  }
}

async function lookup(control) {
  // Auto-clear: hardware scanners type code + Enter but never clear the
  // field; clearing here keeps consecutive scans from concatenating.
  $('controlInput').value = '';
  $('controlInput').focus();
  $('controlInput').classList.remove('is-invalid');
  $('receiptPanel').hidden = true;
  $('familyColumn').classList.remove('scan-dimmed');
  $('logPanel').hidden = false;
  let res;
  try {
    res = await fetch(`${BASE}/scanner/lookup/${encodeURIComponent(control)}`);
  } catch (err) {
    lookupFailed(control, 'Network error. Please check your connection and try again.');
    return;
  }
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    lookupFailed(control, err.error || 'Lookup failed.');
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
  $('memberID').innerHTML = '<option value="">Select claimant&hellip;</option>' +
    data.members.map(m => `<option value="${esc(m.memberID)}">${esc(m.firstname)} ${esc(m.lastname)} (${esc(m.relationship || 'Member')})</option>`).join('');
  $('memberID').value = String(data.head.memberID);
  $('control_no').value = data.control_no;
  lastHistory = data.history;
  evaluateDuplicate(lastHistory);
  if (!$('claim_date').value) { $('claim_date').value = todayStr(); }
  $('emptyState').hidden = true;
  $('familyPanel').hidden = false;
}

function lookupFailed(control, message) {
  showEmpty(message);
  // Restore the scanned value selected so the user can see/correct it;
  // the next gun scan overwrites the selection.
  $('controlInput').value = control;
  $('controlInput').classList.add('is-invalid');
  $('controlInput').select();
}

function todayStr() {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function evaluateDuplicate(history) {
  const aidId = AID_TYPE_ID;
  const aidName = AID_TYPE_NAME;
  const dupe = (history || []).some(r =>
    String(r.aid_type_id) === String(aidId) && String(r.claim_date) === todayStr());
  if (dupe) {
    $('dupAlert').textContent = `Already claimed ${aidName} today. Confirm again only if this is correct.`;
    $('dupAlert').hidden = false;
    $('submitBtn').className = 'btn btn-warning w-100';
  } else {
    $('dupAlert').hidden = true;
    $('submitBtn').className = 'btn btn-success w-100';
  }
}

function renderHistory(rows) {
  $('historyList').innerHTML = rows.length
    ? rows.map((r, i) => `<li class="list-group-item d-flex justify-content-between${i === 0 && lastLoggedAidId !== null ? ' scan-history-flash' : ''}">
        <span><span class="badge bg-light text-dark border me-1">${esc(r.aid_type)}</span>${esc(r.claimant)}</span><span class="text-muted">${esc(r.claim_date)}</span></li>`).join('')
    : '<li class="list-group-item text-muted">No aid received yet.</li>';
}

function showReceipt(text) {
  $('receiptBody').textContent = text;
  $('receiptPanel').hidden = false;
  $('logPanel').hidden = true;
  $('familyColumn').classList.add('scan-dimmed');
}

$('lookupBtn').addEventListener('click', () => {
  const v = $('controlInput').value.trim();
  if (v) lookup(v);
});
$('controlInput').addEventListener('keydown', (e) => {
  if (e.key !== 'Enter') return;
  e.preventDefault();
  const v = $('controlInput').value.trim();
  if (v) {
    lookup(v);
  } else if (!$('familyPanel').hidden && !$('logPanel').hidden) {
    // Bare Enter on an empty input with a family loaded = confirm.
    // The gun always sends code+Enter, so this can only be a human keypress.
    $('logForm').requestSubmit();
  }
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

$('logForm').addEventListener('submit', async (e) => {
  e.preventDefault();
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
    const aidName = AID_TYPE_NAME;
    const claimant = $('memberID').selectedOptions[0]?.text || '';
    lastLoggedAidId = AID_TYPE_ID;
    showReceipt(`${aidName} → ${claimant} (Family #${$('control_no').value}), ${fd.get('claim_date')}`);
    lastHistory = data.history;
    renderHistory(data.history);
    var countEl = document.getElementById('myBatchCount');
    if (countEl && typeof data.myBatchCount === 'number') {
      countEl.textContent = String(data.myBatchCount);
    }
    lastLoggedAidId = null;
    $('controlInput').value = '';
    $('controlInput').focus();
    $('claim_date').value = todayStr();

  } catch (err) {
    $('fieldErrors').innerHTML = '<div>Network error. Please check your connection and try again.</div>';
  } finally {
    $('submitBtn').disabled = false;
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
      lookup(text.trim());
    }, () => {}).catch(() => {
      stopCamera();
      showEmpty('Camera unavailable. Check permissions or use manual entry.');
    });
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>
