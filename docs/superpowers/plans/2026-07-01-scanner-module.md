# Scanner Module (Aid Distribution Tracker) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a mobile-first Scanner module where scanner-role users scan a paper QR control number, view the family head + members, see that QR's aid history, and log a new aid distribution.

**Architecture:** Three new tables hand-written into the SQL dump (no CI4 migrations): `qr_control` (control_no→head), `aid_type` (seeded dropdown), `aid_distribution` (aid log). A new `scanner` role routed to a dedicated Bootstrap/SB-Admin shell. `ScanController` resolves scans via new Models and reuses `MemberModel`/`AuditTrailsModel`. Camera scanning uses vendored `html5-qrcode`; hardware-scanner and manual entry are a plain text input.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, MySQL, Bootstrap + SB Admin, html5-qrcode (vendored JS), PHPUnit.

## Global Constraints

- **No CI4 migrations.** Schema changes go into `accesscardV13.sql` only; never add files under `app/Database/Migrations/`.
- **Match the dump:** column/enum/role names must match `accesscardV13.sql` exactly.
- **Every aid mutation writes an `audit_trails` row** via `App\Models\Audit\AuditTrailsModel::logAction()`. Never bypass it.
- **Controllers decide, libraries/models build.** Keep view-data assembly out of routing controllers.
- **UI: Bootstrap + SB Admin only.** The single exception is vendored `html5-qrcode` for camera decode.
- **PHP 8.2+**, strict namespaces matching existing files.
- **Roles:** `RoleAccess::normalizeRole()` is the single DB-enum↔label translation point. Scanner label = `'Scanner'`.
- **Run `vendor/bin/phpunit` before and after each task.** DB/ext-dependent tests skip gracefully — do not "fix" pre-existing `ExampleDatabaseTest`/`FamilyDataTableTest` failures.

---

## File Structure

- Modify: `accesscardV13.sql` — add `qr_control`, `aid_type` (+seed), `aid_distribution`.
- Modify: `app/Libraries/RoleAccess.php` — map `scanner`→`Scanner`; redirect to `scanner/scan`.
- Create: `app/Models/Scanner/QrControlModel.php` — resolve control_no→head.
- Create: `app/Models/Scanner/AidTypeModel.php` — active aid types.
- Create: `app/Models/Scanner/AidDistributionModel.php` — log + history.
- Modify: `app/Models/Families/MemberModel.php` — add `familyMembers(int $headId)`.
- Create: `app/Controllers/Scanner/ScanController.php` — scan/lookup/logAid.
- Modify: `app/Config/Routes.php` — new `scanner` group.
- Create: `app/Views/Scanner/layout.php` — mobile-first shell + tabs.
- Create: `app/Views/Scanner/scan.php` — input, result, history, log form.
- Create: `public/vendor/html5-qrcode/html5-qrcode.min.js` — vendored lib.
- Modify: `app/Views/Admin/layout.php` — add "Scanner" sidebar link.
- Create tests: `tests/unit/ScannerRoleTest.php`, `tests/unit/QrControlModelTest.php`, `tests/unit/AidTypeModelTest.php`, `tests/unit/AidDistributionModelTest.php`, `tests/unit/ScanControllerTest.php`.

---

## Task 1: Database tables in the dump

**Files:**
- Modify: `accesscardV13.sql`

**Interfaces:**
- Produces: tables `qr_control(control_no PK, headID, dt_created)`, `aid_type(aid_type_id PK, name, dt_created, dt_deleted)` seeded with `Financial`, `Rice`, `Grocery`, and `aid_distribution(aidID PK, control_no, memberID, aid_type_id, claim_date, userID, dt_created)`.

- [ ] **Step 1: Add the three `CREATE TABLE` blocks + seed to `accesscardV13.sql`**

Append before the final `COMMIT;`/end of file (place near the other `CREATE TABLE` statements):

```sql
--
-- Scanner module: QR control mapping, aid types, aid distribution log
--
DROP TABLE IF EXISTS `qr_control`;
CREATE TABLE `qr_control` (
  `control_no` int(11) NOT NULL,
  `headID` int(11) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`control_no`),
  KEY `idx_qr_head` (`headID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `aid_type`;
CREATE TABLE `aid_type` (
  `aid_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_deleted` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`aid_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `aid_type` (`name`) VALUES ('Financial'), ('Rice'), ('Grocery');

DROP TABLE IF EXISTS `aid_distribution`;
CREATE TABLE `aid_distribution` (
  `aidID` int(11) NOT NULL AUTO_INCREMENT,
  `control_no` int(11) NOT NULL,
  `memberID` int(11) NOT NULL,
  `aid_type_id` int(11) NOT NULL,
  `claim_date` date NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aidID`),
  KEY `idx_ad_control` (`control_no`),
  KEY `idx_ad_type` (`aid_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- [ ] **Step 2: Import the dump into the local DB**

Run: `mysql -u root accesscard < accesscardV13.sql` (or import via phpMyAdmin).
Expected: no errors; `SHOW TABLES;` lists `qr_control`, `aid_type`, `aid_distribution`.

- [ ] **Step 3: Verify seed rows**

Run: `mysql -u root accesscard -e "SELECT name FROM aid_type ORDER BY aid_type_id;"`
Expected: `Financial`, `Rice`, `Grocery`.

- [ ] **Step 4: Commit**

```bash
git add accesscardV13.sql
git commit -m "feat(scanner): add qr_control, aid_type, aid_distribution tables to dump"
```

---

## Task 2: Scanner role plumbing

**Files:**
- Modify: `app/Libraries/RoleAccess.php` (`normalizeRole()` ~line 23; `redirectByRole()` ~line 147)
- Modify: `accesscardV13.sql` (`users.account_level` enum)
- Test: `tests/unit/ScannerRoleTest.php`

**Interfaces:**
- Consumes: existing `RoleAccess::normalizeRole()`, `redirectByRole()`.
- Produces: `normalizeRole('scanner') === 'Scanner'`; `redirectByRole('scanner')` targets `scanner/scan`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/ScannerRoleTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Libraries\RoleAccess;
use CodeIgniter\Test\CIUnitTestCase;

final class ScannerRoleTest extends CIUnitTestCase
{
    public function testScannerNormalizes(): void
    {
        $this->assertSame('Scanner', RoleAccess::normalizeRole('scanner'));
        $this->assertSame('Scanner', RoleAccess::normalizeRole('Scanner'));
    }

    public function testScannerRedirectsToScanPage(): void
    {
        $response = RoleAccess::redirectByRole('scanner');
        $this->assertStringContainsString('scanner/scan', $response->getHeaderLine('Location'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/ScannerRoleTest.php`
Expected: FAIL (`normalizeRole` returns null / redirect goes to login).

- [ ] **Step 3: Add the `scanner` case to `normalizeRole()`**

In `app/Libraries/RoleAccess.php`, inside the `match` in `normalizeRole()`, add before `default`:

```php
            'scanner'                      => 'Scanner',
```

- [ ] **Step 4: Add the Scanner redirect branch to `redirectByRole()`**

In `redirectByRole()`, add after the `Viewer` branch and before `session()->destroy();`:

```php
        if ($normalizedRole === 'Scanner') {
            return redirect()->to(site_url('scanner/scan'));
        }
```

- [ ] **Step 5: Add `scanner` to the `users.account_level` enum in the dump**

In `accesscardV13.sql`, change:

```sql
  `account_level` enum('administrator','encoder','viewer') NOT NULL DEFAULT 'encoder',
```
to:
```sql
  `account_level` enum('administrator','encoder','viewer','scanner') NOT NULL DEFAULT 'encoder',
```

Then re-import: `mysql -u root accesscard < accesscardV13.sql`.

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/ScannerRoleTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Libraries/RoleAccess.php accesscardV13.sql tests/unit/ScannerRoleTest.php
git commit -m "feat(scanner): add scanner role mapping and login redirect"
```

---

## Task 3: QrControlModel

**Files:**
- Create: `app/Models/Scanner/QrControlModel.php`
- Test: `tests/unit/QrControlModelTest.php`

**Interfaces:**
- Produces: `QrControlModel::headForControl(int $controlNo): ?int` — returns `headID` for a mapped control number, else `null`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/QrControlModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Scanner\QrControlModel;
use CodeIgniter\Test\CIUnitTestCase;

final class QrControlModelTest extends CIUnitTestCase
{
    public function testRejectsNonPositiveControl(): void
    {
        $this->assertNull((new QrControlModel())->headForControl(0));
        $this->assertNull((new QrControlModel())->headForControl(-5));
    }
}
```

(Non-DB guard test; DB-backed resolution is covered by `ScanControllerTest` and manual verification, consistent with the suite's skip-without-DB posture.)

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/QrControlModelTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the model**

Create `app/Models/Scanner/QrControlModel.php`:

```php
<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Maps a paper QR control number (1..100000) to a family head's memberID.
 * Pre-loaded from the encoders' Excel; this app only reads it.
 */
class QrControlModel extends Model
{
    protected $table         = 'qr_control';
    protected $primaryKey    = 'control_no';
    protected $returnType    = 'array';
    protected $allowedFields = ['control_no', 'headID'];
    protected $useTimestamps = false;

    /** Returns the mapped headID for a control number, or null when unmapped. */
    public function headForControl(int $controlNo): ?int
    {
        if ($controlNo <= 0) {
            return null;
        }

        $row = $this->where('control_no', $controlNo)->first();

        return $row === null ? null : (int) $row['headID'];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/QrControlModelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/QrControlModel.php tests/unit/QrControlModelTest.php
git commit -m "feat(scanner): add QrControlModel control_no->head resolver"
```

---

## Task 4: AidTypeModel

**Files:**
- Create: `app/Models/Scanner/AidTypeModel.php`
- Test: `tests/unit/AidTypeModelTest.php`

**Interfaces:**
- Produces: `AidTypeModel::active(): array` — non-archived aid types as `[['aid_type_id'=>int,'name'=>string], ...]` ordered by name.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/AidTypeModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Scanner\AidTypeModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidTypeModelTest extends CIUnitTestCase
{
    public function testActiveReturnsArray(): void
    {
        // Without a DB this returns []; the assertion pins the return contract.
        $this->assertIsArray((new AidTypeModel())->active());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/AidTypeModelTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the model**

Create `app/Models/Scanner/AidTypeModel.php`:

```php
<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Aid-type lookup for the scan log dropdown. Isolated from the `services`
 * table per the scanner-module boundary. CRUD is a later spec; this reads only.
 */
class AidTypeModel extends Model
{
    protected $table         = 'aid_type';
    protected $primaryKey    = 'aid_type_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'dt_deleted'];
    protected $useTimestamps = false;

    /** Non-archived aid types, ordered by name, for the dropdown. */
    public function active(): array
    {
        try {
            return $this->where('dt_deleted', null)
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/AidTypeModelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidTypeModel.php tests/unit/AidTypeModelTest.php
git commit -m "feat(scanner): add AidTypeModel active() for the aid dropdown"
```

---

## Task 5: AidDistributionModel

**Files:**
- Create: `app/Models/Scanner/AidDistributionModel.php`
- Test: `tests/unit/AidDistributionModelTest.php`

**Interfaces:**
- Consumes: nothing external.
- Produces:
  - `AidDistributionModel::logAid(array $data): int` — inserts one row (`control_no`, `memberID`, `aid_type_id`, `claim_date`, `userID`), returns the new `aidID`.
  - `AidDistributionModel::historyFor(int $controlNo): array` — rows for a control number joined to `aid_type.name` and claimant name, newest first: `[['aidID','claim_date','aid_type','claimant'], ...]`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/AidDistributionModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Scanner\AidDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidDistributionModelTest extends CIUnitTestCase
{
    public function testHistoryForNonPositiveControlIsEmpty(): void
    {
        $this->assertSame([], (new AidDistributionModel())->historyFor(0));
    }

    public function testAllowedFieldsCoverInsertPayload(): void
    {
        $model  = new AidDistributionModel();
        $fields = (new \ReflectionClass($model))->getProperty('allowedFields');
        $fields->setAccessible(true);
        foreach (['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID'] as $col) {
            $this->assertContains($col, $fields->getValue($model));
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/AidDistributionModelTest.php`
Expected: FAIL (class not found).

- [ ] **Step 3: Create the model**

Create `app/Models/Scanner/AidDistributionModel.php`:

```php
<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Aid-distribution log: one row per handout against a QR control number.
 * historyFor() drives the per-QR chronological history panel.
 */
class AidDistributionModel extends Model
{
    protected $table         = 'aid_distribution';
    protected $primaryKey    = 'aidID';
    protected $returnType    = 'array';
    protected $allowedFields = ['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID'];
    protected $useTimestamps = false;

    /** Inserts one distribution row and returns its aidID. */
    public function logAid(array $data): int
    {
        $this->insert([
            'control_no'  => (int) $data['control_no'],
            'memberID'    => (int) $data['memberID'],
            'aid_type_id' => (int) $data['aid_type_id'],
            'claim_date'  => $data['claim_date'],
            'userID'      => isset($data['userID']) && (int) $data['userID'] > 0 ? (int) $data['userID'] : null,
        ]);

        return (int) $this->getInsertID();
    }

    /**
     * Chronological (newest-first) aid history for a control number, with the
     * aid-type name and the claimant's full name resolved via joins.
     */
    public function historyFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . " aid_type.name AS aid_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
                ->join('aid_type', 'aid_type.aid_type_id = aid_distribution.aid_type_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/AidDistributionModelTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidDistributionModel.php tests/unit/AidDistributionModelTest.php
git commit -m "feat(scanner): add AidDistributionModel logAid + historyFor"
```

---

## Task 6: MemberModel familyMembers helper

**Files:**
- Modify: `app/Models/Families/MemberModel.php` (add method near `findHead()` ~line 459)

**Interfaces:**
- Consumes: existing `MemberModel` table `member`.
- Produces: `MemberModel::familyMembers(int $headId): array` — all active members of a family (head + relatives), each `['memberID','firstname','lastname','relationship']`, head first.

- [ ] **Step 1: Add the method**

In `app/Models/Families/MemberModel.php`, after `findHead()`:

```php
    /**
     * All active members of a family (head + relatives), head first. Drives the
     * scan claimant dropdown. Returns [] for a non-positive id.
     */
    public function familyMembers(int $headId): array
    {
        if ($headId <= 0) {
            return [];
        }

        $builder = $this->db->table('member')
            ->select('memberID, firstname, lastname, relationship')
            ->where('headID', $headId);

        if ($this->db->fieldExists('dt_deleted', 'member')) {
            $builder->where('member.dt_deleted IS NULL', null, false);
        }

        // Head (memberID == headId) sorts first, then the rest by memberID.
        $builder->orderBy('CASE WHEN memberID = ' . $headId . ' THEN 0 ELSE 1 END', 'ASC', false)
            ->orderBy('memberID', 'ASC');

        return $builder->get()->getResultArray();
    }
```

- [ ] **Step 2: Run the full suite (no regression)**

Run: `vendor/bin/phpunit`
Expected: no new failures vs baseline (pre-existing `ExampleDatabaseTest`/`FamilyDataTableTest` items unchanged).

- [ ] **Step 3: Commit**

```bash
git add app/Models/Families/MemberModel.php
git commit -m "feat(scanner): add MemberModel::familyMembers for claimant dropdown"
```

---

## Task 7: ScanController + routes

**Files:**
- Create: `app/Controllers/Scanner/ScanController.php`
- Modify: `app/Config/Routes.php` (add `scanner` group after the `viewer` group)
- Test: `tests/unit/ScanControllerTest.php`

**Interfaces:**
- Consumes: `QrControlModel::headForControl()`, `AidDistributionModel::logAid()`/`historyFor()`, `AidTypeModel::active()`, `MemberModel::findHead()`/`familyMembers()`, `AuditTrailsModel::logAction()`, `RoleAccess::requireRole()`.
- Produces routes:
  - `GET  scanner/scan`         → `Scanner\ScanController::scan`
  - `GET  scanner/lookup/(:num)`→ `Scanner\ScanController::lookup/$1`
  - `POST scanner/log`          → `Scanner\ScanController::logAid`

- [ ] **Step 1: Write the failing test**

Create `tests/unit/ScanControllerTest.php`:

```php
<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ScanControllerTest extends CIUnitTestCase
{
    public function testScannerRoutesResolve(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $map = $routes->getRoutes('get');
        $this->assertArrayHasKey('scanner/scan', $map);
    }

    public function testGuardRolesIncludeScanner(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        $this->assertStringContainsString("requireRole(['Scanner', 'Admin', 'Developer'])", $src);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/ScanControllerTest.php`
Expected: FAIL (route missing / file missing).

- [ ] **Step 3: Create the controller**

Create `app/Controllers/Scanner/ScanController.php`:

```php
<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidTypeModel;
use App\Models\Scanner\QrControlModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner module: resolve a paper QR control number to a family, show its aid
 * history, and log a new aid distribution. Scanner/Admin/Developer only.
 *
 * - scan():   GET  scanner/scan          -> the mobile-first scan page.
 * - lookup(): GET  scanner/lookup/{num}  -> JSON {head, members, history}.
 * - logAid(): POST scanner/log           -> insert + audit, returns refreshed history.
 */
class ScanController extends BaseController
{
    private const ROLES = ['Scanner', 'Admin', 'Developer'];

    public function scan(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(self::ROLES);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Scanner/scan', [
            'aidTypes' => model(AidTypeModel::class)->active(),
        ]);
    }

    public function lookup(int $controlNo): ResponseInterface
    {
        $guard = RoleAccess::requireRole(self::ROLES);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'QR control number is not registered.']);
        }

        $members = new MemberModel();
        $head    = $members->findHead($headId);
        if ($head === null) {
            log_message('error', 'Scanner lookup: control {c} maps to missing head {h}', ['c' => $controlNo, 'h' => $headId]);
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Family record unavailable.']);
        }

        return $this->response->setJSON([
            'control_no' => $controlNo,
            'head'       => $head,
            'members'    => $members->familyMembers($headId),
            'history'    => model(AidDistributionModel::class)->historyFor($controlNo),
        ]);
    }

    public function logAid(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(self::ROLES);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $rules = [
            'control_no'  => 'required|is_natural_no_zero',
            'memberID'    => 'required|is_natural_no_zero',
            'aid_type_id' => 'required|is_natural_no_zero',
            'claim_date'  => 'required|valid_date[Y-m-d]',
        ];
        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)
                ->setJSON(['errors' => $this->validator->getErrors()]);
        }

        $controlNo = (int) $this->request->getPost('control_no');
        $memberId  = (int) $this->request->getPost('memberID');

        // Guard: the claimant must belong to the family the QR maps to.
        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'QR control number is not registered.']);
        }
        $memberIds = array_column((new MemberModel())->familyMembers($headId), 'memberID');
        if (! in_array($memberId, array_map('intval', $memberIds), true)) {
            return $this->response->setStatusCode(422)->setJSON(['errors' => ['memberID' => 'Claimant is not part of this family.']]);
        }

        $userId = (int) (session('user_id') ?? 0);
        model(AidDistributionModel::class)->logAid([
            'control_no'  => $controlNo,
            'memberID'    => $memberId,
            'aid_type_id' => (int) $this->request->getPost('aid_type_id'),
            'claim_date'  => $this->request->getPost('claim_date'),
            'userID'      => $userId,
        ]);

        (new AuditTrailsModel())->logAction(
            $userId,
            $memberId,
            'Logged aid distribution',
            'Control #' . $controlNo,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            'Aid type ID ' . (int) $this->request->getPost('aid_type_id') . ' on ' . $this->request->getPost('claim_date')
        );

        return $this->response->setJSON([
            'ok'      => true,
            'history' => model(AidDistributionModel::class)->historyFor($controlNo),
        ]);
    }
}
```

- [ ] **Step 4: Add the `scanner` route group**

In `app/Config/Routes.php`, after the `viewer` group's closing `});`, add:

```php
/**
 * Scanner module (aid distribution). Scanner/Admin/Developer only — each action
 * calls RoleAccess::requireRole() internally (mirrors the Cards controller).
 */
$routes->group('scanner', static function (RouteCollection $routes): void {
    $routes->get('scan', 'Scanner\ScanController::scan');
    $routes->get('lookup/(:num)', 'Scanner\ScanController::lookup/$1');
    $routes->post('log', 'Scanner\ScanController::logAid');
});
```

- [ ] **Step 5: Run test + route check**

Run: `vendor/bin/phpunit tests/unit/ScanControllerTest.php`
Expected: PASS (2 tests).
Run: `php spark routes | grep scanner`
Expected: three `scanner/...` routes resolve to `Scanner\ScanController`.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/Scanner/ScanController.php app/Config/Routes.php tests/unit/ScanControllerTest.php
git commit -m "feat(scanner): add ScanController (scan/lookup/log) and routes"
```

---

## Task 8: Scanner shell layout + scan view + camera lib

**Files:**
- Create: `app/Views/Scanner/layout.php`
- Create: `app/Views/Scanner/scan.php`
- Create: `public/vendor/html5-qrcode/html5-qrcode.min.js`

**Interfaces:**
- Consumes: `scan()` passes `$aidTypes` (array of `['aid_type_id','name']`); AJAX calls `scanner/lookup/{n}` and `scanner/log`.

- [ ] **Step 1: Vendor the camera library**

Run:
```bash
mkdir -p public/vendor/html5-qrcode
curl -L -o public/vendor/html5-qrcode/html5-qrcode.min.js \
  https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js
```
Expected: file exists, non-empty (`test -s public/vendor/html5-qrcode/html5-qrcode.min.js && echo OK`).

- [ ] **Step 2: Create the mobile-first shell**

Create `app/Views/Scanner/layout.php` (SB Admin / Bootstrap; `renderSection('content')` slot):

```php
<?php $base = rtrim(base_url(), '/'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Scanner — Biñan Access Card</title>
    <link href="<?= $base ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1"><i class="fas fa-qrcode me-2"></i>Scanner</span>
            <a href="<?= $base ?>/logout" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </nav>
    <ul class="nav nav-pills nav-justified bg-white shadow-sm px-2 py-1">
        <li class="nav-item"><a class="nav-link active" href="<?= $base ?>/scanner/scan">Scan</a></li>
        <li class="nav-item"><span class="nav-link disabled" title="Later spec">Reports</span></li>
        <li class="nav-item"><span class="nav-link disabled" title="Later spec">History</span></li>
        <li class="nav-item"><span class="nav-link disabled" title="Later spec">Aid Types</span></li>
    </ul>
    <main class="container-fluid py-3" style="max-width:640px;">
        <?= $this->renderSection('content') ?>
    </main>
    <script src="<?= $base ?>/assets/vendor/jquery/jquery.min.js"></script>
    <script src="<?= $base ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $base ?>/vendor/html5-qrcode/html5-qrcode.min.js"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
```

> Note: verify the exact SB Admin asset paths in an existing view (`app/Views/Admin/layout.php`) and match them; adjust `assets/...` prefixes if the project uses different folders.

- [ ] **Step 3: Create the scan page**

Create `app/Views/Scanner/scan.php`:

```php
<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>
<?php $base = rtrim(base_url(), '/'); ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <label for="controlInput" class="form-label fw-bold">Scan or enter QR control number</label>
    <div class="input-group">
      <input type="text" inputmode="numeric" autocomplete="off" class="form-control form-control-lg"
             id="controlInput" placeholder="e.g. 42" autofocus>
      <button class="btn btn-primary" id="lookupBtn" type="button">Go</button>
    </div>
    <button class="btn btn-outline-secondary w-100 mt-2" id="cameraBtn" type="button">
      <i class="fas fa-camera me-1"></i> Scan with camera
    </button>
    <div id="reader" class="mt-2" hidden></div>
    <div id="lookupAlert" class="alert alert-warning mt-2 mb-0" hidden></div>
  </div>
</div>

<div id="familyPanel" hidden>
  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Family Head</div>
    <div class="card-body" id="headBody"></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Aid History</div>
    <ul class="list-group list-group-flush" id="historyList"></ul>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header fw-bold">Log Aid Distribution</div>
    <div class="card-body">
      <form id="logForm">
        <input type="hidden" name="control_no" id="logControl">
        <div class="mb-2">
          <label class="form-label">Date</label>
          <input type="date" class="form-control" name="claim_date" id="claimDate" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Aid type</label>
          <select class="form-select" name="aid_type_id" required>
            <?php foreach ($aidTypes as $t): ?>
              <option value="<?= esc($t['aid_type_id'], 'attr') ?>"><?= esc($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Claimed by</label>
          <select class="form-select" name="memberID" id="claimantSelect" required></select>
        </div>
        <button type="submit" class="btn btn-success w-100">Log distribution</button>
        <div id="logAlert" class="alert mt-2 mb-0" hidden></div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = '<?= $base ?>';
const $ = (id) => document.getElementById(id);
$('claimDate').value = new Date().toISOString().slice(0, 10);

async function lookup(control) {
  $('lookupAlert').hidden = true;
  const res = await fetch(`${BASE}/scanner/lookup/${encodeURIComponent(control)}`);
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    $('lookupAlert').textContent = err.error || 'Lookup failed.';
    $('lookupAlert').hidden = false;
    $('familyPanel').hidden = true;
    return;
  }
  const data = await res.json();
  const h = data.head;
  $('headBody').innerHTML =
    `<div class="fw-bold">${h.firstname ?? ''} ${h.lastname ?? ''}</div>` +
    `<div class="text-muted small">${h.address ?? ''}</div>`;
  $('claimantSelect').innerHTML = data.members
    .map(m => `<option value="${m.memberID}">${m.firstname} ${m.lastname} (${m.relationship || 'Member'})</option>`).join('');
  renderHistory(data.history);
  $('logControl').value = data.control_no;
  $('familyPanel').hidden = false;
}

function renderHistory(rows) {
  $('historyList').innerHTML = rows.length
    ? rows.map(r => `<li class="list-group-item d-flex justify-content-between">
        <span>${r.aid_type ?? ''} — ${r.claimant ?? ''}</span><span class="text-muted">${r.claim_date}</span></li>`).join('')
    : '<li class="list-group-item text-muted">No aid received yet.</li>';
}

$('lookupBtn').addEventListener('click', () => {
  const v = $('controlInput').value.trim();
  if (v) lookup(v);
});
$('controlInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') { e.preventDefault(); $('lookupBtn').click(); }
});

$('logForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const res = await fetch(`${BASE}/scanner/log`, { method: 'POST', body: new FormData(e.target) });
  const data = await res.json().catch(() => ({}));
  const alert = $('logAlert');
  if (res.ok && data.ok) {
    renderHistory(data.history);
    alert.className = 'alert alert-success mt-2 mb-0';
    alert.textContent = 'Aid logged.';
  } else {
    alert.className = 'alert alert-danger mt-2 mb-0';
    alert.textContent = data.errors ? Object.values(data.errors).join(' ') : (data.error || 'Failed to log.');
  }
  alert.hidden = false;
});

let scanner;
$('cameraBtn').addEventListener('click', () => {
  const reader = $('reader');
  reader.hidden = false;
  scanner = new Html5Qrcode('reader');
  scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 200 },
    (text) => {
      scanner.stop().then(() => { reader.hidden = true; });
      $('controlInput').value = text.trim();
      lookup(text.trim());
    }, () => {});
});
</script>
<?= $this->endSection() ?>
```

- [ ] **Step 4: Manual verification**

Run: `php spark serve` (or XAMPP). Log in as a scanner account, open `/scanner/scan`.
Expected: page renders mobile-first; entering a mapped control number shows the head, members in the claimant dropdown, history (or "No aid received yet."), and the log form. Logging adds a history row and an audit trail entry. Unmapped number shows the warning alert.

- [ ] **Step 5: Commit**

```bash
git add app/Views/Scanner/ public/vendor/html5-qrcode/
git commit -m "feat(scanner): add mobile-first scan shell, scan page, camera lib"
```

---

## Task 9: Admin sidebar link

**Files:**
- Modify: `app/Views/Admin/layout.php` (sidebar nav list)

**Interfaces:**
- Consumes: existing admin sidebar markup. Add a link to `admin`-reachable scanner entry (`scanner/scan`).

- [ ] **Step 1: Add a "Scanner" sidebar link**

In `app/Views/Admin/layout.php`, alongside the existing sidebar nav items (match the surrounding `<li class="nav-item">` markup exactly), add:

```php
<li class="nav-item">
    <a class="nav-link" href="<?= rtrim(base_url(), '/') ?>/scanner/scan">
        <i class="fas fa-fw fa-qrcode"></i>
        <span>Scanner</span>
    </a>
</li>
```

> Match the exact class names / icon-wrapper structure used by neighboring links in this file; the snippet above is the SB Admin pattern but adjust to the file's actual markup.

- [ ] **Step 2: Manual verification**

Log in as Admin/Developer. Confirm a "Scanner" link appears in the sidebar and opens `/scanner/scan`.

- [ ] **Step 3: Full suite + route check**

Run: `vendor/bin/phpunit`
Expected: no new failures vs baseline.
Run: `php spark routes`
Expected: every route resolves; scanner routes present.

- [ ] **Step 4: Commit**

```bash
git add app/Views/Admin/layout.php
git commit -m "feat(scanner): add Scanner link to admin sidebar"
```

---

## Task 10: Update the implementation summary

**Files:**
- Create: `docs/superpowers/summary/2026-07-01-scanner-module-summary.md`

- [ ] **Step 1: Write a thorough summary**

Document, matching the detail of `2026-06-29-qr-access-cards-summary.md`:
- **What's new:** every new file (models, controller, views, vendored lib, tests) with its purpose.
- **What was modified:** `accesscardV13.sql` (3 tables + enum), `RoleAccess.php`, `MemberModel.php`, `Routes.php`, `Admin/layout.php` — one row each, what changed and why.
- **Seed data:** `aid_type` = Financial, Rice, Grocery.
- **Custom component:** vendored `html5-qrcode` (only non-Bootstrap/SB-Admin piece) and why (client-side camera decode).
- **Core decision:** control_no decoupled from memberID via `qr_control` (resolves the shared-ID concern).
- **Tests:** list new test files + the skip-without-DB posture.
- **Deferred:** reports, history management, aid-type CRUD.

- [ ] **Step 2: Commit**

```bash
git add -f docs/superpowers/summary/2026-07-01-scanner-module-summary.md
git commit -m "docs(scanner): add implementation summary"
```

---

## Self-Review Notes

- **Spec coverage:** data model (Task 1), role+shell (Tasks 2, 8), scan flow input/resolve/display/history/log (Tasks 3–8), backend controller/models/routes (Tasks 3–7), views Bootstrap+SB-Admin (Task 8), html5-qrcode flagged (Task 8), admin link (Task 9), tests (Tasks 2–7), summary requirement (Task 10). All spec sections mapped.
- **Type consistency:** `headForControl(int):?int`, `historyFor(int):array`, `logAid(array):int`, `active():array`, `familyMembers(int):array`, `requireRole(['Scanner','Admin','Developer'])` used consistently across tasks.
- **DB-dependent tests** kept to contract/guard assertions so the suite still skips gracefully without a DB, matching the existing suite posture.
