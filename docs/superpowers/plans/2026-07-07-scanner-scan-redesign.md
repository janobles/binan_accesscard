# Scanner Scan Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework `app/Views/Scanner/scan.php` into a responsive two-column scan workstation with a passive step indicator, fixed hardware-scanner auto-clear, empty-Enter confirm, focus guard, and a persistent success receipt.

**Architecture:** View/JS/CSS only. The ScanController JSON contract (`scanner/lookup/{n}`, `scanner/log`) is untouched. New page CSS `public/css/scanner-scan.css` registered in the `scanner` style manifest. Tests are string-assertion view tests in `tests/unit/` following `ReportsViewTest.php`.

**Tech Stack:** CodeIgniter 4.7.3 views, Bootstrap 5.3.3 (vendored), vanilla JS, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-07-scanner-scan-redesign-design.md`

## Global Constraints

- No controller/model/route/schema changes. No migrations.
- No inline `style="..."` attributes; Bootstrap utilities + page CSS only (`docs/knowledge/binan-conventions/views-bootstrap.md` Rule 4).
- New CSS goes through the `asset_styles('scanner')` manifest in `app/Helpers/asset_helper.php` — never a hand-added `<link>`.
- Aid type picker persists across scans (session behavior, by design).
- Existing `evaluateDuplicate` green/yellow Confirm semantics kept.
- Work on a feature branch off freshly synced `main` (`git fetch origin && git reset --hard origin/main` first — local main lags).

---

### Task 0: Branch setup

**Files:** none

- [ ] **Step 1: Sync main and branch**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/binan_accesscard
git fetch origin && git checkout main && git reset --hard origin/main
git checkout -b feat/scanner-scan-redesign
```

- [ ] **Step 2: Baseline tests**

Run: `vendor/bin/phpunit`
Expected: PASS (DB/session tests may skip without sqlite3 ext).

---

### Task 1: Register and create `scanner-scan.css`

**Files:**
- Modify: `app/Helpers/asset_helper.php:69-71`
- Create: `public/css/scanner-scan.css`
- Test: `tests/unit/ScanViewTest.php` (created here, extended in later tasks)

**Interfaces:**
- Produces: CSS classes `scan-steps`, `scan-step`, `scan-step--active`, `scan-step--done`, `scan-receipt`, `scan-dimmed`, `scan-history-flash` consumed by Tasks 2–4.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/ScanViewTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ScanViewTest extends CIUnitTestCase
{
    private string $html;

    protected function setUp(): void
    {
        parent::setUp();
        $this->html = file_get_contents(APPPATH . 'Views/Scanner/scan.php');
    }

    public function testScannerManifestIncludesScanCss(): void
    {
        helper('asset');
        $this->assertContains('css/scanner-scan.css', asset_styles('scanner'));
        $this->assertFileExists(FCPATH . 'css/scanner-scan.css');
    }

    public function testNoInlineStyles(): void
    {
        $this->assertStringNotContainsString('style="', $this->html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ScanViewTest`
Expected: FAIL — `css/scanner-scan.css` not in manifest.

- [ ] **Step 3: Add to manifest and create CSS**

In `app/Helpers/asset_helper.php`, change the `scanner` entry:

```php
            'scanner' => [
                'css/scanner-reports.css',
                'css/scanner-scan.css',
            ],
```

Create `public/css/scanner-scan.css`:

```css
/* Scanner scan workstation page. Loaded via asset_styles('scanner'). */

/* Passive step indicator */
.scan-steps {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.scan-step {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    background: var(--bs-secondary-bg, #e9ecef);
    color: var(--bs-secondary-color, #6c757d);
    font-size: 0.875rem;
    white-space: nowrap;
}
.scan-step .scan-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    background: var(--bs-body-bg, #fff);
    font-weight: 700;
    font-size: 0.75rem;
}
.scan-step--active {
    background: var(--bs-primary, #0d6efd);
    color: #fff;
}
.scan-step--active .scan-step-num {
    background: rgba(255, 255, 255, 0.9);
    color: var(--bs-primary, #0d6efd);
}
.scan-step--done {
    background: var(--bs-success-bg-subtle, #d1e7dd);
    color: var(--bs-success-text-emphasis, #0a3622);
}
.scan-step-sep {
    color: var(--bs-secondary-color, #adb5bd);
}

/* Post-log success receipt */
.scan-receipt {
    border-left: 0.25rem solid var(--bs-success, #198754);
}

/* Family panel dimmed after a successful log, until next scan */
.scan-dimmed {
    opacity: 0.6;
}

/* Brief highlight of the newly logged history row */
.scan-history-flash {
    animation: scanHistoryFlash 1.6s ease-out 1;
}
@keyframes scanHistoryFlash {
    from { background-color: var(--bs-success-bg-subtle, #d1e7dd); }
    to   { background-color: transparent; }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ScanViewTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Helpers/asset_helper.php public/css/scanner-scan.css tests/unit/ScanViewTest.php
git commit -m "feat(scanner): add scan page stylesheet to scanner asset manifest"
```

---

### Task 2: Responsive two-column markup + step indicator

**Files:**
- Modify: `app/Views/Scanner/scan.php:1-80` (content section only; script section unchanged in this task)
- Test: `tests/unit/ScanViewTest.php`

**Interfaces:**
- Consumes: CSS classes from Task 1.
- Produces: element IDs consumed by Task 3/4 JS: `stepAid`, `stepScan`, `stepConfirm`, `sessionAidType`, `aidTypeHint`, `controlInput`, `lookupBtn`, `cameraBtn`, `reader`, `lookupAlert`, `familyPanel`, `familyColumn`, `headBody`, `membersList`, `historyList`, `logPanel`, `logForm`, `control_no`, `aid_type_id`, `dupAlert`, `claim_date`, `memberID`, `fieldErrors`, `submitBtn`, `receiptPanel`, `receiptBody`.

- [ ] **Step 1: Extend the failing test**

Add to `tests/unit/ScanViewTest.php`:

```php
    public function testHasStepIndicator(): void
    {
        foreach (['scan-steps', 'stepAid', 'stepScan', 'stepConfirm'] as $needle) {
            $this->assertStringContainsString($needle, $this->html, "missing: {$needle}");
        }
    }

    public function testTwoColumnResponsiveGrid(): void
    {
        $this->assertStringContainsString('col-lg-7', $this->html);
        $this->assertStringContainsString('col-lg-5', $this->html);
    }

    public function testReceiptPanelPresent(): void
    {
        $this->assertStringContainsString('receiptPanel', $this->html);
        $this->assertStringContainsString('scan-receipt', $this->html);
    }

    public function testConfirmMentionsEnterKey(): void
    {
        $this->assertStringContainsString('Confirm (Enter)', $this->html);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ScanViewTest`
Expected: FAIL on the four new tests.

- [ ] **Step 3: Replace the content section of `scan.php`**

Replace everything between `<?= $this->section('content') ?>` and `<?= $this->endSection() ?>` (lines 2–80) with:

```php
<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<nav class="scan-steps" aria-label="Scan progress">
  <span class="scan-step" id="stepAid"><span class="scan-step-num">1</span> Aid type</span>
  <span class="scan-step-sep" aria-hidden="true">&rsaquo;</span>
  <span class="scan-step" id="stepScan"><span class="scan-step-num">2</span> Scan QR</span>
  <span class="scan-step-sep" aria-hidden="true">&rsaquo;</span>
  <span class="scan-step" id="stepConfirm"><span class="scan-step-num">3</span> Confirm</span>
</nav>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-3 h-100">
      <div class="card-body">
        <label for="sessionAidType" class="form-label fw-bold">
          <i class="bi bi-box-seam me-1" aria-hidden="true"></i> Aid type to distribute
        </label>
        <select class="form-select form-select-lg mb-1" id="sessionAidType">
          <option value="">-- Choose aid type before scanning --</option>
          <?php foreach ($aidTypes as $type): ?>
            <option value="<?= esc($type['aid_type_id']) ?>"><?= esc($type['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="aidTypeHint" class="small text-danger" hidden>
          Choose an aid type first, then scan.
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-3 h-100">
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
  </div>
</div>

<div id="familyPanel" hidden>
  <div class="row g-3">
    <div class="col-lg-7" id="familyColumn">
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
    </div>

    <div class="col-lg-5">
      <div class="card border-0 shadow-sm rounded-3 mb-3" id="logPanel">
        <div class="card-body">
          <div class="fw-bold mb-2">Log Distribution</div>
          <form id="logForm">
            <input type="hidden" id="control_no" name="control_no">
            <input type="hidden" id="aid_type_id" name="aid_type_id">
            <div id="dupAlert" class="alert alert-warning" hidden></div>
            <div class="mb-3">
              <label for="claim_date" class="form-label">Date</label>
              <input type="date" class="form-control" id="claim_date" name="claim_date" required>
            </div>
            <div class="mb-3">
              <label for="memberID" class="form-label">Claimant</label>
              <select class="form-select" id="memberID" name="memberID" required>
                <option value="">-- Select claimant --</option>
              </select>
            </div>
            <div id="fieldErrors" class="text-danger small mb-3"></div>
            <button class="btn btn-success w-100" id="submitBtn" type="submit">
              <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Confirm (Enter)
            </button>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-3 scan-receipt mb-3" id="receiptPanel" hidden>
        <div class="card-body">
          <div class="fw-bold text-success mb-1">
            <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> Logged
          </div>
          <div id="receiptBody"></div>
          <div class="text-muted small mt-1">Ready for next scan&hellip;</div>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="fw-bold px-3 pt-3 pb-2">Aid History</div>
        <ul class="list-group list-group-flush" id="historyList"></ul>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
```

Keep the existing `<?= $this->section('scripts') ?>` block exactly as is for now (Task 3 replaces it).

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit --filter ScanViewTest`
Expected: PASS (6 tests). Then `vendor/bin/phpunit` full suite: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Views/Scanner/scan.php tests/unit/ScanViewTest.php
git commit -m "feat(scanner): two-column responsive scan layout with step indicator"
```

---

### Task 3: Scan-loop JS — auto-clear, empty-Enter confirm, focus guard, step states

**Files:**
- Modify: `app/Views/Scanner/scan.php` (scripts section)
- Test: `tests/unit/ScanViewTest.php`

**Interfaces:**
- Consumes: element IDs from Task 2; endpoints `GET scanner/lookup/{n}`, `POST scanner/log` (unchanged).
- Produces: JS functions `lookup(control)`, `setStep(n)`, `showReceipt(text)` used within this file only.

- [ ] **Step 1: Extend the failing test**

Add to `tests/unit/ScanViewTest.php`:

```php
    public function testScanLoopJsBehaviors(): void
    {
        // clear-on-lookup, empty-Enter confirm, focus guard, step engine
        foreach (['setStep(', "requestSubmit", 'window.addEventListener(\'keydown\'', 'showReceipt('] as $needle) {
            $this->assertStringContainsString($needle, $this->html, "missing JS behavior: {$needle}");
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ScanViewTest`
Expected: FAIL — needles absent.

- [ ] **Step 3: Replace the scripts section**

Replace the whole `<?= $this->section('scripts') ?>` … `<?= $this->endSection() ?>` block with:

```php
<?= $this->section('scripts') ?>
<script>
const BASE = '<?= rtrim(base_url(), '/') ?>';
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

let lastHistory = [];
let lastLoggedAidId = null;

// Passive step indicator: 1 = pick aid type, 2 = scan, 3 = confirm.
function setStep(n) {
  const steps = [[1, 'stepAid'], [2, 'stepScan'], [3, 'stepConfirm']];
  for (const [num, id] of steps) {
    $(id).classList.toggle('scan-step--active', num === n);
    $(id).classList.toggle('scan-step--done', num < n);
  }
}

function currentStep() {
  if (!$('sessionAidType').value) return 1;
  if (!$('familyPanel').hidden) return 3;
  return 2;
}

async function lookup(control) {
  if (!$('sessionAidType').value) {
    $('aidTypeHint').hidden = false;
    $('sessionAidType').focus();
    return;
  }
  // Auto-clear: hardware scanners type code + Enter but never clear the
  // field; clearing here keeps consecutive scans from concatenating.
  $('controlInput').value = '';
  $('controlInput').focus();
  $('aidTypeHint').hidden = true;
  $('lookupAlert').hidden = true;
  $('receiptPanel').hidden = true;
  $('familyColumn')?.classList.remove('scan-dimmed');
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
  $('memberID').innerHTML = '<option value="">-- Select claimant --</option>' +
    data.members.map(m => `<option value="${esc(m.memberID)}">${esc(m.firstname)} ${esc(m.lastname)} (${esc(m.relationship || 'Member')})</option>`).join('');
  $('memberID').value = String(data.head.memberID);
  $('control_no').value = data.control_no;
  $('aid_type_id').value = $('sessionAidType').value;
  lastHistory = data.history;
  evaluateDuplicate(lastHistory);
  if (!$('claim_date').value) { $('claim_date').value = todayStr(); }
  $('familyPanel').hidden = false;
  setStep(3);
}

function lookupFailed(control, message) {
  $('lookupAlert').textContent = message;
  $('lookupAlert').hidden = false;
  $('familyPanel').hidden = true;
  // Restore the scanned value selected so the user can see/correct it;
  // the next gun scan overwrites the selection.
  $('controlInput').value = control;
  $('controlInput').select();
  setStep(2);
}

function todayStr() {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function evaluateDuplicate(history) {
  const aidId = $('sessionAidType').value;
  const aidName = $('sessionAidType').selectedOptions[0]?.text || 'this aid';
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
$('sessionAidType').addEventListener('change', () => {
  if (!$('familyPanel').hidden) {
    $('aid_type_id').value = $('sessionAidType').value;
    evaluateDuplicate(lastHistory);
  }
  setStep(currentStep());
});

// Focus guard: a stray click must never break the gun flow. Any printable
// key typed outside a form control re-arms the scan input.
window.addEventListener('keydown', (e) => {
  const t = e.target;
  const inField = t instanceof HTMLElement && ['INPUT', 'SELECT', 'TEXTAREA'].includes(t.tagName);
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
    const aidName = $('sessionAidType').selectedOptions[0]?.text || 'Aid';
    const claimant = $('memberID').selectedOptions[0]?.text || '';
    lastLoggedAidId = $('aid_type_id').value;
    showReceipt(`${aidName} → ${claimant} (Family #${$('control_no').value}), ${fd.get('claim_date')}`);
    lastHistory = data.history;
    renderHistory(data.history);
    lastLoggedAidId = null;
    $('controlInput').value = '';
    $('controlInput').focus();
    $('claim_date').value = todayStr();
    setStep(2);
  } catch (err) {
    $('fieldErrors').innerHTML = '<div>Network error. Please check your connection and try again.</div>';
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
      lookup(text.trim());
    }, () => {}).catch(() => {
      reader.hidden = true;
      $('lookupAlert').textContent = 'Camera unavailable. Check permissions or use manual entry.';
      $('lookupAlert').hidden = false;
    });
});

setStep(currentStep());
</script>
<?= $this->endSection() ?>
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit --filter ScanViewTest`
Expected: PASS (7 tests). Then full `vendor/bin/phpunit`: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Views/Scanner/scan.php tests/unit/ScanViewTest.php
git commit -m "feat(scanner): auto-clear scan loop, empty-Enter confirm, receipt, focus guard"
```

---

### Task 4: Manual smoke test + review

**Files:** none (verification)

- [ ] **Step 1: Serve and smoke-test**

Run: `php spark serve --port 8090` (use the intl-enabled `php`, not XAMPP's). Log in as a Scanner-role account, open `scanner/scan`, and verify against the spec's testing checklist:

1. No aid type → step 1 highlighted; choosing one moves highlight to step 2.
2. Type a valid control number + Enter → family loads, input is EMPTY and focused, step 3 active.
3. Immediately type another control + Enter → replaces family, no concatenation.
4. With family loaded and input empty, press bare Enter → distribution logs; receipt card shows "{aid} → {claimant} (Family #{n}), {date}"; family column dims; history shows new row with green flash; step back to 2.
5. Invalid control → alert shows, value restored and selected in input.
6. Same family + same aid type again → yellow Confirm + duplicate warning.
7. Click somewhere on the page body, then scan → input refocuses, flow unbroken.
8. Resize below 992px → single column, order: scan input → family → confirm/receipt → history.

- [ ] **Step 2: CodeRabbit review**

Run: `coderabbit review --base main --agent` (background; wait for completion). Triage per `superpowers:receiving-code-review` — verify each finding before applying.

- [ ] **Step 3: Full suite + finish**

Run: `vendor/bin/phpunit`
Expected: PASS. Then use `superpowers:finishing-a-development-branch` to merge/PR.
