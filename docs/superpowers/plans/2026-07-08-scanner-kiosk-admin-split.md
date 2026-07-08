# Scanner Kiosk / Admin-Server Split — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the scanner module by URL prefix — `/admin/*` owns aid-types, batch control (incl. aid selection), and overall reports; `/scanner/*` becomes a pure green-themed kiosk (scan + own performance) — with aid type bound to the batch and batch-only report scope.

**Architecture:** Aid type moves onto `distribution_batch` (one aid type per batch, chosen at open). The kiosk logs against the active batch's aid type — no picker. Management surfaces move into the admin dashboard shell; the kiosk keeps only scan + a self-scoped performance page that polls a JSON stats endpoint every 5s. The old Scanner dashboard shell (`Scanner/layout.php`) and aid-picker (`scanner/setting`) are retired.

**Tech Stack:** CodeIgniter 4 (PHP 8.2+), MySQL/InnoDB, Bootstrap 5 + SB Admin 1, Chart.js, html5-qrcode, PHPUnit.

## Global Constraints

- **No migrations.** Schema changes ship as a new SQL dump `accesscardV16.sql` (copy of V15 + the batch column). Never add CI4 migrations or alter schema in code.
- **Match the dump exactly** — column names, enum values, role names. Employee accounts store as `User`.
- **Every family mutation writes an audit trail** via `Audit/AuditTrailsModel`. Batch open/close and aid logging already do — keep it.
- **Controllers decide, libraries build.** Dashboard view-data assembly stays in controllers/`DashboardPageBuilder`; do not move logic into views.
- **PHP 8.2+**, typed signatures everywhere, **no** `declare(strict_types=1)`. Match existing namespace conventions.
- **No-DB test posture:** scanner models return safe empty shapes on any `\Throwable` — preserve this in every model edit.
- Run `vendor/bin/phpunit` before and after each task; `php spark routes` after any route change.

---

## File Structure

**New:**
- `accesscardV16.sql` — V15 + `distribution_batch.aid_type_id`.
- `app/Controllers/Admin/DistributionController.php` — admin aid-types + batches (moved from `Scanner\ManageController`).
- `app/Controllers/Admin/ReportsController.php` — admin overall reports (moved from `Scanner\ReportsController`).
- `app/Views/Admin/distribution.php` — admin aid-types + batches page (admin shell).
- `app/Views/Admin/reports.php` — admin reports page (admin shell).
- `app/Views/Scanner/performance.php` — kiosk own-stats page (kiosk shell).

**Modified:**
- `accesscardV15.sql` reference in memory/docs → point demos at V16.
- `app/Models/Scanner/DistributionBatchModel.php` — `open()` takes aid type; `allowedFields` + joins expose it.
- `app/Models/Scanner/AidStatsModel.php` — drop date params, batch-only scope.
- `app/Controllers/Scanner/ScanController.php` — drop `setting()`; `scan()` no aid param; `logAid()` reads aid from batch; add `stats()` JSON; add `performance()`.
- `app/Config/Routes.php` — new admin routes; slim scanner group.
- `app/Views/components/dashboard_sidebar.php` — admin nav points to new admin routes; retire scanner-only variant links.
- `app/Views/Scanner/kiosk-layout.php` — green theme; remove "Change type"; aid badge from batch.
- `app/Views/Scanner/scan.php` — no-batch empty state; aid from batch.

**Deleted (after moves land):**
- `app/Controllers/Scanner/ManageController.php`, `app/Controllers/Scanner/ReportsController.php`
- `app/Views/Scanner/setting.php`, `manage.php`, `manage-*-body.php`, `reports.php`, `layout.php`

---

### Task 1: New SQL dump V16 with `distribution_batch.aid_type_id`

**Files:**
- Create: `accesscardV16.sql` (copy of `accesscardV15.sql` with the column added)

**Interfaces:**
- Produces: `distribution_batch.aid_type_id INT(11) NOT NULL` — consumed by all later tasks.

- [ ] **Step 1: Copy V15 to V16**

```bash
cp accesscardV15.sql accesscardV16.sql
```

- [ ] **Step 2: Add the column to the CREATE TABLE**

Edit `accesscardV16.sql`, in the `CREATE TABLE \`distribution_batch\`` block, add the column after `name` and a key. Result:

```sql
CREATE TABLE `distribution_batch` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `aid_type_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `idx_db_aidtype` (`aid_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

(If `distribution_batch` INSERT rows exist in the dump, add the aid_type_id value to each row tuple; V15 ships batches empty per the V15 memo, so there should be none.)

- [ ] **Step 3: Apply the dump locally to verify it imports**

```bash
mysql -h 127.0.0.1 -u root accesscard < accesscardV16.sql && echo "import ok"
```
Expected: `import ok`, no SQL error.

- [ ] **Step 4: Verify the column exists**

```bash
mysql -h 127.0.0.1 -u root accesscard -e "SHOW COLUMNS FROM distribution_batch LIKE 'aid_type_id';"
```
Expected: one row naming `aid_type_id`.

- [ ] **Step 5: Commit**

```bash
git add accesscardV16.sql
git commit -m "feat(db): V16 dump adds distribution_batch.aid_type_id"
```

---

### Task 2: Batch model carries aid type

**Files:**
- Modify: `app/Models/Scanner/DistributionBatchModel.php`
- Test: `tests/Scanner/DistributionBatchModelTest.php` (create if absent; skips without sqlite3 — mirror existing scanner model tests)

**Interfaces:**
- Consumes: `distribution_batch.aid_type_id` (Task 1).
- Produces:
  - `open(string $name, int $aidTypeId, int $userId): int`
  - `activeBatch(): ?array` — now includes `aid_type_id` and `aid_type_name` (joined).
  - `allBatches(): array` — each row includes `aid_type_id`, `aid_type_name`.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Scanner;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class DistributionBatchModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    public function testOpenRequiresAidType(): void
    {
        if (! extension_loaded('sqlite3')) {
            $this->markTestSkipped('sqlite3 not available');
        }
        $m = new DistributionBatchModel();
        $this->assertSame(0, $m->open('Batch A', 0, 1), 'aid type 0 must refuse');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter testOpenRequiresAidType`
Expected: FAIL (`open()` currently takes 2 args) or error on arg count.

- [ ] **Step 3: Update the model**

In `DistributionBatchModel.php`:
- Add `'aid_type_id'` to `$allowedFields`.
- Change `open()`:

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

- Change `activeBatch()` and `allBatches()` to join the aid type name. Replace the query bodies:

```php
    public function activeBatch(): ?array
    {
        try {
            $row = $this->select('distribution_batch.*, aid_type.name AS aid_type_name')
                ->join('aid_type', 'aid_type.aid_type_id = distribution_batch.aid_type_id', 'left')
                ->where('distribution_batch.closed_at', null)
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function allBatches(): array
    {
        try {
            return $this->select('distribution_batch.*, aid_type.name AS aid_type_name')
                ->join('aid_type', 'aid_type.aid_type_id = distribution_batch.aid_type_id', 'left')
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter testOpenRequiresAidType`
Expected: PASS (or SKIP without sqlite3 — acceptable).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: green (existing `open()` 2-arg callers will still fail to compile — they are updated in Task 5/6; if any test calls `open()` with 2 args, update it in the same commit).

- [ ] **Step 6: Commit**

```bash
git add app/Models/Scanner/DistributionBatchModel.php tests/Scanner/DistributionBatchModelTest.php
git commit -m "feat(scanner): batch carries aid_type_id; open() requires it"
```

---

### Task 3: `AidStatsModel` — batch-only scope, drop date params

**Files:**
- Modify: `app/Models/Scanner/AidStatsModel.php`
- Test: `tests/Scanner/AidStatsModelTest.php` (extend if present; else add a shape test)

**Interfaces:**
- Produces (new signatures):
  - `receivedVsNot(?int $batchId = null): array`
  - `byBarangay(?int $batchId = null): array`
  - `byAidType(?int $batchId = null): array`
  - `perScanner(int $batchId, ?int $onlyUserId = null): array` (unchanged)
  - `applyScope($builder, ?int $batchId)` (private, date args removed)

- [ ] **Step 1: Write the failing test**

```php
public function testReceivedVsNotTakesBatchOnly(): void
{
    if (! extension_loaded('sqlite3')) {
        $this->markTestSkipped('sqlite3 not available');
    }
    $out = (new \App\Models\Scanner\AidStatsModel())->receivedVsNot(null);
    $this->assertArrayHasKey('coverage', $out);
    $this->assertArrayHasKey('received', $out);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter testReceivedVsNotTakesBatchOnly`
Expected: FAIL (arg mismatch — current signature is `($from, $to, $batchId)`).

- [ ] **Step 3: Update the model**

- Replace `applyScope`:

```php
    private function applyScope($builder, ?int $batchId)
    {
        if ($batchId !== null && $batchId > 0) {
            $builder->where('aid_distribution.batch_id', $batchId);
        }

        return $builder;
    }
```

- Change each public method's signature to `(?int $batchId = null)` and update the internal `applyScope(...)` calls to pass only `$batchId`. Concretely:
  - `receivedVsNot(?int $batchId = null)`: `$this->applyScope($b, $batchId);`
  - `byBarangay(?int $batchId = null)`: `$this->applyScope($rb, $batchId);`
  - `byAidType(?int $batchId = null)`: `$this->applyScope($b, $batchId);`
- Leave `perScanner()` unchanged.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter testReceivedVsNotTakesBatchOnly`
Expected: PASS or SKIP.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidStatsModel.php tests/Scanner/AidStatsModelTest.php
git commit -m "refactor(scanner): AidStats batch-only scope, drop date window"
```

---

### Task 4: Admin `DistributionController` (aid-types + batches)

**Files:**
- Create: `app/Controllers/Admin/DistributionController.php`
- Create: `app/Views/Admin/distribution.php`
- Modify: `app/Config/Routes.php`

**Interfaces:**
- Consumes: `DistributionBatchModel::open($name,$aidTypeId,$userId)` (Task 2), `AidTypeModel::active()/all()`, `AidDistributionModel::allDistributions()`.
- Produces routes under `/admin/`: `aid-types`, `aid-types/create|archive|restore|delete`, `batches`, `batches/open|close`, `distributions/void`.

This controller is the moved `Scanner\ManageController` body, re-guarded to **Admin/Developer only** and rendered in the admin shell. Port every action verbatim except: `requireRole(['Admin','Developer'])` on all actions, redirect targets `admin/distribution`, `openBatch()` reads `aid_type_id` and passes it to `open()`, and `index()` renders the admin shell.

- [ ] **Step 1: Create the controller**

```php
<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAccount;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidTypeModel;
use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin server: aid-type catalogue + distribution-batch control. Admin/Developer
 * only. Batch open binds the aid type for the whole batch. Every mutation writes
 * an audit_trails row. Rendered in the admin dashboard shell.
 */
class DistributionController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** GET admin/distribution — aid types + batches hub. */
    public function index(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        return view('Admin/distribution', [
            'pageTitle'         => 'Distribution',
            'username'          => session('username') ?? 'Admin',
            'user'              => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'aidTypes'          => model(AidTypeModel::class)->all(),
            'activeAidTypes'    => model(AidTypeModel::class)->active(),
            'distributions'     => model(AidDistributionModel::class)->allDistributions(),
            'batches'           => model(DistributionBatchModel::class)->allBatches(),
            'activeBatch'       => model(DistributionBatchModel::class)->activeBatch(),
            'currentRole'       => $role,
            'canManageAccounts' => true,
            'sidebarRoleClass'  => strtolower((string) $role),
            'navActive'         => ['distribution' => 'active'],
        ]);
    }

    public function createAidType(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('admin/distribution')->with('error', 'Aid type name is required.');
        }
        $id = model(AidTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to add aid type.');
        }
        $this->audit('Created aid type "' . $name . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type added.');
    }

    public function archiveAidType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->archive($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to archive aid type.');
        }
        $this->audit('Archived aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type archived.');
    }

    public function restoreAidType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->restore($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to restore aid type.');
        }
        $this->audit('Restored aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type restored.');
    }

    public function deleteAidType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $used = model(AidDistributionModel::class)->where('aid_type_id', $id)->countAllResults();
        if ($used > 0) {
            return redirect()->to('admin/distribution')
                ->with('error', 'Cannot delete: aid type is used by ' . $used . ' distribution(s). Archive it instead.');
        }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->delete($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to delete aid type.');
        }
        $this->audit('Deleted aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type deleted.');
    }

    public function voidDistribution(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $row = model(AidDistributionModel::class)->find($id);
        if ($row === null) {
            return redirect()->to('admin/distribution')->with('error', 'Distribution not found.');
        }
        if (! model(AidDistributionModel::class)->void($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to void distribution.');
        }
        $this->audit(
            'Voided aid distribution #' . $id,
            (int) ($row['memberID'] ?? 0),
            'Control #' . (int) ($row['control_no'] ?? 0) . ', aid type ID ' . (int) ($row['aid_type_id'] ?? 0) . ', claim date ' . (string) ($row['claim_date'] ?? '')
        );
        return redirect()->to('admin/distribution')->with('success', 'Distribution voided.');
    }

    /** POST admin/batches/open — name + aid type. */
    public function openBatch(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name      = trim((string) $this->request->getPost('name'));
        $aidTypeId = (int) $this->request->getPost('aid_type_id');
        if ($name === '') {
            return redirect()->to('admin/distribution')->with('error', 'Batch name is required.');
        }
        if ($aidTypeId <= 0) {
            return redirect()->to('admin/distribution')->with('error', 'Choose an aid type for this batch.');
        }
        $id = model(DistributionBatchModel::class)->open($name, $aidTypeId, (int) (session('user_id') ?? 0));
        if ($id <= 0) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to open batch. Close the active batch first.');
        }
        $this->audit('Opened distribution batch "' . $name . '" #' . $id . ' (aid type ID ' . $aidTypeId . ')');
        return redirect()->to('admin/distribution')->with('success', 'Batch opened. Scanning is now live.');
    }

    public function closeBatch(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $batch = model(DistributionBatchModel::class)->find($id);
        if (! model(DistributionBatchModel::class)->close($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to close batch.');
        }
        $this->audit('Closed distribution batch "' . (string) ($batch['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Batch closed. Statistics reset for the next batch.');
    }

    private function audit(string $action, int $memberId = 0, ?string $detail = null): void
    {
        (new AuditTrailsModel())->logAction(
            (int) (session('user_id') ?? 0),
            $memberId > 0 ? $memberId : null,
            $action,
            null,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $detail
        );
    }
}
```

- [ ] **Step 2: Create the view (admin shell)**

Create `app/Views/Admin/distribution.php` extending the admin dashboard layout that other admin pages use (open an existing admin view such as `app/Views/Admin/cards.php` to copy its `extend`/`section` wrapper exactly). Inside `content`, render two cards, reusing the old scanner partial markup as the starting point:
- **Aid types** card: port the table + create/archive/restore/delete forms from `app/Views/Scanner/manage-aidtypes-body.php`, changing every `site_url('scanner/aid-types/...')` to `site_url('admin/aid-types/...')`.
- **Batches** card: port from `app/Views/Scanner/manage-batches-body.php`, change `scanner/batches/...` → `admin/batches/...`, and add a **required aid-type `<select name="aid_type_id">`** to the open-batch form populated from `$activeAidTypes` (each `<option value="<?= aid_type_id ?>">name</option>`). Show each batch row's `aid_type_name`.

Match the flash-message and card conventions already used in the admin views (verify against `app/Views/Admin/cards.php`).

- [ ] **Step 3: Add routes**

In `app/Config/Routes.php`, inside the `admin` group, add:

```php
    $routes->group('distribution', static function (RouteCollection $routes): void {
        $routes->get('', 'Admin\DistributionController::index');
    });
    $routes->get('distribution', 'Admin\DistributionController::index');
    $routes->group('aid-types', static function (RouteCollection $routes): void {
        $routes->post('create', 'Admin\DistributionController::createAidType');
        $routes->post('archive/(:num)', 'Admin\DistributionController::archiveAidType/$1');
        $routes->post('restore/(:num)', 'Admin\DistributionController::restoreAidType/$1');
        $routes->post('delete/(:num)', 'Admin\DistributionController::deleteAidType/$1');
    });
    $routes->group('batches', static function (RouteCollection $routes): void {
        $routes->post('open', 'Admin\DistributionController::openBatch');
        $routes->post('close/(:num)', 'Admin\DistributionController::closeBatch/$1');
    });
    $routes->post('distributions/void/(:num)', 'Admin\DistributionController::voidDistribution/$1');
```

- [ ] **Step 4: Verify routes resolve**

Run: `php spark routes | grep -E "admin/(distribution|aid-types|batches|distributions)"`
Expected: each route maps to `Admin\DistributionController::*`.

- [ ] **Step 5: Manual smoke — open a batch with an aid type**

Run `php spark serve`, log in as admin, go to `/admin/distribution`, open a batch selecting an aid type. Expected: success flash, batch row shows the aid type name, second open blocked while active.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Admin/DistributionController.php app/Views/Admin/distribution.php app/Config/Routes.php
git commit -m "feat(admin): distribution control (aid-types + batches) in admin shell"
```

---

### Task 5: Kiosk `ScanController` — aid from batch, no setting, add stats + performance

**Files:**
- Modify: `app/Controllers/Scanner/ScanController.php`
- Modify: `app/Views/Scanner/scan.php`
- Create: `app/Views/Scanner/performance.php`
- Modify: `app/Config/Routes.php`

**Interfaces:**
- Consumes: `DistributionBatchModel::activeBatch()` (now has `aid_type_id`, `aid_type_name`), `AidStatsModel::perScanner($batchId, $userId)`, `AidDistributionModel::familiesForUserInBatch()`.
- Produces routes: `scanner/scan`, `scanner/performance`, `scanner/stats` (JSON), `scanner/lookup/(:num)`, `scanner/log`. Removes `scanner/setting`.

- [ ] **Step 1: Rewrite `scan()` — no aid param, aid from batch**

Replace `setting()` and `scan()` with a single `scan()`:

```php
    /** GET scanner/scan — kiosk lookup UI. Aid type comes from the active batch. */
    public function scan(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        $userId      = (int) (session('user_id') ?? 0);

        return view('Scanner/scan', [
            'pageTitle'    => 'Scan',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $activeBatch,
            'aidType'      => $activeBatch !== null
                ? ['aid_type_id' => (int) $activeBatch['aid_type_id'], 'name' => (string) ($activeBatch['aid_type_name'] ?? 'Aid')]
                : null,
            'myBatchCount' => $activeBatch !== null
                ? model(AidDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id'])
                : 0,
        ]);
    }
```

Delete the `setting()` method entirely.

- [ ] **Step 2: `logAid()` reads aid type from the batch, not POST**

In `logAid()`, remove `'aid_type_id' => 'required|is_natural_no_zero',` from `$rules`. After resolving `$activeBatch` (the existing 409-on-null block), set:

```php
        $aidTypeId = (int) $activeBatch['aid_type_id'];
```

and in the `logAid([...])` array use `'aid_type_id' => $aidTypeId,`. In the audit `logAction` detail string, replace `(int) $this->request->getPost('aid_type_id')` with `$aidTypeId` (both occurrences).

- [ ] **Step 3: Add `stats()` JSON endpoint (kiosk poll)**

```php
    /** GET scanner/stats — JSON own-performance snapshot for kiosk polling. */
    public function stats(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        $userId      = (int) (session('user_id') ?? 0);
        if ($activeBatch === null) {
            return $this->response->setJSON(['batch' => null, 'families' => 0, 'handouts' => 0]);
        }

        $batchId = (int) $activeBatch['batch_id'];
        $rows    = model(\App\Models\Scanner\AidStatsModel::class)->perScanner($batchId, $userId);
        $mine    = $rows[0] ?? ['families' => 0, 'handouts' => 0];

        return $this->response->setJSON([
            'batch'    => ['id' => $batchId, 'name' => (string) $activeBatch['name'], 'aid_type' => (string) ($activeBatch['aid_type_name'] ?? '')],
            'families' => (int) ($mine['families'] ?? 0),
            'handouts' => (int) ($mine['handouts'] ?? 0),
            'updated'  => date('c'),
        ]);
    }
```

- [ ] **Step 4: Add `performance()` page (kiosk shell)**

```php
    /** GET scanner/performance — this kiosk's own live metrics. */
    public function performance(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $batchModel = model(DistributionBatchModel::class);
        $active     = $batchModel->activeBatch();
        $batches    = $batchModel->allBatches();
        $batchId    = (int) $this->request->getGet('batch');
        if ($batchId <= 0) {
            $batchId = $active !== null ? (int) $active['batch_id'] : (int) ($batches[0]['batch_id'] ?? 0);
        }

        $userId = (int) (session('user_id') ?? 0);
        $stats  = model(\App\Models\Scanner\AidStatsModel::class);

        return view('Scanner/performance', [
            'pageTitle'    => 'My Performance',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $active,
            'batches'      => $batches,
            'batchId'      => $batchId,
            'mine'         => $batchId > 0 ? ($stats->perScanner($batchId, $userId)[0] ?? ['families' => 0, 'handouts' => 0]) : ['families' => 0, 'handouts' => 0],
            'byAidType'    => $batchId > 0 ? $stats->byAidType($batchId) : [],
        ]);
    }
```

- [ ] **Step 5: Create `app/Views/Scanner/performance.php`**

Extend `Scanner/kiosk-layout` (`<?= $this->extend('Scanner/kiosk-layout') ?>`). In the `content` section render: big stat tiles (families, handouts) bound to ids `#statFamilies` / `#statHandouts`, a batch `<select>` (reload on change → `?batch=`), a Chart.js canvas for `byAidType`, a "Last updated <span id='lastUpdated'>—</span>" line, and a `<button id="refreshNow">Refresh</button>`. In the `scripts` section add the poll:

```php
<?= $this->section('scripts') ?>
<script>
(function () {
  var url = '<?= site_url('scanner/stats') ?>';
  function paint(d) {
    document.getElementById('statFamilies').textContent = d.families;
    document.getElementById('statHandouts').textContent = d.handouts;
    document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
  }
  function poll() { fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(function (r) { return r.json(); }).then(paint).catch(function () {}); }
  document.getElementById('refreshNow').addEventListener('click', poll);
  poll();
  setInterval(poll, 5000);
})();
</script>
<?= $this->endSection() ?>
```

(Use the existing Chart.js include pattern from `app/Views/Scanner/reports.php` for the aid-type chart.)

- [ ] **Step 6: Update `scan.php` — no-batch empty state, aid from batch**

In `app/Views/Scanner/scan.php`, wrap the scan UI so that when `$activeBatch === null` it shows an alert: *"No active distribution batch. Ask an administrator to start one."* and hides the scan input. Remove any hidden `aid_type` input that posted the aid type; `logAid` now derives it server-side. Keep posting `control_no`, `memberID`, `claim_date`.

- [ ] **Step 7: Update routes**

In `app/Config/Routes.php` replace the scanner group with:

```php
$routes->group('scanner', static function (RouteCollection $routes): void {
    $routes->get('scan', 'Scanner\ScanController::scan');
    $routes->get('performance', 'Scanner\ScanController::performance');
    $routes->get('stats', 'Scanner\ScanController::stats');
    $routes->get('lookup/(:num)', 'Scanner\ScanController::lookup/$1');
    $routes->post('log', 'Scanner\ScanController::logAid');
});
```

- [ ] **Step 8: Verify routes + no dangling references**

Run:
```bash
php spark routes | grep scanner
grep -rn "scanner/setting\|scanner/manage\|scanner/reports\|scanner/aid-types\|scanner/batches\|scanner/distributions" app/
```
Expected: routes show only scan/performance/stats/lookup/log; grep returns nothing (fix any hit).

- [ ] **Step 9: Manual smoke**

With a batch open (aid type set in Task 4), `/scanner/scan` logs against that aid type; `/scanner/performance` shows live tiles that tick up ~5s after a scan; with no batch open, `/scanner/scan` shows the empty state (no redirect loop).

- [ ] **Step 10: Run suite + commit**

```bash
vendor/bin/phpunit
git add app/Controllers/Scanner/ScanController.php app/Views/Scanner/scan.php app/Views/Scanner/performance.php app/Config/Routes.php
git commit -m "feat(scanner): kiosk logs against batch aid type; add stats poll + performance page"
```

---

### Task 6: Admin `ReportsController` (overall reports)

**Files:**
- Create: `app/Controllers/Admin/ReportsController.php`
- Create: `app/Views/Admin/reports.php`
- Modify: `app/Config/Routes.php`

**Interfaces:**
- Consumes: `AidStatsModel` (Task 3 batch-only signatures), `DistributionBatchModel::allBatches()/activeBatch()`, `ReportsPdfGenerator`.
- Produces routes: `admin/reports`, `admin/reports/pdf`.

Port `Scanner\ReportsController`, dropping all date handling and the scanner-role self-scoping (admin always sees all kiosks).

- [ ] **Step 1: Create the controller**

```php
<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAccount;
use App\Models\Scanner\AidStatsModel;
use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin overall aid-distribution reports: combined totals + per-kiosk table,
 * batch-scoped (no date filter). PDF export. Admin/Developer only.
 */
class ReportsController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** [batchId, batch|null] resolved against known batches, defaulting to active/latest. */
    private function resolveBatch(array $batches, ?array $active): array
    {
        $batchId = (int) $this->request->getGet('batch');
        if ($batchId <= 0) {
            $batchId = $active !== null ? (int) $active['batch_id'] : (int) ($batches[0]['batch_id'] ?? 0);
        }
        foreach ($batches as $b) {
            if ((int) $b['batch_id'] === $batchId) {
                return [$batchId, $b];
            }
        }
        return [0, null];
    }

    public function index(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        $batchModel        = model(DistributionBatchModel::class);
        $batches           = $batchModel->allBatches();
        [$batchId, $batch] = $this->resolveBatch($batches, $batchModel->activeBatch());
        $scope             = $batchId > 0 ? $batchId : null;
        $stats             = model(AidStatsModel::class);
        $role              = RoleAccess::normalizeRole((string) session()->get('role'));

        return view('Admin/reports', [
            'pageTitle'         => 'Reports',
            'username'          => session('username') ?? 'Admin',
            'user'              => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'summary'           => $stats->receivedVsNot($scope),
            'byBarangay'        => $stats->byBarangay($scope),
            'byAidType'         => $stats->byAidType($scope),
            'batches'           => $batches,
            'batchId'           => $batchId > 0 ? $batchId : null,
            'batchName'         => $batch['name'] ?? null,
            'perScanner'        => $batchId > 0 ? $stats->perScanner($batchId) : [],
            'currentRole'       => $role,
            'canManageAccounts' => true,
            'sidebarRoleClass'  => strtolower((string) $role),
            'navActive'         => ['reports' => 'active'],
        ]);
    }

    public function pdf(): ResponseInterface
    {
        if ($g = $this->guard()) { return $g; }

        $batchModel        = model(DistributionBatchModel::class);
        $batches           = $batchModel->allBatches();
        [$batchId, $batch] = $this->resolveBatch($batches, $batchModel->activeBatch());
        $scope             = $batchId > 0 ? $batchId : null;
        $stats             = model(AidStatsModel::class);

        $bytes = (new \App\Libraries\Scanner\ReportsPdfGenerator())->generate(
            $stats->receivedVsNot($scope),
            $stats->byBarangay($scope),
            $stats->byAidType($scope),
            null,
            null,
            $batchId > 0 ? $stats->perScanner($batchId) : [],
            $batch['name'] ?? null
        );

        $name = 'aid-report-' . ($batchId > 0 ? 'batch' . $batchId : 'all') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($bytes);
    }
}
```

(Note: `ReportsPdfGenerator::generate()` still accepts the two date args; passing `null, null` keeps its signature untouched — verify by opening `app/Libraries/Scanner/ReportsPdfGenerator.php`. If it renders a date line, it already handles null.)

- [ ] **Step 2: Create `app/Views/Admin/reports.php`**

Port `app/Views/Scanner/reports.php` into the **admin** layout wrapper (copy the `extend`/`section` header from `app/Views/Admin/cards.php`). Remove the date-range form entirely; keep the batch `<select>` (reload on change → `?batch=`). Keep the Chart.js charts and the per-kiosk table (`$perScanner`, always shown for admin). Change the PDF link to `site_url('admin/reports/pdf') . '?batch=' . batchId`. Add the poll block targeting a JSON refresh of the summary tiles — for the first cut, a **Refresh** button that reloads the page with the current `?batch=` plus a 5s auto-reload is acceptable:

```php
<?= $this->section('scripts') ?>
<script>
(function () {
  var btn = document.getElementById('refreshNow');
  if (btn) { btn.addEventListener('click', function () { location.reload(); }); }
  document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
  setTimeout(function () { location.reload(); }, 5000);
})();
</script>
<?= $this->endSection() ?>
```

(A JSON stats endpoint for admin can replace the reload later; page-reload polling is correct and simplest for the admin overview.)

- [ ] **Step 3: Add routes**

Inside the `admin` group:

```php
    $routes->get('reports', 'Admin\ReportsController::index');
    $routes->get('reports/pdf', 'Admin\ReportsController::pdf');
```

- [ ] **Step 4: Verify + smoke**

Run: `php spark routes | grep "admin/reports"` → maps to `Admin\ReportsController`. Log in as admin → `/admin/reports` shows combined totals + per-kiosk table, batch selector switches scope, PDF downloads, no date inputs.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Admin/ReportsController.php app/Views/Admin/reports.php app/Config/Routes.php
git commit -m "feat(admin): overall batch-scoped reports (combined + per-kiosk)"
```

---

### Task 7: Sidebar/nav rewire + retire old scanner shell

**Files:**
- Modify: `app/Views/components/dashboard_sidebar.php`
- Modify: `app/Views/Scanner/kiosk-layout.php`
- Delete: `app/Controllers/Scanner/ManageController.php`, `app/Controllers/Scanner/ReportsController.php`, `app/Views/Scanner/setting.php`, `app/Views/Scanner/manage.php`, `app/Views/Scanner/manage-aidtypes-body.php`, `app/Views/Scanner/manage-batches-body.php`, `app/Views/Scanner/manage-distributions-body.php`, `app/Views/Scanner/reports.php`, `app/Views/Scanner/layout.php`

**Interfaces:**
- Consumes: admin routes (Tasks 4/6), kiosk routes (Task 5).

- [ ] **Step 1: Admin sidebar → new admin routes**

In `app/Views/components/dashboard_sidebar.php`, in the admin nav (the `else` branch), under the `QR Code` heading replace the three scanner links (lines ~52-55) with:

```php
                <a class="nav-link <?= esc($navActive['cards'] ?? '') ?>" href="<?= site_url('admin/cards') ?>"><div class="sb-nav-link-icon"><i class="bi bi-qr-code" aria-hidden="true"></i></div>Generate</a>
                <a class="nav-link <?= esc($navActive['distribution'] ?? '') ?>" href="<?= site_url('admin/distribution') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></div>Distribution</a>
                <a class="nav-link <?= esc($navActive['reports'] ?? '') ?>" href="<?= site_url('admin/reports') ?>"><div class="sb-nav-link-icon"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></div>Reports</a>
```

(The kiosk `Scan` link is intentionally dropped from the admin sidebar — scanning is a kiosk activity. Admin/Dev can still reach `/scanner/scan` directly for testing.)

- [ ] **Step 2: Remove the scanner-only sidebar variant**

The `if ($sidebarScannerOnly)` branch served the old scanner dashboard shell (manage/reports), now gone. Since kiosk pages use `kiosk-layout` (no sidebar), this branch is dead. Remove the `$sidebarScannerOnly` branch and the surrounding conditional, keeping only the admin nav. Confirm no view still passes `sidebarScannerOnly` (grep).

- [ ] **Step 3: Green-theme the kiosk header + fix aid badge**

In `app/Views/Scanner/kiosk-layout.php`:
- Change `<nav class="navbar navbar-dark bg-dark px-3">` to the Biñan green: `<nav class="navbar navbar-dark px-3" style="background-color:#1f7a3d;">` (or a `bg-success`/theme class if one exists — check `docs/knowledge/sbadmin/` for the green token; use the documented value).
- Remove the `Change type` link (aid picker is gone):

```php
    <?php if ($aidType !== null): ?>
      <span class="badge bg-light text-dark"><?= esc($aidType['name']) ?></span>
    <?php endif; ?>
```

- [ ] **Step 4: Delete retired controllers and views**

```bash
git rm app/Controllers/Scanner/ManageController.php app/Controllers/Scanner/ReportsController.php \
       app/Views/Scanner/setting.php app/Views/Scanner/manage.php \
       app/Views/Scanner/manage-aidtypes-body.php app/Views/Scanner/manage-batches-body.php \
       app/Views/Scanner/manage-distributions-body.php app/Views/Scanner/reports.php \
       app/Views/Scanner/layout.php
```

- [ ] **Step 5: Verify nothing references the deleted files/routes**

```bash
php spark routes
grep -rn "Scanner\\\\ManageController\|Scanner\\\\ReportsController\|Scanner/layout\|Scanner/setting\|sidebarScannerOnly\|scanner/manage\|scanner/reports\|scanner/setting" app/
```
Expected: `php spark routes` resolves cleanly (no missing controllers); grep returns nothing.

- [ ] **Step 6: Run suite + commit**

```bash
vendor/bin/phpunit
git add -A
git commit -m "refactor(scanner): retire old scanner shell; green kiosk; admin nav rewire"
```

---

### Task 8: Docs, knowledge, and end-to-end verification

**Files:**
- Modify: `PROJECT_STRUCTURE.md` (route/controller map), `docs/knowledge/` scanner note if present, memory dump pointer.

- [ ] **Step 1: Update the route/structure docs**

Update `PROJECT_STRUCTURE.md` scanner/admin sections to the new map (admin owns distribution + reports; kiosk owns scan + performance). Update any `docs/knowledge/binan-conventions/` scanner reference that names `scanner/manage`, `scanner/reports`, or `scanner/setting`.

- [ ] **Step 2: Point demo workflow at V16**

Update the dump reference (memory `dump-v15-batches` successor / `docs`): demo import uses `accesscardV16.sql`. Keep V15 note as superseded.

- [ ] **Step 3: End-to-end smoke (the spec's Test focus)**

Fresh DB from V16, then verify:
- `php spark routes` — every route resolves.
- Login redirects: Scanner → `/scanner/scan`; Admin → admin shell.
- Admin opens a batch with aid type; kiosk scan logs that aid type; audit rows written (check `/admin/audit-trails`).
- Kiosk `/scanner/scan` with no open batch → empty state, no redirect loop.
- `/admin/reports` batch scoping works; no date inputs anywhere.
- Per-kiosk attribution: log scans from two distinct Scanner accounts → `/admin/reports` per-kiosk table shows two rows; each `/scanner/performance` shows only its own.

- [ ] **Step 4: Full suite**

Run: `vendor/bin/phpunit`
Expected: green (DB/session tests may SKIP without sqlite3).

- [ ] **Step 5: CodeRabbit review before merge**

```bash
coderabbit auth status
coderabbit review --base main --agent
```
Triage per `superpowers:receiving-code-review`; fix in-scope bugs, park the rest in a GitHub issue citing PR/branch.

- [ ] **Step 6: Commit docs**

```bash
git add PROJECT_STRUCTURE.md docs/
git commit -m "docs(scanner): kiosk/admin split route map + V16 demo workflow"
```

---

## Self-Review

- **Spec coverage:** shell-by-prefix (Tasks 4-7); aid bound to batch + V16 (Tasks 1-2); aid selection moved to admin (Task 4); kiosk pure scan + green (Tasks 5,7); performance emphasis + per-kiosk (Tasks 5,6); 5s polling + refresh + last-updated (Tasks 5,6); date filter removed (Tasks 3,6); single-session note (docs, Task 8); concurrency (no code needed — documented). All covered.
- **Type consistency:** `open($name,$aidTypeId,$userId)` used consistently (Tasks 2,4); `AidStatsModel` batch-only signatures used in Tasks 5,6 match Task 3; `activeBatch()['aid_type_id'|'aid_type_name']` produced in Task 2, consumed in 5,6; nav keys `distribution`/`reports` set in Tasks 4/6 and read in Task 7.
- **Placeholders:** none — view-porting steps name the exact source file, target layout, and the exact URL substitutions.
- **Open verification during exec:** confirm `ReportsPdfGenerator::generate()` tolerates `null` dates (Task 6 Step 1) and the green theme token from `docs/knowledge/sbadmin/` (Task 7 Step 3).
