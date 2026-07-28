# Family Form Uppercase (Branch 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Store worker-typed names and addresses in UPPERCASE everywhere, and render picked dropdown values as uppercase without changing what is stored.

**Architecture:** All name/address cleaning already funnels through one class, `App\Support\MemberFieldNormalizer` - both the manual Add/Edit form (`FamilyController`) and the Excel importer (`FamilyExcelImporter`) delegate to it. Changing two methods there changes both entry paths. The display layer then stops re-casing (`FamilyDataTablePresenter`), CSS renders picked values as caps, and a one-time SQL patch brings existing rows into line.

**Tech Stack:** PHP 8.2+, CodeIgniter 4, PHPUnit, MySQL, vanilla JS (no jQuery in this file).

## Global Constraints

Copied verbatim from `CLAUDE.md` and the spec. Every task's requirements implicitly include this section.

- **No migrations.** DB schema source of truth is the SQL dump (`accesscardV18.sql`). Never add migrations or alter schema in code. This branch adds a `sql/patches/` data patch, which is not a migration.
- **Dump stays V18.** No schema or seed change is triggered by this branch. If one becomes necessary, stop and flag it - do not silently cut V19.
- **PHP 8.2+.** Typed signatures everywhere; no `declare(strict_types=1)` (matches CI4 appstarter).
- **Uppercase applies to typed fields only.** Names, address, and "Other" freetext are stored UPPERCASE. Dropdown-picked values (sex, civil status, education, job, religion, relationship, suffix, barangay) keep their canonical stored form and are rendered uppercase via CSS.
- **Do not touch** `in_list[Male,Female]` rules (`FamilyController:817,826`, `MemberModel:29`), `FamilyExcelImporter:1107`, `FamilyExcelTemplate:144`, or the option lists in `FamilyProfilingFormV2` / `FamilyFormOptionsModel::getOptions()`. Changing any of these breaks saves or the import round-trip.
- **Comment style:** plain-language developer comments, no em dashes, no AI-slop phrasing.
- Run `vendor/bin/phpunit` before and after changes. DB/session tests skip without the `sqlite3` extension; that is expected.

---

### Task 1: Uppercase the normalizer

**Files:**
- Modify: `app/Support/MemberFieldNormalizer.php:62-82`
- Test: `tests/unit/MemberFieldNormalizerTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `MemberFieldNormalizer::cleanName(mixed $value): string` and `MemberFieldNormalizer::cleanAddress(mixed $value): string`, both now returning UPPERCASE. `combineAddressBarangay()` and `splitAddressBarangay()` keep their existing signatures and are unchanged.

Both methods currently end with `mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')`. Only that final call changes; the character allowlists and whitespace collapsing stay exactly as they are.

- [ ] **Step 1: Write the failing tests**

Append these two methods to `tests/unit/MemberFieldNormalizerTest.php`, inside the class:

```php
    public function testCleanNameUppercasesAndKeepsNamePunctuation(): void
    {
        $this->assertSame('DELA CRUZ', MemberFieldNormalizer::cleanName('dela cruz'));
        $this->assertSame('DELA CRUZ', MemberFieldNormalizer::cleanName('  Dela   Cruz  '));
        $this->assertSame("O'BRIEN-SANTOS JR.", MemberFieldNormalizer::cleanName("o'brien-santos jr."));
        // Digits and symbols are stripped, as before.
        $this->assertSame('JUAN', MemberFieldNormalizer::cleanName('Juan123'));
        // Enye must survive uppercasing.
        $this->assertSame('PEÑA', MemberFieldNormalizer::cleanName('peña'));
    }

    public function testCleanAddressUppercasesAndKeepsAddressPunctuation(): void
    {
        $this->assertSame('123 RIZAL ST.', MemberFieldNormalizer::cleanAddress('123 rizal st.'));
        $this->assertSame('BLK 4 LOT 12 (PHASE 1)', MemberFieldNormalizer::cleanAddress('blk 4 lot 12 (phase 1)'));
        $this->assertSame('#5 MABINI ST., PUROK 2', MemberFieldNormalizer::cleanAddress('#5 mabini st., purok 2'));
        // Odd symbols are stripped, as before.
        $this->assertSame('123 MAIN', MemberFieldNormalizer::cleanAddress('123 <Main>'));
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'testCleanName|testCleanAddress' tests/unit/MemberFieldNormalizerTest.php`

Expected: FAIL. Assertions report Title Case actual values, e.g. `Failed asserting that 'Dela Cruz' is identical to 'DELA CRUZ'`.

- [ ] **Step 3: Change the two methods**

In `app/Support/MemberFieldNormalizer.php`, in `cleanName()`, replace the return line:

```php
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
```

with:

```php
        return mb_strtoupper($value, 'UTF-8');
```

Then make the identical replacement in `cleanAddress()`. Both methods have the same final line; change both.

Update each method's docblock: replace the phrase `then applies Title Case` with `then uppercases`. In `cleanName()` the sentence becomes "collapses repeated whitespace, then uppercases."; in `cleanAddress()`, "collapses repeated whitespace, then uppercases."

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'testCleanName|testCleanAddress' tests/unit/MemberFieldNormalizerTest.php`

Expected: PASS, 2 tests.

- [ ] **Step 5: Run the full suite to find every Title Case assertion this breaks**

Run: `vendor/bin/phpunit`

Expected: FAIL. `tests/unit/FamilyExcelImporterTest.php` and `tests/unit/MemberFieldNormalizerSplitTest.php` assert `'Dela Cruz'`-style values in many places. Do not fix them yet - Task 2 does that. Record the failing test names; you will need the list.

- [ ] **Step 6: Commit**

```bash
git add app/Support/MemberFieldNormalizer.php tests/unit/MemberFieldNormalizerTest.php
git commit -m "feat: store typed names and addresses in uppercase"
```

---

### Task 2: Update the tests that assert Title Case

**Files:**
- Modify: `tests/unit/FamilyExcelImporterTest.php`
- Modify: `tests/unit/MemberFieldNormalizerSplitTest.php`

**Interfaces:**
- Consumes: `MemberFieldNormalizer::cleanName()` / `cleanAddress()` from Task 1, now uppercase.
- Produces: a green suite. No production code changes in this task.

These are assertion updates, not behavior changes. Input fixtures stay mixed-case on purpose - that is what proves normalization happens. Only *expected* values change.

- [ ] **Step 1: Run the suite and capture the failures**

Run: `vendor/bin/phpunit 2>&1 | tail -40`

Expected: FAIL, with a list of assertion mismatches showing Title Case expected vs UPPERCASE actual.

- [ ] **Step 2: Update each failing expectation**

For every failure, change only the **expected** value to uppercase. Leave input fixtures alone.

Example, in `tests/unit/FamilyExcelImporterTest.php:451`:

```php
        // before
        $this->assertStringContainsString('Juan Dela Cruz', $taken[0]['message']);
        // after
        $this->assertStringContainsString('JUAN DELA CRUZ', $taken[0]['message']);
```

Rules while editing:
- Row-builder fixtures such as `$this->headRow(3, '6001', ['lastname' => 'Dela Cruz'])` are **inputs**. Leave them as `'Dela Cruz'`.
- `$this->existingPerson('Juan', 'Dela Cruz', ...)` stands in for a row already in the DB. Leave it Title Case - name matching is case-insensitive on both sides (`FamilyExcelImporter::normalizeText`, `MemberModel` uses `LOWER(...)`), so a Title Case fixture still matching after the change is exactly the behavior you want to keep proving.
- Only change an assertion's expected string when the value passed through `cleanName()` or `cleanAddress()`.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`

Expected: PASS. If a test about duplicate detection still fails, do not "fix" it by uppercasing a fixture - that would mask a real regression. Stop and investigate.

- [ ] **Step 4: Commit**

```bash
git add tests/unit/FamilyExcelImporterTest.php tests/unit/MemberFieldNormalizerSplitTest.php
git commit -m "test: expect uppercase names and addresses"
```

---

### Task 3: Uppercase the "Other" freetext in the browser

**Files:**
- Modify: `public/assets/js/dashboard/manage-family-modal.js:71-80`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `cleanOtherValue(value)` returning an uppercase string. Call sites are unchanged.

When a worker picks "Other" for religion, job, education, civil status, or relationship, they type a value into a companion input. That is typed text, so it is stored uppercase. The function currently strips odd characters, collapses whitespace, then title-cases via a regex.

- [ ] **Step 1: Replace the title-case tail with uppercase**

Replace the whole function:

```javascript
    function cleanOtherValue(value) {
        return String(value || '')
            .replace(/[^\p{L}\p{N}\s.,'\-/&()]/gu, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .replace(/(^|[^\p{L}])(\p{L})/gu, function (match, boundary, letter) {
                return boundary + letter.toUpperCase();
            });
    }
```

with:

```javascript
    function cleanOtherValue(value) {
        return String(value || '')
            .replace(/[^\p{L}\p{N}\s.,'\-/&()]/gu, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toUpperCase();
    }
```

- [ ] **Step 2: Verify in the browser**

Start the dev server if it is down (`php spark serve`), open Manage Records, click Add, and in the head section pick Religion → "Others". Type `born again christian` into the freetext input that appears, then click away.

Expected: the value reads `BORN AGAIN CHRISTIAN`.

- [ ] **Step 3: Commit**

```bash
git add public/assets/js/dashboard/manage-family-modal.js
git commit -m "feat: uppercase other-freetext values in the family form"
```

---

### Task 4: Stop re-casing in the records table

**Files:**
- Modify: `app/Libraries/FamilyDataTablePresenter.php:35,38,57`

**Interfaces:**
- Consumes: uppercase stored values from Task 1.
- Produces: presenter output unchanged in appearance. No signature changes.

The presenter wraps stored values in `mb_strtoupper` because storage used to be Title Case. Storage is now authoritative, so the calls are redundant. Removing them means the table shows exactly what is stored, which is what makes a casing bug visible instead of hidden.

- [ ] **Step 1: Remove the three calls**

Line 35:

```php
        // before
        $nameHtml = '<span class="entity-title">' . esc(mb_strtoupper($name)) . '</span>';
        // after
        $nameHtml = '<span class="entity-title">' . esc($name) . '</span>';
```

Line 38:

```php
        // before
            $nameHtml .= '<small class="text-muted d-block">' . esc(mb_strtoupper($relationship)) . '</small>';
        // after
            $nameHtml .= '<small class="text-muted d-block">' . esc($relationship) . '</small>';
```

Line 57:

```php
        // before
            'address' => esc(mb_strtoupper((string) ($row['address'] ?? ''))),
        // after
            'address' => esc((string) ($row['address'] ?? '')),
```

Note: `$relationship` is a picked value, so after this change the table shows it in its stored canonical form (`Head`, `Child`). That is intended - the table is not the entry form, and the CSS in Task 5 is scoped to the form.

- [ ] **Step 2: Run the suite**

Run: `vendor/bin/phpunit`

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Libraries/FamilyDataTablePresenter.php
git commit -m "refactor: let stored casing drive the records table"
```

---

### Task 5: Render form values as uppercase

**Files:**
- Modify: `public/css/familymodal.css` (append near the existing form-control block around line 597)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: CSS only. No JS or PHP depends on this.

This is what makes picked values read as caps without changing what is stored. It is purely visual: `text-transform` does not alter the posted value.

- [ ] **Step 1: Add the rule**

Append to `public/css/familymodal.css`:

```css
/* Government forms are filled in capitals. Typed values are also stored uppercase
   (see MemberFieldNormalizer); picked dropdown values keep their stored form and
   are only displayed this way, so nothing here changes what gets posted. */
.family-entry-form input:not([type="checkbox"]):not([type="radio"]),
.family-entry-form select,
.family-entry-form textarea {
    text-transform: uppercase;
}
```

Do not add `::placeholder` uppercasing - placeholders are instructions to the worker, not data.

- [ ] **Step 2: Verify in the browser**

Open the Add form. Type `dela cruz` into Last Name: it displays as `DELA CRUZ`. Pick a Civil Status: it displays in caps.

Then save the record and reopen it for editing. Every dropdown must still show its selected value - not "Select". This is the round-trip check that proves picked values were left alone.

- [ ] **Step 3: Commit**

```bash
git add public/css/familymodal.css
git commit -m "style: display family form values in uppercase"
```

---

### Task 6: Write the backfill patch

**Files:**
- Create: `sql/patches/v18-uppercase-names.sql`

**Interfaces:**
- Consumes: nothing. Standalone SQL.
- Produces: a reviewed patch file. **This task does not run it against real data.**

The code change only affects records saved from now on. Without this, families entered earlier keep Title Case and the table shows mixed casing forever.

- [ ] **Step 1: Confirm the exact column names**

Run: `grep -A 20 'CREATE TABLE `member`' accesscardV18.sql`

Confirm the spelling of the name and address columns before writing anything. Do not guess from this plan - read the dump.

- [ ] **Step 2: Read the existing patch for house style**

Run: `cat sql/patches/v17-indexes.sql`

Match its header comment format and general shape.

- [ ] **Step 3: Write the patch**

Create `sql/patches/v18-uppercase-names.sql`, using the column names confirmed in Step 1:

```sql
-- v18-uppercase-names.sql
--
-- One-time data patch. Brings existing member rows in line with the uppercase
-- normalization added in MemberFieldNormalizer (names and addresses only).
--
-- Scope: worker-typed columns. Picked values (sex, civilstatus, education, job,
-- religion, relationship, suffix) are deliberately untouched, since those are
-- chosen from dropdowns and keep their canonical stored form.
--
-- Idempotent: UPPER() on already-uppercase text is a no-op, so re-running is safe.
-- NOT reversible: the original capitalization is not recorded anywhere. Take a
-- database dump before running this.

UPDATE `member`
SET `firstname`  = UPPER(`firstname`),
    `middlename` = UPPER(`middlename`),
    `lastname`   = UPPER(`lastname`),
    `address`    = UPPER(`address`);
```

- [ ] **Step 4: Verify the row counts it will affect**

Run this read-only query first and record the number:

```sql
SELECT COUNT(*) AS rows_needing_change
FROM `member`
WHERE BINARY `firstname`  <> UPPER(`firstname`)
   OR BINARY `middlename` <> UPPER(`middlename`)
   OR BINARY `lastname`   <> UPPER(`lastname`)
   OR BINARY `address`    <> UPPER(`address`);
```

- [ ] **Step 5: Test it on a copy, never on the live database**

```bash
mysqldump -u root accesscard > /tmp/accesscard-backup.sql
mysql -u root -e "CREATE DATABASE accesscard_uppercase_test"
mysql -u root accesscard_uppercase_test < /tmp/accesscard-backup.sql
mysql -u root accesscard_uppercase_test < sql/patches/v18-uppercase-names.sql
mysql -u root accesscard_uppercase_test -e "SELECT firstname, lastname, address FROM member LIMIT 10"
```

Expected: names and addresses uppercase; every other column unchanged.

Then re-run the same patch against the test database a second time and confirm it succeeds and changes nothing further, proving idempotency.

- [ ] **Step 6: Commit the patch file**

```bash
git add sql/patches/v18-uppercase-names.sql
git commit -m "chore: add uppercase backfill patch for member names and addresses"
```

- [ ] **Step 7: Stop and hand off**

Do **not** run the patch against the `accesscard` database. Report to the user: the confirmed column list, the row count from Step 4, and the result of the copy test. The user has approved the backfill in principle and asked to see the statements and counts before it runs for real.

---

### Task 7: End-to-end verification

**Files:**
- No changes. Verification only.

**Interfaces:**
- Consumes: all preceding tasks.
- Produces: evidence that the branch is complete.

Per `CLAUDE.md`, UI changes are verified against the dev server with Playwright.

- [ ] **Step 1: Run the full suite**

Run: `vendor/bin/phpunit`

Expected: PASS. DB/session tests skipping without `sqlite3` is expected and is not a failure.

- [ ] **Step 2: Confirm every route still resolves**

Run: `php spark routes`

Expected: no errors.

- [ ] **Step 3: Walk the create path**

Start the dev server, log in as `developer` / `developer123`, open Manage Records → Add. Enter a head with deliberately lowercase input: last name `dela cruz`, first name `juan`, address `123 rizal st.`, and pick a barangay, civil status, education, job, and monthly income. Save.

Expected: the record saves, and the records table shows `DELA CRUZ, JUAN` with an uppercase address.

- [ ] **Step 4: Walk the edit round-trip**

Reopen that record for editing.

Expected: name and address fields show uppercase, **and every dropdown still shows its selected value**. A dropdown reading "Select" means a picked value was uppercased somewhere it should not have been. That is a bug - find it before continuing.

- [ ] **Step 5: Check the import path still normalizes**

Import a small Excel file with mixed-case names through Manage Records → Import.

Expected: imported names land uppercase, since the importer shares `MemberFieldNormalizer`. Duplicate detection must still flag a person who already exists in the database with Title Case storage - this proves the case-insensitive matching held.

- [ ] **Step 6: Report**

Summarize for the user: suite result, the four walked paths, and the backfill status from Task 6 Step 7 (written, tested on a copy, awaiting their go-ahead).

---

## Self-Review

**Spec coverage.** Branch 1 of the spec lists: normalizer uppercase (Task 1), `cleanOtherValue` (Task 3), presenter cleanup (Task 4), CSS `text-transform` on inputs and selects (Task 5), the backfill patch (Task 6), and test updates (Task 2). Verification steps from the spec map to Task 7. The spec's "dropdown-picked values are not touched" constraint appears in Global Constraints and is checked in Task 5 Step 2 and Task 7 Step 4.

**Placeholders.** None. Every code step contains the actual before/after text.

**Type consistency.** `cleanName` and `cleanAddress` keep their `(mixed $value): string` signatures throughout; `cleanOtherValue(value)` keeps its single-argument JS signature. No task introduces a name another task does not define.

**Out of scope, deliberately:** the salary bracket semantics, age-eligibility fixes, the Bootstrap rework, and the layout changes. Those are Branches 2 and 3.
