<?php
/**
 * Scan page (Scanner kiosk > Scan), the screen the distribution kiosk sits on.
 *
 * Refuses to scan at all when no distribution batch is open, because a scan with no
 * batch to record against has nowhere to go. Built for a keyboard-wedge scanner gun
 * rather than a mouse: the input stays focused and a scan completes in one action, so
 * anything added here that steals focus breaks the whole flow. After a scan, the
 * Family Information/Audit Logs tabs (Scanner\ScanController::history()) are
 * fetched as an AJAX fragment and injected inline - same markup as the
 * standalone "View all" page, just without leaving this page.
 */
?>
<?= $this->extend('Scanner/kiosk-layout') ?>
<?= $this->section('content') ?>

<?php /* Scanners have no sidebar, so this is their only route to their own
         stats. Placed off to the side, away from the scan input and result
         area. tabindex="-1" keeps it out of the tab sequence entirely: the
         window keydown guard below treats an anchor as "already in a field"
         and won't recapture focus for the scan input on the next keystroke,
         so a gun scan fired while this link holds focus would otherwise
         vanish silently. A click/tap still works with tabindex="-1". */ ?>
<div class="text-end mb-2">
  <a href="<?= esc(site_url('scanner/performance'), 'attr') ?>" class="small" tabindex="-1">My Performance (this scanner's own stats)</a>
</div>

<?php if ($activeBatch === null): ?>
<div class="alert alert-warning" role="alert">
  No active distribution batch. Ask an administrator to start one.
</div>
<?php else: ?>

<div class="row g-3 align-items-end mb-3">
  <div class="col-12">
    <label for="controlInput" class="form-label fw-bold">Scan or enter QR control number</label>
    <div class="input-group input-group-lg">
      <input type="text" inputmode="numeric" autocomplete="off" class="form-control"
             id="controlInput" placeholder="e.g. 42" autofocus>
      <button class="btn btn-outline-secondary" id="cameraBtn" type="button" title="Scan with camera" aria-label="Scan with camera">
        <i class="bi bi-camera" aria-hidden="true"></i>
      </button>
      <button class="btn btn-primary" id="lookupBtn" type="button" aria-label="Look up control number">
        <i class="bi bi-search" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</div>
<div id="reader" class="mt-2 mb-3" hidden></div>

<div id="emptyState" class="text-center py-5">
  <i id="emptyIcon" class="bi bi-qr-code-scan display-3 text-secondary" aria-hidden="true"></i>
  <div id="emptyTitle" class="fw-bold mt-3">No family loaded</div>
  <div id="emptyText" class="text-muted small">Scan a QR card to log a distribution.</div>
</div>

<div id="familyPanel" hidden>
  <?php /* Two separate cards, not one shared box - a plain flex layout so
           neither card can be pushed past the row's own edge (unlike a
           Bootstrap .row's negative gutter margins). Family card is first in
           DOM so it stacks above the QR card on mobile (flex-column). */ ?>
  <div class="d-flex flex-column flex-lg-row gap-3">
    <div class="flex-lg-grow-1 scan-panel-family">
      <div class="card rounded-3 mb-3 h-100">
        <div class="card-header"><span id="familyPanelTitle"></span></div>
        <div class="card-body">
          <?php /* One-action result banner: scan = log. alert-success when the
                   handout was recorded, alert-danger when the family was
                   already logged in this batch. */ ?>
          <div id="resultBanner" class="alert d-none align-items-center gap-3 mb-3" role="alert" aria-live="assertive">
            <i id="resultIcon" class="bi fs-3" aria-hidden="true"></i>
            <div>
              <div id="resultTitle" class="fw-bold"></div>
              <div id="resultText" class="small"></div>
            </div>
          </div>

          <?php /* Family Information/Audit Logs tabs, fetched from
                   scanner/history/{controlNo} as an AJAX fragment and injected
                   here (Scanner\ScanController::history()'s isAJAX() branch) -
                   same markup the standalone "View all" page renders. */ ?>
          <div id="historyFragment"></div>
        </div>
      </div>
    </div>

    <div class="flex-lg-shrink-0 scan-panel-control">
      <div class="card rounded-3 mb-3 text-center h-100">
        <div class="card-header"><span>Control No</span></div>
        <div class="card-body">
          <div class="d-inline-block border rounded-3 p-3 mb-2 bg-white">
            <img id="qrImage" src="" alt="QR Code">
          </div>
          <div id="qrHeadline" class="font-monospace fw-bold fs-3 text-primary"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($activeBatch !== null): ?>
<?php /* The injected history fragment (loadHistoryFragment()) reuses the
         "View all" page's toolbar/filter-panel/table-paginate markup, so it
         needs the same JS those depend on - not part of the kiosk bundle,
         since scan.php is the only kiosk page that needs it. */ ?>
<script src="<?= esc(asset_url('assets/js/dashboard/lookup-search.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/records-filter-panel.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/table-paginate.js'), 'attr') ?>"></script>
<script>
const BASE = '<?= rtrim(base_url(), '/') ?>';
const AID_TYPE_NAME = <?= json_encode((string) $subsidyType['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const $ = (id) => document.getElementById(id);

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

// Result banner: alert-success once Log Distribution records the handout,
// alert-danger when the family was already logged in this batch.
function showBanner(logged, title, text) {
  const banner = $('resultBanner');
  banner.classList.remove('d-none', 'alert-success', 'alert-danger');
  banner.classList.add('d-flex', logged ? 'alert-success' : 'alert-danger');
  $('resultIcon').className = 'bi fs-3 ' + (logged ? 'bi-check-circle-fill' : 'bi-x-octagon-fill');
  $('resultTitle').textContent = title;
  $('resultText').textContent = text;
}

function renderFamily(data) {
  const h = data.head;
  const fullName = `${h.firstname} ${h.lastname}${h.suffix ? ' ' + h.suffix : ''}`.trim();

  if ($('familyPanelTitle')) {
    $('familyPanelTitle').textContent = `${fullName} (Family #${data.control_no})`;
  }
  if ($('qrHeadline')) {
    $('qrHeadline').textContent = data.control_no;
  }
  if ($('qrImage') && data.qr_code_image) {
    $('qrImage').src = data.qr_code_image;
    $('qrImage').style.display = 'inline-block';
  }
  loadHistoryFragment(data.control_no);
  $('emptyState').hidden = true;
  $('familyPanel').hidden = false;
}

// Fetches the same Family Information/Audit Logs tabs the standalone "View all"
// page renders (Scanner\ScanController::history()'s isAJAX() branch returns
// just the fragment, no page shell) and injects it inline. <script> tags
// don't execute via innerHTML, so they're re-created manually - same trick
// dashboard-modal-loader.js uses for its AJAX partials.
async function loadHistoryFragment(controlNo) {
  const container = $('historyFragment');
  let html;
  try {
    const res = await fetch(`${BASE}/scanner/history/${controlNo}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    html = await res.text();
  } catch (err) {
    container.innerHTML = '<div class="text-muted small">History unavailable.</div>';
    return;
  }
  container.innerHTML = html;
  container.querySelectorAll('script').forEach((oldScript) => {
    const newScript = document.createElement('script');
    Array.from(oldScript.attributes).forEach((attr) => newScript.setAttribute(attr.name, attr.value));
    newScript.textContent = oldScript.textContent;
    oldScript.replaceWith(newScript);
  });
  // These libraries bind on DOMContentLoaded, which already fired before this
  // fragment existed - re-bind explicitly against just the new content.
  if (typeof window.initLookupSearch === 'function') { window.initLookupSearch(container); }
  if (typeof window.initRecordsFilterPanel === 'function') { window.initRecordsFilterPanel(container); }
  if (typeof window.initTablePagination === 'function') { window.initTablePagination(container); }
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
    // Prefer the server-returned subsidy type: the batch (and its subsidy type) may
    // have changed since this page loaded.
    showBanner(true, 'Logged', `${data.subsidy_type_name || AID_TYPE_NAME} → ${headName} (Family #${data.control_no})`);
  } else {
    const d = data.duplicate || {};
    const by = d.scanned_by ? ` by ${d.scanned_by}` : '';
    showBanner(false, 'Duplicate Entry', `Already gave out to Family #${data.control_no} in this batch (${d.dt_created || d.claim_date || ''}${by}). Nothing was logged.`);
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
