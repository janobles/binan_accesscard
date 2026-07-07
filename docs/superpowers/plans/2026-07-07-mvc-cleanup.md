# MVC Cleanup & FamilyController Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tick all five `docs/knowledge/violations.md` items — split the 1750-line FamilyController into three controllers + two libraries, move raw queries into models, delete dead code, move inline styles to CSS, reword the strict_types convention — with zero behavior/URL/schema change.

**Architecture:** Pure extraction. Public method bodies move verbatim from `FamilyController` into `FamilyImportController`, `FamilyDataTableController`, and libraries `FamilyDataTablePresenter` / `FamilyModalDataBuilder`; shared guards move to a `FamilyRequestContext` trait. `Routes.php` retargets the same URLs.

**Tech Stack:** PHP 8.2, CodeIgniter 4, PHPUnit (`CIUnitTestCase`), Bootstrap 5 / SB Admin.

**Spec:** `docs/superpowers/specs/2026-07-07-mvc-cleanup-design.md`

## Global Constraints

- No migrations; schema source of truth is the SQL dump. No new tables/columns.
- Every family mutation keeps its `Audit/AuditTrailsModel` write — verify per task.
- URLs unchanged; only controller targets in `app/Config/Routes.php` change.
- JSON response shapes for `dataTable` and `importStatus` are frontend contracts — relocate, never alter.
- Baseline: 87 tests, 281 assertions, 5 skipped, 0 failures. Every commit ≥ this.
- After each task: `vendor/bin/phpunit` green AND `php spark routes` exits clean.
- Method bodies move **verbatim** (copy-paste, adjust only `$this->request`/`$this->response` access where a library needs them injected). No opportunistic rewrites.
- New test files: `tests/unit/`, namespace `Tests\Unit`, `final class ... extends CIUnitTestCase`.
- Branch: `refactor/mvc-cleanup`. Before creating it: `git fetch origin && git checkout main && git reset --hard origin/main` (local main lags; see memory).

---

### Task 1: Lookup controller queries → models

**Files:**
- Modify: `app/Models/Lookups/ServiceModel.php` (add 2 methods)
- Modify: `app/Models/Lookups/SectorModel.php` (add 1 method)
- Modify: `app/Controllers/Lookups/ServiceController.php:176-219`
- Modify: `app/Controllers/Lookups/SectorController.php:226-237`
- Test: `tests/unit/LookupModelUsageTest.php` (create)

**Interfaces:**
- Produces: `ServiceModel::insertWithNextId(array $data): int|false`, `ServiceModel::isInUse(int $serviceId): bool`, `SectorModel::isInUse(int $sectorId): bool`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use CodeIgniter\Test\CIUnitTestCase;

final class LookupModelUsageTest extends CIUnitTestCase
{
    public function testServiceModelExposesUsageAndInsertHelpers(): void
    {
        $this->assertTrue(method_exists(ServiceModel::class, 'isInUse'));
        $this->assertTrue(method_exists(ServiceModel::class, 'insertWithNextId'));
    }

    public function testSectorModelExposesUsageHelper(): void
    {
        $this->assertTrue(method_exists(SectorModel::class, 'isInUse'));
    }

    public function testControllersNoLongerBuildRawQueries(): void
    {
        $service = file_get_contents(APPPATH . 'Controllers/Lookups/ServiceController.php');
        $sector  = file_get_contents(APPPATH . 'Controllers/Lookups/SectorController.php');
        $this->assertStringNotContainsString('->table(', $service);
        $this->assertStringNotContainsString('->table(', $sector);
        $this->assertStringNotContainsString('Database::connect', $service);
    }
}
```

- [ ] **Step 2: Run to verify fail** — `vendor/bin/phpunit --filter LookupModelUsageTest` → FAIL (methods missing).

- [ ] **Step 3: Implement.** In `ServiceModel` add (mirroring the controller code at `ServiceController.php:181-188` and `:208-219`):

```php
/**
 * Inserts a service inside a transaction, assigning the next serviceID.
 * Returns the new serviceID or false on failure.
 */
public function insertWithNextId(array $data): int|false
{
    $db = $this->db;
    $db->transStart();
    $data['serviceID'] = $this->nextServiceId();
    $inserted = $this->insert($this->dataForCurrentSchema($data)) !== false;
    $db->transComplete();

    return ($inserted && $db->transStatus() !== false) ? (int) $data['serviceID'] : false;
}

/**
 * True if any `member_services` row links to this service ID. Guards
 * archive/delete so in-use services cannot be removed.
 */
public function isInUse(int $serviceId): bool
{
    if (! $this->db->tableExists('member_services')) {
        return false;
    }

    return $this->db->table('member_services')
        ->where('serviceID', $serviceId)
        ->countAllResults() > 0;
}
```

In `SectorModel` add (mirroring `SectorController.php:228-237`; keep the `use App\Libraries\SectorIds;` import in the model):

```php
/**
 * True if any `member` row references this sector ID (sectorID stores a JSON
 * array, matched via SectorIds::containsCondition). Guards archive/delete.
 */
public function isInUse(int $sectorId): bool
{
    if (! $this->db->tableExists('member')) {
        return false;
    }

    return $this->db->table('member')
        ->where(SectorIds::containsCondition($sectorId, 'sectorID'), null, false)
        ->countAllResults() > 0;
}
```

Then in `ServiceController`: replace the `181-188` transaction block with

```php
$newId = $model->insertWithNextId($data);
$saved = $newId !== false;
if ($saved) {
    $serviceId = $newId;
}
```

delete private `serviceIsUsed()` and replace its call site(s) with `$model->isInUse($serviceId)` (grep `serviceIsUsed(` for call sites). In `SectorController`: delete private `sectorIsUsed()`, replace call site(s) with `$model->isInUse($sectorId)`; remove the now-unused `SectorIds` import from the controller **only if** grep shows no other use in that file.

- [ ] **Step 4: Verify** — `vendor/bin/phpunit` green (88+ tests), `php spark routes` clean.
- [ ] **Step 5: Commit** — `refactor(lookups): move usage/insert queries from controllers to models`

---

### Task 2: Inline styles → page CSS

**Files:**
- Modify: `app/Views/Family/list.php:47,69`, `app/Views/Accounts/account-form-modal.php:44`
- Modify: `public/css/managerecord.css`, `public/css/accounts.css`

**Interfaces:** none (visual only).

- [ ] **Step 1:** In `managerecord.css` append:

```css
.family-filter-field .dropdown-menu {
    max-height: 14rem;
}
```

In `list.php` lines 47 and 69 drop ` style="max-height: 14rem;"` from both `dropdown-menu` divs.

- [ ] **Step 2:** In `accounts.css`, next to existing `.account-card-header` rules, add:

```css
.edit-account-modal .account-card-header {
    border: 0;
    background: transparent;
    padding: 0 0 0.5rem;
}
```

In `account-form-modal.php:44` drop the `style="..."` attribute.

- [ ] **Step 3: Verify** — `grep -n 'style="' app/Views/Family/list.php app/Views/Accounts/account-form-modal.php` returns nothing; phpunit green; load Manage Records + account modal in browser, dropdowns still scroll at 14rem, header unchanged.
- [ ] **Step 4: Commit** — `refactor(views): move inline styles to page CSS`

---

### Task 3: strict_types convention reword (docs only)

**Files:**
- Modify: `CLAUDE.md` (Non-Negotiables, "PHP 8.2+" line)
- Modify: `docs/knowledge/violations.md` (tick item 5)

- [ ] **Step 1:** In `CLAUDE.md` change

```
- **PHP 8.2+.** Respect existing strict-type / namespace conventions.
```

to

```
- **PHP 8.2+.** Typed signatures everywhere; no `declare(strict_types=1)`
  (matches CI4 appstarter — see `docs/knowledge/php-practices/idioms.md`).
  Respect existing namespace conventions.
```

- [ ] **Step 2:** Tick violations.md item 5 `[x]` + `*(Fixed: reworded convention, this commit)*`.
- [ ] **Step 3: Commit** — `docs: resolve strict_types convention as typed-signatures-only`

---

### Task 4: Shared context trait `FamilyRequestContext`

**Files:**
- Create: `app/Controllers/Families/FamilyRequestContext.php`
- Modify: `app/Controllers/Families/FamilyController.php`
- Test: `tests/unit/FamilyRequestContextTest.php` (create)

**Interfaces:**
- Produces (trait methods, exact current signatures, bodies moved verbatim from `FamilyController.php:961-1084` and `:981-994` and `:1001-1016`):
  - `isEmployeeContext(): bool`
  - `currentRouteBase(): string`
  - `partialGuard(RedirectResponse $guard, string $message): string|RedirectResponse`
  - `recordMissing(): string`
  - `jsonError(string $message, int $statusCode, ?string $code = null)`
  - `requireFamilyEntryAccess(): ?RedirectResponse`
  - `requireFamilyViewAccess(): ?RedirectResponse`
- All `private` in the trait; keep the existing docblocks (esp. the `uri_string()` comment at `:963-967`).

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class FamilyRequestContextTest extends CIUnitTestCase
{
    public function testTraitExistsAndCarriesGuards(): void
    {
        $this->assertTrue(trait_exists(\App\Controllers\Families\FamilyRequestContext::class));
        foreach (['isEmployeeContext', 'currentRouteBase', 'partialGuard', 'recordMissing', 'jsonError', 'requireFamilyEntryAccess', 'requireFamilyViewAccess'] as $method) {
            $this->assertTrue(method_exists(\App\Controllers\Families\FamilyRequestContext::class, $method), $method);
        }
    }

    public function testFamilyControllerUsesTrait(): void
    {
        $this->assertContains(
            \App\Controllers\Families\FamilyRequestContext::class,
            class_uses(\App\Controllers\Families\FamilyController::class)
        );
    }
}
```

- [ ] **Step 2:** `vendor/bin/phpunit --filter FamilyRequestContextTest` → FAIL.
- [ ] **Step 3:** Create the trait (header below), cut the seven methods verbatim from FamilyController into it, add `use FamilyRequestContext;` at the top of the class body.

```php
<?php

namespace App\Controllers\Families;

use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Request-context helpers shared by the Families controllers: admin/employee
 * route detection, access guards, and modal/JSON error fragments. Relies on
 * BaseController's $this->request / $this->response.
 */
trait FamilyRequestContext
{
    // ... the seven methods, verbatim ...
}
```

- [ ] **Step 4:** `vendor/bin/phpunit` green; `php spark routes` clean.
- [ ] **Step 5: Commit** — `refactor(families): extract FamilyRequestContext trait`

---

### Task 5: Import extraction → `FamilyImportController`

**Files:**
- Create: `app/Controllers/Families/FamilyImportController.php`
- Modify: `app/Controllers/Families/FamilyController.php` (remove moved methods + now-unused imports)
- Modify: `app/Config/Routes.php:40-43,97-100`
- Test: `tests/unit/FamilyRoutesTest.php` (create)

**Interfaces:**
- Consumes: `FamilyRequestContext` trait (Task 4).
- Produces: `FamilyImportController` with public `downloadTemplate()`, `importForm(): string|RedirectResponse`, `import()`, `importStatus(int $jobId)` — bodies verbatim from `FamilyController.php:222-419`.

- [ ] **Step 1: Write failing route test**

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class FamilyRoutesTest extends CIUnitTestCase
{
    /** Route → handler pairs that must survive the controller split. */
    public function testFamilyRoutesResolveToSplitControllers(): void
    {
        $collection = Services::routes(true);
        require APPPATH . 'Config/Routes.php';
        $getRoutes  = $collection->getRoutes('GET');
        $postRoutes = $collection->getRoutes('POST');

        $expected = [
            ['admin/manage-family/import', $getRoutes, 'FamilyImportController::importForm'],
            ['admin/manage-family/template', $getRoutes, 'FamilyImportController::downloadTemplate'],
            ['employee/manage-family/import', $postRoutes, 'FamilyImportController::import'],
            ['admin/manage-family/data', $getRoutes, 'FamilyDataTableController::dataTable'],
            ['employee/manage-family/data', $getRoutes, 'FamilyDataTableController::dataTable'],
            ['viewer/manage-family/data', $getRoutes, 'FamilyDataTableController::dataTable'],
            ['admin/manage-family/view/([0-9]+)', $getRoutes, 'FamilyController::viewFamily'],
            ['families', $postRoutes, 'FamilyController::store'],
        ];

        foreach ($expected as [$path, $routes, $handler]) {
            $this->assertArrayHasKey($path, $routes, $path);
            $this->assertStringContainsString($handler, (string) $routes[$path], $path);
        }
    }
}
```

(Adjust the key format after first failing run if CI4 normalizes route keys differently — the assertion intent is fixed: same URLs, new handlers. dataTable keys will fail until Task 6; mark those two lines in with the others now and expect this test fully green only after Task 6 — alternatively split assertions across the two tasks. **Decision: include only import + unchanged rows now; append the dataTable rows in Task 6 Step 1.**)

- [ ] **Step 2:** Run → FAIL (`FamilyImportController` unknown).
- [ ] **Step 3:** Create controller:

```php
<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyExcelTemplate;
use App\Models\Jobs\JobQueueModel;
use CodeIgniter\HTTP\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Excel import side of Manage Family: template download, import form,
 * import submission (queued via FamilyImportJob), and status polling.
 * Split out of FamilyController; JSON status payload shape is a frontend
 * contract (assets/js/dashboard import polling) and must not change.
 */
class FamilyImportController extends BaseController
{
    use FamilyRequestContext;

    // downloadTemplate(), importForm(), import(), importStatus() — bodies
    // moved VERBATIM from FamilyController.php:222-419.
}
```

Move the four methods, plus any private helper used **only** by them (check with grep before moving: e.g. `auditSystemError` is used by other actions — if shared, keep it in FamilyController and duplicate-check; if it turns out shared by both controllers, move it into the trait instead). Update `Routes.php` lines 40-43 and 97-100 to `Families\FamilyImportController::...` (same paths). Remove imports from FamilyController that no longer resolve (`FamilyExcelTemplate`, `JobQueueModel`, `Xlsx`) only if unused after the move.

- [ ] **Step 4:** `vendor/bin/phpunit` green; `php spark routes` clean; manual: import form loads, template downloads.
- [ ] **Step 5: Commit** — `refactor(families): extract FamilyImportController`

---

### Task 6: dataTable extraction → controller + presenter library

**Files:**
- Create: `app/Controllers/Families/FamilyDataTableController.php`
- Create: `app/Libraries/FamilyDataTablePresenter.php`
- Modify: `app/Controllers/Families/FamilyController.php` (remove `dataTable` block, lines 1311-1577)
- Modify: `app/Config/Routes.php:39,96,123`
- Test: `tests/unit/FamilyDataTablePresenterTest.php` (create); extend `FamilyRoutesTest` with the dataTable rows from Task 5.

**Interfaces:**
- Consumes: `FamilyRequestContext` (for route base), `SearchModel`, `SectorModel`, QR control numbers (see how `dataTable()` at `:1322` fetches them — keep the same collaborators).
- Produces:
  - `FamilyDataTableController::dataTable()` — same JSON envelope (`draw`, `recordsTotal`, `recordsFiltered`, `data`, optional `error`) via presenter.
  - `FamilyDataTablePresenter` (constructor: `__construct(private string $routeBase, private bool $allMembersScope, ...)` — final shape decided from what the moved helpers actually need; no request/session access inside the library, values passed in) with public methods:
    - `row(array $row, array $sectorShortcodes, array $controlNumbers = []): array` (from `dataTableRow`)
    - `payload(int $draw, int $total, int $filtered, array $data, ?string $error = null): array` (from `dataTablePayload`)
    - private: `qrCell`, `displayName`, `actions` (from `dataTableQrCell`, `dataTableDisplayName`, `dataTableActions` — HTML output byte-identical).
  - Ordering/shortcode helpers (`dataTableOrder`, `dataTableSectorShortcodes`, `dataTableRouteBase`) stay in the controller — they read the request/session.

- [ ] **Step 1: Write failing presenter test**

```php
<?php

namespace Tests\Unit;

use App\Libraries\FamilyDataTablePresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyDataTablePresenterTest extends CIUnitTestCase
{
    public function testPayloadEnvelopeShape(): void
    {
        $presenter = new FamilyDataTablePresenter('admin/manage-family', false);
        $payload   = $presenter->payload(3, 10, 2, [['x']]);

        $this->assertSame(3, $payload['draw']);
        $this->assertSame(10, $payload['recordsTotal']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertSame([['x']], $payload['data']);
        $this->assertArrayNotHasKey('error', $payload);

        $withError = $presenter->payload(1, 0, 0, [], 'boom');
        $this->assertSame('boom', $withError['error']);
    }
}
```

(Adjust constructor args in the test to the final signature chosen in Step 3 — then keep test and class in sync.)

- [ ] **Step 2:** Run → FAIL (class missing). Also append dataTable rows to `FamilyRoutesTest` (Task 5 list) → FAIL.
- [ ] **Step 3:** Create presenter (move `dataTableRow`, `dataTableQrCell`, `dataTableDisplayName`, `dataTableActions`, `dataTablePayload` verbatim; replace `$this->currentRouteBase()` / request reads inside moved code with constructor-injected values). Create controller:

```php
<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyDataTablePresenter;

/**
 * Server-side DataTables endpoint for the Manage Records family list
 * (admin, employee, viewer route groups). JSON envelope is consumed by
 * assets/js DataTables init — shape must not change.
 */
class FamilyDataTableController extends BaseController
{
    use FamilyRequestContext;

    // dataTable() body verbatim from FamilyController.php:1322-1392,
    // with row/payload calls going through FamilyDataTablePresenter.
    // dataTableOrder(), dataTableSectorShortcodes(), dataTableRouteBase()
    // move here as private methods (they read request/session).
}
```

Retarget `Routes.php` lines 39, 96, 123 to `Families\FamilyDataTableController::dataTable`. Delete moved code from FamilyController.

- [ ] **Step 4:** phpunit green; `spark routes` clean; manual: Manage Records table loads for admin + employee + viewer, search/sort/paging work, QR cell + action buttons render identically (compare a row's HTML before/after via browser dev tools).
- [ ] **Step 5: Commit** — `refactor(families): extract dataTable controller and presenter`

---

### Task 7: Modal view-data → `FamilyModalDataBuilder`

**Files:**
- Create: `app/Libraries/FamilyModalDataBuilder.php`
- Modify: `app/Controllers/Families/FamilyController.php` (`renderFamilyModal:1609`, `familyModalUpdateData:1689`, `shapeModalMembers:1725`, `serviceNameMap:911`, `incomeLabelMap:929`)
- Test: `tests/unit/FamilyModalDataBuilderTest.php` (create)

**Interfaces:**
- Consumes: `MemberModel`, `MemberServiceModel`, `ServiceModel`, `FamilyFormOptionsModel`, `FamilyRecordPresenter`/`FamilyProfilingFormV2` supports (keep exactly the collaborators the moved code already uses).
- Produces: `FamilyModalDataBuilder` with public methods (names final, bodies moved verbatim):
  - `updateData(array $head, array $headServiceIds): array` (from `familyModalUpdateData`)
  - `shapeMembers(array $members, array $serviceIdsByMember): array` (from `shapeModalMembers`)
  - `serviceNameMap(array $serviceIdsByMember): array`, `incomeLabelMap(): array`
- `renderFamilyModal()` **stays** in FamilyController (it decides guards/redirects and calls `view()`), but its data-assembly middle section now calls the builder. `createFamily()`, `viewFamily()`, `editFamily()` keep their signatures.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit;

use App\Libraries\FamilyModalDataBuilder;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyModalDataBuilderTest extends CIUnitTestCase
{
    public function testShapeMembersAttachesServiceIds(): void
    {
        $builder = new FamilyModalDataBuilder();
        $shaped  = $builder->shapeMembers(
            [['memberID' => 7, 'firstname' => 'Ana']],
            [7 => [1, 2]]
        );

        $this->assertCount(1, $shaped);
        $this->assertSame([1, 2], $shaped[0]['serviceIDs'] ?? $shaped[0]['services'] ?? null);
    }
}
```

(Before writing, read `shapeModalMembers` at `:1725` and assert the **actual** key it sets — fix the assertion to the real key, don't chain `??`.)

- [ ] **Step 2:** Run → FAIL. **Step 3:** Create builder, move the four methods verbatim, update `renderFamilyModal` call sites. **Step 4:** phpunit green; manual: view/edit family modals open for admin + employee, create form loads, saving an edit still writes a `FAMILY_UPDATED` audit row.
- [ ] **Step 5: Commit** — `refactor(families): extract FamilyModalDataBuilder`

---

### Task 8: Request-shaper consolidation + dead code

**Files:**
- Create: `app/Libraries/FamilyRequestShaper.php`
- Modify: `app/Controllers/Families/FamilyController.php`
- Test: `tests/unit/FamilyRequestShaperTest.php` (create)

**Interfaces:**
- Produces: `FamilyRequestShaper` (stateless; request values passed in as arrays/scalars — no `$this->request` inside) with public methods, bodies verbatim from FamilyController:
  - `memberPayloadFromArray(array $member): array` (from `:1236`)
  - `hasMemberData(array $member): bool` (`:1264`)
  - `moneyOrNull(mixed $value): ?float` (`:1274`)
  - `nullableText(mixed $value): ?string` (`:1283`)
  - `cleanName(mixed $value): string` (`:1295`)
  - `cleanAddress(mixed $value): string` (`:1306`)
  - `combineAddressBarangay(mixed $address, mixed $barangay): ?string` (`:1120`)
  - `splitAddressBarangay(mixed $combined): array` (`:1133`)
  - `rulesForEntryType(string $entryType): array` (`:1198`)
- Stays in controller (request-bound): `memberPayload(string $prefix)` (reads POST — now delegates to `memberPayloadFromArray`), `entryType()`, `submissionWasTruncated()`, `splitHeadAndMembers()`.
- Delete: `shapeExistingMembers()` (`:824`) — dead, grep-verified.

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit;

use App\Libraries\FamilyRequestShaper;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyRequestShaperTest extends CIUnitTestCase
{
    private FamilyRequestShaper $shaper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shaper = new FamilyRequestShaper();
    }

    public function testMoneyOrNull(): void
    {
        $this->assertNull($this->shaper->moneyOrNull(''));
        $this->assertNull($this->shaper->moneyOrNull(null));
        $this->assertSame(1500.5, $this->shaper->moneyOrNull('1,500.50'));
    }

    public function testCleanNameTrimsAndNormalizes(): void
    {
        $this->assertSame('Ana Cruz', $this->shaper->cleanName('  Ana   Cruz '));
    }

    public function testSplitAndCombineAddressRoundTrip(): void
    {
        $combined = $this->shaper->combineAddressBarangay('123 St', 'Canlalay');
        $parts    = $this->shaper->splitAddressBarangay($combined);
        $this->assertSame('123 St', $parts['address'] ?? $parts[0]);
        $this->assertSame('Canlalay', $parts['barangay'] ?? $parts[1]);
    }
}
```

(Same rule as Task 7: read the real bodies first — `moneyOrNull` may not strip commas, `splitAddressBarangay` return keys must be asserted as implemented. Pin **current** behavior, not wished-for behavior.)

- [ ] **Step 2:** Run → FAIL. **Step 3:** Create library, move methods verbatim, controller delegates via a single `private FamilyRequestShaper $shaper` (instantiate in `initController` or lazily). Delete `shapeExistingMembers()`. Re-grep `shapeExistingMembers` repo-wide → zero hits.
- [ ] **Step 4:** phpunit green; manual: family create (head + 1 member + services) and update succeed, audit rows written for both.
- [ ] **Step 5: Commit** — `refactor(families): extract FamilyRequestShaper, drop dead shapeExistingMembers`

---

### Task 9: Docs / RAG sweep + violations ticks

**Files:**
- Modify: `docs/knowledge/violations.md` (tick items 1-4 with commit hashes)
- Modify: `docs/knowledge/binan-conventions/mvc-boundaries.md` (new controllers/libraries as the worked example of controllers-decide/libraries-build; fix stale FamilyController references)
- Modify: `PROJECT_STRUCTURE.md` (add the 6 new files, adjust FamilyController description/line counts)

- [ ] **Step 1:** Grep docs for stale references: `grep -rn "FamilyController" docs/ PROJECT_STRUCTURE.md CLAUDE.md` — update every hit that describes the old shape.
- [ ] **Step 2:** Tick violations.md items 1-4 `[x]` + `*(Fixed: <commit>)*` each.
- [ ] **Step 3:** phpunit green (final count recorded in PR body). Commit — `docs(knowledge): record FamilyController split, tick violations`

---

### Task 10: Verification + review gate + PR

- [ ] **Step 1:** Full suite: `vendor/bin/phpunit` → 0 failures, ≥ 87 tests + new ones. `php spark routes` → all family routes resolve to the three controllers.
- [ ] **Step 2:** Manual smoke (all as admin, repeat create/update as employee): login → role redirect; Manage Records dataTable (search/sort/page); family create; view/edit modal; update; archive; restore; Excel template download; import + status polling; confirm `audit_trails` rows for create/update/archive/restore/import.
- [ ] **Step 3:** `coderabbit auth status`, then `coderabbit review --base main --agent` **in background; wait for completion** (minutes on this diff; retry on `TRPCClientError`). Triage per `superpowers:receiving-code-review`: verify each finding against code + CLAUDE.md non-negotiables; fix genuine in-scope bugs; re-run phpunit; park pre-existing/out-of-scope in a GitHub issue (format per CLAUDE.md) citing PR # + branch; note won't-fix reasons.
- [ ] **Step 4:** Push branch, open PR to `main` (body: baseline vs final test counts, smoke checklist, CodeRabbit outcome). Merge per `superpowers:finishing-a-development-branch`.
