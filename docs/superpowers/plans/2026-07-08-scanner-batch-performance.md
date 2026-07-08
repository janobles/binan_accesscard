# Scanner Batch & Performance Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Batch-scoped aid distribution with per-scanner performance stats, a distribution-setting page before scanning, and a minimal kiosk shell for the scan flow.

**Architecture:** New `distribution_batch` table (SQL dump bump V14→V15, no migrations) with a single-open-batch invariant enforced in a new `DistributionBatchModel`. `aid_distribution` gains `batch_id`. Admin opens/closes batches from `scanner/manage` (audited). The scan flow splits into `scanner/setting` (aid-type pick) → `scanner/scan` (kiosk shell, live personal counter). Reports gain a batch selector and role-aware per-scanner performance.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, MySQL, Bootstrap 5 (SB Admin 1 house style), PHPUnit.

## Global Constraints

- **No migrations.** Schema change = new dump `accesscardV15.sql` only. Never alter schema in code.
- **Every mutation writes an audit trail** via `App\Models\Audit\AuditTrailsModel::logAction()`.
- Typed signatures everywhere; **no** `declare(strict_types=1)`.
- Stats/model methods return safe empty shapes on any DB error (`try/catch \Throwable`).
- Valid Bootstrap 5 components/utilities only; scan pages fit viewport without scrolling; zero added per-scan keypresses.
- Role guard literal for scanner pages: `RoleAccess::requireRole(['Scanner', 'Admin', 'Developer'])`. Batch open/close: `RoleAccess::requireRole(['Admin', 'Developer'])`.
- Run `vendor/bin/phpunit` before starting and after every task (DB/session tests skip without sqlite3 — that is normal).
- Consult `.claude/skills/binan-conventions/SKILL.md` before controller/view/route edits.

---

### Task 1: SQL dump V15

**Files:**
- Create: `accesscardV15.sql` (copy of `accesscardV14.sql` + additions)

**Interfaces:**
- Produces: tables `distribution_batch(batch_id, name, started_at, closed_at, created_by)`; column `aid_distribution.batch_id INT NULL` + index `idx_ad_batch`.

- [ ] **Step 1: Copy dump**

```bash
cp accesscardV14.sql accesscardV15.sql
```

- [ ] **Step 2: Add batch table + column**

In `accesscardV15.sql`, directly after the `aid_distribution` CREATE TABLE block (search `CREATE TABLE \`aid_distribution\``), add:

```sql
DROP TABLE IF EXISTS `distribution_batch`;
CREATE TABLE `distribution_batch` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

And inside the `aid_distribution` CREATE TABLE column list, after `` `userID` int(11) DEFAULT NULL, `` add:

```sql
  `batch_id` int(11) DEFAULT NULL,
```

and in its KEY list add:

```sql
  KEY `idx_ad_batch` (`batch_id`),
```

(keep valid SQL comma placement).

- [ ] **Step 3: Verify by importing into a scratch DB**

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS accesscard_v15_check; CREATE DATABASE accesscard_v15_check;"
mysql -h 127.0.0.1 -u root accesscard_v15_check < accesscardV15.sql
mysql -h 127.0.0.1 -u root accesscard_v15_check -e "DESCRIBE distribution_batch; SHOW COLUMNS FROM aid_distribution LIKE 'batch_id';"
mysql -h 127.0.0.1 -u root -e "DROP DATABASE accesscard_v15_check;"
```

Expected: `distribution_batch` describes 5 columns; `batch_id` column exists on `aid_distribution`. (Use the intl-enabled `php`/local MySQL per repo dev setup; if MySQL is unreachable, at minimum `grep -c "distribution_batch" accesscardV15.sql` returns ≥2.)

- [ ] **Step 4: Re-import V15 into the working `accesscard` DB** (drop → import → re-import Excel via UI is the established demo workflow; coordinate with JP if data present).

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS accesscard; CREATE DATABASE accesscard;"
mysql -h 127.0.0.1 -u root accesscard < accesscardV15.sql
```

- [ ] **Step 5: Commit**

```bash
git add accesscardV15.sql
git commit -m "feat(db): dump V15 adds distribution_batch table and aid_distribution.batch_id"
```

---

### Task 2: DistributionBatchModel

**Files:**
- Create: `app/Models/Scanner/DistributionBatchModel.php`
- Test: `tests/unit/DistributionBatchModelTest.php`

**Interfaces:**
- Produces:
  - `activeBatch(): ?array` — the single open row (`closed_at IS NULL`) or null.
  - `open(string $name, int $userId): int` — new batch_id, 0 on failure/blank name/already-open.
  - `close(int $batchId): bool` — stamps `closed_at`; false if id ≤ 0 or already closed.
  - `allBatches(): array` — newest first.

- [ ] **Step 1: Write the failing test** — `tests/unit/DistributionBatchModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\Test\CIUnitTestCase;

final class DistributionBatchModelTest extends CIUnitTestCase
{
    public function testActiveBatchReturnsNullOrArray(): void
    {
        $out = (new DistributionBatchModel())->activeBatch();
        $this->assertTrue($out === null || is_array($out));
    }

    public function testOpenRejectsBlankName(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->open('   ', 1));
    }

    public function testCloseRejectsNonPositiveId(): void
    {
        $this->assertFalse((new DistributionBatchModel())->close(0));
    }

    public function testAllBatchesReturnsArray(): void
    {
        $this->assertIsArray((new DistributionBatchModel())->allBatches());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter DistributionBatchModelTest`
Expected: FAIL/ERROR — class `App\Models\Scanner\DistributionBatchModel` not found.

- [ ] **Step 3: Implement** — `app/Models/Scanner/DistributionBatchModel.php`:

```php
<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Distribution batches: one row per giving event (e.g. one day of handouts).
 * At most one batch may be open (closed_at IS NULL) at a time; open() enforces
 * that invariant. Closing a batch is the manual "reset" — the next batch's
 * statistics start from zero. All methods keep the scanner module's no-DB
 * test posture: safe empty shapes on any DB error.
 */
class DistributionBatchModel extends Model
{
    protected $table         = 'distribution_batch';
    protected $primaryKey    = 'batch_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'closed_at', 'created_by'];
    protected $useTimestamps = false;

    /** The single open batch, or null when none (or on DB error). */
    public function activeBatch(): ?array
    {
        try {
            $row = $this->where('closed_at', null)
                ->orderBy('batch_id', 'DESC')
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Opens a batch; refuses when the name is blank or a batch is already open. */
    public function open(string $name, int $userId): int
    {
        $name = trim($name);
        if ($name === '' || $this->activeBatch() !== null) {
            return 0;
        }

        try {
            if ($this->insert([
                'name'       => $name,
                'created_by' => $userId > 0 ? $userId : null,
            ]) === false) {
                return 0;
            }

            return (int) $this->getInsertID();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Closes (resets) an open batch by stamping closed_at. */
    public function close(int $batchId): bool
    {
        if ($batchId <= 0) {
            return false;
        }

        try {
            $row = $this->find($batchId);
            if (! is_array($row) || $row['closed_at'] !== null) {
                return false;
            }

            return $this->update($batchId, ['closed_at' => date('Y-m-d H:i:s')]) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Every batch, newest first, for the manage table and reports selector. */
    public function allBatches(): array
    {
        try {
            return $this->orderBy('batch_id', 'DESC')->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter DistributionBatchModelTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/DistributionBatchModel.php tests/unit/DistributionBatchModelTest.php
git commit -m "feat(scanner): DistributionBatchModel with single-open-batch invariant"
```

---

### Task 3: AidDistributionModel batch support

**Files:**
- Modify: `app/Models/Scanner/AidDistributionModel.php`
- Test: `tests/unit/AidDistributionModelTest.php` (append tests)

**Interfaces:**
- Consumes: `batch_id` column from Task 1.
- Produces:
  - `logAid(array $data): int` now also stores optional `batch_id` (int > 0 or NULL).
  - `familiesForUserInBatch(int $userId, int $batchId): int` — COUNT(DISTINCT control_no) by this user in this batch. Task 5's `myBatchCount` and the kiosk counter use this.

- [ ] **Step 1: Write the failing tests** — append to `tests/unit/AidDistributionModelTest.php`:

```php
    public function testFamiliesForUserInBatchRejectsNonPositiveIds(): void
    {
        $m = new \App\Models\Scanner\AidDistributionModel();
        $this->assertSame(0, $m->familiesForUserInBatch(0, 1));
        $this->assertSame(0, $m->familiesForUserInBatch(1, 0));
    }

    public function testLogAidAcceptsBatchIdKeyWithoutError(): void
    {
        // No DB in unit posture: invalid control_no short-circuits before insert,
        // proving the signature tolerates the new key.
        $m = new \App\Models\Scanner\AidDistributionModel();
        $this->assertSame(0, $m->logAid(['control_no' => 0, 'batch_id' => 5]));
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter AidDistributionModelTest`
Expected: FAIL — `familiesForUserInBatch` undefined.

- [ ] **Step 3: Implement.** In `AidDistributionModel.php`:

Change `$allowedFields` to:

```php
    protected $allowedFields = ['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID', 'batch_id'];
```

In `logAid()`, extend the insert array (after the `userID` line):

```php
            'batch_id'    => isset($data['batch_id']) && (int) $data['batch_id'] > 0 ? (int) $data['batch_id'] : null,
```

Add method:

```php
    /** Distinct families (control numbers) this user has served within a batch. */
    public function familiesForUserInBatch(int $userId, int $batchId): int
    {
        if ($userId <= 0 || $batchId <= 0) {
            return 0;
        }

        try {
            return (int) ($this->builder()
                ->select('COUNT(DISTINCT control_no) AS n')
                ->where('userID', $userId)
                ->where('batch_id', $batchId)
                ->get()->getRowArray()['n'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit --filter AidDistributionModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidDistributionModel.php tests/unit/AidDistributionModelTest.php
git commit -m "feat(scanner): aid_distribution rows carry batch_id; per-user family count"
```

---

### Task 4: Batch open/close in ManageController + manage view

**Files:**
- Modify: `app/Controllers/Scanner/ManageController.php`
- Modify: `app/Config/Routes.php` (scanner group, after the `distributions/void` line)
- Create: `app/Views/Scanner/manage-batches-body.php`
- Modify: `app/Views/Scanner/manage.php` (include the new body partial; follow how `manage-aidtypes-body.php` is included — read the file first)
- Test: `tests/unit/ManageControllerTest.php` (append)

**Interfaces:**
- Consumes: `DistributionBatchModel::open/close/activeBatch/allBatches` (Task 2).
- Produces: routes `POST scanner/batches/open` → `openBatch()`, `POST scanner/batches/close/(:num)` → `closeBatch($1)`; view data keys `batches` (array) and `activeBatch` (?array) passed to `Scanner/manage`.

- [ ] **Step 1: Write the failing tests** — append to `tests/unit/ManageControllerTest.php`:

```php
    public function testBatchRoutesResolve(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $post = $routes->getRoutes('POST');
        $this->assertArrayHasKey('scanner/batches/open', $post);
        $this->assertArrayHasKey('scanner/batches/close/([0-9]+)', $post);
    }

    public function testBatchActionsGuardAdminOnly(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        // Batch lifecycle is Admin/Developer only (stricter than the page guard).
        $this->assertGreaterThanOrEqual(2, substr_count($src, "requireRole(['Admin', 'Developer'])"));
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter ManageControllerTest`
Expected: FAIL on both new tests.

- [ ] **Step 3: Add routes.** In `app/Config/Routes.php` scanner group, after the `distributions/void` line:

```php
    $routes->post('batches/open', 'Scanner\ManageController::openBatch');
    $routes->post('batches/close/(:num)', 'Scanner\ManageController::closeBatch/$1');
```

- [ ] **Step 4: Add controller actions.** In `ManageController.php` add `use App\Models\Scanner\DistributionBatchModel;` and:

```php
    /** POST scanner/batches/open — Admin/Developer only. */
    public function openBatch(): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('scanner/manage')->with('error', 'Batch name is required.');
        }

        $id = model(DistributionBatchModel::class)->open($name, (int) (session('user_id') ?? 0));
        if ($id <= 0) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to open batch. Close the active batch first.');
        }

        $this->audit('Opened distribution batch "' . $name . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Batch opened. Scanning is now live.');
    }

    /** POST scanner/batches/close/{id} — Admin/Developer only. Manual reset. */
    public function closeBatch(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $batch = model(DistributionBatchModel::class)->find($id);
        if (! model(DistributionBatchModel::class)->close($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to close batch.');
        }

        $this->audit('Closed distribution batch "' . (string) ($batch['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Batch closed. Statistics reset for the next batch.');
    }
```

In `index()`, add to the view-data array:

```php
            'batches'     => model(DistributionBatchModel::class)->allBatches(),
            'activeBatch' => model(DistributionBatchModel::class)->activeBatch(),
```

- [ ] **Step 5: Create `app/Views/Scanner/manage-batches-body.php`.** Read `manage-aidtypes-body.php` first and copy its card anatomy/house style. Content requirements (adapt markup to the house pattern found):

```php
<?php
/** Distribution Batches card: open form + active-batch close + past list.
 * Buttons render only for Admin/Developer ($canManageBatches). */
$canManageBatches = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<div class="card border-0 rounded-3 mb-3">
  <div class="card-body">
    <div class="fw-bold mb-2"><i class="bi bi-collection" aria-hidden="true"></i> Distribution Batches</div>

    <?php if (($activeBatch ?? null) !== null): ?>
      <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span><strong><?= esc($activeBatch['name']) ?></strong> — open since <?= esc($activeBatch['started_at']) ?></span>
        <?php if ($canManageBatches): ?>
        <form method="post" action="<?= site_url('scanner/batches/close/' . (int) $activeBatch['batch_id']) ?>"
              onsubmit="return confirm('Close this batch? Statistics reset for the next batch.');">
          <?= csrf_field() ?>
          <button class="btn btn-warning btn-sm" type="submit">Close batch</button>
        </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary mb-3">No active batch. Scanning is paused until one is opened.</div>
      <?php if ($canManageBatches): ?>
      <form method="post" action="<?= site_url('scanner/batches/open') ?>" class="row g-2 align-items-end mb-3">
        <?= csrf_field() ?>
        <div class="col-auto flex-grow-1">
          <label for="batchName" class="form-label mb-0">Batch name</label>
          <input class="form-control" type="text" id="batchName" name="name" maxlength="100"
                 placeholder="e.g. Rice Distribution — <?= esc(date('M j, Y')) ?>" required>
        </div>
        <div class="col-auto">
          <button class="btn btn-primary" type="submit">Open batch</button>
        </div>
      </form>
      <?php endif; ?>
    <?php endif; ?>

    <table class="table manage-record-table align-middle w-100 mb-0">
      <thead><tr><th>Batch</th><th>Started</th><th>Closed</th></tr></thead>
      <tbody>
        <?php foreach (($batches ?? []) as $b): ?>
          <tr>
            <td><?= esc($b['name']) ?></td>
            <td><?= esc($b['started_at']) ?></td>
            <td><?= $b['closed_at'] === null ? '<span class="badge bg-success">Open</span>' : esc($b['closed_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (($batches ?? []) === []): ?>
          <tr><td colspan="3" class="text-muted">No batches yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
```

Include it in `manage.php` above the aid-types section, mirroring how the other body partials are pulled in.

- [ ] **Step 6: Run tests**

Run: `vendor/bin/phpunit --filter ManageControllerTest`
Expected: PASS. Then full suite: `vendor/bin/phpunit` — no new failures.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/Scanner/ManageController.php app/Config/Routes.php app/Views/Scanner/manage-batches-body.php app/Views/Scanner/manage.php tests/unit/ManageControllerTest.php
git commit -m "feat(scanner): admin batch open/close on manage page, audited"
```

---

### Task 5: ScanController — setting page, guards, batch stamping

**Files:**
- Modify: `app/Controllers/Scanner/ScanController.php`
- Modify: `app/Config/Routes.php` (scanner group)
- Test: `tests/unit/ScanControllerBatchTest.php` (new)

**Interfaces:**
- Consumes: `DistributionBatchModel::activeBatch()`, `AidDistributionModel::familiesForUserInBatch()`, `AidTypeModel::active()`.
- Produces:
  - `GET scanner/setting` → `setting()` — renders `Scanner/setting` (kiosk shell) with `activeBatch`, `aidTypes`, `myBatchCount`.
  - `scan()` now requires `?aid_type=N` (must be an active aid type) AND an open batch; otherwise redirects to `scanner/setting`. Passes `aidType` (row), `activeBatch`, `myBatchCount` to the view.
  - `logAid()` returns 409 `{errors:{general:...}}` when no open batch; stamps `batch_id`; JSON response gains `myBatchCount` (int).

- [ ] **Step 1: Write the failing tests** — `tests/unit/ScanControllerBatchTest.php`:

```php
<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ScanControllerBatchTest extends CIUnitTestCase
{
    public function testSettingRouteResolves(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $this->assertArrayHasKey('scanner/setting', $routes->getRoutes('GET'));
    }

    public function testScanGuardsAidTypeAndBatch(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        // scan() must fall back to the setting page when prerequisites are missing.
        $this->assertStringContainsString("redirect()->to('scanner/setting')", $src);
        // logAid() must refuse with 409 when no batch is open.
        $this->assertStringContainsString('setStatusCode(409)', $src);
        // Every logged row is stamped with the open batch and the response
        // carries the live personal counter.
        $this->assertStringContainsString("'batch_id'", $src);
        $this->assertStringContainsString('myBatchCount', $src);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter ScanControllerBatchTest`
Expected: FAIL — route missing, source assertions fail.

- [ ] **Step 3: Add route.** In the scanner group, before the `scan` line:

```php
    $routes->get('setting', 'Scanner\ScanController::setting');
```

- [ ] **Step 4: Rework `ScanController`.** Add `use App\Models\Scanner\DistributionBatchModel;`. Add:

```php
    /** GET scanner/setting — pick the aid type for this scanning session. */
    public function setting(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        $userId      = (int) (session('user_id') ?? 0);

        return view('Scanner/setting', [
            'pageTitle'    => 'Distribution Setting',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $activeBatch,
            'aidTypes'     => model(AidTypeModel::class)->active(),
            'myBatchCount' => $activeBatch !== null
                ? model(AidDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id'])
                : 0,
        ]);
    }
```

Rework `scan()` — replace the body after the guard with:

```php
        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return redirect()->to('scanner/setting');
        }

        $aidTypeId = (int) $this->request->getGet('aid_type');
        $aidType   = null;
        foreach (model(AidTypeModel::class)->active() as $t) {
            if ((int) $t['aid_type_id'] === $aidTypeId) {
                $aidType = $t;
                break;
            }
        }
        if ($aidType === null) {
            return redirect()->to('scanner/setting');
        }

        $userId = (int) (session('user_id') ?? 0);

        return view('Scanner/scan', [
            'pageTitle'    => 'Scan',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $activeBatch,
            'aidType'      => $aidType,
            'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id']),
        ]);
```

(The dashboard-shell keys — `activeTab`, `currentRole`, `canManageAccounts`, `sidebarRoleClass`, `sidebarUserUrl`, `navActive` — are no longer needed by the kiosk views; drop them from these two actions only.)

In `logAid()`, after the member-guard block and before `$userId = ...`, add:

```php
        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return $this->response->setStatusCode(409)
                ->setJSON(['errors' => ['general' => 'No active distribution batch. Ask an administrator to open one.']]);
        }
```

Add `'batch_id' => (int) $activeBatch['batch_id'],` to the `logAid([...])` data array. Change the success response to:

```php
        return $this->response->setJSON([
            'ok'           => true,
            'history'      => model(AidDistributionModel::class)->historyFor($controlNo),
            'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id']),
        ]);
```

Update the class docblock: setting() line, kiosk shell note, 409 semantics.

- [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit --filter ScanControllerBatchTest` → PASS. Full suite: `vendor/bin/phpunit` — fix any test still asserting the old scan() view keys.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Scanner/ScanController.php app/Config/Routes.php tests/unit/ScanControllerBatchTest.php
git commit -m "feat(scanner): setting action, batch guards, 409 without batch, live counter"
```

---

### Task 6: Kiosk shell + setting view

**Files:**
- Create: `app/Views/Scanner/kiosk-layout.php`
- Create: `app/Views/Scanner/setting.php`
- Test: `tests/unit/KioskViewTest.php` (new)

**Interfaces:**
- Consumes: view data from Task 5 (`activeBatch`, `aidTypes`, `myBatchCount`, `pageTitle`, `username`).
- Produces: kiosk layout with sections `content` and `scripts`; header element `#myBatchCount` (the live counter Task 7's JS updates). No sidebar, no topnav partials.

- [ ] **Step 1: Write the failing test** — `tests/unit/KioskViewTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class KioskViewTest extends CIUnitTestCase
{
    public function testKioskLayoutHasNoDashboardChrome(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/kiosk-layout.php');
        $this->assertStringNotContainsString('dashboard_sidebar', $src);
        $this->assertStringNotContainsString('dashboard-topnav', $src);
        $this->assertStringContainsString('myBatchCount', $src);
        $this->assertStringContainsString("renderSection('content')", $src);
    }

    public function testSettingViewExtendsKioskLayout(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/setting.php');
        $this->assertStringContainsString("extend('Scanner/kiosk-layout')", $src);
        $this->assertStringContainsString('scanner/scan', $src);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter KioskViewTest`
Expected: ERROR — files missing.

- [ ] **Step 3: Create `kiosk-layout.php`:**

```php
<?php
/**
 * Kiosk shell for the scan flow (setting + scan pages): full-viewport, no
 * sidebar/topbar. Deliberately minimal for time-and-motion — one slim header
 * bar (batch · aid type · live personal counter · change-type · logout) and
 * the page content. Reports and Manage stay in Scanner/layout (dashboard shell).
 */
$pageTitle    = $pageTitle ?? 'Scan';
$username     = $username ?? 'Scanner';
$activeBatch  = $activeBatch ?? null;
$aidType      = $aidType ?? null;
$myBatchCount = (int) ($myBatchCount ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin'), asset_styles('scanner')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-3">
  <span class="navbar-brand mb-0 h1">
    <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>
    <?= $activeBatch !== null ? esc($activeBatch['name']) : 'No active batch' ?>
  </span>
  <div class="d-flex align-items-center gap-3 text-white">
    <?php if ($aidType !== null): ?>
      <span class="badge bg-info text-dark"><?= esc($aidType['name']) ?></span>
      <a class="link-light small" href="<?= site_url('scanner/setting') ?>">Change type</a>
    <?php endif; ?>
    <span class="badge bg-success fs-6" title="Families you served this batch">
      You: <span id="myBatchCount"><?= $myBatchCount ?></span> families
    </span>
    <span class="small"><?= esc($username) ?></span>
    <a class="btn btn-outline-light btn-sm" href="<?= site_url('logout') ?>">Logout</a>
  </div>
</nav>
<main class="container-fluid px-4 py-3">
  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
  <?php endif; ?>
  <?= $this->renderSection('content') ?>
</main>
<?php foreach (array_merge(asset_scripts('foot'), asset_scripts('scanner')) as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
```

**Before committing:** open `Scanner/layout.php` and copy its exact script-loading loop (function names/groups may differ from `asset_scripts('foot')` — mirror whatever it does, including the idle-timeout script if present). Verify the logout route exists in `Routes.php` (`grep -n "logout" app/Config/Routes.php`) and use the actual path.

- [ ] **Step 4: Create `setting.php`:**

```php
<?= $this->extend('Scanner/kiosk-layout') ?>
<?= $this->section('content') ?>

<?php if (($activeBatch ?? null) === null): ?>
  <div class="text-center py-5">
    <i class="bi bi-pause-circle display-3 text-secondary" aria-hidden="true"></i>
    <div class="fw-bold mt-3">No active distribution</div>
    <div class="text-muted">Ask an administrator to open a batch, then refresh this page.</div>
  </div>
<?php else: ?>
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card border-0 rounded-3">
        <div class="card-body text-center">
          <div class="fw-bold fs-4 mb-1"><?= esc($activeBatch['name']) ?></div>
          <div class="text-muted small mb-4">Open since <?= esc($activeBatch['started_at']) ?></div>
          <form method="get" action="<?= site_url('scanner/scan') ?>">
            <label for="aidTypePick" class="form-label fw-bold">Aid type to distribute</label>
            <select class="form-select form-select-lg mb-3" id="aidTypePick" name="aid_type" required autofocus>
              <option value="">Choose aid type&hellip;</option>
              <?php foreach ($aidTypes as $type): ?>
                <option value="<?= esc($type['aid_type_id'], 'attr') ?>"><?= esc($type['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-success btn-lg w-100" type="submit">
              <i class="bi bi-upc-scan me-1" aria-hidden="true"></i> Start scanning
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
```

- [ ] **Step 5: Run tests**

Run: `vendor/bin/phpunit --filter KioskViewTest` → PASS.

- [ ] **Step 6: Manual smoke test**

`php spark serve` (intl-enabled php) → login as scanner (developer/developer123 works for role checks) → visit `/scanner/setting`. No batch: paused state. Open batch via `/scanner/manage` as admin → setting shows picker → Start scanning lands on `/scanner/scan?aid_type=N` (scan view reworked next task; a redirect loop must NOT occur).

- [ ] **Step 7: Commit**

```bash
git add app/Views/Scanner/kiosk-layout.php app/Views/Scanner/setting.php tests/unit/KioskViewTest.php
git commit -m "feat(scanner): kiosk shell and distribution-setting page"
```

---

### Task 7: Rework scan view for kiosk shell

**Files:**
- Modify: `app/Views/Scanner/scan.php`
- Test: `tests/unit/KioskViewTest.php` (append)

**Interfaces:**
- Consumes: `aidType` row + `activeBatch` + `myBatchCount` from Task 5; `#myBatchCount` element from kiosk header (Task 6); `myBatchCount` field in `logAid` JSON.
- Produces: scan page with no aid-type dropdown; fixed aid type from server.

- [ ] **Step 1: Append failing test** to `KioskViewTest.php`:

```php
    public function testScanViewUsesKioskLayoutWithoutAidDropdown(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/scan.php');
        $this->assertStringContainsString("extend('Scanner/kiosk-layout')", $src);
        $this->assertStringNotContainsString('sessionAidType', $src);
        $this->assertStringContainsString('myBatchCount', $src);
    }
```

Run: `vendor/bin/phpunit --filter KioskViewTest` → new test FAILS.

- [ ] **Step 2: Rework `scan.php`.** Precise changes to the existing file:

1. Line 1: `<?= $this->extend('Scanner/kiosk-layout') ?>`.
2. Delete the whole `col-md-4` aid-type block (label + `#sessionAidType` select + `#aidTypeHint`); widen the control-input column to `col-12` (single-row toolbar — scan input only).
3. Hidden input keeps working but is server-filled:
   ```php
   <input type="hidden" id="aid_type_id" name="aid_type_id" value="<?= esc($aidType['aid_type_id'], 'attr') ?>">
   ```
4. JS deltas:
   - Add near the top:
     ```js
     const AID_TYPE_NAME = <?= json_encode((string) $aidType['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
     ```
   - Delete `syncAidEmphasis()` (definition + both call sites) and the whole `$('sessionAidType').addEventListener('change', ...)` block.
   - In `lookup()`: remove the `if (!$('sessionAidType').value) {...}` gate and the `$('aidTypeHint').hidden = true;` and `$('aid_type_id').value = $('sessionAidType').value;` lines (the hidden input already holds the value).
   - In `evaluateDuplicate()`: `const aidId = $('aid_type_id').value;` and `const aidName = AID_TYPE_NAME;`.
   - In the submit handler: `const aidName = AID_TYPE_NAME;` and after `renderHistory(data.history);` add:
     ```js
     if (typeof data.myBatchCount === 'number') {
       document.getElementById('myBatchCount').textContent = String(data.myBatchCount);
     }
     ```
   - In the submit handler's error branch, surface a 409 like other errors (it already renders `data.errors` — no change needed, just verify).

Everything else (camera, focus guard, receipt, duplicate warning, bare-Enter confirm) stays byte-identical.

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit --filter KioskViewTest` → PASS. Full suite: `vendor/bin/phpunit`.

- [ ] **Step 4: Manual smoke test (time-and-motion critical)**

Full flow: setting → pick type → scan page → type a registered control number + Enter → family loads → Enter → logged, receipt shows, **header counter increments without reload**, input refocused. Close batch in another tab → next confirm returns 409 with readable error. Page fits viewport, no scrolling.

- [ ] **Step 5: Commit**

```bash
git add app/Views/Scanner/scan.php tests/unit/KioskViewTest.php
git commit -m "feat(scanner): scan page on kiosk shell, server-fixed aid type, live counter"
```

---

### Task 8: AidStatsModel — batch scope + perScanner

**Files:**
- Modify: `app/Models/Scanner/AidStatsModel.php`
- Test: `tests/unit/AidStatsModelTest.php` (append)

**Interfaces:**
- Produces:
  - `receivedVsNot(?string $from = null, ?string $to = null, ?int $batchId = null): array` (same keys).
  - `byBarangay(...same...): array`, `byAidType(...same...): array` — each gains trailing `?int $batchId = null`.
  - `perScanner(int $batchId, ?int $onlyUserId = null): array` — rows `['userID' => int, 'scanner' => string, 'handouts' => int, 'families' => int]`, most families first. `$onlyUserId` filters to one user (scanner-role view — server-side, not hidden client-side).

- [ ] **Step 1: Append failing tests** to `AidStatsModelTest.php`:

```php
    public function testMethodsAcceptBatchIdWithoutError(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->receivedVsNot(null, null, 3));
        $this->assertIsArray($m->byBarangay(null, null, 3));
        $this->assertIsArray($m->byAidType(null, null, 3));
    }

    public function testPerScannerReturnsArrayAndRejectsBadBatch(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->perScanner(1));
        $this->assertSame([], $m->perScanner(0));
    }
```

Run: `vendor/bin/phpunit --filter AidStatsModelTest` → FAIL (too many args / undefined method).

- [ ] **Step 2: Implement.** Replace `applyRange` with a combined scope helper and thread `$batchId` through:

```php
    /** Applies the optional date window and/or batch scope to aid_distribution. */
    private function applyScope($builder, ?string $from, ?string $to, ?int $batchId)
    {
        if ($batchId !== null && $batchId > 0) {
            $builder->where('aid_distribution.batch_id', $batchId);
        }
        if ($from !== null && $from !== '') {
            $builder->where('aid_distribution.claim_date >=', $from . ' 00:00:00');
        }
        if ($to !== null && $to !== '') {
            $builder->where('aid_distribution.claim_date <=', $to . ' 23:59:59');
        }

        return $builder;
    }
```

Each of the three existing methods: add `, ?int $batchId = null` to the signature and change its `$this->applyRange($b, $from, $to)` call(s) to `$this->applyScope($b, $from, $to, $batchId)`. Delete `applyRange`.

Add:

```php
    /**
     * Per-scanner performance within one batch: handouts logged and distinct
     * families (control numbers) served, most families first. $onlyUserId
     * narrows to a single user for the scanner-role reports view.
     */
    public function perScanner(int $batchId, ?int $onlyUserId = null): array
    {
        if ($batchId <= 0) {
            return [];
        }

        try {
            $b = $this->db->table('aid_distribution')
                ->select('aid_distribution.userID,'
                    . " COALESCE(users.username, 'Unknown') AS scanner,"
                    . ' COUNT(aid_distribution.aidID) AS handouts,'
                    . ' COUNT(DISTINCT aid_distribution.control_no) AS families')
                ->join('users', 'users.userID = aid_distribution.userID', 'left')
                ->where('aid_distribution.batch_id', $batchId)
                ->groupBy('aid_distribution.userID')
                ->orderBy('families', 'DESC')
                ->orderBy('scanner', 'ASC');
            if ($onlyUserId !== null) {
                $b->where('aid_distribution.userID', $onlyUserId);
            }

            return array_map(static fn ($r) => [
                'userID'   => (int) $r['userID'],
                'scanner'  => (string) $r['scanner'],
                'handouts' => (int) $r['handouts'],
                'families' => (int) $r['families'],
            ], $b->get()->getResultArray());
        } catch (\Throwable $e) {
            return [];
        }
    }
```

Update the class docblock (batch scope sentence).

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit --filter AidStatsModelTest` → PASS. Full suite clean.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Scanner/AidStatsModel.php tests/unit/AidStatsModelTest.php
git commit -m "feat(scanner): batch-scoped stats and per-scanner performance"
```

---

### Task 9: Reports — batch selector + role-aware performance section

**Files:**
- Modify: `app/Controllers/Scanner/ReportsController.php`
- Modify: `app/Views/Scanner/reports.php`
- Test: `tests/unit/ReportsBatchTest.php` (new)

**Interfaces:**
- Consumes: `AidStatsModel` batch params + `perScanner()` (Task 8); `DistributionBatchModel::allBatches()/activeBatch()` (Task 2).
- Produces: `GET scanner/reports?batch=N` scopes all numbers to batch N (date inputs ignored when batch set); new view data `batches`, `batchId` (?int), `batchName` (?string), `perScanner` (array), `isScannerRole` (bool).

- [ ] **Step 1: Write failing test** — `tests/unit/ReportsBatchTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsBatchTest extends CIUnitTestCase
{
    public function testControllerScopesByBatchAndRole(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ReportsController.php');
        $this->assertStringContainsString('perScanner', $src);
        // Scanner role sees only its own row — filtered server-side.
        $this->assertStringContainsString("'Scanner'", $src);
        $this->assertStringContainsString('getGet(\'batch\')', $src);
    }

    public function testReportsViewRendersPerformanceSection(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/reports.php');
        $this->assertStringContainsString('perScanner', $src);
        $this->assertStringContainsString('name="batch"', $src);
    }
}
```

Run: `vendor/bin/phpunit --filter ReportsBatchTest` → FAIL.

- [ ] **Step 2: Rework `ReportsController::index()`.** Add `use App\Models\Scanner\DistributionBatchModel;`. Replace the body between the guard and the `return view(...)` with:

```php
        [$from, $to] = $this->normalizeDates();

        $batchModel = model(DistributionBatchModel::class);
        $batches    = $batchModel->allBatches();

        $batchId = (int) $this->request->getGet('batch');
        $batch   = null;
        foreach ($batches as $b) {
            if ((int) $b['batch_id'] === $batchId) {
                $batch = $b;
                break;
            }
        }
        // Batch scope and date scope are alternative filters: a chosen batch
        // wins and the date window is cleared to keep the label truthful.
        if ($batch !== null) {
            [$from, $to] = [null, null];
        } else {
            $batchId = 0;
        }

        $role          = RoleAccess::normalizeRole((string) session()->get('role'));
        $isScannerRole = $role === 'Scanner';
        $canManage     = in_array($role, ['Developer', 'Admin'], true);

        $stats      = model(AidStatsModel::class);
        $scope      = $batchId > 0 ? $batchId : null;
        $summary    = $stats->receivedVsNot($from, $to, $scope);
        $byBarangay = $stats->byBarangay($from, $to, $scope);
        $byAidType  = $stats->byAidType($from, $to, $scope);
        $perScanner = $batchId > 0
            ? $stats->perScanner($batchId, $isScannerRole ? (int) (session('user_id') ?? 0) : null)
            : [];
```

and extend the view-data array with:

```php
            'batches'       => $batches,
            'batchId'       => $batchId > 0 ? $batchId : null,
            'batchName'     => $batch['name'] ?? null,
            'perScanner'    => $perScanner,
            'isScannerRole' => $isScannerRole,
```

Apply the same batch resolution in `pdf()` (parse `batch` param, clear dates when set, pass `$scope` as the third arg to the three stats calls, compute `$perScanner` only when `! $isScannerRole`), and pass `$perScanner` + `$batch['name'] ?? null` to the generator (Task 10 extends its signature — do Tasks 9 and 10 together before running the full suite, or temporarily keep `pdf()` unchanged until Task 10; prefer doing 9 then 10 then one shared verification).

- [ ] **Step 3: Rework `reports.php`.** Three additions, house style (`components/card`, `components/data_table`, `components/stat_card`):

1. Batch selector — inside the existing `.reports-filter` form, before the From input:

```php
    <label for="batchPick" class="form-label mb-0">Batch</label>
    <select class="form-select" id="batchPick" name="batch" onchange="this.form.submit()">
      <option value="">All dates (use range)</option>
      <?php foreach ($batches as $b): ?>
        <option value="<?= esc($b['batch_id'], 'attr') ?>" <?= ((int) ($batchId ?? 0)) === (int) $b['batch_id'] ? 'selected' : '' ?>>
          <?= esc($b['name']) ?><?= $b['closed_at'] === null ? ' (open)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
```

2. Range label — replace the `$rangeLabel` computation's first line so a batch wins:

```php
 <?php $rangeLabel = ($batchName ?? null) !== null
     ? 'Showing batch: ' . esc($batchName)
     : ($from || $to
         ? 'Showing ' . ($from ? esc($from) : 'the beginning') . ' to ' . ($to ? esc($to) : 'today')
         : 'Showing all dates'); ?>
```

3. Performance section — after the KPI tiles (`</section>`), render only when a batch is selected:

```php
<?php if (($batchId ?? null) !== null): ?>
<?php
$scannerRows = [];
foreach ($perScanner as $p) {
    $scannerRows[] = [
        esc($p['scanner']),
        esc((string) $p['families']),
        esc((string) $p['handouts']),
    ];
}
?>
<?= view('components/data_table', [
    'icon' => 'person-badge',
    'title' => ($isScannerRole ?? false) ? 'My performance this batch' : 'Scanner performance this batch',
    'columns' => ['Scanner', 'Families served', 'Handouts logged'],
    'rows' => $scannerRows,
    'emptyMessage' => 'No scans in this batch yet.',
    'tableClass' => 'table manage-record-table align-middle w-100 mb-0',
    'footer' => $rangeLabel,
]) ?>
<?php endif; ?>
```

4. Download link — append the batch param so the PDF matches the screen: extend the existing query-string expression with `($batchId ?? null ? '&batch=' . (int) $batchId : '')` (and make it produce `?batch=N` when only batch is set — mirror the existing from/to conditional structure).

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit --filter ReportsBatchTest` → PASS. Full suite clean (pdf() may still be pre-Task-10 — keep it compiling).

- [ ] **Step 5: Manual smoke test** — as Admin: reports show batch dropdown; picking the open batch shows per-scanner table + batch-scoped tiles. As Scanner (seed account): same page shows only own row and title "My performance this batch".

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Scanner/ReportsController.php app/Views/Scanner/reports.php tests/unit/ReportsBatchTest.php
git commit -m "feat(scanner): batch selector and role-aware performance on reports"
```

---

### Task 10: PDF export batch scope

**Files:**
- Modify: `app/Libraries/Scanner/ReportsPdfGenerator.php`
- Modify: `app/Views/Scanner/pdf/report.php`
- Modify: `app/Controllers/Scanner/ReportsController.php::pdf()` (finish Task 9's step)

**Interfaces:**
- Consumes: `perScanner` rows (Task 8), batch resolution (Task 9).
- Produces: `generate()` gains two trailing optional params: `array $perScanner = []`, `?string $batchName = null`. Per-scanner table renders only when `$perScanner !== []` (controller passes it only for Admin/Developer).

- [ ] **Step 1: Read the generator + pdf view first** (`app/Libraries/Scanner/ReportsPdfGenerator.php`, `app/Views/Scanner/pdf/report.php`) — mirror their existing table/heading style exactly.

- [ ] **Step 2: Extend `generate()` signature** with `array $perScanner = [], ?string $batchName = null`, pass both into the view data. In the pdf view: when `$batchName` is set, use it in the heading/range line instead of the date range; when `$perScanner` is non-empty, render a "Scanner performance" table (Scanner / Families served / Handouts logged) in the same table style as the barangay section.

- [ ] **Step 3: Finish `ReportsController::pdf()`** per Task 9 step 2 (batch param, `$scope` threading, `$perScanner` for non-scanner roles only, filename gains `-batchN` suffix when scoped):

```php
        $name = 'aid-report-' . ($batchId > 0 ? 'batch' . $batchId : (($from ?: 'start') . '_' . ($to ?: 'today'))) . '.pdf';
```

- [ ] **Step 4: Verify** — full suite `vendor/bin/phpunit` clean; manual: download PDF with batch selected (admin) → contains batch name + per-scanner table; as scanner → no per-scanner table.

- [ ] **Step 5: Commit**

```bash
git add app/Libraries/Scanner/ReportsPdfGenerator.php app/Views/Scanner/pdf/report.php app/Controllers/Scanner/ReportsController.php
git commit -m "feat(scanner): PDF export honors batch scope with admin per-scanner table"
```

---

### Task 11: Knowledge base (RAG) update + final verification

**Files:**
- Create: `docs/knowledge/binan-conventions/scanner-batches.md`
- Modify: `docs/knowledge/violations.md` (only if items were fixed/found)
- Modify: `.claude/skills/binan-conventions/SKILL.md` (grep index — read it first; add entries pointing at the new doc)

- [ ] **Step 1: Write `scanner-batches.md`.** Read one existing doc in `docs/knowledge/binan-conventions/` first and copy its format. Content must cover:
  - Batch concept: `distribution_batch`, single-open invariant lives in `DistributionBatchModel::open()`, close = manual stats reset, `aid_distribution.batch_id` NULL = pre-batch history.
  - Shell convention: **kiosk shell** (`Views/Scanner/kiosk-layout.php`) for setting + scan pages; **dashboard shell** (`Views/Scanner/layout.php`) for reports + manage. New scanner-facing scan-flow pages go in the kiosk shell.
  - Flow: `scanner/setting` (aid-type pick) → `scanner/scan?aid_type=N`; scan/log blocked without an open batch (redirect / 409).
  - Role rules: batch open/close = Admin/Developer only, audited; reports per-scanner table filtered server-side for Scanner role.

- [ ] **Step 2: Update the binan-conventions grep index** so "batch", "kiosk", "scanner shell", "perScanner" route to the new doc.

- [ ] **Step 3: Update `violations.md`** only for items this branch actually fixed or verified-new items.

- [ ] **Step 4: Final verification**
  - `vendor/bin/phpunit` — full suite green (sqlite3-skips OK).
  - `php spark routes` — every new route resolves.
  - End-to-end smoke: open batch (admin) → setting → scan → counter increments → reports batch view (admin + scanner) → PDF → close batch → scan blocked (409 / setting paused state).

- [ ] **Step 5: Commit**

```bash
git add docs/knowledge/ .claude/skills/binan-conventions/SKILL.md
git commit -m "docs(knowledge): scanner batch concept and kiosk-shell convention"
```

After this task: use superpowers:finishing-a-development-branch (CodeRabbit review via `coderabbit review --base main --agent` before PR, per CLAUDE.md).
