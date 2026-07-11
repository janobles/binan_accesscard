# Manual QR Control Numbers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `qr_control` the single source of truth for control numbers so every printed card is scannable, and let staff assign/fix a head's control number from the manual Add/Edit family forms.

**Architecture:** Drop the `memberID` fallback in the card-print path (heads without a `qr_control` row are excluded from cards). Add a required "QR Number" field to the family modal; `store()` wires it through the existing `FamilyRecordWriter::persistFamily($controlNo)` param, and `update()` upserts the head's mapping via a new `QrControlModel` method. The Edit field locks (read-only, rejected server-side) once aid has been recorded under the head's current number, so history is never stranded.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, MySQL, PHPUnit.

## Global Constraints

- **No migrations / no schema changes.** Source of truth is the SQL dump. `qr_control(control_no PK, headID)` and `aid_distribution(control_no int, no FK)` already exist — use as-is.
- **Column/enum/role names match the dump exactly.**
- **Every family mutation writes an audit trail** (already done in `store()`/`update()`; do not remove).
- **Controllers decide, libraries/models build.**
- Tests follow the repo's guard style: skip when the table isn't available (`hasTable()` / `markTestSkipped`), assert pure/edge logic without requiring seeded DB rows.
- Run `vendor/bin/phpunit` before and after each task.

## File Structure

- `app/Models/Scanner/QrControlModel.php` — add `controlForHead()`, `takenByOtherHead()`, `upsertForHead()`.
- `app/Models/Scanner/AidDistributionModel.php` — add `hasClaims(int $controlNo)`.
- `app/Models/Families/MemberModel.php` — `headsForCards()`: exclude unmapped heads, drop `memberID` fallback.
- `app/Libraries/Qr/QrCardPdfGenerator.php` — remove the now-dead `?? memberID` fallbacks.
- `app/Controllers/Families/FamilyController.php` — validation + wiring in `store()`, `update()`, `renderFamilyModal()`/`familyModalUpdateData()`.
- `app/Views/Family/family-modal.php` — QR Number field (add + edit + lock state).
- `tests/unit/QrControlModelTest.php`, `AidDistributionModelTest.php` (new), `MemberHeadsForCardsTest.php` — coverage.

---

### Task 1: `QrControlModel` head-centric methods

**Files:**
- Modify: `app/Models/Scanner/QrControlModel.php`
- Test: `tests/unit/QrControlModelTest.php`

**Interfaces:**
- Produces:
  - `controlForHead(int $headId): ?int` — current control_no for a head, or null.
  - `takenByOtherHead(int $controlNo, int $headId): bool` — true if control_no belongs to a *different* head.
  - `upsertForHead(int $controlNo, int $headId): void` — insert or move the head's mapping to `$controlNo`. Throws `\RuntimeException` if `$controlNo` is taken by another head; no-op if already mapped to the same pair.

- [ ] **Step 1: Write the failing tests**

Add to `tests/unit/QrControlModelTest.php` (inside the class):

```php
    public function testControlForHeadRejectsNonPositive(): void
    {
        $this->assertNull((new QrControlModel())->controlForHead(0));
        $this->assertNull((new QrControlModel())->controlForHead(-3));
    }

    public function testTakenByOtherHeadRejectsNonPositiveControl(): void
    {
        $this->assertFalse((new QrControlModel())->takenByOtherHead(0, 5));
    }

    public function testUpsertForHeadRejectsNonPositiveArgs(): void
    {
        $model = new QrControlModel();
        // Non-positive args are a no-op (mirrors assign()); must not throw.
        $model->upsertForHead(0, 5);
        $model->upsertForHead(5, 0);
        $this->assertTrue(true);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter QrControlModelTest`
Expected: FAIL — "Call to undefined method ...controlForHead()".

- [ ] **Step 3: Implement the methods**

Add to `app/Models/Scanner/QrControlModel.php` after `headForControl()`:

```php
    /** Returns the control number currently mapped to a head, or null when unmapped. */
    public function controlForHead(int $headId): ?int
    {
        if ($headId <= 0) {
            return null;
        }

        $row = $this->where('headID', $headId)->first();

        return $row === null ? null : (int) $row['control_no'];
    }

    /** True when $controlNo is already assigned to a head other than $headId. */
    public function takenByOtherHead(int $controlNo, int $headId): bool
    {
        if ($controlNo <= 0) {
            return false;
        }

        $row = $this->where('control_no', $controlNo)->first();

        return $row !== null && (int) $row['headID'] !== $headId;
    }

    /**
     * Insert or move a head's control-number mapping to $controlNo. No-op when the
     * head already maps to $controlNo. Throws when $controlNo belongs to another head.
     */
    public function upsertForHead(int $controlNo, int $headId): void
    {
        if ($controlNo <= 0 || $headId <= 0) {
            return;
        }

        if ($this->takenByOtherHead($controlNo, $headId)) {
            throw new \RuntimeException('QR Number ' . $controlNo . ' is already assigned to another family.');
        }

        $existing = $this->controlForHead($headId);
        if ($existing === $controlNo) {
            return;
        }

        // control_no is the primary key, so a "move" is delete-then-insert of the
        // head's row (there is at most one row per head).
        if ($existing !== null) {
            $this->where('headID', $headId)->delete();
        }

        $this->insert(['control_no' => $controlNo, 'headID' => $headId]);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter QrControlModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/QrControlModel.php tests/unit/QrControlModelTest.php
git commit -m "feat(qr): add head-centric control-number lookup + upsert"
```

---

### Task 2: `AidDistributionModel::hasClaims()` (the edit lock)

**Files:**
- Modify: `app/Models/Scanner/AidDistributionModel.php`
- Test: `tests/unit/AidDistributionModelTest.php` (create)

**Interfaces:**
- Produces: `hasClaims(int $controlNo): bool` — true if any `aid_distribution` row exists for that control number. Drives the Edit-form lock.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/AidDistributionModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Scanner\AidDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidDistributionModelTest extends CIUnitTestCase
{
    public function testHasClaimsRejectsNonPositiveControl(): void
    {
        $this->assertFalse((new AidDistributionModel())->hasClaims(0));
        $this->assertFalse((new AidDistributionModel())->hasClaims(-1));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AidDistributionModelTest`
Expected: FAIL — "Call to undefined method ...hasClaims()".

- [ ] **Step 3: Implement**

Add to `app/Models/Scanner/AidDistributionModel.php` after `logAid()`:

```php
    /** True when at least one aid claim has been recorded under this control number. */
    public function hasClaims(int $controlNo): bool
    {
        if ($controlNo <= 0) {
            return false;
        }

        return $this->where('control_no', $controlNo)->countAllResults() > 0;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter AidDistributionModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidDistributionModel.php tests/unit/AidDistributionModelTest.php
git commit -m "feat(qr): add aid-claim presence check for edit lock"
```

---

### Task 3: Drop the `memberID` print fallback

**Files:**
- Modify: `app/Models/Families/MemberModel.php` (`headsForCards()`, ~line 348-422)
- Modify: `app/Libraries/Qr/QrCardPdfGenerator.php` (lines 33-34, 62-63, 119)
- Test: `tests/unit/MemberHeadsForCardsTest.php`

**Interfaces:**
- Consumes: `headsForCards()` still returns `list<array{memberID:int, controlNo:int, fullname:string, barangay:string}>`, but now only for heads that have a `qr_control` row. `controlNo` is always the real `control_no`.

- [ ] **Step 1: Write the failing test**

Add to `tests/unit/MemberHeadsForCardsTest.php`:

```php
    public function testHeadsForCardsControlNoEqualsMappedControl(): void
    {
        $model = $this->modelOrSkip();
        $heads = $model->headsForCards();
        if ($heads === []) {
            $this->markTestSkipped('No mapped heads seeded to assert against.');
        }

        foreach ($heads as $head) {
            $this->assertArrayHasKey('controlNo', $head);
            $this->assertIsInt($head['controlNo']);
            // Every returned head must resolve back through qr_control — i.e. its
            // controlNo is a real mapping, never a memberID fallback.
            $this->assertSame(
                $head['memberID'],
                (new \App\Models\Scanner\QrControlModel())->headForControl($head['controlNo']),
                'controlNo ' . $head['controlNo'] . ' must map back to its head via qr_control'
            );
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter MemberHeadsForCardsTest`
Expected: FAIL if any unmapped head is currently returned (its `controlNo` = `memberID` won't resolve). If the environment has no unmapped heads it will skip/pass — proceed anyway to make the code correct.

- [ ] **Step 3: Filter unmapped heads + drop the fallback**

In `app/Models/Families/MemberModel.php`, `headsForCards()`, after the existing `->join('qr_control qc', 'qc.headID = member.memberID', 'left')` and the `member.headID = member.memberID` where (around line 356), add:

```php
        // qr_control is the single source of truth for control numbers: a head with
        // no mapping cannot be scanned, so it is excluded from card generation
        // rather than printed with a memberID that the scanner would reject.
        $builder->where('qc.control_no IS NOT NULL', null, false);
```

Then change the return map (line 417) from:

```php
                'controlNo' => isset($row['control_no']) ? (int) $row['control_no'] : (int) $row['memberID'],
```

to:

```php
                'controlNo' => (int) $row['control_no'],
```

- [ ] **Step 4: Remove dead fallbacks in the PDF generator**

In `app/Libraries/Qr/QrCardPdfGenerator.php` replace each `$x['controlNo'] ?? $x['memberID']` with `$x['controlNo']`:

- Line 33: `$firstNo = ControlNumber::format($heads[0]['controlNo']);`
- Line 34: `$lastNo  = ControlNumber::format($heads[count($heads) - 1]['controlNo']);`
- Line 62: `$chunkFirst = ControlNumber::format($chunk[0]['controlNo']);`
- Line 63: `$chunkLast  = ControlNumber::format($chunk[count($chunk) - 1]['controlNo']);`
- Line 119: `$control = ControlNumber::format($head['controlNo']);`

- [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit --filter 'MemberHeadsForCardsTest|QrCardPdfGeneratorTest|QrCardControllerTest'`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Families/MemberModel.php app/Libraries/Qr/QrCardPdfGenerator.php tests/unit/MemberHeadsForCardsTest.php
git commit -m "fix(qr): exclude unmapped heads from cards; drop memberID fallback"
```

---

### Task 4: Manual Add — capture and assign the QR number

**Files:**
- Modify: `app/Controllers/Families/FamilyController.php` (`rulesForEntryType()` ~1162, `store()` ~46-189)
- Modify: `app/Views/Family/family-modal.php` (head pane, after the Barangay field ~line 195)

**Interfaces:**
- Consumes: `FamilyRecordWriter::persistFamily(headPayload, memberPayloads, headServiceIds, operatorUserId, ip, userAgent, auditSuffix='', controlNo=null): int` — pass `controlNo` as the 8th arg.
- Consumes: `QrControlModel::takenByOtherHead(int, int): bool`.
- Produces: POST field `qr_control_no` (digits) on both add and edit forms.

- [ ] **Step 1: Add the field to the modal head pane**

In `app/Views/Family/family-modal.php`, immediately after the Barangay `</div>` (the `col-12 col-xl-3` block ending ~line 194), add:

```php
                        <?php $qrLocked = ! empty($qrLocked ?? false); ?>
                        <div class="col-12 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadQr">QR Number</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadQr" name="qr_control_no" type="text"
                                inputmode="numeric" pattern="[0-9]*"
                                value="<?= esc($oldValue('qr_control_no'), 'attr') ?>"
                                <?= $qrLocked ? 'readonly' : 'required' ?>>
                            <?php if ($qrLocked): ?>
                                <small class="text-muted">Locked: aid already recorded under this number.</small>
                            <?php endif; ?>
                        </div>
```

- [ ] **Step 2: Add the validation rule**

In `rulesForEntryType()`, in the head-branch return array (the `return $rules + [ ... ];` for heads, ~line 1179), add:

```php
            'qr_control_no' => 'required|is_natural_no_zero',
```

- [ ] **Step 3: Wire the control number through `store()`**

In `store()`, after the `$userId = (int) session()->get('user_id');` line (~135) add:

```php
        $controlNo = (int) $this->request->getPost('qr_control_no');

        if (model(\App\Models\Scanner\QrControlModel::class)->takenByOtherHead($controlNo, 0)) {
            return $this->storeError('QR Number ' . $controlNo . ' is already assigned to another family.');
        }
```

Then change the `persistFamily(...)` call (~162-169) to pass the audit suffix and control number:

```php
            $writer->persistFamily(
                $this->memberPayload('head_'),
                $memberPayloads,
                array_map('intval', $serviceIds),
                $userId,
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString(),
                '',
                $controlNo
            );
```

(`takenByOtherHead($controlNo, 0)` — headID 0 never owns a row, so this is a plain "is this number already used" check for the new family.)

- [ ] **Step 4: Manual verification**

Run: `php spark serve` (use the intl-enabled `php`, not XAMPP's — see memory), then:
1. Login, Manage Records → Add.
2. Save a family with QR Number 900001.
3. Confirm no error; the head appears in the list.
4. Scanner → Scan / lookup 900001 → resolves to that family.
5. Add another family reusing 900001 → inline error "already assigned to another family."

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Families/FamilyController.php app/Views/Family/family-modal.php
git commit -m "feat(family): require + assign QR control number on manual add"
```

---

### Task 5: Manual Edit — prefill, upsert, and lock

**Files:**
- Modify: `app/Controllers/Families/FamilyController.php` (`familyModalUpdateData()` ~1620, `renderFamilyModal()` update branch ~1580, `update()` ~488-570)

**Interfaces:**
- Consumes: `QrControlModel::controlForHead(int): ?int`, `QrControlModel::upsertForHead(int, int): void`, `AidDistributionModel::hasClaims(int): bool`.
- Produces: view vars `qr_control_no` (in `formValues`) and `qrLocked` (bool) for the Update modal.

- [ ] **Step 1: Prefill the current number + compute the lock**

In `familyModalUpdateData()`, add `head_...`? No — the QR field name is `qr_control_no`. Add it to the `formValues` array (after `'head_barangay' => ...`):

```php
                'qr_control_no' => (string) (model(\App\Models\Scanner\QrControlModel::class)->controlForHead($headId) ?? ''),
```

- [ ] **Step 2: Pass the lock flag into the update view**

In `renderFamilyModal()`, update branch, compute the lock and add it to the merged array. After `[$head, $members] = $this->splitHeadAndMembers($rows, $headId);` (and the null guard), add:

```php
            $currentControl = model(\App\Models\Scanner\QrControlModel::class)->controlForHead($headId);
            $qrLocked = $currentControl !== null
                && model(\App\Models\Scanner\AidDistributionModel::class)->hasClaims($currentControl);
```

Then add `'qrLocked' => $qrLocked,` to the third array passed to `view('Family/family-modal', array_merge(... , [ ... ]))` (alongside `'submitLabel' => 'Update'`).

Also add `'qrLocked' => false,` to the **create** branch's array (alongside `'saveDisabled' => false,`) so the view var is always defined.

- [ ] **Step 3: Validate + upsert in `update()`**

In `update()`, after the `$userId = (int) session()->get('user_id');` line (~528) and before `$memberModel->beginTransaction();`, add the lock + collision guards:

```php
        $qrModel        = model(\App\Models\Scanner\QrControlModel::class);
        $currentControl = $qrModel->controlForHead($headId);
        $locked         = $currentControl !== null
            && model(\App\Models\Scanner\AidDistributionModel::class)->hasClaims($currentControl);

        // Locked heads keep their number: ignore any submitted change (defense in
        // depth in case the readonly field was tampered with).
        $controlNo = $locked ? (int) $currentControl : (int) $this->request->getPost('qr_control_no');

        if (! $locked) {
            if ($controlNo <= 0) {
                return $this->failUpdate('QR Number is required.', 422);
            }
            if ($qrModel->takenByOtherHead($controlNo, $headId)) {
                return $this->failUpdate('QR Number ' . $controlNo . ' is already assigned to another family.', 422);
            }
        }
```

Then, inside the transaction, after the head is updated (`updateHead()` succeeds, ~line 546) add the upsert so a failure rolls the transaction back:

```php
        if (! $locked) {
            try {
                $qrModel->upsertForHead($controlNo, $headId);
            } catch (\Throwable $e) {
                $memberModel->rollbackTransaction();

                return $this->failUpdate($e->getMessage(), 422);
            }
        }
```

- [ ] **Step 4: Manual verification**

Run: `php spark serve`, then:
1. Edit the MADRID-style orphan → QR Number is blank & required → enter its number → save → scanner now resolves it.
2. Edit a family, change its QR Number to one owned by another family → inline 422 "already assigned to another family."
3. Record an aid claim under a family's number (Scanner), then edit that family → QR Number field is `readonly` with the "Locked" note; submitting a tampered value leaves the number unchanged.

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS (no regressions).

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Families/FamilyController.php
git commit -m "feat(family): edit QR control number with backfill + post-claim lock"
```

---

### Task 6: Fix the stale comment + smoke the full flow

**Files:**
- Modify: `app/Controllers/Cards/QrCardController.php` (docblock lines 22-23)

- [ ] **Step 1: Correct the fossil comment**

In `app/Controllers/Cards/QrCardController.php`, replace the class docblock lines that read:

```php
 * The control number is derived from memberID (see ControlNumber), so there is
 * one card per head and no stored code. Batch generation writes ONE audit row.
```

with:

```php
 * The control number is the head's paper QR number, stored in qr_control (the
 * same source the scanner reads), so a printed card always resolves on scan.
 * Heads without a qr_control mapping are excluded from generation. Batch
 * generation writes ONE audit row.
```

- [ ] **Step 2: Full-suite + end-to-end smoke**

Run: `vendor/bin/phpunit`
Expected: PASS.

Then `php spark serve` and walk the whole loop once: Add family (QR 900002) → Generate cards (900002 appears, no orphan cards) → Scan 900002 → resolves. Confirm a head with no number never appears in a generated batch.

- [ ] **Step 3: Commit**

```bash
git add app/Controllers/Cards/QrCardController.php
git commit -m "docs(qr): correct stale control-number docblock"
```

---

## Self-Review

- **Spec coverage:** §1 print fallback → Task 3. §2 manual Add → Task 4. §3 manual Edit + backfill → Tasks 1, 5. §4 aid-history lock → Tasks 2, 5. §5 error handling (dup 422, locked read-only + server reject) → Tasks 4, 5. Stale comment → Task 6. All covered.
- **Type consistency:** `controlForHead`, `takenByOtherHead`, `upsertForHead`, `hasClaims` names are identical across Tasks 1-5. `headsForCards` returns `controlNo:int` consumed unchanged by the generator. `persistFamily`'s 8th param `controlNo` matches the wiring in Task 4.
- **Placeholder scan:** none — every code step shows exact code.
- **Note for implementer:** `model(Class::class)` is CI4's shared-instance helper (used elsewhere in this controller, e.g. `QrCardController`). `is_natural_no_zero` is a built-in CI4 rule (positive integer). The QR field lives in the shared `family-modal.php`, so it renders for both add (`required`) and edit (`required` or `readonly` via `$qrLocked`); the create branch defines `qrLocked=false` so the var is always set.
