# Issue #9 Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve all 13 items in GitHub issue #9 (post-PR#8 dead code + CodeRabbit re-review findings) on one branch, then re-review with CodeRabbit and open a PR to `main`.

**Architecture:** Surgical fixes across controllers, models, libraries, and views. No schema changes (job_queue DDL is already in `accesscardV14.sql`). Two items are won't-fix (documented). Adopt an existing orphaned view partial across three layouts.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, Bootstrap 5 + SB-Admin adapter, PHPUnit, MySQL (schema from `accesscardV14.sql`).

## Global Constraints

- **No migrations.** Schema source of truth is the SQL dump (`accesscardV14.sql`). Never add migrations or alter schema in code.
- **Match the SQL dump** for column/enum/role names. Employee accounts store as `User` role.
- **Every family mutation writes an audit trail** (`Audit/AuditTrailsModel`). Don't bypass it.
- **Controllers decide, libraries build.** View-data assembly lives in `Libraries/DashboardPageBuilder.php`.
- **PHP 8.2+.** Respect existing strict-type / namespace conventions.
- **CSS:** Prefer vendored Bootstrap + SB-Admin components; add custom CSS only where no component exists, and document it in the summary file.
- **Branch:** `chore/issue-9-cleanup` (already created off updated `main`, holds only the spec commit).
- **Run `vendor/bin/phpunit` before and after each task — the suite must stay green.**

---

### Task 1: Delete dead `family-form` asset manifest entry (P1-1)

**Files:**
- Modify: `app/Helpers/asset_helper.php:69-71`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing (removal only).

- [ ] **Step 1: Confirm still dead**

Run: `grep -rn "family-form" app/ public/ | grep "asset_styles"`
Expected: no output (no caller of `asset_styles('family-form')`).
Run: `ls public/css/familyform.css`
Expected: "No such file or directory".

- [ ] **Step 2: Remove the manifest key**

In `app/Helpers/asset_helper.php`, delete these three lines from the `asset_styles` `$manifest`:

```php
            'family-form' => [
                'css/familyform.css',
            ],
```

- [ ] **Step 3: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK (no failures).

- [ ] **Step 4: Commit**

```bash
git add app/Helpers/asset_helper.php
git commit -m "chore(assets): drop dead family-form manifest entry (#9)"
```

---

### Task 2: Fix spoofable import extension check (P2-1)

**Files:**
- Modify: `app/Controllers/Families/FamilyController.php:277`

**Interfaces:**
- Consumes: CodeIgniter `UploadedFile::guessExtension()` (server-side MIME-derived extension).
- Produces: nothing.

- [ ] **Step 1: Replace the client-extension gate**

In `app/Controllers/Families/FamilyController.php`, change:

```php
        if (strtolower((string) $file->getClientExtension()) !== 'xlsx') {
            return $this->jsonError('The file must be an .xlsx workbook saved from the template.', 422);
        }
```

to:

```php
        // guessExtension() derives the extension server-side from the file's MIME
        // type, so a renamed .exe/.php can't pass as .xlsx (getClientExtension is
        // attacker-controlled). xlsx is a zip container, so allow the zip guess too.
        $guessedExtension = strtolower((string) $file->guessExtension());

        if (! in_array($guessedExtension, ['xlsx', 'zip'], true)) {
            return $this->jsonError('The file must be an .xlsx workbook saved from the template.', 422);
        }
```

- [ ] **Step 2: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 3: Manual smoke (record in commit body if run)**

Import a real `.xlsx` via the family-entry modal → accepted. Rename a `.txt` to `.xlsx` and import → rejected with the 422 message.

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/Families/FamilyController.php
git commit -m "fix(import): validate upload via server-side guessExtension (#9)"
```

> **Note on xlsx MIME:** `guessExtension()` maps the OOXML MIME to `xlsx` on most setups, but some environments report the zip container as `zip`. Both are allowed above so legitimate templates aren't rejected; the goal is blocking executables/scripts, which guess as neither.

---

### Task 3: Audit the swallowed Throwable in `store()` (P2-2)

**Files:**
- Modify: `app/Controllers/Families/FamilyController.php:170-180` (the `store()` catch block)

**Interfaces:**
- Consumes: `FamilyController::auditSystemError(string $context, Throwable $exception): void` (existing private helper, ~line 690 — writes a `SYSTEM_ERROR` audit row, best-effort).
- Produces: nothing.

- [ ] **Step 1: Add the audit call for the non-domain exception**

In `store()`, the current catch is:

```php
        } catch (Throwable $exception) {
            $memberModel->rollbackTransaction();

            // persistFamily can also throw beyond FamilyRecordWriteException (QR
            // assignment, audit, or an unexpected DB error). Catch them all so the
            // transaction is always rolled back and the request fails gracefully.
            return $this->storeError(
                $exception instanceof FamilyRecordWriteException
                    ? $exception->getMessage()
                    : 'The family record was not saved.'
            );
        }
```

Change it to audit the unexpected (non-domain) case, matching `import()`/`changeFamilyState()`:

```php
        } catch (Throwable $exception) {
            $memberModel->rollbackTransaction();

            // persistFamily can also throw beyond FamilyRecordWriteException (QR
            // assignment, audit, or an unexpected DB error). Catch them all so the
            // transaction is always rolled back and the request fails gracefully.
            if (! $exception instanceof FamilyRecordWriteException) {
                // Unexpected failure — record it like import()/changeFamilyState()
                // do, so silent write failures surface on the audit page.
                $this->auditSystemError('saving a family record', $exception);
            }

            return $this->storeError(
                $exception instanceof FamilyRecordWriteException
                    ? $exception->getMessage()
                    : 'The family record was not saved.'
            );
        }
```

- [ ] **Step 2: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 3: Commit**

```bash
git add app/Controllers/Families/FamilyController.php
git commit -m "fix(family): audit unexpected store() failures like import (#9)"
```

---

### Task 4: Serialize `nextServiceId()` id allocation (P2-3)

**Files:**
- Modify: `app/Models/Lookups/ServiceModel.php:198-207`

**Interfaces:**
- Consumes: CodeIgniter model `$this->db` connection (transactions + raw builder lock).
- Produces: `nextServiceId(): int` — same signature, now race-safe.

**Context:** `serviceID` is not AUTO_INCREMENT (schema is dump-sourced; can't change it). Callers assign the id explicitly, so two concurrent creates can both read the same MAX. Wrap the read in a transaction with a `FOR UPDATE` row lock so allocation serializes. The caller (`ServiceController` create flow) must run the insert inside the same transaction for the lock to hold — verify and adjust in Step 3.

- [ ] **Step 1: Add the lock to `nextServiceId()`**

Current:

```php
    public function nextServiceId(): int
    {
        if (! $this->hasTable()) {
            return 1;
        }

        $row = $this->selectMax($this->primaryKey, 'max_id')->first();

        return ((int) ($row['max_id'] ?? 0)) + 1;
    }
```

Replace with a locked read (raw query so we can append `FOR UPDATE`):

```php
    public function nextServiceId(): int
    {
        if (! $this->hasTable()) {
            return 1;
        }

        // serviceID is not AUTO_INCREMENT, so two concurrent creates could read the
        // same MAX and collide. Take a FOR UPDATE lock inside the caller's
        // transaction so allocation serializes. Requires the caller to have an open
        // transaction and to insert before committing (see ServiceController).
        $db  = $this->db;
        $sql = 'SELECT MAX(' . $db->protectIdentifiers($this->primaryKey) . ') AS max_id FROM '
            . $db->protectIdentifiers($this->table) . ' FOR UPDATE';
        $row = $db->query($sql)->getRowArray();

        return ((int) ($row['max_id'] ?? 0)) + 1;
    }
```

- [ ] **Step 2: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK. (No unit test drives concurrency; correctness is by construction. If `ServiceModel` has an existing test, confirm `nextServiceId()` still returns `max+1` on a seeded row.)

- [ ] **Step 3: Confirm the caller wraps create in a transaction**

Run: `grep -n "nextServiceId\|transBegin\|transStart\|transComplete\|beginTransaction" app/Controllers/Lookups/ServiceController.php app/Models/Lookups/ServiceModel.php`

If the create path does **not** already open a transaction around `nextServiceId()` + insert, wrap it. Minimal pattern in the create flow:

```php
        $db = \Config\Database::connect();
        $db->transStart();
        $serviceId = $serviceModel->nextServiceId();
        // ... existing insert using $serviceId ...
        $db->transComplete();
```

Keep the existing audit-trail write inside/after the transaction as it currently is. If a transaction already exists, leave it and just confirm `nextServiceId()` is called inside it.

- [ ] **Step 4: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Lookups/ServiceModel.php app/Controllers/Lookups/ServiceController.php
git commit -m "fix(services): lock nextServiceId allocation against races (#9)"
```

---

### Task 5: Fix hardcoded empty-state colspan (P2-4)

**Files:**
- Modify: `app/Views/Lookups/services.php:156`

**Interfaces:**
- Consumes: `$canManage` (bool, already defined at `services.php:20`).
- Produces: nothing.

**Context:** The table has 5 always-on columns (Name, Code, Category, Description, Status) plus an Actions column shown only when `$canManage`. The empty-state row hardcodes `colspan="6"`, which over-spans by one when Actions is hidden.

- [ ] **Step 1: Compute the colspan from `$canManage`**

Change:

```php
						<td colspan="6" class="sector-empty-state"><?= $keyword !== '' ? 'No services match your search.' : 'No service or program records found.' ?></td>
```

to:

```php
						<td colspan="<?= $canManage ? 6 : 5 ?>" class="sector-empty-state"><?= $keyword !== '' ? 'No services match your search.' : 'No service or program records found.' ?></td>
```

- [ ] **Step 2: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 3: Commit**

```bash
git add app/Views/Lookups/services.php
git commit -m "fix(services-view): colspan honors hidden Actions column (#9)"
```

---

### Task 6: Remove runtime CREATE TABLE from JobQueueModel (P2-5)

**Files:**
- Modify: `app/Models/Jobs/JobQueueModel.php:19-21` (docblock), `:42-76` (remove `ensureTable()`)
- Modify: `app/Controllers/Families/FamilyController.php:299`
- Modify: `app/Commands/QueueWork.php:76`

**Interfaces:**
- Consumes: `JobQueueModel::hasTable(): bool` (existing).
- Produces: `JobQueueModel` with no `ensureTable()` method (callers must guard on `hasTable()`).

**Context:** `job_queue` DDL already ships in `accesscardV14.sql` (line 78). The runtime `CREATE TABLE` in `ensureTable()` violates the no-migrations / dump-is-truth rule and is now redundant. Remove it; callers assume the dump created the table and fail gracefully if it's missing.

- [ ] **Step 1: Delete `ensureTable()` and fix the class docblock**

Remove the entire `ensureTable()` method (from `/** Creates \`job_queue\`... */` through its closing brace, lines ~42-76).

In the class docblock, replace:

```php
 * No CI4 migrations exist in this project (schema ships as accesscardV1.4.sql),
 * so ensureTable() creates the table on demand; sql/job_queue.sql holds the
 * canonical DDL for the schema dump.
```

with:

```php
 * No CI4 migrations exist in this project — the `job_queue` table ships in the
 * schema dump (accesscardV14.sql); sql/job_queue.sql holds the standalone DDL for
 * reference. Callers guard on hasTable() rather than creating it at runtime.
```

- [ ] **Step 2: Fix the FamilyController caller**

At `app/Controllers/Families/FamilyController.php:299`, the `import()` flow currently calls `$jobs->ensureTable();` inside the enqueue `try`. Replace the `ensureTable()` call with a guard before enqueue. Change:

```php
        $jobs = new JobQueueModel();

        try {
            $jobs->ensureTable();

            $jobId = $jobs->enqueue(
```

to:

```php
        $jobs = new JobQueueModel();

        if (! $jobs->hasTable()) {
            @unlink($storedPath);

            return $this->jsonError('The background job queue is unavailable (missing job_queue table from accesscardV14.sql).', 422);
        }

        try {
            $jobId = $jobs->enqueue(
```

- [ ] **Step 3: Fix the QueueWork caller**

At `app/Commands/QueueWork.php:76`, replace:

```php
            $model = new JobQueueModel();
            $model->ensureTable();
```

with:

```php
            $model = new JobQueueModel();

            if (! $model->hasTable()) {
                CLI::write('The job_queue table is missing (import it from accesscardV14.sql). Exiting.', 'red');

                return EXIT_ERROR;
            }
```

(Confirm `EXIT_ERROR` and `CLI` are already available in this command — CI4 commands import both. If `EXIT_ERROR` is undefined, use `EXIT_SUCCESS` to exit quietly, matching the existing "already running" early return above.)

- [ ] **Step 4: Verify no `ensureTable` references remain**

Run: `grep -rn "ensureTable" app/`
Expected: no output.

- [ ] **Step 5: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Jobs/JobQueueModel.php app/Controllers/Families/FamilyController.php app/Commands/QueueWork.php
git commit -m "refactor(jobs): drop runtime CREATE TABLE; job_queue ships in dump (#9)"
```

---

### Task 7: Investigate & dedupe `ModelQueryHelpers` trait (P2-6)

**Files:**
- Read: `app/Models/Concerns/ModelQueryHelpers.php` and the sibling traits (`LookupModelTrait`, `NormalizesIds`, `RecordStatus`, `MemberQueryFilters`, `ResolvesMemberNames`, `ResolvesSectorNames`, `ResolvesUserNames`)
- Modify: whichever files the investigation identifies (may be none)

**Interfaces:**
- Consumes: existing trait methods.
- Produces: no behavior change — only removes duplication if genuinely present.

**Context:** The finding claims `ModelQueryHelpers` duplicates logic already in the smaller traits and will drift. This is an investigate-first task; do not restructure blindly.

- [ ] **Step 1: Map the overlap**

Run: `grep -n "function " app/Models/Concerns/ModelQueryHelpers.php`
Then for each method name, run: `grep -rn "function <methodName>" app/Models/Concerns/`
Identify methods in `ModelQueryHelpers` whose body is identical (or a thin wrapper) to a method in a smaller trait.

- [ ] **Step 2: Find who uses `ModelQueryHelpers`**

Run: `grep -rn "use App\\\\Models\\\\Concerns\\\\ModelQueryHelpers\|use ModelQueryHelpers" app/`
Note every model that `use`s it.

- [ ] **Step 3: Decide and act**

- If `ModelQueryHelpers` is a **pure aggregator** (just `use`s the smaller traits, no unique logic): leave it, and add a one-line docblock stating it's an intentional aggregator. Skip to Step 5.
- If it **copies** logic that also lives in a smaller trait: delete the duplicated method(s) from `ModelQueryHelpers`, have it `use` the authoritative smaller trait instead, and confirm every consumer still resolves the same method names. Make no signature changes.

- [ ] **Step 4: Verify behavior unchanged**

Run: `vendor/bin/phpunit`
Expected: OK. Pay attention to `FamilyDataTableTest`, `SectorIdsTest`, `ViewFormatterTest` — they exercise model query paths.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Concerns/
git commit -m "refactor(models): dedupe ModelQueryHelpers against smaller traits (#9)"
```

(If Step 3 concluded "no change needed — pure aggregator", commit only the docblock note, or skip the commit and record the finding as won't-fix with the reason for the CodeRabbit triage.)

---

### Task 8: Least-privilege queue-worker docs (P2-7)

**Files:**
- Modify: `scripts/README.md`

**Interfaces:** none (docs only).

- [ ] **Step 1: Locate the SYSTEM guidance**

Run: `grep -n "SYSTEM\|schtasks\|RunLevel\|/RU\|highest" scripts/README.md`

- [ ] **Step 2: Rewrite for a least-privilege account**

Change the documented scheduled-task setup so the queue worker runs under a dedicated low-privilege service account instead of `SYSTEM`. Update any `schtasks` example to pass `/RU <serviceAccount>` (not `SYSTEM`) and note the account only needs: read/write to `writable/` and `writable/uploads/`, and network access to MySQL. Add one sentence explaining why (least privilege — the worker parses untrusted uploaded files).

- [ ] **Step 3: Commit**

```bash
git add scripts/README.md
git commit -m "docs(scripts): run queue worker under least-privilege account (#9)"
```

---

### Task 9: Fix stale `updateDeveloper()` docblock (P2-8)

**Files:**
- Modify: `app/Controllers/Accounts/ProfileController.php` (docblock above `updateDeveloper()`, ~line 63-68, and the class docblock at line 16 if it also mis-describes the flow)

**Interfaces:** none (docblock only).

**Context:** Verify the finding before editing — the method still processes an optional `new_password` (lines ~111-127) alongside username and personal fields, and the developer credentials live in `credentials.json` (`DeveloperProfile`). The docblock is "stale" only where it misstates the storage/flow. Read the method body first, then correct the docblock to match what the code actually does — do not delete accurate password wording.

- [ ] **Step 1: Read the actual flow**

Run: `sed -n '60,160p' app/Controllers/Accounts/ProfileController.php`
Confirm: what fields are updated, whether password change is still supported, and where they persist (`DeveloperProfile` / `credentials.json`).

- [ ] **Step 2: Correct the docblock to match the code**

Update the `updateDeveloper()` docblock so it accurately describes the real flow (editable username + personal fields persisted to `credentials.json` via `DeveloperProfile`, plus the optional current-password-gated password change if that is what the body does). Remove only wording that contradicts the code.

- [ ] **Step 3: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/Accounts/ProfileController.php
git commit -m "docs(profile): correct updateDeveloper docblock to match flow (#9)"
```

---

### Task 10: Escape `asset_url()` in tag attributes (P2-10)

**Files:**
- Modify: `app/Views/Auth/login.php:14`, `:24`
- Modify: `app/Views/Employee/layout.php:37`
- Modify: `app/Views/Viewer/layout.php:44`

**Interfaces:** none.

**Context:** Four `asset_url()` outputs are emitted raw into `src`/`href` attributes. Wrap each in `esc(..., 'attr')`. (The issue lists ×3; the login file has two occurrences — fix all four for consistency.)

- [ ] **Step 1: Wrap each occurrence**

`app/Views/Auth/login.php:14`:
```php
    <link rel="icon" type="image/png" href="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>">
```
`app/Views/Auth/login.php:24`:
```php
                    <img src="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>" alt="City of Binan Logo" class="login-logo">
```
`app/Views/Employee/layout.php:37`:
```php
                <img class="sidebar-brand-icon" src="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>" alt="City of Binan Logo">
```
`app/Views/Viewer/layout.php:44`:
```php
                <img class="sidebar-brand-icon" src="<?= esc(asset_url('assets/image/binan.png'), 'attr') ?>" alt="City of Binan Logo">
```

- [ ] **Step 2: Confirm no raw `asset_url(` remains in these files**

Run: `grep -rn "asset_url(" app/Views/Auth/login.php app/Views/Employee/layout.php app/Views/Viewer/layout.php | grep -v "esc("`
Expected: no output.

- [ ] **Step 3: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 4: Commit**

```bash
git add app/Views/Auth/login.php app/Views/Employee/layout.php app/Views/Viewer/layout.php
git commit -m "fix(views): escape asset_url() in img/link attributes (#9)"
```

---

### Task 11: Rename relationship label "Children" → "Child" (P2-11)

**Files:**
- Modify: `app/Models/Families/FamilyFormOptionsModel.php:37`

**Interfaces:** none (label string).

- [ ] **Step 1: Check for dependents on the exact string**

Run: `grep -rn "'Children'\|\"Children\"" app/ public/`
If any JS/PHP compares against the literal `Children` for this relationship, note it — the rename must stay consistent. (Relationship labels are display values; confirm no stored data keys off the literal.)

- [ ] **Step 2: Rename the label**

In `app/Models/Families/FamilyFormOptionsModel.php:37`, change `'Children',` to `'Child',`.

- [ ] **Step 3: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Families/FamilyFormOptionsModel.php
git commit -m "fix(family-form): relationship label Children -> Child (#9)"
```

---

### Task 12: Adopt `topbar-account-menu` partial across layouts (P1-2/3)

**Files:**
- Modify: `app/Views/Admin/layout.php` (inline account dropdown block, ~:89-103)
- Modify: `app/Views/Employee/layout.php` (inline account dropdown, ~:71)
- Modify: `app/Views/Viewer/layout.php` (inline account dropdown, ~:81)
- Modify: `app/Libraries/DashboardPageBuilder.php` (admin ~:172, employee ~:542, viewer ~:631 view-data arrays)
- Reference (do not change): `app/Views/Partials/topbar-account-menu.php`, `css/sb-admin-adapter.css` (`.topbar-account-*` — keep)

**Interfaces:**
- Consumes: `RoleAccess::normalizeRole(string $role): ?string` → 'Admin'|'Employee'|'Viewer'|'Developer'|'Scanner' (for the account-level label). The partial reads `$user`, `$username`, `$accountLevelLabel`, `$accountSettingsUrl` (defaults to `site_url('account/profile')`), `$accountSettingsMode` (defaults `'modal'`).
- Produces: identical account menu markup in all three layouts, driven by one partial.

**Context:** The branch kept inline dropdowns; main's richer partial (avatar + full name + account-level label) is orphaned. Decision: adopt the partial. `$user` (`currentSessionUser()`) is already passed to all three layouts; only `accountLevelLabel` is missing. Keep the `.topbar-account-*` custom CSS (no SB-Admin equivalent for the summary header) — document in the summary file.

- [ ] **Step 1: Add `accountLevelLabel` to the three view-data arrays**

In `app/Libraries/DashboardPageBuilder.php`, in each of the admin/employee/viewer data arrays (near the existing `'username' => ...` and `'user' => $this->currentSessionUser()` entries), add:

```php
            'accountLevelLabel'  => \App\Libraries\RoleAccess::normalizeRole((string) (session()->get('role') ?? '')) ?? 'Account',
```

Match the array's existing key alignment/quoting style. Do this in all three arrays (admin, employee, viewer). If `RoleAccess` is already imported at the top of the file, use the short name; otherwise use the fully-qualified name as shown.

- [ ] **Step 2: Replace the inline dropdown in Admin/layout.php**

Read the current inline block first:
Run: `sed -n '88,104p' app/Views/Admin/layout.php`

Replace the entire inline `<li class="nav-item dropdown no-arrow"> ... </li>` account block (the one containing `adminUserDropdown`) with:

```php
                    <?= view('Partials/topbar-account-menu', ['user' => $user, 'username' => $username, 'accountLevelLabel' => $accountLevelLabel]) ?>
```

- [ ] **Step 3: Replace the inline dropdown in Employee/layout.php**

Run: `sed -n '68,86p' app/Views/Employee/layout.php` to see the `employeeUserDropdown` block, then replace that whole `<li ...> ... </li>` account block with the same `view('Partials/topbar-account-menu', ...)` call as Step 2.

- [ ] **Step 4: Replace the inline dropdown in Viewer/layout.php**

Run: `sed -n '78,96p' app/Views/Viewer/layout.php` to see the `viewerUserDropdown` block, then replace that whole `<li ...> ... </li>` account block with the same `view('Partials/topbar-account-menu', ...)` call.

- [ ] **Step 5: Confirm the partial's toggle markup fits the topbar**

The partial renders `<li class="nav-item dropdown topbar-account">` with a `<button class="nav-link ... dropdown-toggle">`. Confirm it sits correctly inside each layout's topbar `<ul class="navbar-nav ...">`. Load each dashboard (admin, employee, viewer) and verify: the menu opens, shows avatar + uppercase full name + the correct account level, "Account Settings" opens the My Account modal, and "Sign Out" logs out.

- [ ] **Step 6: Verify suite green**

Run: `vendor/bin/phpunit`
Expected: OK. (Check for any test asserting the old `adminUserDropdown` id / inline labels; update it to the partial's markup if present.)

- [ ] **Step 7: Commit**

```bash
git add app/Views/Admin/layout.php app/Views/Employee/layout.php app/Views/Viewer/layout.php app/Libraries/DashboardPageBuilder.php
git commit -m "refactor(topbar): adopt shared account-menu partial across layouts (#9)"
```

---

### Task 13: Demo `card h-100` classes on stat cards (P2-9) — verify then trim

**Files:**
- Inspect: `app/Views/Admin/layout.php:138-162`, `app/Views/Viewer/layout.php:111-135`, `app/Views/Family/list.php:26`
- Modify: only those where the class is genuinely a demo leftover, not functional

**Interfaces:** none.

**Context:** The finding calls `card h-100` an SB-Admin-Pro demo leftover. But the stat cards use `stat-card ... card shadow-sm h-100 py-2`, where `h-100` gives equal-height cards in a Bootstrap row — that is functional, not demo. Do not blindly strip it. Determine whether `card` (the Bootstrap component class) is redundant given the custom `.stat-card` styling, and whether removing `card`/`h-100` changes rendering.

- [ ] **Step 1: Check what `.stat-card` provides vs the Bootstrap `.card`**

Run: `grep -n "stat-card" css/sb-admin-adapter.css css/*.css public/css/*.css 2>/dev/null | head`
Determine if `.stat-card` already sets background/border/shadow (making the `.card` class redundant) and whether height is controlled by the grid (making `h-100` load-bearing).

- [ ] **Step 2: Decide**

- If `h-100` is load-bearing for equal-height rows: **keep it**, record P2-9 as won't-fix (functional, not demo) with this reasoning for the CodeRabbit triage / summary file.
- If `card`/`h-100` is genuinely redundant with `.stat-card` and removal is visually identical: remove only the redundant tokens from the affected stat cards, keeping `stat-card`, `shadow-sm`, `py-2` as the layout intends.

- [ ] **Step 3: Verify rendering unchanged**

Load each affected dashboard and confirm the stat-card row still renders with equal heights and correct spacing.

- [ ] **Step 4: Verify suite green + commit**

Run: `vendor/bin/phpunit` → OK.
```bash
git add app/Views/
git commit -m "chore(views): trim redundant demo card classes on stat cards (#9)"
```
(If Step 2 concluded won't-fix, skip the commit and note it for the summary file.)

---

### Task 14: Write the summary file (documents won't-fix + custom-CSS keep)

**Files:**
- Create: `docs/superpowers/summaries/2026-07-06-issue-9-cleanup-summary.md` (create the `summaries/` dir if absent)

**Interfaces:** none.

- [ ] **Step 1: Write the summary**

Document, concisely:
- **Custom CSS kept:** `.topbar-account-*` in `css/sb-admin-adapter.css` — the account-menu summary header (avatar + name block) has no SB-Admin/Bootstrap component equivalent, so the custom CSS is required and intentional.
- **Won't-fix #1 — scrollTop (P2-12):** `manage-family-modal.js:805` `box.scrollTop = 0` is deliberate. It is `prefers-reduced-motion`-guarded, only fires when the suggestion set changes, and pairs with the `is-updated` flash to surface the top match. Preserving manual scroll would add state-tracking for a low-value edge; smooth-scroll would feel janky mid-typing. Kept as-is.
- **Won't-fix #2 (if applicable) — P2-9 stat-card classes:** if Task 13 kept `h-100`, record that it's functional (equal-height rows), not a demo leftover.
- **JobQueue (P2-5):** runtime `CREATE TABLE` removed; `job_queue` already ships in `accesscardV14.sql`; callers now guard on `hasTable()`.
- **P2-6 outcome:** state whether `ModelQueryHelpers` was deduped or kept as an aggregator, with the reason.

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/summaries/2026-07-06-issue-9-cleanup-summary.md
git commit -m "docs(cleanup): issue #9 fix summary + won't-fix rationale (#9)"
```

---

### Task 15: CodeRabbit re-review, full-suite gate, and PR

**Files:** none (process).

- [ ] **Step 1: Full suite green**

Run: `vendor/bin/phpunit`
Expected: OK, no failures.

- [ ] **Step 2: Confirm CodeRabbit auth**

Run: `coderabbit auth status`
Expected: signed in. (If not, ask the user to run `coderabbit auth login` in a real terminal.)

- [ ] **Step 3: Run the review (background, wait)**

Run: `coderabbit review --base main --agent`
Wait for completion (large diffs take a few minutes). Triage every finding per `superpowers:receiving-code-review` — do not blind-apply. Fix in-scope genuine bugs introduced by this branch; re-run `vendor/bin/phpunit`. Park pre-existing/out-of-scope findings back into issue #9 (or a new issue) with the PR # + branch as a receipt.

- [ ] **Step 4: Smoke-test key flows**

Login → each role redirect (admin/employee/viewer) → new topbar account menu on each → family create (audit row written) → family import of a real `.xlsx` (accepted) → service create.

- [ ] **Step 5: Push and open the PR**

```bash
git push -u origin chore/issue-9-cleanup
gh pr create --base main --title "Cleanup: resolve issue #9 (dead code + CodeRabbit re-review backlog)" --body "$(cat <<'EOF'
Resolves all 13 items in #9 — post-PR#8 dead code + CodeRabbit re-review findings.

See `docs/superpowers/specs/2026-07-06-issue-9-cleanup-design.md` and
`docs/superpowers/summaries/2026-07-06-issue-9-cleanup-summary.md` for scope,
decisions, and won't-fix rationale.

Closes #9

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 6: Update issue #9**

Check off resolved items in the issue body (mark won't-fix items with their rationale), citing the PR # as the receipt. Close #9 with `gh issue close` once the PR merges (not before).

---

## Self-Review

**Spec coverage:** All 13 issue items map to tasks — P1-1→T1, P2-1→T2, P2-2→T3, P2-3→T4, P2-4→T5, P2-5→T6, P2-6→T7, P2-7→T8, P2-8→T9, P2-10→T10, P2-11→T11, P1-2/3→T12, P2-9→T13, P2-12→T14 (documented won't-fix). Summary + review/PR in T14/T15. Covered.

**Placeholder scan:** No TBD/TODO. The two investigate-tasks (T7, T13) carry explicit decision rules and concrete grep commands, not vague "handle it" language.

**Type consistency:** `nextServiceId(): int` unchanged. `auditSystemError(string, Throwable): void` used as defined. `RoleAccess::normalizeRole(string): ?string` and the partial's variable names (`$user`, `$username`, `$accountLevelLabel`) are consistent across T12. `hasTable(): bool` used consistently in T6.
