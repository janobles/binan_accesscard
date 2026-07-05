# Pre-set Aid Type + Fast-Scan Distribution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a scanner set the aid type once before scanning, then log each family's handout with a single Confirm tap.

**Architecture:** Move aid-type selection out of the per-family form into a sticky "Distributing: [aid type]" selector at the top of the Scan screen. Each scan resolves the family, defaults the claimant to the head, and needs one Confirm tap. Duplicate claims (same aid type, same day) show a warning but can be overridden. Almost all changes live in one view; the only backend change adds `aid_type_id` to the history query so duplicate detection is id-based.

**Tech Stack:** CodeIgniter 4 (PHP 8.2+), SB-Admin frame, Bootstrap 5, Bootstrap Icons, vanilla JS, `html5-qrcode`.

## Global Constraints

- **No DB migration / no schema change.** Source of truth is `accesscardV14.sql`.
- **Match the SQL dump** for column names / enum / role names exactly.
- **Every family mutation writes an audit trail** — the existing `logAid()` audit path is preserved, not bypassed.
- **No new UI framework, CDN, or JS library.** Reuse SB-Admin + Bootstrap 5 + Bootstrap Icons (`bi-*`) and vanilla JS already in `scan.php`.
- **Roles:** Scan actions are Scanner/Admin/Developer only (existing `RoleAccess::requireRole`), unchanged.
- **PHP 8.2+**, respect existing strict-type / namespace conventions.
- Run `vendor/bin/phpunit` before and after changes — it must stay green.

---

### Task 1: Expose `aid_type_id` in per-QR history (backend, id-based duplicate detection)

Aid-type **names are not unique** (`AidTypeModel::create()` has no uniqueness check and the `aid_type` table has no unique index). Client-side duplicate detection must therefore compare the pre-set aid type's **id**, not its name. `historyFor()` currently returns the joined `name` only; add the id.

**Files:**
- Modify: `app/Models/Scanner/AidDistributionModel.php` (`historyFor()`, around lines 53-62)

**Interfaces:**
- Consumes: nothing new.
- Produces: `historyFor(int $controlNo)` rows now include `aid_type_id` (int) in addition to the existing `aidID`, `claim_date`, `aid_type`, `claimant`. `ScanController::lookup()` and `logAid()` already return `historyFor()` output verbatim in their JSON `history` field, so the new key flows to the client with no controller change.

- [ ] **Step 1: Add `aid_type_id` to the `historyFor` select**

In `app/Models/Scanner/AidDistributionModel.php`, change the `select(...)` in `historyFor()` from:

```php
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . " aid_type.name AS aid_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
```

to:

```php
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . ' aid_distribution.aid_type_id,'
                    . " aid_type.name AS aid_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
```

Leave the joins, `where`, and `orderBy` clauses untouched.

- [ ] **Step 2: Verify the suite still passes**

Run: `vendor/bin/phpunit`
Expected: PASS (same result as before the change; DB-join tests remain skipped without `sqlite3`, no test regresses).

- [ ] **Step 3: Sanity-check the query column**

Run: `php -l app/Models/Scanner/AidDistributionModel.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Scanner/AidDistributionModel.php
git commit -m "feat(scanner): expose aid_type_id in per-QR history for duplicate detection"
```

---

### Task 2: Sticky pre-set aid-type selector + fast-scan Confirm flow (view)

Rework `Scanner/scan.php`: pre-set aid type at the top, remove the per-scan aid-type dropdown, default the claimant to the head, replace "Log Distribution" with a one-tap Confirm, add an id-based duplicate warning, and reset+refocus after each log.

**Files:**
- Modify: `app/Views/Scanner/scan.php` (markup in the `content` section, JS in the `scripts` section)

**Interfaces:**
- Consumes: `$aidTypes` (already passed by `ScanController::scan()`), each row `['aid_type_id' => int, 'name' => string]`. The `scanner/lookup/{num}` JSON `history` rows now carry `aid_type_id` (Task 1). `scanner/log` POST contract is unchanged: `control_no`, `memberID`, `aid_type_id`, `claim_date`.
- Produces: nothing consumed by later tasks (final task).

- [ ] **Step 1: Add the sticky pre-set aid-type card and move the input under it**

Replace the first card (the "Scan or enter QR control number" card) with a setup card that carries the aid-type selector, then the control input:

```php
<div class="card border-0 shadow-sm rounded-3 mb-3">
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
```

- [ ] **Step 2: Remove the per-scan aid-type dropdown and add the duplicate banner + Confirm button**

In the `#logPanel` form, delete the entire aid-type `<div class="mb-3">…</div>` block (the `<label for="aid_type_id">` + its `<select id="aid_type_id">`). Add a hidden `aid_type_id` input, a duplicate warning banner above the fields, and change the submit button. The form becomes:

```php
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
          <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Confirm
        </button>
      </form>
```

- [ ] **Step 3: Guard lookup on a chosen aid type**

At the top of `lookup(control)` (before the `fetch`), block when no aid type is set:

```javascript
async function lookup(control) {
  if (!$('sessionAidType').value) {
    $('aidTypeHint').hidden = false;
    $('sessionAidType').focus();
    return;
  }
  $('aidTypeHint').hidden = true;
  $('lookupAlert').hidden = true;
```

- [ ] **Step 4: Default the claimant to the head**

After the block that builds `#memberID` options, auto-select the head:

```javascript
  $('memberID').innerHTML = '<option value="">-- Select claimant --</option>' +
    data.members.map(m => `<option value="${esc(m.memberID)}">${esc(m.firstname)} ${esc(m.lastname)} (${esc(m.relationship || 'Member')})</option>`).join('');
  $('memberID').value = String(data.head.memberID);
```

- [ ] **Step 5: Run id-based duplicate detection on lookup**

After `$('control_no').value = data.control_no;` and before `$('familyPanel').hidden = false;`, set the hidden aid-type field and evaluate duplicates:

```javascript
  $('aid_type_id').value = $('sessionAidType').value;
  evaluateDuplicate(data.history);
```

Add the helper (near `renderHistory`):

```javascript
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
```

The existing `claim_date` default block can reuse `todayStr()`.

- [ ] **Step 6: Reset and refocus after a successful log**

Replace the success branch of the `logForm` submit handler so it surfaces success on the always-visible `#lookupAlert`, clears the panel, and refocuses:

```javascript
    $('lookupAlert').className = 'alert alert-success mt-2 mb-0';
    $('lookupAlert').textContent = 'Distribution logged successfully.';
    $('lookupAlert').hidden = false;
    $('familyPanel').hidden = true;
    $('controlInput').value = '';
    $('controlInput').focus();
    setTimeout(() => {
      $('lookupAlert').hidden = true;
      $('lookupAlert').className = 'alert alert-warning mt-2 mb-0';
    }, 2500);
```

- [ ] **Step 7: Confirm duplicate styling resets per lookup**

`evaluateDuplicate` runs every lookup and sets the button class in both branches — no stale warning leaks. Confirmation checkpoint, no code change.

- [ ] **Step 8: Lint the view**

Run: `php -l app/Views/Scanner/scan.php`
Expected: `No syntax errors detected`.

- [ ] **Step 9: Confirm routes still resolve**

Run: `php spark routes`
Expected: `scanner/scan`, `scanner/lookup/(:num)`, `scanner/log` still resolve to `Scanner\ScanController` (no route change made).

- [ ] **Step 10: Manual smoke test**

Start the dev server and:
1. Login as Scanner → Scan tab.
2. Without an aid type, click Go/camera → red hint shows, no lookup.
3. Choose aid type; enter a known control number → family card, claimant defaults to head, date today, button "Confirm".
4. Click Confirm → success, panel clears, input refocuses, aid type stays. Check `aid_distribution` + `audit_trails` rows.
5. Re-scan same family + same aid type → yellow "Already claimed … today" banner, Confirm turns yellow; still logs on override.
6. Scan a different family → no stale banner, button green.
7. Change claimant to a non-head member and Confirm → logs against that member.

- [ ] **Step 11: Commit**

```bash
git add app/Views/Scanner/scan.php
git commit -m "feat(scanner): pre-set aid type with one-tap confirm fast-scan flow"
```
