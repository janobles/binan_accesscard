# Aid Type Restore + One-Action Scan + Kiosk Member Details Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebind batches/scans to `aid_type_id` (undoing the service binding), give Aid Types its own Reference Data page, make the kiosk scan a single action with success/duplicate banners, and show per-member sector/service/category badges on the kiosk.

**Architecture:** Forward-fix on the unmerged `feat/v18-batch-service` branch. The V18 dump/patch are rewritten in place (V17 already has the right batch schema). Code changes are renames/reverts in the scanner + admin distribution stack, one new reference page (`admin/aidtypes`), and a rewritten kiosk scan flow where `POST scanner/log` does lookup + duplicate check + insert in one call.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, MySQL (schema = SQL dump, NO migrations), Bootstrap 5 / SB Admin 1, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-13-aid-type-restore-one-action-scan-design.md`

## Global Constraints

- **No migrations.** Schema source of truth is `accesscardV18.sql`. Seeds never touch tables/columns.
- **Every family mutation writes an audit trail** via `Audit/AuditTrailsModel`.
- **Controllers decide, libraries build**: dashboard view data goes through `Libraries/DashboardPageBuilder.php`.
- PHP 8.2+ typed signatures, no `declare(strict_types=1)`.
- Scanner module keeps its "no-DB test posture": model methods return safe empty shapes on any DB error.
- Toolbar/UI standard: search blue, add `#198754` via `btn('add')`, valid Bootstrap only, no added per-scan keypresses on the kiosk.
- Comments: plain-language, human-reader style, no AI-slop.
- Duplicate scope: **one logged distribution per family (control_no) per batch**, regardless of date.
- One-action scan: claimant = family head, claim date = today, always. No override.
- Restore-from-git refs used below: `dc2b7cf^` (pre-branch models), `main` (pre-branch controller/routes). Both exist on this repo.
- Run tests with: `vendor/bin/phpunit` (DB/session tests skip without sqlite3 ext — that's fine).

---

### Task 1: V18 dump + patch rewritten (schema keeps aid_type)

**Files:**
- Modify: `accesscardV18.sql`
- Delete: `sql/patches/v18-batch-service.sql`
- Create: `sql/patches/v18-refdata-cleanup.sql`

**Interfaces:**
- Produces: DB schema where `distribution_batch.aid_type_id` and `aid_distribution.aid_type_id` exist (V17 shape), `aid_type` table exists with Financial/Rice/Grocery, test reference rows (services 47–48, category 8, sector 11) are gone, developer login exists.

- [ ] **Step 1: Rewrite the dump from V17**

`accesscardV18.sql` was generated from V17 by dropping `aid_type` and renaming columns. Rebuild it the correct way — start from V17 and apply ONLY the cleanup:

```bash
cp accesscardV17.sql accesscardV18.sql
```

Then edit `accesscardV18.sql`:

1. Update the header comment block (top of file) to say: V18 = V17 + test reference rows removed + developer account; batches bind `aid_type_id` (aid_type is its own reference table, unrelated to services).
2. Remove the test seed rows from the INSERT statements:
   - In `INSERT INTO sector ...` delete the row with `sectorID` 11 (`'TS'`).
   - In `INSERT INTO category ...` delete the row with `categoryID` 8 (`'TSC'`).
   - In `INSERT INTO services ...` delete rows with `serviceID` 47 and 48 (`'TS1'`, `'TSC1'`).
   Fix trailing commas/semicolons so each INSERT stays valid SQL.
3. Confirm the `users` INSERT contains the developer row (`developer`, argon2id hash, `account_level 'developer'`, `isactive 'Enable'`). V17 already has it — keep as-is.
4. Confirm `aid_type`, `distribution_batch` (with `aid_type_id` + `idx_db_aidtype`), and `aid_distribution` (with `aid_type_id` + `idx_ad_type`) are present unchanged from V17.

- [ ] **Step 2: Replace the patch**

```bash
git rm sql/patches/v18-batch-service.sql
```

Create `sql/patches/v18-refdata-cleanup.sql`:

```sql
-- V17 -> V18 in-place upgrade. The batch/scan schema is unchanged from V17
-- (batches bind aid_type_id). This patch only removes the test reference
-- rows and resets the developer login to developer/developer123.

TRUNCATE TABLE aid_distribution;
TRUNCATE TABLE distribution_batch;

DELETE FROM services WHERE serviceID IN (47, 48);
DELETE FROM category WHERE categoryID = 8;
DELETE FROM sector   WHERE sectorID = 11;

-- Databases imported before the developer-enforcement PR lack the
-- 'developer' enum value; align with the V18 dump.
ALTER TABLE users
  MODIFY account_level ENUM('viewer','scanner','administrator','developer','encoder') NOT NULL DEFAULT 'encoder';

-- Hash from: php -r "echo password_hash('developer123', PASSWORD_ARGON2ID);"
INSERT INTO users (username, password, account_level, isactive)
SELECT 'developer',
       '$argon2id$v=19$m=65536,t=4,p=1$UHVBVzJEMFV2VDNhaU5xTg$hzjRbNAe6Pw4DFwVP9VApkJtRhRfnuSHsv7laHnXHiQ',
       'developer', 'Enable'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'developer');

UPDATE users SET
  password = '$argon2id$v=19$m=65536,t=4,p=1$UHVBVzJEMFV2VDNhaU5xTg$hzjRbNAe6Pw4DFwVP9VApkJtRhRfnuSHsv7laHnXHiQ',
  account_level = 'developer',
  isactive = 'Enable'
WHERE username = 'developer';
```

(TRUNCATEs wipe demo scan data logged under service ids — approved in the spec. No FOREIGN_KEY_CHECKS toggle needed: nothing references these tables.)

- [ ] **Step 3: Reimport and verify**

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS accesscard; CREATE DATABASE accesscard;"
mysql -h 127.0.0.1 -u root accesscard < accesscardV18.sql
mysql -h 127.0.0.1 -u root accesscard -e "SHOW COLUMNS FROM distribution_batch LIKE 'aid_type_id'; SELECT COUNT(*) FROM aid_type; SELECT COUNT(*) FROM services WHERE serviceID IN (47,48);"
```

Expected: `aid_type_id` column exists, aid_type count = 3, services count = 0.

- [ ] **Step 4: Commit**

```bash
git add accesscardV18.sql sql/patches/
git commit -m "fix(db): V18 keeps aid_type binding; patch only cleans test rows"
```

---

### Task 2: Models revert to aid_type

**Files:**
- Create: `app/Models/Scanner/AidTypeModel.php` (restore from git)
- Modify: `app/Models/Scanner/DistributionBatchModel.php`
- Modify: `app/Models/Scanner/AidDistributionModel.php`
- Test: `tests/unit/AidTypeModelTest.php` (restore), `tests/unit/DistributionBatchModelTest.php`, `tests/unit/AidDistributionModelTest.php`

**Interfaces:**
- Produces:
  - `AidTypeModel::active(): array`, `all(): array`, `create(string $name): int`, `archive(int $id): bool`, `restore(int $id): bool`, `deleteIfUnused(int $id): int`
  - `DistributionBatchModel::open(string $name, int $aidTypeId, int $userId): int`; `activeBatch()`/`allBatches()` rows carry `aid_type_id` + `aid_type_name`
  - `AidDistributionModel::logAid(array $data)` expects `aid_type_id` key; `historyFor()` rows carry `aid_type` (name) instead of `service`/`service_code`; `allDistributions()` rows carry `aid_type`
  - `AidDistributionModel::inBatch(int $controlNo, int $batchId): ?array` — the per-batch duplicate probe (row with `claim_date`, `dt_created`, `scanned_by`), null when none/error

- [ ] **Step 1: Restore AidTypeModel + its test from git**

```bash
git show dc2b7cf^:app/Models/Scanner/AidTypeModel.php > app/Models/Scanner/AidTypeModel.php
git show dc2b7cf^:tests/unit/AidTypeModelTest.php > tests/unit/AidTypeModelTest.php
```

In the restored `AidTypeModel.php`, update the class docblock first line to reflect its new home (it now backs the `admin/aidtypes` reference page, not a scan-log dropdown):

```php
/**
 * Aid-type reference lookup (Financial/Rice/Grocery, admin-editable) backing
 * the admin/aidtypes page and the batch-open modal. Isolated from the
 * `services` table: aid types are their own concept, not services/programs.
 */
```

- [ ] **Step 2: Rebind DistributionBatchModel**

In `app/Models/Scanner/DistributionBatchModel.php`:

- `$allowedFields`: `['name', 'aid_type_id', 'closed_at', 'created_by']`
- Replace both service joins (in `activeBatch()` and `allBatches()`) with:

```php
$this->select('distribution_batch.*, aid_type.name AS aid_type_name')
    ->join('aid_type', 'aid_type.aid_type_id = distribution_batch.aid_type_id', 'left')
```

- `open()` signature and body:

```php
/** Opens a batch; refuses when name blank, aid type missing, or a batch is open. */
public function open(string $name, int $aidTypeId, int $userId): int
{
    $name = trim($name);
    if ($name === '' || $aidTypeId <= 0 || $this->activeBatch() !== null) {
        return 0;
    }

    try {
        if ($this->insert([
            'name'        => $name,
            'aid_type_id' => $aidTypeId,
            'created_by'  => $userId > 0 ? $userId : null,
        ]) === false) {
            return 0;
        }

        return (int) $this->getInsertID();
    } catch (\Throwable $e) {
        return 0;
    }
}
```

- Update the class docblock sentence about services binding accordingly.

- [ ] **Step 3: Rebind AidDistributionModel + add inBatch()**

In `app/Models/Scanner/AidDistributionModel.php`:

- `$allowedFields`: `['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID', 'batch_id']`
- `logAid()`: replace both `service_id` occurrences with `aid_type_id` (guard + insert array).
- `historyFor()` select/join becomes:

```php
return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
        . ' aid_distribution.aid_type_id,'
        . " aid_type.name AS aid_type,"
        . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
    ->join('aid_type', 'aid_type.aid_type_id = aid_distribution.aid_type_id', 'left')
    ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
    ->where('aid_distribution.control_no', $controlNo)
    ->orderBy('aid_distribution.claim_date', 'DESC')
    ->orderBy('aid_distribution.aidID', 'DESC')
    ->findAll();
```

- `allDistributions()`: swap the services join for the aid_type join, select `aid_type.name AS aid_type` (drop `service`/`service_code` aliases). Update the docblock.
- Add the duplicate probe:

```php
/**
 * The existing distribution row for this family in this batch, or null.
 * One family may only be logged once per batch; this is the probe the
 * scan endpoint uses to report "Duplicate Entry".
 */
public function inBatch(int $controlNo, int $batchId): ?array
{
    if ($controlNo <= 0 || $batchId <= 0) {
        return null;
    }

    try {
        $row = $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                . ' aid_distribution.dt_created,'
                . " COALESCE(users.username, '') AS scanned_by")
            ->join('users', 'users.userID = aid_distribution.userID', 'left')
            ->where('aid_distribution.control_no', $controlNo)
            ->where('aid_distribution.batch_id', $batchId)
            ->first();

        return is_array($row) ? $row : null;
    } catch (\Throwable $e) {
        return null;
    }
}
```

- [ ] **Step 4: Update the model tests**

`tests/unit/AidDistributionModelTest.php`: change the allowed-fields expectation back to `aid_type_id`:

```php
foreach (['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID'] as $col) {
```

`tests/unit/DistributionBatchModelTest.php`: read the file; wherever it asserts `service_id` in allowedFields or `open(...service...)` semantics, swap to `aid_type_id` (the test name `testOpenRequiresAidType` already matches). Follow the existing assertion style in that file.

- [ ] **Step 5: Run the unit tests**

```bash
vendor/bin/phpunit --filter 'AidTypeModelTest|AidDistributionModelTest|DistributionBatchModelTest'
```

Expected: PASS (DB-backed ones may skip without sqlite3 — source-assertion ones must pass).

- [ ] **Step 6: Commit**

```bash
git add app/Models/Scanner tests/unit
git commit -m "feat(models): restore AidTypeModel; batches and scans bind aid_type_id"
```

---

### Task 3: Admin batches/distributions pages rebind to aid type

**Files:**
- Modify: `app/Controllers/Admin/DistributionController.php`
- Modify: `app/Views/Admin/batch-create-modal.php`
- Modify: `app/Views/Admin/distribution-batches-body.php`
- Modify: `app/Views/Admin/distribution-distributions-body.php`
- Modify: `app/Libraries/DashboardPageBuilder.php`
- Modify: `app/Views/Admin/layout.php`

**Interfaces:**
- Consumes: `AidTypeModel::active()`, `DistributionBatchModel::open(string, int $aidTypeId, int)`, batch rows with `aid_type_name`, distribution rows with `aid_type`.
- Produces: view var `activeAidTypes` (list of `{aid_type_id, name}`) on the batches page; POST field `aid_type_id` on `admin/batches/open`.

- [ ] **Step 1: DistributionController::openBatch takes aid_type_id**

Replace the service lines in `openBatch()`:

```php
$aidTypeId = (int) $this->request->getPost('aid_type_id');
if ($name === '') {
    return redirect()->to('admin/batches')->with('error', 'Batch name is required.');
}
if ($aidTypeId <= 0) {
    return redirect()->to('admin/batches')->with('error', 'Choose an aid type for this batch.');
}
$batchModel = model(DistributionBatchModel::class);
$id         = $batchModel->open($name, $aidTypeId, (int) (session('user_id') ?? 0));
```

Audit line: `'Opened distribution batch "' . $name . '" #' . $id . ' (aid type ID ' . $aidTypeId . ')'`.

In `voidDistribution()`, the audit detail swaps `'service ID ' . (int) ($row['service_id'] ?? 0)` for `'aid type ID ' . (int) ($row['aid_type_id'] ?? 0)`.

Update the class docblock ("Batch open binds an aid type...").

- [ ] **Step 2: Batch modal = name + aid-type select**

Replace `app/Views/Admin/batch-create-modal.php` body (keep the modal shell, csrf, footer) with a single select — the category→service cascade and its script are deleted entirely:

```php
<?php
/**
 * New Batch modal: name + aid type pick. Aid types come from the aid_type
 * reference table (admin/aidtypes page).
 *
 * Variables:
 * - $activeAidTypes list of aid type rows (aid_type_id, name)
 */
$activeAidTypes = $activeAidTypes ?? [];
?>
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('admin/batches/open') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="newBatchModalLabel">New Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="batchName" class="form-label">Batch name</label>
          <input type="text" class="form-control" id="batchName" name="name" required maxlength="100"
                 placeholder="e.g. Relief Distribution — <?= esc(date('M j, Y')) ?>">
        </div>
        <div class="mb-3">
          <label for="batchAidType" class="form-label">Aid type</label>
          <select class="form-select" id="batchAidType" name="aid_type_id" required>
            <option value="" selected disabled>Choose an aid type...</option>
            <?php foreach ($activeAidTypes as $t): ?>
              <option value="<?= (int) $t['aid_type_id'] ?>"><?= esc($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Open Batch</button>
      </div>
    </form>
  </div>
</div>
```

- [ ] **Step 3: Batches body shows aid type**

In `app/Views/Admin/distribution-batches-body.php`:

- Replace the `$serviceLabel` closure with nothing; where it was used, print `esc((string) ($row['aid_type_name'] ?? ''))`.
- Active-batch banner badge: `<span class="badge bg-light text-dark border"><?= esc((string) ($activeBatch['aid_type_name'] ?? '')) ?></span>`; delete the category_code badge block.
- Table header: `<th>Batch</th><th>Aid Type</th><th>Started</th><th>Closed</th>` (4 columns); the row prints `$b['aid_type_name']`; empty-state colspan becomes 4.
- Update the file docblock.

- [ ] **Step 4: Distributions body shows aid type**

In `app/Views/Admin/distribution-distributions-body.php`:

- Column header `Service` → `Aid Type`.
- Cell: `<td><span class="badge bg-light text-dark border"><?= esc((string) $d['aid_type']) ?></span></td>`
- Update the docblock ("Each row shows the aid type the batch handed out").

- [ ] **Step 5: DashboardPageBuilder supplies activeAidTypes**

In `app/Libraries/DashboardPageBuilder.php` (admin data array, around line 197):

- Replace
  `'activeCategories' => $isBatches ? (new CategoryModel())->getActive() : [],`
  `'activeServices'   => $isBatches ? (new ServiceModel())->getActive() : [],`
  with
  `'activeAidTypes'   => $isBatches ? model(AidTypeModel::class)->active() : [],`
- Add `use App\Models\Scanner\AidTypeModel;` to the imports.
- If `ServiceModel`/`CategoryModel` imports were only used for those two lines, they're still used elsewhere in the file (sector/service/category list bundles) — leave them.

In `app/Views/Admin/layout.php` batches block, the modal include becomes:

```php
<?= view('Admin/batch-create-modal', [
    'activeAidTypes' => $activeAidTypes,
]) ?>
```

and add `$activeAidTypes = $activeAidTypes ?? [];` next to the other defensive defaults at the top of layout.php (match how `$batches` is defaulted, around line 29).

- [ ] **Step 6: Verify routes + tests, commit**

```bash
php spark routes | grep -E "admin/batches|admin/distributions"
vendor/bin/phpunit
git add app/Controllers/Admin/DistributionController.php app/Views/Admin app/Libraries/DashboardPageBuilder.php
git commit -m "feat(admin): batches bind aid type; modal is name + aid-type select"
```

Expected: routes resolve; suite green (scan-related tests still reference service — they are fixed in Tasks 5–6; if any fail here on service strings in ADMIN files only, fix; kiosk failures wait for their task. Run the full suite again at the end of Task 6.)

---

### Task 4: Aid Types reference page (admin/aidtypes)

**Files:**
- Create: `app/Controllers/Admin/AidTypesController.php`
- Create: `app/Views/Admin/aidtypes-body.php`
- Create: `app/Views/Admin/aidtype-create-modal.php`
- Modify: `app/Config/Routes.php`
- Modify: `app/Libraries/DashboardPageBuilder.php`
- Modify: `app/Models/ViewLayoutModel.php` (only if page titles live there — see Step 4)
- Modify: `app/Views/Admin/layout.php`
- Modify: `app/Views/components/dashboard_sidebar.php`
- Test: `tests/unit/DashboardControllerRoutingTest.php` (add route assertions if the file covers admin pages; follow its existing style)

**Interfaces:**
- Consumes: `AidTypeModel` full API from Task 2.
- Produces: page `admin/aidtypes` with view vars `aidTypes` (all rows) and `currentRole`; POST routes `admin/aidtypes/create|archive/(:num)|restore/(:num)|delete/(:num)`.

- [ ] **Step 1: Routes**

In `app/Config/Routes.php`, inside the admin group next to the categories group:

```php
$routes->get('aidtypes', 'Admin\AidTypesController::index');
$routes->group('aidtypes', static function (RouteCollection $routes): void {
    $routes->post('create', 'Admin\AidTypesController::create');
    $routes->post('archive/(:num)', 'Admin\AidTypesController::archive/$1');
    $routes->post('restore/(:num)', 'Admin\AidTypesController::restore/$1');
    $routes->post('delete/(:num)', 'Admin\AidTypesController::deleteType/$1');
});
```

- [ ] **Step 2: Controller**

`app/Controllers/Admin/AidTypesController.php` — the CRUD bodies are ports of the pre-branch methods (see `git show main:app/Controllers/Admin/DistributionController.php`, methods `createAidType`/`archiveAidType`/`restoreAidType`/`deleteAidType`), rehomed and redirecting to `admin/aidtypes`:

```php
<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Aid Types reference page (Reference Data group): list + add/archive/
 * restore/delete for the aid_type table. Admin/Developer only. Every
 * mutation writes an audit_trails row. Rendered in the admin dashboard shell.
 */
class AidTypesController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** GET admin/aidtypes — aid-type management page. */
    public function index(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('aidtypes');
    }

    /** POST admin/aidtypes/create — add an aid type. */
    public function create(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('admin/aidtypes')->with('error', 'Aid type name is required.');
        }
        $id = model(AidTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to add aid type.');
        }
        $this->audit('Created aid type "' . $name . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type added.');
    }

    /** POST admin/aidtypes/archive/{id} — soft-archive (drops out of new-batch picks). */
    public function archive(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->archive($id)) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to archive aid type.');
        }
        $this->audit('Archived aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type archived.');
    }

    /** POST admin/aidtypes/restore/{id} — un-archive. */
    public function restore(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->restore($id)) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to restore aid type.');
        }
        $this->audit('Restored aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type restored.');
    }

    /** POST admin/aidtypes/delete/{id} — permanent delete, blocked while referenced. */
    public function deleteType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type   = model(AidTypeModel::class)->find($id);
        $result = model(AidTypeModel::class)->deleteIfUnused($id);
        if ($result > 0) {
            return redirect()->to('admin/aidtypes')->with('error', 'This aid type is used by ' . $result . ' distribution(s) and cannot be deleted. Archive it instead.');
        }
        if ($result < 0) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to delete aid type.');
        }
        $this->audit('Permanently deleted aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type deleted.');
    }

    private function audit(string $action): void
    {
        (new AuditTrailsModel())->logAction(
            (int) (session('user_id') ?? 0),
            null,
            $action,
            null,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            null
        );
    }
}
```

- [ ] **Step 3: Views**

`app/Views/Admin/aidtypes-body.php` — the pre-branch aid-types table, re-pointed at the new routes. Base it on `git show 3c6a17c^:app/Views/Admin/distribution-aidtypes-body.php` with these changes:

- Docblock: "Aid Types reference body: Add button + aid-type table. Rendered inside components/card by Admin/layout.php's aidtypes block (vars: aidTypes, currentRole)."
- All four form actions change from `admin/aid-types/...` to `admin/aidtypes/...`.
- Variable `$canManageAidTypes` logic stays (`in_array($currentRole ?? '', ['Admin', 'Developer'], true)`).

`app/Views/Admin/aidtype-create-modal.php`:

```php
<?php
/** Add Aid Type modal: single name field, posts to admin/aidtypes/create. */
?>
<div class="modal fade" id="addAidTypeModal" tabindex="-1" aria-labelledby="addAidTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('admin/aidtypes/create') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="addAidTypeModalLabel">Add Aid Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="aidTypeName" class="form-label">Name</label>
          <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="100"
                 placeholder="e.g. Financial">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Add Aid Type</button>
      </div>
    </form>
  </div>
</div>
```

In `app/Views/Admin/layout.php`, add a block next to the batches block (and default `$aidTypes = $aidTypes ?? [];` at the top with the other defaults):

```php
<?php if ($activePage === 'aidtypes'): ?>
    <?= view('components/card', [
        'icon' => 'box-seam',
        'title' => 'Aid Types',
        'cardClass' => 'sector-management',
        'bodyView' => 'Admin/aidtypes-body',
        'bodyData' => [
            'aidTypes' => $aidTypes,
            'currentRole' => $currentRole,
        ],
    ]) ?>
    <?= view('Admin/aidtype-create-modal') ?>
<?php endif; ?>
```

- [ ] **Step 4: Builder + sidebar wiring**

`app/Libraries/DashboardPageBuilder.php`:

- Add to the returned admin array: `'aidTypes' => $activePage === 'aidtypes' ? model(AidTypeModel::class)->all() : [],`
- navActive map: add `'aidtypes' => $layoutModel->navActive($activePage, 'aidtypes'),`

Check `app/Models/ViewLayoutModel.php` `pageTitle()`: if it maps page keys to titles, add `'aidtypes' => 'Aid Types'` following its pattern; if it falls back to a default, skip.

`app/Views/components/dashboard_sidebar.php` — add under the Reference Data heading, after Manage Categories:

```php
<a class="nav-link <?= esc($navActive['aidtypes'] ?? '') ?>" href="<?= site_url('admin/aidtypes') ?>"><div class="sb-nav-link-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></div>Aid Types</a>
```

- [ ] **Step 5: Verify + commit**

```bash
php spark routes | grep aidtypes
vendor/bin/phpunit
git add app/Controllers/Admin/AidTypesController.php app/Views/Admin app/Config/Routes.php app/Libraries/DashboardPageBuilder.php app/Views/components/dashboard_sidebar.php app/Models/ViewLayoutModel.php
git commit -m "feat(admin): Aid Types reference page under Reference Data"
```

Expected: 5 aidtypes routes listed; suite state no worse than Task 3.

---

### Task 5: Reports count by aid type again

**Files:**
- Modify: `app/Models/Scanner/AidStatsModel.php`
- Modify: `app/Libraries/DashboardPageBuilder.php`
- Modify: `app/Controllers/Admin/ReportsController.php`
- Modify: `app/Views/Admin/reports-body.php`
- Modify: `app/Views/Scanner/pdf/report.php`
- Modify: `app/Libraries/Scanner/ReportsPdfGenerator.php`
- Modify: `public/assets/js/dashboard/scanner-reports.js`
- Test: `tests/unit/AidStatsModelTest.php`, `tests/unit/ReportsPdfGeneratorTest.php`

**Interfaces:**
- Produces: `AidStatsModel::byAidType(?int $batchId = null): array` returning `list<array{aid_type:string,count:int}>`; view var `reportsByAidType`; JSON key `byAidType` in the reports payload and `admin/reports/stats` response.

- [ ] **Step 1: Model**

Replace `AidStatsModel::byService()` with:

```php
/**
 * Handout counts per aid type, within the batch scope, busiest first.
 * Aid types with zero handouts are omitted.
 */
public function byAidType(?int $batchId = null): array
{
    try {
        $b = $this->db->table('aid_type')
            ->select('aid_type.name AS aid_type,'
                . ' COUNT(aid_distribution.aidID) AS count')
            ->join('aid_distribution', 'aid_distribution.aid_type_id = aid_type.aid_type_id', 'left');
        $this->applyScope($b, $batchId);
        $rows = $b->groupBy('aid_type.aid_type_id')
            ->having('count >', 0)
            ->orderBy('count', 'DESC')
            ->orderBy('aid_type.name', 'ASC')
            ->get()->getResultArray();

        return array_map(static fn ($r) => [
            'aid_type' => (string) $r['aid_type'],
            'count'    => (int) $r['count'],
        ], $rows);
    } catch (\Throwable $e) {
        return [];
    }
}
```

- [ ] **Step 2: Builder + controller**

`DashboardPageBuilder`: in `buildReportsData()` and the empty default block, rename `reportsByService` → `reportsByAidType` and the `byService($batchId)` call → `byAidType($batchId)`. Grep to catch all spots:

```bash
grep -rn "byService\|reportsByService" app/ public/assets/js tests/
```

`ReportsController`: `stats()` and `pdf()` pass `byAidType` (rename the array key it emits from `byService` to `byAidType`; the generator param order is unchanged).

- [ ] **Step 3: Views + JS + PDF**

`reports-body.php`:
- `$reportsByService` default → `$reportsByAidType = $reportsByAidType ?? [];`
- Chart card title: `'Number of handouts by aid type'`; canvas id stays `chartService` only if the JS keeps it — rename BOTH to `chartAidType` (see JS below).
- JSON payload: `'byAidType' => $reportsByAidType,`

`scanner-reports.js` (lines ~102–142): rename `data.byService` → `data.byAidType`, `charts.byService` → `charts.byAidType`, `ctx('chartService')` → `ctx('chartAidType')`, and the `serviceLabel` mapper becomes `function (a) { return a.aid_type; }` (aid types have no shortcode).

`Scanner/pdf/report.php`: heading `Handouts by aid type`; loop over `$byAidType`; cell prints `esc($a['aid_type'])`; empty check `$byAidType === []`.

`ReportsPdfGenerator.php`: rename the `$byService` parameter to `$byAidType` (and the `'byService'` view-data key to `'byAidType'`), update the `@param` shape to `list<array{aid_type:string,count:int}>`.

- [ ] **Step 4: Tests**

`tests/unit/ReportsPdfGeneratorTest.php`: the byAidType fixture row becomes `[['aid_type' => 'Rice', 'count' => 5]]` and assert the PDF/HTML contains `Rice` (follow the file's existing assertion style).

`tests/unit/AidStatsModelTest.php`: rename byService references to byAidType (this test was renamed in commit 411576e — reverse that mapping).

- [ ] **Step 5: Run + commit**

```bash
vendor/bin/phpunit --filter 'AidStatsModelTest|ReportsPdfGeneratorTest'
grep -rn "byService" app/ public/ tests/   # expect: no hits
git add -A app/ public/assets/js/dashboard/scanner-reports.js tests/
git commit -m "feat(reports): stats, charts and PDF count by aid type"
```

---

### Task 6: One-action scan (kiosk) + aid-type badge

**Files:**
- Modify: `app/Controllers/Scanner/ScanController.php`
- Modify: `app/Views/Scanner/scan.php`
- Modify: `app/Views/Scanner/kiosk-layout.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/unit/ScanControllerBatchTest.php`, `tests/unit/ScanViewTest.php`, `tests/unit/KioskViewTest.php`, `tests/unit/ScanControllerTest.php` (check contents; update service→aid_type strings)

**Interfaces:**
- Consumes: `AidDistributionModel::inBatch()`, `logAid()` with `aid_type_id`; batch rows with `aid_type_name`.
- Produces: `POST scanner/log` accepting only `control_no`, responding 200 JSON:
  `{ok:true, logged:bool, duplicate:{claim_date,dt_created,scanned_by}|null, control_no:int, head:object, members:list, history:list, myBatchCount:int}`
  (errors keep their current status codes: 403/404/409/422/500). `GET scanner/lookup/(:num)` is removed.

- [ ] **Step 1: Update the source-assertion tests first (TDD on the contract)**

`tests/unit/ScanControllerBatchTest.php` — `testScanGuardsServiceAndBatch()` becomes:

```php
public function testScanGuardsAidTypeBatchAndDuplicates(): void
{
    $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
    // logAid() must refuse with 409 when no batch is open.
    $this->assertStringContainsString('setStatusCode(409)', $src);
    // The aid type comes from the active batch, not POST.
    $this->assertStringContainsString("(int) \$activeBatch['aid_type_id']", $src);
    // One-action scan: claimant is the family head, claim date is today.
    $this->assertStringContainsString("(int) \$head['memberID']", $src);
    $this->assertStringContainsString("date('Y-m-d')", $src);
    // Per-batch duplicate guard: a family logs at most once per batch.
    $this->assertStringContainsString('inBatch(', $src);
    // Every logged row is stamped with the open batch and the response
    // carries the live personal counter.
    $this->assertStringContainsString("'batch_id'", $src);
    $this->assertStringContainsString('myBatchCount', $src);
}
```

`tests/unit/ScanViewTest.php` — update the flagged assertions (keep the file's setup):

```php
$this->assertStringContainsString('AID_TYPE_NAME', $this->html);
$this->assertStringNotContainsString('SERVICE_NAME', $this->html);
$this->assertStringNotContainsString('name="service_id"', $this->html);
// One-action scan: no confirm form, one result banner region.
$this->assertStringNotContainsString('id="logForm"', $this->html);
$this->assertStringContainsString('id="resultBanner"', $this->html);
$this->assertStringContainsString('Duplicate Entry', $this->html);
```

Read both files fully before editing; keep their other assertions unless they reference the removed form/service constants.

Run: `vendor/bin/phpunit --filter 'ScanViewTest|ScanControllerBatchTest'` — expected: FAIL (contract not implemented yet).

- [ ] **Step 2: Routes**

In the scanner group of `app/Config/Routes.php`, delete the lookup line:

```php
$routes->get('lookup/(:num)', 'Scanner\ScanController::lookup/$1');
```

(`POST scanner/log` stays.)

- [ ] **Step 3: ScanController — merge lookup into logAid, rebind to aid type**

In `app/Controllers/Scanner/ScanController.php`:

1. `scan()`: replace the `'service' => ...` view-data entry with:

```php
'aidType' => $activeBatch !== null
    ? [
        'aid_type_id' => (int) $activeBatch['aid_type_id'],
        'name'        => (string) ($activeBatch['aid_type_name'] ?? 'Aid'),
    ]
    : null,
```

2. `stats()`: the batch payload swaps the service keys for `'aid_type' => (string) ($activeBatch['aid_type_name'] ?? ''),`.

3. Delete the whole `lookup()` method.

4. Replace `logAid()` with the one-action version:

```php
/**
 * POST scanner/log — the whole scan in one action. Resolves the control
 * number to a family, refuses when the family was already logged in the
 * open batch (Duplicate Entry), otherwise inserts a distribution for the
 * family HEAD dated today and audits it. The response always carries the
 * family panel data so the kiosk renders in one round trip.
 */
public function logAid(): ResponseInterface
{
    $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
    if ($guard instanceof RedirectResponse) {
        return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
    }

    if (! $this->validate(['control_no' => 'required|is_natural_no_zero'])) {
        return $this->response->setStatusCode(422)
            ->setJSON(['errors' => $this->validator->getErrors()]);
    }
    $controlNo = (int) $this->request->getPost('control_no');

    $headId = model(QrControlModel::class)->headForControl($controlNo);
    if ($headId === null) {
        return $this->response->setStatusCode(404)->setJSON(['error' => 'QR control number is not registered.']);
    }
    $members = new MemberModel();
    $head    = $members->findHead($headId);
    if ($head === null) {
        log_message('error', 'Scanner log: control {c} maps to missing head {h}', ['c' => $controlNo, 'h' => $headId]);
        return $this->response->setStatusCode(404)->setJSON(['error' => 'Family record unavailable.']);
    }

    $activeBatch = model(DistributionBatchModel::class)->activeBatch();
    if ($activeBatch === null) {
        return $this->response->setStatusCode(409)
            ->setJSON(['error' => 'No active distribution batch. Ask an administrator to open one.']);
    }
    $batchId = (int) $activeBatch['batch_id'];
    $userId  = (int) (session('user_id') ?? 0);

    $familyPayload = [
        'control_no' => $controlNo,
        'head'       => $head,
        'members'    => $members->familyMembers($headId),
    ];

    // One handout per family per batch: a repeat scan reports the original
    // entry instead of logging again. The check is server-side so a stale
    // kiosk page can never double-log.
    $existing = model(AidDistributionModel::class)->inBatch($controlNo, $batchId);
    if ($existing !== null) {
        return $this->response->setJSON($familyPayload + [
            'ok'           => true,
            'logged'       => false,
            'duplicate'    => $existing,
            'history'      => model(AidDistributionModel::class)->historyFor($controlNo),
            'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, $batchId),
        ]);
    }

    $aidTypeId = (int) $activeBatch['aid_type_id'];
    $claimDate = date('Y-m-d');

    // The insert and its audit row must land together: without a shared
    // transaction, a handout could get logged with no audit trail (or an
    // audit row could survive a rolled-back handout).
    $db = db_connect();
    $db->transStart();

    $aidId = model(AidDistributionModel::class)->logAid([
        'control_no'  => $controlNo,
        'memberID'    => (int) $head['memberID'],
        'aid_type_id' => $aidTypeId,
        'claim_date'  => $claimDate,
        'userID'      => $userId,
        'batch_id'    => $batchId,
    ]);

    $audited = $aidId > 0 && (new AuditTrailsModel())->logAction(
        $userId,
        (int) $head['memberID'],
        'Logged aid distribution',
        'Control #' . $controlNo,
        $this->request->getIPAddress(),
        (string) $this->request->getUserAgent(),
        'Aid type ID ' . $aidTypeId . ' on ' . $claimDate
    );

    if (! $audited) {
        $db->transRollback();
        return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the aid distribution.']);
    }

    $db->transComplete();
    if ($db->transStatus() === false) {
        return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the aid distribution.']);
    }

    return $this->response->setJSON($familyPayload + [
        'ok'           => true,
        'logged'       => true,
        'duplicate'    => null,
        'history'      => model(AidDistributionModel::class)->historyFor($controlNo),
        'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, $batchId),
    ]);
}
```

Update the class docblock route list (lookup gone; logAid described as the one-action scan).

- [ ] **Step 4: Kiosk badge**

`app/Views/Scanner/kiosk-layout.php`: replace the `$service` default and badge with:

```php
$aidType = $aidType ?? null;
```

```php
<?php if ($aidType !== null): ?>
  <span class="badge bg-info text-dark fs-6"><?= esc($aidType['name']) ?></span>
<?php endif; ?>
```

Also update the shell docblock ("batch · aid type · live personal counter · logout").

- [ ] **Step 5: Rewrite scan.php**

Replace the Log Distribution card, receipt panel, dup alert, and confirm handlers. Full new content for `app/Views/Scanner/scan.php`:

```php
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
```

Notes: `h.badges` / `m.badges` render empty until Task 7 adds them to the payload — `badgeList(undefined)` returns `''`, so this is safe. The `<template id="dupTitleText">` keeps the "Duplicate Entry" copy in markup so `ScanViewTest` can assert it.

- [ ] **Step 6: KioskViewTest + ScanControllerTest**

Read `tests/unit/KioskViewTest.php` and `tests/unit/ScanControllerTest.php`; update any `service`/`SERVICE_`/`lookup` expectations to the new contract (aid type badge var `$aidType`, no `scanner/lookup` route). If `ScanControllerTest` asserts the lookup route resolves, change it to assert the route is GONE:

```php
$this->assertArrayNotHasKey('scanner/lookup/([0-9]+)', $routes->getRoutes('GET'));
```

- [ ] **Step 7: Run + commit**

```bash
php spark routes | grep scanner
vendor/bin/phpunit
git add app/Controllers/Scanner app/Views/Scanner app/Config/Routes.php tests/unit
git commit -m "feat(scanner): one-action scan with Logged/Duplicate Entry banners"
```

Expected: whole suite green (all earlier service-string stragglers resolved by now); `scanner/lookup` no longer listed.

---

### Task 7: Kiosk per-member sector/service/category badges

**Files:**
- Modify: `app/Models/Families/MemberModel.php`
- Modify: `app/Controllers/Scanner/ScanController.php`
- Test: `tests/unit/MemberBadgesTest.php` (create)

**Interfaces:**
- Consumes: `SectorIds::normalize()`, `member.sectorID` JSON, `member_services` ↔ `services` (`services.category` holds the category NAME).
- Produces: `MemberModel::referenceBadges(array $memberIds): array` — map of memberID → `list<string>` of badge labels: sector shortcodes, then category names (deduped), then service shortcodes. ScanController attaches `badges` to `head` and each member row.

- [ ] **Step 1: Failing test**

`tests/unit/MemberBadgesTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use CodeIgniter\Test\CIUnitTestCase;

final class MemberBadgesTest extends CIUnitTestCase
{
    public function testReferenceBadgesExistsAndIsSafeWithoutDb(): void
    {
        $model = new MemberModel();
        // Empty input never touches the DB and returns an empty map.
        $this->assertSame([], $model->referenceBadges([]));
    }

    public function testScanControllerAttachesBadges(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        $this->assertStringContainsString('referenceBadges(', $src);
        $this->assertStringContainsString("'badges'", $src);
    }
}
```

Run: `vendor/bin/phpunit --filter MemberBadgesTest` — expected: FAIL (method missing).

- [ ] **Step 2: MemberModel::referenceBadges()**

Add to `app/Models/Families/MemberModel.php` (near `familyMembers()`), and note `familyMembers()`'s select must also include `sectorID` — change its select to `'memberID, firstname, lastname, relationship, birthday, sex, sectorID'`:

```php
/**
 * Reference-data badges per member for the kiosk family panel: sector
 * shortcodes (member.sectorID JSON), then category names, then service
 * shortcodes (member_services -> services). Returns memberID => list of
 * badge labels; empty map on empty input or any DB error.
 *
 * @param list<int> $memberIds
 * @return array<int, list<string>>
 */
public function referenceBadges(array $memberIds): array
{
    $memberIds = array_values(array_filter(array_map('intval', $memberIds), static fn (int $id): bool => $id > 0));
    if ($memberIds === []) {
        return [];
    }

    try {
        // Sector shortcodes keyed by sectorID, resolved once for the family.
        $sectorCodes = [];
        foreach ($this->db->table('sector')->select('sectorID, shortcode')->get()->getResultArray() as $s) {
            $sectorCodes[(int) $s['sectorID']] = (string) $s['shortcode'];
        }

        $sectorJson = [];
        foreach ($this->db->table('member')->select('memberID, sectorID')->whereIn('memberID', $memberIds)->get()->getResultArray() as $m) {
            $sectorJson[(int) $m['memberID']] = $m['sectorID'];
        }

        $serviceRows = $this->db->table('member_services')
            ->select('member_services.memberID, services.shortcode, services.category')
            ->join('services', 'services.serviceID = member_services.serviceID')
            ->whereIn('member_services.memberID', $memberIds)
            ->where('services.dt_deleted IS NULL', null, false)
            ->get()->getResultArray();

        $badges = [];
        foreach ($memberIds as $id) {
            $sectors = [];
            foreach (\App\Libraries\SectorIds::normalize($sectorJson[$id] ?? '[]') as $sid) {
                if (isset($sectorCodes[$sid])) {
                    $sectors[] = $sectorCodes[$sid];
                }
            }
            $categories = [];
            $services   = [];
            foreach ($serviceRows as $r) {
                if ((int) $r['memberID'] !== $id) {
                    continue;
                }
                $cat = trim((string) ($r['category'] ?? ''));
                if ($cat !== '' && ! in_array($cat, $categories, true)) {
                    $categories[] = $cat;
                }
                $code = trim((string) ($r['shortcode'] ?? ''));
                if ($code !== '') {
                    $services[] = $code;
                }
            }
            $badges[$id] = array_merge($sectors, $categories, $services);
        }

        return $badges;
    } catch (\Throwable $e) {
        return [];
    }
}
```

- [ ] **Step 3: Attach badges in ScanController::logAid()**

Right after `$familyPayload` is built, decorate head + members:

```php
$memberRows = $familyPayload['members'];
$badges     = $members->referenceBadges(array_map(static fn (array $m): int => (int) $m['memberID'], $memberRows));
$familyPayload['head']['badges'] = $badges[(int) $head['memberID']] ?? [];
foreach ($memberRows as $i => $m) {
    $memberRows[$i]['badges'] = $badges[(int) $m['memberID']] ?? [];
}
$familyPayload['members'] = $memberRows;
```

(The scan.php from Task 6 already renders `badges` — nothing to change client-side.)

- [ ] **Step 4: Run + commit**

```bash
vendor/bin/phpunit --filter 'MemberBadgesTest|ScanViewTest|ScanControllerBatchTest'
vendor/bin/phpunit
git add app/Models/Families/MemberModel.php app/Controllers/Scanner/ScanController.php tests/unit/MemberBadgesTest.php
git commit -m "feat(scanner): per-member sector/category/service badges on the kiosk"
```

---

### Task 8: Docs, knowledge base, smoke test, V17 deletion

**Files:**
- Modify: `docs/knowledge/binan-conventions/scanner-batches.md`
- Modify: `CLAUDE.md` (dump reference if it names a version), `PROJECT_STRUCTURE.md` (if it names deleted/added files)
- Delete: `accesscardV17.sql` (only after smoke test passes)

- [ ] **Step 1: Rewrite scanner-batches knowledge doc**

Update `docs/knowledge/binan-conventions/scanner-batches.md` to describe: batches bind `aid_type_id` (aid_type reference table, `admin/aidtypes` CRUD); one-action scan (`POST scanner/log` with `control_no` only, head+today, per-batch duplicate guard via `AidDistributionModel::inBatch()`); Logged/Duplicate Entry banners; kiosk member badges via `MemberModel::referenceBadges()`. Plain human-reader language, match the doc's existing structure.

- [ ] **Step 2: Update CLAUDE.md / PROJECT_STRUCTURE.md**

Grep for stale references:

```bash
grep -rn "V17\|v17\|service_id\|aid-types\|AidType" CLAUDE.md PROJECT_STRUCTURE.md docs/knowledge/ | grep -v node_modules
```

Fix what's stale (dump version line, file map entries for `AidTypesController`, `aidtypes-body.php`, renamed patch).

- [ ] **Step 3: Full verification pass**

```bash
php spark routes            # every route resolves
vendor/bin/phpunit          # green
```

Smoke test against the dev server (start `php spark serve` on app.baseURL if down; login developer/developer123), using the Playwright MCP:

1. `admin/aidtypes`: page renders, add an aid type, archive/restore it.
2. `admin/batches`: New Batch modal shows name + aid-type select; open a batch.
3. `scanner/scan`: kiosk badge shows the aid type name. (Scanning needs family/QR data — if the DB has none, import a demo family via the UI first per the V14 workflow, generate a card, then:) scan a control number → big green "Logged" banner, history updated, member collapse works, badges visible; scan the SAME number → big red "Duplicate Entry" banner and `SELECT COUNT(*) FROM aid_distribution` unchanged.
4. Close the batch, open a new one, scan the same card → logs again (green).
5. `admin/distributions`: rows show Aid Type column. `admin/reports`: renders, chart titled by aid type, PDF downloads.

- [ ] **Step 4: Delete V17 and commit**

Only after Step 3 passes:

```bash
git rm accesscardV17.sql
git add -A docs/ CLAUDE.md PROJECT_STRUCTURE.md
git commit -m "docs: scanner-batches doc follows aid-type restore; drop V17 dump"
```

---

### Task 9: Code review + branch wrap-up

- [ ] **Step 1: CodeRabbit review**

```bash
coderabbit auth status
coderabbit review --base main --agent   # run in background, WAIT for completion
```

Triage per the repo workflow (superpowers:receiving-code-review posture): verify each finding, fix genuine in-scope bugs, re-run `vendor/bin/phpunit`, park pre-existing/out-of-scope findings in a GitHub issue citing branch + PR. If the CodeRabbit CLI refuses (trial lapsed), fall back to `/code-review` with the same discipline.

- [ ] **Step 2: Final suite + routes, then hand off**

```bash
vendor/bin/phpunit
php spark routes
```

Then use superpowers:finishing-a-development-branch to decide merge/PR (remember: `gh pr create --repo janobles/binan_accesscard`; base `main`). PR 2 (UI/UX pass) starts after this PR merges.
