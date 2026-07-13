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

<?php /* One-action result banner: green for a new QR, red for a duplicate. */ ?>
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
  <div id="emptyTitle" class="fw-bold mt-3">No QR scanned</div>
  <div id="emptyText" class="text-muted small">Scan a QR card to log a distribution.</div>
</div>

<div id="scanPanel" hidden>
  <div class="card border-0 rounded-3 mb-3 bg-white py-4 px-4">
    <div class="card-body d-flex flex-column flex-md-row align-items-center justify-content-center gap-4">
      <img id="qrImage" src="" alt="Scanned QR code" class="scanner-result-qr rounded-3 shadow-sm border bg-white" hidden>
      <div class="text-center">
        <div class="text-muted text-uppercase fw-bold mb-2 fs-4">Scanned QR Code</div>
        <div id="qrHeadline" class="scanner-result-number fw-bold text-primary mb-0"></div>
      </div>
    </div>
  </div>
  <div class="d-flex justify-content-center mt-4">
    <button type="button" class="btn btn-danger btn-lg px-5" id="voidScanBtn" hidden>
      <i class="bi bi-trash3 me-2" aria-hidden="true"></i>Void Scan
    </button>
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
let currentControlNo = null;

// Empty-state zone doubles as the error surface: idle prompt by default,
// warning icon + message when a scan fails.
function showEmpty(error) {
  $('emptyState').hidden = false;
  $('scanPanel').hidden = true;
  $('voidScanBtn').hidden = true;
  currentControlNo = null;
  $('resultBanner').classList.add('d-none');
  $('resultBanner').classList.remove('d-flex');
  if (error) {
    $('emptyIcon').className = 'bi bi-exclamation-triangle display-3 text-warning';
    $('emptyTitle').textContent = 'Scan problem';
    $('emptyText').textContent = error;
  } else {
    $('emptyIcon').className = 'bi bi-qr-code-scan display-3 text-secondary';
    $('emptyTitle').textContent = 'No QR scanned';
    $('emptyText').textContent = 'Scan a QR card to log a distribution.';
  }
}

function showBanner(kind, text) {
  const banner = $('resultBanner');
  const warning = kind === 'voided';
  const success = kind === 'logged';
  banner.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
  banner.classList.add('d-flex', success ? 'alert-success' : (warning ? 'alert-warning' : 'alert-danger'));
  $('resultIcon').className = 'bi display-6 ' + (success ? 'bi-check-circle-fill' : (warning ? 'bi-trash3-fill' : 'bi-x-octagon-fill'));
  $('resultTitle').textContent = success ? 'Logged' : (warning ? 'Scan Voided' : (kind === 'duplicate' ? $('dupTitleText').content.textContent : 'Void Failed'));
  $('resultText').textContent = text;
}

function renderScan(data) {
  currentControlNo = Number(data.control_no);
  $('qrHeadline').textContent = data.control_no;
  if (data.qr_code_image) {
    $('qrImage').src = data.qr_code_image;
    $('qrImage').hidden = false;
  }
  $('emptyState').hidden = true;
  $('scanPanel').hidden = false;
  $('voidScanBtn').hidden = false;
}

// One action: scan = log. Family encoding is not required in temporary mode.
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

  renderScan(data);
  if (data.logged) {
    showBanner('logged', `${data.aid_type_name || AID_TYPE_NAME} → QR #${data.control_no} recorded.`);
  } else {
    const d = data.duplicate || {};
    showBanner('duplicate', `QR #${data.control_no} was already recorded in this batch (${d.dt_created || d.claim_date || ''}). Nothing was logged.`);
  }
  const countEl = document.getElementById('myBatchCount');
  if (countEl && typeof data.myBatchCount === 'number') {
    countEl.textContent = String(data.myBatchCount);
  }
}

$('voidScanBtn').addEventListener('click', async () => {
  const controlNo = currentControlNo;
  if (!controlNo || !window.confirm(`Void QR #${controlNo} from the active batch?`)) {
    $('controlInput').focus();
    return;
  }

  const button = $('voidScanBtn');
  const fd = new FormData();
  fd.append('control_no', String(controlNo));
  button.disabled = true;

  try {
    const res = await fetch(`${BASE}/scanner/void`, { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok) {
      showBanner('error', data.error || Object.values(data.errors || {}).join(' ') || 'Unable to void scan.');
      return;
    }

    currentControlNo = null;
    button.hidden = true;
    showBanner('voided', `QR #${controlNo} was removed from the active batch.`);
    const countEl = document.getElementById('myBatchCount');
    if (countEl && typeof data.myBatchCount === 'number') {
      countEl.textContent = String(data.myBatchCount);
    }
  } catch (err) {
    showBanner('error', 'Network error. The scan was not voided.');
  } finally {
    button.disabled = false;
    $('controlInput').focus();
  }
});

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
