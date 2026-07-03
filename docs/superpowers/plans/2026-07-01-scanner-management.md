# Scanner Management (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the Scanner module into a point-of-service **Scan** tab (verify + log a claim inline, with identity fields) and a global **Manage** tab (search/void all distributions + aid-types CRUD).

**Architecture:** No schema change. Extend three existing models (`AidTypeModel`, `AidDistributionModel`, `MemberModel`). Move the log form out of `manage.php` into `scan.php`. Repurpose `manage.php` into a two-section back-office hub. Add a `Scanner\ManageController` for aid-type CRUD + distribution void; leave `ScanController` handling scan/lookup/log.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, Bootstrap 5 / SB-Admin 2, DataTables (already wired via `asset_helper.php`), PHPUnit.

## Global Constraints

- **No migrations, no schema changes.** DB schema source of truth is `accesscardV13.sql`. Aid types are name-only; `aid_type` already has `aid_type_id`, `name`, `dt_created`, `dt_deleted`.
- **Bootstrap / SB-Admin components only** for UI. No new JS libraries.
- **Every data mutation writes `audit_trails`** via `App\Models\Audit\AuditTrailsModel::logAction(int $userId, ?int $memberId, string $action, ?string $description, ?string $ipAddress, ?string $userAgent, ?string $detail)`.
- **Every Scanner action guarded** with `RoleAccess::requireRole(['Scanner', 'Admin', 'Developer'])` (exact literal — tests grep for it).
- **Tests are no-DB contract/guard tests** (assert return types are arrays, grep controller source for guard literals, grep view/model source for expected strings). Match the existing suite posture — no live-DB round trips.
- `aid_distribution` columns: `aidID` (PK), `control_no`, `memberID`, `aid_type_id`, `claim_date`, `userID`, `dt_created`. **No `dt_deleted`** — void is a hard delete + audit.
- `member` has `birthday` (date) and `sex` (enum `Male`/`Female`).
- `users`: `userID` PK, `username`. `qr_control`: `control_no` PK, `headID`.
- Run `vendor/bin/phpunit` — baseline is **46 tests, 0 failures, 4 skipped**.

---

### Task 1: Enrich member payload with birthday + sex (Scan identity fields)

**Files:**
- Modify: `app/Models/Families/MemberModel.php:488-490` (the `familyMembers()` select)
- Modify: `app/Views/Scanner/scan.php:63-64` (member row render)
- Test: `tests/unit/MemberFamilyFieldsTest.php` (create)

**Interfaces:**
- Produces: `MemberModel::familyMembers(int $headId): array` — each row now also has keys `birthday`, `sex` (in addition to `memberID`, `firstname`, `lastname`, `relationship`).

- [ ] **Step 1: Write the failing test**

Create `tests/unit/MemberFamilyFieldsTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class MemberFamilyFieldsTest extends CIUnitTestCase
{
    public function testFamilyMembersSelectsIdentityFields(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Families/MemberModel.php');
        // familyMembers() must select birthday and sex for scan-panel identity checks.
        $this->assertMatchesRegularExpression('/familyMembers.*?birthday.*?sex/s', $src);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter testFamilyMembersSelectsIdentityFields`
Expected: FAIL — `birthday`/`sex` not yet in the select.

- [ ] **Step 3: Widen the select**

In `app/Models/Families/MemberModel.php`, change the `familyMembers()` builder select (currently `'memberID, firstname, lastname, relationship'`):

```php
        $builder = $this->db->table('member')
            ->select('memberID, firstname, lastname, relationship, birthday, sex')
            ->where('headID', $headId);
```

- [ ] **Step 4: Render the fields in the scan panel**

In `app/Views/Scanner/scan.php`, replace the `membersList` render (lines ~63-64):

```javascript
  $('membersList').innerHTML = data.members
    .map(m => `<li class="list-group-item">
        <div>${esc(m.firstname)} ${esc(m.lastname)} <span class="text-muted">(${esc(m.relationship || 'Member')})</span></div>
        <div class="small text-muted">${esc(m.sex || '—')} · ${esc(m.birthday || '—')}</div>
      </li>`).join('');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter testFamilyMembersSelectsIdentityFields`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/Families/MemberModel.php app/Views/Scanner/scan.php tests/unit/MemberFamilyFieldsTest.php
git commit -m "feat(scanner): show member birthday+sex in scan panel for ID verification"
```

---

### Task 2: Move the log-distribution form into the Scan tab

**Files:**
- Modify: `app/Views/Scanner/scan.php` (add inline log form + submit JS; drop the `logDistLink` redirect)
- Modify: `app/Controllers/Scanner/ScanController.php:38-46` (pass `aidTypes` to the scan view)
- Test: `tests/unit/ScanControllerTest.php` (add a case)

**Interfaces:**
- Consumes: `AidTypeModel::active(): array`, `ScanController::logAid()` (POST `scanner/log`, unchanged), `MemberModel::familyMembers()` (from Task 1).
- Produces: Scan page posts to `scanner/log` inline and refreshes history without navigating away.

- [ ] **Step 1: Write the failing test**

In `tests/unit/ScanControllerTest.php`, add:

```php
    public function testScanViewLogsInline(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/scan.php');
        // Logging happens in the Scan tab now: a log form posting to scanner/log,
        // not a redirect link to scanner/manage.
        $this->assertStringContainsString('scanner/log', $src);
        $this->assertStringNotContainsString('scanner/manage?control_no=', $src);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter testScanViewLogsInline`
Expected: FAIL — `scan.php` still links to `scanner/manage?control_no=`.

- [ ] **Step 3: Pass aid types to the scan view**

In `app/Controllers/Scanner/ScanController.php`, add `'aidTypes'` to the `scan()` view payload (mirroring `manage()`):

```php
        return view('Scanner/scan', [
            'activeTab' => 'scan',
            'pageTitle' => 'Scan',
            'username'  => session('username') ?? 'Scanner',
            'aidTypes'  => model(AidTypeModel::class)->active(),
            'currentRole' => $role,
            'canManageAccounts' => $canManage,
            'sidebarRoleClass' => $canManage ? 'developer' : 'admin',
            'sidebarUserUrl' => site_url('admin/dashboard'),
            'navActive' => ['scanner' => 'active'],
        ]);
```

(`AidTypeModel` is already imported in this controller.)

- [ ] **Step 4: Replace the redirect link with an inline log form**

In `app/Views/Scanner/scan.php`, replace the `logDistLink` anchor block (lines ~36-38) with the log form + alert:

```php
  <div class="card shadow-sm mb-3" id="logPanel">
    <div class="card-header fw-bold">Log Distribution</div>
    <div class="card-body">
      <div id="logAlert" class="alert alert-success mb-3" hidden></div>
      <form id="logForm">
        <input type="hidden" id="control_no" name="control_no">
        <div class="mb-3">
          <label for="claim_date" class="form-label">Date</label>
          <input type="date" class="form-control" id="claim_date" name="claim_date" required>
        </div>
        <div class="mb-3">
          <label for="aid_type_id" class="form-label">Aid Type</label>
          <select class="form-select" id="aid_type_id" name="aid_type_id" required>
            <option value="">-- Select aid type --</option>
            <?php foreach ($aidTypes as $type): ?>
              <option value="<?= esc($type['aid_type_id']) ?>"><?= esc($type['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="memberID" class="form-label">Claimant</label>
          <select class="form-select" id="memberID" name="memberID" required>
            <option value="">-- Select claimant --</option>
          </select>
        </div>
        <div id="fieldErrors" class="text-danger small mb-3"></div>
        <button class="btn btn-success w-100" id="submitBtn" type="submit">Log Distribution</button>
      </form>
    </div>
  </div>
```

- [ ] **Step 5: Wire the form in the scan JS**

In `app/Views/Scanner/scan.php`, inside `lookup()` replace the old `logDistLink` line (line ~66) with claimant-dropdown + control + date population:

```javascript
  $('memberID').innerHTML = '<option value="">-- Select claimant --</option>' +
    data.members.map(m => `<option value="${esc(m.memberID)}">${esc(m.firstname)} ${esc(m.lastname)} (${esc(m.relationship || 'Member')})</option>`).join('');
  $('control_no').value = data.control_no;
  if (!$('claim_date').value) {
    $('claim_date').value = new Date().toISOString().slice(0, 10);
  }
```

Then add the submit handler before the camera block (`let scanner;`):

```javascript
$('logForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  $('logAlert').hidden = true;
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
    $('logAlert').textContent = 'Distribution logged successfully.';
    $('logAlert').hidden = false;
    renderHistory(data.history);
  } finally {
    $('submitBtn').disabled = false;
  }
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter testScanViewLogsInline`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Views/Scanner/scan.php app/Controllers/Scanner/ScanController.php tests/unit/ScanControllerTest.php
git commit -m "feat(scanner): log distribution inline in Scan tab (point-of-service)"
```

---

### Task 3: AidTypeModel CRUD methods

**Files:**
- Modify: `app/Models/Scanner/AidTypeModel.php` (add `all`, `create`, `archive`, `restore`, `delete`)
- Test: `tests/unit/AidTypeModelTest.php` (add cases)

**Interfaces:**
- Produces:
  - `AidTypeModel::all(): array` — active + archived, active first then by name.
  - `AidTypeModel::create(string $name): int` — insert with `dt_created`, returns new id (0 on failure).
  - `AidTypeModel::archive(int $id): bool` — sets `dt_deleted = now()`.
  - `AidTypeModel::restore(int $id): bool` — sets `dt_deleted = null`.
  - `AidTypeModel::delete(int $id): bool` — hard delete (base Model method; callers must gate on no references).

- [ ] **Step 1: Write the failing test**

In `tests/unit/AidTypeModelTest.php`, add:

```php
    public function testAllReturnsArray(): void
    {
        // No DB -> []; pins the contract that all() exists and returns array.
        $this->assertIsArray((new AidTypeModel())->all());
    }

    public function testCrudMethodsExist(): void
    {
        $model = new AidTypeModel();
        $this->assertTrue(method_exists($model, 'create'));
        $this->assertTrue(method_exists($model, 'archive'));
        $this->assertTrue(method_exists($model, 'restore'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AidTypeModelTest`
Expected: FAIL — `all()` undefined.

- [ ] **Step 3: Implement the methods**

In `app/Models/Scanner/AidTypeModel.php`, add after `active()`:

```php
    /** Active + archived, active first then alphabetical, for the management table. */
    public function all(): array
    {
        try {
            return $this->orderBy('dt_deleted IS NULL', 'DESC', false)
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Insert a new aid type; returns the new id (0 on failure). */
    public function create(string $name): int
    {
        $this->insert(['name' => $name, 'dt_deleted' => null]);

        return (int) $this->getInsertID();
    }

    /** Soft-archive: stamp dt_deleted so it drops out of active(). */
    public function archive(int $id): bool
    {
        return $this->update($id, ['dt_deleted' => date('Y-m-d H:i:s')]) !== false;
    }

    /** Un-archive: clear dt_deleted. */
    public function restore(int $id): bool
    {
        return $this->update($id, ['dt_deleted' => null]) !== false;
    }
```

Note: `dt_created` is DB-defaulted (`current_timestamp()` per the dump) and is not in `allowedFields`, so `create()` does not set it. `delete()` is inherited from `CodeIgniter\Model` — no override needed.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter AidTypeModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidTypeModel.php tests/unit/AidTypeModelTest.php
git commit -m "feat(scanner): AidTypeModel CRUD (all/create/archive/restore)"
```

---

### Task 4: AidDistributionModel global list + void

**Files:**
- Modify: `app/Models/Scanner/AidDistributionModel.php` (add `allDistributions`, `void`, `find` helper reuse)
- Test: `tests/unit/AidDistributionModelTest.php` (add cases)

**Interfaces:**
- Produces:
  - `AidDistributionModel::allDistributions(): array` — every claim, newest first, each row: `aidID`, `claim_date`, `aid_type` (name), `claimant` (member full name), `head` (family head full name), `scanned_by` (username or `''`).
  - `AidDistributionModel::void(int $aidId): bool` — hard-delete one distribution row.

- [ ] **Step 1: Write the failing test**

In `tests/unit/AidDistributionModelTest.php`, add:

```php
    public function testAllDistributionsReturnsArray(): void
    {
        $this->assertIsArray((new \App\Models\Scanner\AidDistributionModel())->allDistributions());
    }

    public function testVoidMethodExists(): void
    {
        $this->assertTrue(method_exists(new \App\Models\Scanner\AidDistributionModel(), 'void'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AidDistributionModelTest`
Expected: FAIL — `allDistributions()` undefined.

- [ ] **Step 3: Implement the methods**

In `app/Models/Scanner/AidDistributionModel.php`, add after `historyFor()`:

```php
    /**
     * Every distribution, newest first, with aid-type name, claimant name,
     * family-head name, and the scanning user's username resolved via joins.
     * Drives the Manage-tab global table.
     */
    public function allDistributions(): array
    {
        try {
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . " aid_type.name AS aid_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant,"
                    . " TRIM(CONCAT(head.firstname, ' ', head.lastname)) AS head,"
                    . " COALESCE(users.username, '') AS scanned_by")
                ->join('aid_type', 'aid_type.aid_type_id = aid_distribution.aid_type_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->join('qr_control', 'qr_control.control_no = aid_distribution.control_no', 'left')
                ->join('member head', 'head.memberID = qr_control.headID', 'left')
                ->join('users', 'users.userID = aid_distribution.userID', 'left')
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Hard-delete one distribution (void a wrong entry). Audited by the caller. */
    public function void(int $aidId): bool
    {
        if ($aidId <= 0) {
            return false;
        }

        return $this->delete($aidId) !== false;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter AidDistributionModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidDistributionModel.php tests/unit/AidDistributionModelTest.php
git commit -m "feat(scanner): AidDistributionModel global list + void"
```

---

### Task 5: ManageController + routes (aid-type CRUD, distribution void, Manage page)

**Files:**
- Create: `app/Controllers/Scanner/ManageController.php`
- Modify: `app/Controllers/Scanner/ScanController.php` (remove `manage()` — moves to the new controller)
- Modify: `app/Config/Routes.php:122-127` (scanner group routes)
- Test: `tests/unit/ManageControllerTest.php` (create)

**Interfaces:**
- Consumes: `AidTypeModel::all/create/archive/restore/delete`, `AidDistributionModel::allDistributions/void/find`, `AuditTrailsModel::logAction`, `RoleAccess`.
- Produces routes: `GET scanner/manage`, `POST scanner/aid-types/create`, `POST scanner/aid-types/archive/(:num)`, `POST scanner/aid-types/restore/(:num)`, `POST scanner/aid-types/delete/(:num)`, `POST scanner/distributions/void/(:num)`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/ManageControllerTest.php`:

```php
<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ManageControllerTest extends CIUnitTestCase
{
    public function testManageRoutesResolve(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $get  = $routes->getRoutes('GET');
        $post = $routes->getRoutes('POST');
        $this->assertArrayHasKey('scanner/manage', $get);
        $this->assertArrayHasKey('scanner/aid-types/create', $post);
        $this->assertArrayHasKey('scanner/distributions/void/([0-9]+)', $post);
    }

    public function testEveryActionGuardsScannerRole(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        // Guard literal appears once per action (manage, create, archive, restore, delete, void).
        $this->assertGreaterThanOrEqual(6, substr_count($src, "requireRole(['Scanner', 'Admin', 'Developer'])"));
        // Mutations are audited.
        $this->assertStringContainsString('logAction', $src);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ManageControllerTest`
Expected: FAIL — controller/routes missing.

- [ ] **Step 3: Create the controller**

Create `app/Controllers/Scanner/ManageController.php`:

```php
<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner Manage tab: global back-office. Renders the aid-types + all-distributions
 * hub and handles their mutations (aid-type CRUD, distribution void). Every action
 * is Scanner/Admin/Developer-only and writes an audit_trails row.
 */
class ManageController extends BaseController
{
    /** GET scanner/manage — the two-section hub page. */
    public function index(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));
        $canManage = in_array($role, ['Developer', 'Admin'], true);

        return view('Scanner/manage', [
            'activeTab'         => 'manage',
            'pageTitle'         => 'Manage',
            'username'          => session('username') ?? 'Scanner',
            'aidTypes'          => model(AidTypeModel::class)->all(),
            'distributions'     => model(AidDistributionModel::class)->allDistributions(),
            'currentRole'       => $role,
            'canManageAccounts' => $canManage,
            'sidebarRoleClass'  => $canManage ? 'developer' : 'admin',
            'sidebarUserUrl'    => site_url('admin/dashboard'),
            'navActive'         => ['scanner' => 'active'],
        ]);
    }

    /** POST scanner/aid-types/create */
    public function createAidType(): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('scanner/manage')->with('error', 'Aid type name is required.');
        }

        $id = model(AidTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to add aid type.');
        }

        $this->audit('Created aid type "' . $name . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type added.');
    }

    /** POST scanner/aid-types/archive/{id} */
    public function archiveAidType(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->archive($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to archive aid type.');
        }

        $this->audit('Archived aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type archived.');
    }

    /** POST scanner/aid-types/restore/{id} */
    public function restoreAidType(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->restore($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to restore aid type.');
        }

        $this->audit('Restored aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type restored.');
    }

    /** POST scanner/aid-types/delete/{id} — only when never referenced. */
    public function deleteAidType(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $used = model(AidDistributionModel::class)
            ->where('aid_type_id', $id)->countAllResults();
        if ($used > 0) {
            return redirect()->to('scanner/manage')
                ->with('error', 'Cannot delete: aid type is used by ' . $used . ' distribution(s). Archive it instead.');
        }

        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->delete($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to delete aid type.');
        }

        $this->audit('Deleted aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);

        return redirect()->to('scanner/manage')->with('success', 'Aid type deleted.');
    }

    /** POST scanner/distributions/void/{id} — hard-delete a wrong claim. */
    public function voidDistribution(int $id): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $row = model(AidDistributionModel::class)->find($id);
        if ($row === null) {
            return redirect()->to('scanner/manage')->with('error', 'Distribution not found.');
        }

        if (! model(AidDistributionModel::class)->void($id)) {
            return redirect()->to('scanner/manage')->with('error', 'Unable to void distribution.');
        }

        $this->audit(
            'Voided aid distribution #' . $id,
            (int) ($row['memberID'] ?? 0),
            'Control #' . (int) ($row['control_no'] ?? 0) . ', aid type ID ' . (int) ($row['aid_type_id'] ?? 0)
        );

        return redirect()->to('scanner/manage')->with('success', 'Distribution voided.');
    }

    /** Write an audit_trails row for the acting scanner. */
    private function audit(string $action, int $memberId = 0, ?string $detail = null): void
    {
        $userId = (int) (session('user_id') ?? 0);
        (new AuditTrailsModel())->logAction(
            $userId,
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

- [ ] **Step 4: Remove `manage()` from ScanController**

In `app/Controllers/Scanner/ScanController.php`, delete the entire `manage()` method (lines ~50-72) and update the class docblock line `- manage(): ...` to note manage moved to `ManageController`. Leave `scan()`, `lookup()`, `logAid()` intact.

- [ ] **Step 5: Update the routes**

In `app/Config/Routes.php`, replace the scanner group body:

```php
$routes->group('scanner', static function (RouteCollection $routes): void {
    $routes->get('scan', 'Scanner\ScanController::scan');
    $routes->get('lookup/(:num)', 'Scanner\ScanController::lookup/$1');
    $routes->post('log', 'Scanner\ScanController::logAid');

    $routes->get('manage', 'Scanner\ManageController::index');
    $routes->post('aid-types/create', 'Scanner\ManageController::createAidType');
    $routes->post('aid-types/archive/(:num)', 'Scanner\ManageController::archiveAidType/$1');
    $routes->post('aid-types/restore/(:num)', 'Scanner\ManageController::restoreAidType/$1');
    $routes->post('aid-types/delete/(:num)', 'Scanner\ManageController::deleteAidType/$1');
    $routes->post('distributions/void/(:num)', 'Scanner\ManageController::voidDistribution/$1');
});
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter ManageControllerTest`
Expected: PASS
Run: `vendor/bin/phpunit --filter ScanControllerTest`
Expected: PASS (`scanner/manage` still resolves — now to `ManageController::index`).

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/Scanner/ManageController.php app/Controllers/Scanner/ScanController.php app/Config/Routes.php tests/unit/ManageControllerTest.php
git commit -m "feat(scanner): ManageController for aid-type CRUD + distribution void"
```

---

### Task 6: Manage view — aid-types table + all-distributions table

**Files:**
- Rewrite: `app/Views/Scanner/manage.php`
- Test: `tests/unit/ManageViewTest.php` (create)

**Interfaces:**
- Consumes: `$aidTypes` (from `AidTypeModel::all()`), `$distributions` (from `allDistributions()`), the aid-type/void POST routes from Task 5.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/ManageViewTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ManageViewTest extends CIUnitTestCase
{
    public function testManageViewHasBothSections(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/manage.php');
        // Aid-types CRUD form + all-distributions void action.
        $this->assertStringContainsString('scanner/aid-types/create', $src);
        $this->assertStringContainsString('scanner/distributions/void/', $src);
        // No leftover single-QR log form.
        $this->assertStringNotContainsString('id="logForm"', $src);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter ManageViewTest`
Expected: FAIL — old log-form `manage.php` still present.

- [ ] **Step 3: Rewrite the view**

Replace the entire contents of `app/Views/Scanner/manage.php`:

```php
<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php if (session('success')): ?>
  <div class="alert alert-success"><?= esc(session('success')) ?></div>
<?php elseif (session('error')): ?>
  <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<ul class="nav nav-pills mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-dist" type="button">All Distributions</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-types" type="button">Aid Types</button></li>
</ul>

<div class="tab-content">
  <!-- All distributions -->
  <div class="tab-pane fade show active" id="tab-dist">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">All Aid Distributions</div>
      <div class="card-body">
        <table class="table table-striped table-bordered w-100" id="distTable">
          <thead>
            <tr><th>Date</th><th>Family Head</th><th>Claimant</th><th>Aid Type</th><th>Scanned By</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($distributions as $d): ?>
              <tr>
                <td><?= esc($d['claim_date']) ?></td>
                <td><?= esc($d['head']) ?></td>
                <td><?= esc($d['claimant']) ?></td>
                <td><?= esc($d['aid_type']) ?></td>
                <td><?= esc($d['scanned_by']) ?></td>
                <td>
                  <form method="post" action="<?= site_url('scanner/distributions/void/' . $d['aidID']) ?>"
                        onsubmit="return confirm('Void this distribution? This permanently removes the record.');">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Void</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Aid types CRUD -->
  <div class="tab-pane fade" id="tab-types">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>Aid Types</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAidTypeModal">Add</button>
      </div>
      <div class="card-body">
        <table class="table table-striped table-bordered w-100">
          <thead><tr><th>Name</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($aidTypes as $t): ?>
              <?php $archived = ! empty($t['dt_deleted']); ?>
              <tr>
                <td><?= esc($t['name']) ?></td>
                <td><?= $archived ? '<span class="badge bg-secondary">Archived</span>' : '<span class="badge bg-success">Active</span>' ?></td>
                <td>
                  <?php if ($archived): ?>
                    <form method="post" action="<?= site_url('scanner/aid-types/restore/' . $t['aid_type_id']) ?>" class="d-inline">
                      <button class="btn btn-sm btn-outline-success" type="submit">Restore</button>
                    </form>
                    <form method="post" action="<?= site_url('scanner/aid-types/delete/' . $t['aid_type_id']) ?>" class="d-inline"
                          onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?= site_url('scanner/aid-types/archive/' . $t['aid_type_id']) ?>" class="d-inline">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">Archive</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add aid type modal -->
<div class="modal fade" id="addAidTypeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('scanner/aid-types/create') ?>">
      <div class="modal-header">
        <h5 class="modal-title">Add Aid Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="aidTypeName" class="form-label">Name</label>
        <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="255">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('#distTable').DataTable({ order: [[0, 'desc']] });
  }
});
</script>
<?= $this->endSection() ?>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter ManageViewTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Views/Scanner/manage.php tests/unit/ManageViewTest.php
git commit -m "feat(scanner): Manage tab view (all distributions + aid-types CRUD)"
```

---

### Task 7: Full-suite verification + manual smoke test

**Files:** none (verification only)

- [ ] **Step 1: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all green — at least the prior **46** plus the new tests, **0 failures, 0 errors**, 4 skipped unchanged.

- [ ] **Step 2: Confirm routes resolve**

Run: `php spark routes | grep scanner`
Expected: `scanner/scan`, `scanner/lookup/(:num)`, `scanner/log`, `scanner/manage`, `scanner/aid-types/create`, `scanner/aid-types/archive/(:num)`, `scanner/aid-types/restore/(:num)`, `scanner/aid-types/delete/(:num)`, `scanner/distributions/void/(:num)` all listed with their controllers.

- [ ] **Step 3: Manual smoke test (dev server / XAMPP)**

Log in as a Scanner-role account and verify:
1. **Scan tab:** scan/enter a registered control number → member rows show sex + birthday → aid-type + claimant dropdowns populate → submit logs the claim → this-family history updates in place (no redirect).
2. **Manage tab → Aid Types:** Add "Medicine" → appears Active → shows in the Scan tab's aid-type dropdown → Archive it → drops from the Scan dropdown but stays (Archived) in the Manage table → Restore.
3. **Manage tab → All Distributions:** the claim from step 1 appears (head, claimant, aid type, date, scanned-by) → Void it → row disappears.
4. Confirm each Manage action wrote an `audit_trails` row (check the admin Audit Trails page).

- [ ] **Step 4: Commit any fixes, then write the summary**

Per the project convention, write `docs/superpowers/summary/2026-07-01-scanner-management-summary.md` recapping what shipped (mirrors the existing scanner-module summary format), and commit it with `git add -f` (the `docs/superpowers` path is gitignored).
```

- [ ] **Step 5: Final commit**

```bash
git add -f docs/superpowers/summary/2026-07-01-scanner-management-summary.md
git commit -m "docs(scanner): management phase-1 implementation summary"
```
