# Manage Records UI/UX + Design System Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the manage-records toolbar (single database search + one multi-column filter panel with live-apply and pills), make QR NO. the sortable default order, and land the design system layer (btn() helper, conventions doc, two reusable components) that other tabs adopt later.

**Architecture:** Server-side DataTables stays untouched conceptually; the `/data` endpoint gains a `qr` sort key backed by a `qr_control` join in both scope models. The toolbar becomes a props-only component (`components/records_toolbar.php`) plus a pills component, consumed by `Family/list.php`. All interaction (live-apply debounce, pill render/remove, barangay narrow) lives in the existing `public/assets/js/dashboard/family-datatable.js`, rewritten in place so `asset_helper.php` references stay valid.

**Tech Stack:** CodeIgniter 4 (PHP 8.2), Bootstrap 5.3.3, DataTables (server-side), vanilla JS, phpunit.

**Spec:** `docs/superpowers/specs/2026-07-12-manage-records-ui-design.md`

## Global Constraints

- No migrations, no schema changes. `qr_control` join is read-only.
- Every rule from CLAUDE.md applies: controllers decide, libraries build; typed signatures; match SQL dump names (`qr_control.control_no`, `headID`).
- Buttons use plain Bootstrap classes via `btn(string $role)` helper. No button component/partial.
- Filter panel: stock Bootstrap only (`dropdown-menu` + `row`/`col` + `form-check`). No drill-in JS.
- Redundancy rules: no Apply/Reset inside the panel; pill x removes one filter; toolbar Clear resets keyword + filters + sort.
- Default table order: QR NO. ascending (1 to n). Rows without a control number sort last.
- Live-apply debounce about 350ms, client-side JS only (no CI4 Throttler).
- `managerecord.css` is additive only: Lookups and audit-trails pages reuse its `.records-*` hooks (see comments in `app/Views/Lookups/*.php`); do not rename or remove existing rules.
- Comments in plain language, no em dashes (repo comment style).
- Work on branch `feat/manage-records-ui` off a freshly synced main (`git fetch origin && git reset --hard origin/main` on main first; local main is known to lag).

---

### Task 1: btn() role helper

**Files:**
- Create: `app/Helpers/ui_helper.php`
- Modify: `app/Config/Autoload.php:91` (`public $helpers = ['asset'];` becomes `['asset', 'ui']`)
- Test: `tests/unit/UiHelperTest.php`

**Interfaces:**
- Produces: `btn(string $role): string` returning a full Bootstrap class string. Roles: `search`, `clear`, `add`, `import`, `filter`. Unknown role throws `InvalidArgumentException`. Loaded globally via Autoload, so views call `btn('add')` directly.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

/**
 * btn() maps semantic button roles to the Bootstrap classes documented in
 * docs/knowledge/binan-conventions/ui-design-system.md. The map is the single
 * source of truth for button colors across the dashboard toolbars.
 */
final class UiHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ui');
    }

    public function testKnownRolesReturnDocumentedClasses(): void
    {
        $this->assertSame('btn btn-success', btn('search'));
        $this->assertSame('btn btn-danger', btn('clear'));
        $this->assertSame('btn btn-primary', btn('add'));
        $this->assertSame('btn btn-warning', btn('import'));
        $this->assertSame('btn btn-outline-secondary', btn('filter'));
    }

    public function testUnknownRoleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        btn('launch-missiles');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/UiHelperTest.php`
Expected: ERROR "Call to undefined function btn()" (or helper file not found).

- [ ] **Step 3: Write minimal implementation**

`app/Helpers/ui_helper.php`:

```php
<?php

/**
 * UI helper: semantic button roles.
 *
 * btn() is the single source of truth for toolbar button colors. The role
 * table is documented in docs/knowledge/binan-conventions/ui-design-system.md;
 * extend the map there first, then here.
 */

if (! function_exists('btn')) {
    /**
     * Returns the Bootstrap class string for a semantic button role.
     *
     * @throws InvalidArgumentException on a role not in the map, so a typo
     *                                  fails loudly instead of rendering an unstyled button.
     */
    function btn(string $role): string
    {
        $map = [
            'search' => 'btn btn-success',
            'clear'  => 'btn btn-danger',
            'add'    => 'btn btn-primary',
            'import' => 'btn btn-warning',
            'filter' => 'btn btn-outline-secondary',
        ];

        if (! isset($map[$role])) {
            throw new InvalidArgumentException('Unknown button role: ' . $role);
        }

        return $map[$role];
    }
}
```

In `app/Config/Autoload.php` change line 91:

```php
    public $helpers = ['asset', 'ui'];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/UiHelperTest.php`
Expected: OK (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Helpers/ui_helper.php app/Config/Autoload.php tests/unit/UiHelperTest.php
git commit -m "feat(ui): btn() role helper for standardized button classes"
```

---

### Task 2: QR sort on the /data endpoint

**Files:**
- Modify: `app/Controllers/Families/FamilyDataTableController.php:105-138` (`dataTableOrder()`)
- Modify: `app/Models/Families/MemberModel.php:255-276` (`applyMemberOrder()`)
- Modify: `app/Models/SearchModel.php` (`applyAllMembersOrder()`, around line 120)
- Test: `tests/unit/FamilyDataTableOrderTest.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: order key `'qr'` accepted by `MemberModel::searchFamilies()` and `SearchModel::allMembers()` via their existing `$orderKey` parameter. `dataTableOrder()` returns `['qr', 'asc']` when DataTables sends no order (the new default) and maps column 0 to `'qr'`. Task 4's JS relies on: column 0 orderable, default order asc.

- [ ] **Step 1: Write the failing test**

`tests/unit/FamilyDataTableOrderTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Controllers\Families\FamilyDataTableController;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * dataTableOrder() turns the DataTables order[] request into a
 * [columnKey, direction] pair. Default (no order requested) is QR ascending
 * so the table always opens 1 to n, per the manage-records UI spec.
 */
final class FamilyDataTableOrderTest extends CIUnitTestCase
{
    private function orderFor(array $get): array
    {
        $_GET = $get;
        Services::reset(true);
        $controller = new FamilyDataTableController();
        $controller->initController(Services::request(), Services::response(), Services::logger());

        $invoker = $this->getPrivateMethodInvoker($controller, 'dataTableOrder');

        return $invoker();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testNoOrderDefaultsToQrAscending(): void
    {
        $this->assertSame(['qr', 'asc'], $this->orderFor([]));
    }

    public function testEmptyDirectionFallsBackToQrAscending(): void
    {
        $this->assertSame(['qr', 'asc'], $this->orderFor([
            'order' => [['column' => '1', 'dir' => '']],
        ]));
    }

    public function testQrColumnMapsToQrKey(): void
    {
        $this->assertSame(['qr', 'desc'], $this->orderFor([
            'order' => [['column' => '0', 'dir' => 'desc']],
        ]));
    }

    public function testAddressAndBirthdayStillMap(): void
    {
        $this->assertSame(['address', 'asc'], $this->orderFor([
            'order' => [['column' => '3', 'dir' => 'asc']],
        ]));
        $this->assertSame(['birthday', 'desc'], $this->orderFor([
            'order' => [['column' => '4', 'dir' => 'desc']],
        ]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/FamilyDataTableOrderTest.php`
Expected: FAIL. `testNoOrderDefaultsToQrAscending` gets `['newest', 'desc']`.

- [ ] **Step 3: Implement**

In `FamilyDataTableController::dataTableOrder()` replace the two `['newest', 'desc']` fallbacks and the column map:

```php
    private function dataTableOrder(): array
    {
        $order = $this->request->getGet('order');

        // No column sort requested (fresh table, or third header click which
        // DataTables sends as an empty direction). Default order is the QR
        // control number ascending so the list always reads 1 to n.
        if (! is_array($order) || ! isset($order[0]) || ! is_array($order[0])) {
            return ['qr', 'asc'];
        }

        $firstOrder = $order[0];
        $column = (int) ($firstOrder['column'] ?? 0);
        $requestedDirection = strtolower((string) ($firstOrder['dir'] ?? ''));

        if ($requestedDirection === '') {
            return ['qr', 'asc'];
        }

        $direction = $requestedDirection === 'desc' ? 'desc' : 'asc';
        // Column order: 0=QR, 1=name, 2=sector, 3=address, 4=birthday, 5=actions.
        // Sector and actions are non-orderable; unknown columns fall back to name.
        $orderKey = match ($column) {
            0 => 'qr',
            3 => 'address',
            4 => 'birthday',
            default => 'name',
        };

        return [$orderKey, $direction];
    }
```

In `MemberModel::applyMemberOrder()` add a `qr` case before `case 'name':`:

```php
            case 'qr':
                // qr_control holds one row per family head. Heads without a
                // control number sort last in either direction.
                $builder->join('qr_control qc_sort', 'qc_sort.headID = member.memberID', 'left')
                    ->orderBy('qc_sort.control_no IS NULL', 'ASC', false)
                    ->orderBy('qc_sort.control_no', $direction);
                return;
```

In `SearchModel::applyAllMembersOrder()` add a `qr` case before `case 'newest':` (the deep scope lists members; a member's QR is its head's control number, so join on `m.headID`):

```php
            case 'qr':
                $builder->join('qr_control qc_sort', 'qc_sort.headID = m.headID', 'left')
                    ->orderBy('qc_sort.control_no IS NULL', 'ASC', false)
                    ->orderBy('qc_sort.control_no', $direction);
                return;
```

Also update the docblock of `applyMemberOrder()`/`applyAllMembersOrder()` to mention the `qr` key.

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit tests/unit/FamilyDataTableOrderTest.php` then the full suite `vendor/bin/phpunit`
Expected: new test PASS; no regressions (DB tests may skip without sqlite3, that is normal).

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Families/FamilyDataTableController.php app/Models/Families/MemberModel.php app/Models/SearchModel.php tests/unit/FamilyDataTableOrderTest.php
git commit -m "feat(records): sortable QR column, default order 1 to n"
```

---

### Task 3: records_toolbar + filter_pills components, rewire Family list views

**Files:**
- Create: `app/Views/components/records_toolbar.php`
- Create: `app/Views/components/filter_pills.php`
- Modify: `app/Views/Family/list.php` (render toolbar + pills above the card)
- Modify: `app/Views/Family/list-body.php` (drop the old filter form, keep the table)
- Test: `tests/unit/RecordsToolbarViewTest.php`

**Interfaces:**
- Consumes: `btn()` from Task 1.
- Produces: DOM contract for Task 4's JS, all hooks listed here and only here:
  - form `#familyDataTableFilters` wrapping the whole toolbar
  - keyword input `[data-records-database-keyword]` (name `q`)
  - filter panel root `[data-records-panel]`; sector group `[data-records-filter="sector"]` (checkboxes name `sectorID[]`), barangay group `[data-records-filter="barangay"]` (checkboxes name `barangay[]`) with narrow input `[data-records-narrow]`, status radios name `status` values `all|active|archived`
  - pills container `#familyFilterPills` (rendered by filter_pills)
  - clear button `[data-records-clear]`
  - table `#familyRecordsTable` with `data-ajax-url` (unchanged)

- [ ] **Step 1: Write the failing view test**

`tests/unit/RecordsToolbarViewTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renders components/records_toolbar with representative props and asserts the
 * DOM hooks family-datatable.js depends on. Markup details can change freely;
 * these hooks are the contract.
 */
final class RecordsToolbarViewTest extends CIUnitTestCase
{
    private function render(array $overrides = []): string
    {
        helper('ui');

        return view('components/records_toolbar', $overrides + [
            'routeBase'        => 'admin/manage-family',
            'keyword'          => '',
            'status'           => 'all',
            'sectorOptions'    => [['sectorID' => '1', 'shortcode' => 'ip', 'sector_name' => 'Indigenous People']],
            'barangayOptions'  => ['Canlalay', 'Malaban'],
            'selectedSectorIds' => [],
            'selectedBarangays' => [],
            'sectorOptionLabel' => static fn (array $s): string => 'IP - Indigenous People',
            'canEdit'          => true,
        ]);
    }

    public function testRendersJsHooks(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="familyDataTableFilters"', $html);
        $this->assertStringContainsString('data-records-database-keyword', $html);
        $this->assertStringContainsString('data-records-panel', $html);
        $this->assertStringContainsString('data-records-filter="sector"', $html);
        $this->assertStringContainsString('data-records-filter="barangay"', $html);
        $this->assertStringContainsString('data-records-narrow', $html);
        $this->assertStringContainsString('data-records-clear', $html);
        $this->assertStringContainsString('name="status"', $html);
    }

    public function testViewerRoleHidesAddAndImport(): void
    {
        $html = $this->render(['canEdit' => false]);

        $this->assertStringNotContainsString('data-family-add-record', $html);
        $this->assertStringNotContainsString('Import', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/RecordsToolbarViewTest.php`
Expected: ERROR, view file `components/records_toolbar` not found.

- [ ] **Step 3: Create `app/Views/components/records_toolbar.php`**

```php
<?php
/**
 * Manage-records toolbar: one database-wide keyword search, one multi-column
 * filter panel (live-apply, no Apply/Reset buttons by design; see the
 * 2026-07-12 manage-records UI spec), and two button groups split by meaning:
 * search actions (Search, Clear) and record actions (Add, Import).
 *
 * Props-only component. Behavior lives in assets/js/dashboard/family-datatable.js,
 * wired to the data-records-* hooks below. Button classes come from btn()
 * (app/Helpers/ui_helper.php).
 *
 * Variables (all defaulted defensively):
 * - $routeBase         string   role route base, e.g. 'admin/manage-family'
 * - $keyword           string   current database keyword
 * - $status            string   'all' | 'active' | 'archived'
 * - $sectorOptions     array    sector rows (sectorID + shortcode/sector_name)
 * - $barangayOptions   array    barangay name strings
 * - $selectedSectorIds string[] checked sector ids
 * - $selectedBarangays string[] checked barangay names
 * - $sectorOptionLabel callable array -> display label
 * - $canEdit           bool     shows Add/Import when true
 */
$routeBase = (string) ($routeBase ?? 'admin/manage-family');
$keyword = trim((string) ($keyword ?? ''));
$status = in_array((string) ($status ?? 'all'), ['all', 'active', 'archived'], true) ? (string) $status : 'all';
$sectorOptions = (array) ($sectorOptions ?? []);
$barangayOptions = (array) ($barangayOptions ?? []);
$selectedSectorIds = array_map('strval', (array) ($selectedSectorIds ?? []));
$selectedBarangays = array_map('strval', (array) ($selectedBarangays ?? []));
$sectorOptionLabel = $sectorOptionLabel ?? static fn (array $sector): string => (string) ($sector['sector_name'] ?? '');
$canEdit = (bool) ($canEdit ?? true);
?>
<form class="row g-2 align-items-center mb-2" id="familyDataTableFilters" aria-label="Family records search and filters">
    <div class="col-12 col-lg">
        <input
            class="form-control"
            type="search"
            name="q"
            value="<?= esc($keyword, 'attr') ?>"
            aria-label="Search entire database"
            placeholder="Search entire database (incl. members)..."
            autocomplete="off"
            data-records-database-keyword
        >
    </div>

    <div class="col-auto">
        <div class="dropdown" data-records-panel>
            <button class="<?= btn('filter') ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filters
            </button>
            <div class="dropdown-menu dropdown-menu-end records-filter-panel p-3">
                <div class="row g-3">
                    <div class="col-12 col-md-4" data-records-filter="sector">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Sector</div>
                        <div class="records-filter-list overflow-auto">
                            <?php foreach ($sectorOptions as $sector): ?>
                                <?php
                                $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                $sectorName = $sectorOptionLabel((array) $sector);
                                ?>
                                <?php if ($sectorId !== '' && $sectorName !== ''): ?>
                                    <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                        <input class="form-check-input m-0" type="checkbox" name="sectorID[]" value="<?= esc($sectorId, 'attr') ?>" data-records-pill-label="<?= esc($sectorName, 'attr') ?>" <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label text-wrap small"><?= esc($sectorName) ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-4" data-records-filter="barangay">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Barangay</div>
                        <input class="form-control form-control-sm mb-1" type="search" placeholder="Type to narrow list..." aria-label="Narrow barangay list" data-records-narrow>
                        <div class="records-filter-list overflow-auto">
                            <?php foreach ($barangayOptions as $barangay): ?>
                                <?php $barangayName = trim((string) $barangay); ?>
                                <?php if ($barangayName !== ''): ?>
                                    <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                        <input class="form-check-input m-0" type="checkbox" name="barangay[]" value="<?= esc($barangayName, 'attr') ?>" data-records-pill-label="<?= esc($barangayName, 'attr') ?>" <?= in_array($barangayName, $selectedBarangays, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label text-wrap small"><?= esc($barangayName) ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-4" data-records-filter="status">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Status</div>
                        <?php foreach (['all' => 'All', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label): ?>
                            <label class="form-check d-flex align-items-center gap-2 py-1">
                                <input class="form-check-input m-0" type="radio" name="status" value="<?= esc($value, 'attr') ?>" data-records-pill-label="<?= esc($label, 'attr') ?>" <?= $status === $value ? 'checked' : '' ?>>
                                <span class="form-check-label small"><?= esc($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
        <div class="btn-group" role="group" aria-label="Search actions">
            <button class="<?= btn('search') ?>" type="submit">Search</button>
            <button class="<?= btn('clear') ?>" type="button" data-records-clear>Clear</button>
        </div>
        <?php if ($canEdit): ?>
        <div class="btn-group" role="group" aria-label="Record actions">
            <button class="<?= btn('add') ?> js-open-family-add-modal" type="button" data-family-add-record data-modal-url="<?= esc(site_url($routeBase . '/create?partial=1'), 'attr') ?>" data-modal-title="New Family Record">Add</button>
            <button class="<?= btn('import') ?> js-open-family-import-modal" type="button" data-modal-url="<?= esc(site_url($routeBase . '/import'), 'attr') ?>" data-modal-title="Import from Excel" title="Bulk-import families from an Excel file">Import</button>
        </div>
        <?php endif; ?>
    </div>
</form>
<?= view('components/filter_pills', [
    'id' => 'familyFilterPills',
]) ?>
```

- [ ] **Step 4: Create `app/Views/components/filter_pills.php`**

```php
<?php
/**
 * Applied-filter pill row. The container is server-rendered; the pills
 * themselves are rendered by JS from the checked filter inputs (see
 * renderFilterPills() in assets/js/dashboard/family-datatable.js), which keeps
 * one renderer instead of a PHP copy and a JS copy.
 *
 * Pill markup contract (JS must produce exactly this shape):
 *   <span class="badge text-bg-light border d-inline-flex align-items-center gap-1">
 *     Sector: IP - Indigenous People
 *     <button type="button" class="btn-close" aria-label="Remove filter" data-records-pill-remove="..."></button>
 *   </span>
 *
 * Variables:
 * - $id string container id, required by the consuming page's JS
 */
$id = (string) ($id ?? 'filterPills');
?>
<div class="d-flex flex-wrap align-items-center gap-1 mb-2" id="<?= esc($id, 'attr') ?>" aria-live="polite" aria-label="Applied filters"></div>
```

- [ ] **Step 5: Rewire `app/Views/Family/list.php`**

Replace the single `view('components/card', ...)` call so the toolbar renders above the card (keep lines 1-23, the prop normalization, unchanged):

```php
<?= view('components/records_toolbar', [
    'routeBase' => $routeBase,
    'keyword' => $keyword,
    'status' => $status,
    'sectorOptions' => $sectorOptions,
    'barangayOptions' => $barangayOptions,
    'selectedSectorIds' => $selectedSectorIds,
    'selectedBarangays' => $selectedBarangays,
    'sectorOptionLabel' => $sectorOptionLabel,
    'canEdit' => $canEdit,
]) ?>

<?= view('components/card', [
    'icon' => 'table',
    'title' => 'Family Records',
    'cardClass' => 'overflow-hidden',
    'bodyClass' => 'd-flex flex-column overflow-hidden p-3',
    'bodyView' => 'Family/list-body',
    'bodyData' => [
        'routeBase' => $routeBase,
    ],
]) ?>
```

- [ ] **Step 6: Slim `app/Views/Family/list-body.php`**

Delete the whole `<form id="familyDataTableFilters">...</form>` block (lines 9-84). The file keeps only the doc comment (updated: the toolbar now lives in components/records_toolbar) and the `<div class="table-responsive">...<table id="familyRecordsTable">...` block, unchanged.

- [ ] **Step 7: Run tests**

Run: `vendor/bin/phpunit tests/unit/RecordsToolbarViewTest.php` then `vendor/bin/phpunit`
Expected: PASS; ScanViewTest and other view tests unaffected.

- [ ] **Step 8: Commit**

```bash
git add app/Views/components/records_toolbar.php app/Views/components/filter_pills.php app/Views/Family/list.php app/Views/Family/list-body.php tests/unit/RecordsToolbarViewTest.php
git commit -m "feat(records): toolbar + filter panel + pills as reusable components"
```

---

### Task 4: JS behavior (live-apply, pills, narrow, QR default sort) + CSS

**Files:**
- Modify: `public/assets/js/dashboard/family-datatable.js` (rewrite in place; path is referenced by `app/Helpers/asset_helper.php:101,121,134` and must not change)
- Modify: `public/css/managerecord.css` (append rules only)

**Interfaces:**
- Consumes: DOM hooks from Task 3 (exact list in that task) and the `qr` order key from Task 2.
- Produces: `window.reloadFamilyDataTable()` (kept, used by the Add/Update modal). Pill markup per the contract in `filter_pills.php`.

- [ ] **Step 1: Rewrite `public/assets/js/dashboard/family-datatable.js`**

Full replacement:

```js
// Server-side DataTables integration for the Manage Records family table.
// Toolbar markup lives in app/Views/components/records_toolbar.php; the
// data-records-* attributes there are the contract this file depends on.
(function (window, document) {
    'use strict';

    var FILTER_DEBOUNCE_MS = 350;

    function selectedCheckboxes(container) {
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll('input[type="checkbox"]:checked'));
    }

    function selectedValues(container) {
        return selectedCheckboxes(container).map(function (input) { return input.value; }).filter(Boolean);
    }

    function rowMatchesSearch(row, searchTerm) {
        if (!searchTerm) {
            return true;
        }

        return row.textContent.toLowerCase().indexOf(searchTerm) !== -1;
    }

    function initializeFamilyDataTable() {
        var tableElement = document.getElementById('familyRecordsTable');
        var filterForm = document.getElementById('familyDataTableFilters');
        var pillsContainer = document.getElementById('familyFilterPills');

        if (!tableElement || !filterForm || typeof window.DataTable !== 'function') {
            return;
        }

        var scope = 'heads';
        var keywordInput = filterForm.querySelector('[data-records-database-keyword]');
        var sectorFilter = filterForm.querySelector('[data-records-filter="sector"]');
        var barangayFilter = filterForm.querySelector('[data-records-filter="barangay"]');
        var quickSearchTerm = '';
        var quickSearchInput = null;
        var debounceTimer = null;

        function statusValue() {
            var checked = filterForm.querySelector('input[name="status"]:checked');
            return checked ? checked.value : 'all';
        }

        function applyCurrentPageQuickSearch() {
            var searchTerm = quickSearchTerm.trim().toLowerCase();

            tableElement.querySelectorAll('tbody tr').forEach(function (row) {
                if (row.querySelector('td.dt-empty')) {
                    return;
                }

                row.style.display = rowMatchesSearch(row, searchTerm) ? '' : 'none';
            });
        }

        function bindCurrentPageQuickSearch() {
            var container = typeof dataTable.table === 'function'
                ? dataTable.table().container()
                : tableElement.closest('.dt-container');
            var dataTablesSearchInput = container ? container.querySelector('.dt-search input') : null;

            if (!dataTablesSearchInput) {
                return;
            }

            quickSearchInput = dataTablesSearchInput.cloneNode(true);
            quickSearchInput.value = quickSearchTerm;
            quickSearchInput.placeholder = 'Filter loaded results...';
            dataTablesSearchInput.parentNode.replaceChild(quickSearchInput, dataTablesSearchInput);

            quickSearchInput.addEventListener('input', function () {
                quickSearchTerm = quickSearchInput.value;
                applyCurrentPageQuickSearch();
            });
        }

        // One pill per checked filter input. Pill markup contract is documented
        // in app/Views/components/filter_pills.php.
        function renderFilterPills() {
            if (!pillsContainer) {
                return;
            }

            pillsContainer.textContent = '';

            var entries = [];
            selectedCheckboxes(sectorFilter).forEach(function (input) {
                entries.push({ prefix: 'Sector', input: input });
            });
            selectedCheckboxes(barangayFilter).forEach(function (input) {
                entries.push({ prefix: 'Barangay', input: input });
            });
            var status = statusValue();
            if (status !== 'all') {
                entries.push({
                    prefix: 'Status',
                    input: filterForm.querySelector('input[name="status"]:checked')
                });
            }

            entries.forEach(function (entry) {
                if (!entry.input) {
                    return;
                }

                var pill = document.createElement('span');
                pill.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
                pill.appendChild(document.createTextNode(
                    entry.prefix + ': ' + (entry.input.dataset.recordsPillLabel || entry.input.value)
                ));

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-close';
                remove.setAttribute('aria-label', 'Remove filter ' + entry.prefix);
                remove.addEventListener('click', function () {
                    if (entry.input.type === 'radio') {
                        var allRadio = filterForm.querySelector('input[name="status"][value="all"]');
                        if (allRadio) {
                            allRadio.checked = true;
                        }
                    } else {
                        entry.input.checked = false;
                    }
                    onFilterChanged();
                });
                pill.appendChild(remove);

                pillsContainer.appendChild(pill);
            });
        }

        // Filters live-apply: each panel change redraws pills at once and
        // reloads the table after a short pause so rapid multi-checking sends
        // one request instead of one per click.
        function onFilterChanged() {
            renderFilterPills();

            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function () {
                debounceTimer = null;
                dataTable.ajax.reload(null, true);
            }, FILTER_DEBOUNCE_MS);
        }

        var dataTable = new window.DataTable(tableElement, {
            processing: true,
            serverSide: true,
            searching: true,
            scrollX: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            // Default order: QR control number ascending, 1 to n. The /data
            // endpoint maps column 0 to the qr_control join (dataTableOrder()).
            order: [[0, 'asc']],
            ajax: {
                url: tableElement.dataset.ajaxUrl,
                data: function (request) {
                    if (request.search) {
                        request.search.value = '';
                        request.search.regex = false;
                    }

                    request.q = keywordInput ? keywordInput.value.trim() : '';
                    request.status = statusValue();
                    request.sectorID = selectedValues(sectorFilter);
                    request.barangay = selectedValues(barangayFilter);
                    request.scope = scope;
                }
            },
            columns: [
                { data: 'qr', name: 'qr', orderSequence: ['asc', 'desc'], className: 'text-center text-nowrap' },
                { data: 'name', name: 'name', orderSequence: ['asc', 'desc'] },
                { data: 'sector', name: 'sector', orderable: false },
                { data: 'address', name: 'address', orderSequence: ['asc', 'desc'] },
                { data: 'birthday', name: 'birthday', orderSequence: ['asc', 'desc'] },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
            ],
            layout: {
                topStart: 'search',
                topEnd: 'pageLength',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            language: {
                emptyTable: 'No records found.',
                zeroRecords: 'No matching records found.',
                processing: 'Loading records...',
                lengthMenu: 'Show _MENU_ entries',
                search: ''
            },
            drawCallback: function () {
                if (typeof window.initFamilyListActionDropdowns === 'function') {
                    window.initFamilyListActionDropdowns(tableElement);
                }

                applyCurrentPageQuickSearch();
            }
        });

        bindCurrentPageQuickSearch();
        renderFilterPills();

        // Exposed so the Add/Update modal can refresh the table after a save.
        window.reloadFamilyDataTable = function () {
            try {
                dataTable.ajax.reload(null, false);
            } catch (error) {
                /* table not initialised yet */
            }
        };

        // Explicit keyword search. Switches to the whole-database scope so the
        // keyword also matches non-head family members.
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            scope = 'all';
            dataTable.ajax.reload(null, true);
        });

        filterForm.addEventListener('change', function (event) {
            var target = event.target;

            if (target.matches('input[type="checkbox"], input[name="status"]')) {
                onFilterChanged();
            }
        });

        // Barangay type-to-narrow: hides non-matching options, nothing more.
        var narrowInput = filterForm.querySelector('[data-records-narrow]');
        if (narrowInput && barangayFilter) {
            narrowInput.addEventListener('input', function () {
                var term = narrowInput.value.trim().toLowerCase();

                barangayFilter.querySelectorAll('[data-records-option]').forEach(function (option) {
                    var matches = !term || option.textContent.toLowerCase().indexOf(term) !== -1;
                    option.classList.toggle('d-none', !matches);
                });
            });
        }

        // The single full reset: keyword, filters, scope, quick search, sort.
        var clearButton = filterForm.querySelector('[data-records-clear]');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                if (keywordInput) {
                    keywordInput.value = '';
                }
                filterForm.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                    input.checked = false;
                });
                var allRadio = filterForm.querySelector('input[name="status"][value="all"]');
                if (allRadio) {
                    allRadio.checked = true;
                }
                if (narrowInput) {
                    narrowInput.value = '';
                    narrowInput.dispatchEvent(new Event('input'));
                }
                quickSearchTerm = '';
                if (quickSearchInput) {
                    quickSearchInput.value = '';
                }
                scope = 'heads';
                renderFilterPills();
                dataTable.order([[0, 'asc']]);
                dataTable.ajax.reload(null, true);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFamilyDataTable);
    } else {
        initializeFamilyDataTable();
    }
})(window, document);
```

- [ ] **Step 2: Append panel rules to `public/css/managerecord.css`**

Append only (existing `.records-*` and `.family-*` rules are reused by Lookups and audit-trails pages; do not touch them):

```css
/* Manage Records filter panel (components/records_toolbar.php). Wide
   multi-column dropdown; columns scroll independently so the panel keeps a
   fixed height. Collapses to stacked columns on small screens via the grid. */
.records-filter-panel {
    min-width: min(560px, 92vw);
}

.records-filter-panel .records-filter-list {
    max-height: 14rem;
}
```

- [ ] **Step 3: Verify in the browser**

Run: `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090` (use the intl-enabled php, not XAMPP's) and log in as developer/developer123.

Check on `/admin/manage-records`:
- table loads ordered QR 1, 2, 3, ...; clicking QR NO. toggles asc/desc
- Filters opens the wide panel; checking a sector updates pills at once and the table about 350ms later; panel stays open
- barangay narrow input hides non-matching entries
- pill x removes that filter and reloads; status pill x returns status to All
- Search with a non-head member's name returns that member's family (scope all)
- Clear empties keyword, unchecks everything, resets sort to QR asc
- in-card input filters visible rows only; placeholder reads "Filter loaded results..."
- narrow viewport: toolbar stacks, panel fits (min() clamp)
- viewer login: no Add/Import buttons

Expected: all behaviors as listed. Fix before committing.

- [ ] **Step 4: Full test suite**

Run: `vendor/bin/phpunit`
Expected: PASS (DB/session skips without sqlite3 are normal).

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/dashboard/family-datatable.js public/css/managerecord.css
git commit -m "feat(records): live-apply filter panel, pills, QR default sort in JS"
```

---

### Task 5: Design system conventions doc + retrieval index

**Files:**
- Create: `docs/knowledge/binan-conventions/ui-design-system.md`
- Modify: `.claude/skills/binan-conventions/SKILL.md` (append one grep-index row)

**Interfaces:**
- Consumes: final markup/behavior from Tasks 1-4 (document what shipped, not what was planned).

- [ ] **Step 1: Write `docs/knowledge/binan-conventions/ui-design-system.md`**

```markdown
# UI Design System

**Scope:** button roles, records toolbar anatomy, filter panel + pills, dual
search. Reference implementation: `admin/manage-records`
(`app/Views/Family/list.php` + `components/records_toolbar.php`).
Spec receipt: `docs/superpowers/specs/2026-07-12-manage-records-ui-design.md`.

## Rule 1: Button colors come from btn()

`btn(string $role)` (`app/Helpers/ui_helper.php`, autoloaded) is the single
source of truth. Never hardcode a `btn-*` color class on a toolbar action.

| Role     | Classes                    | Meaning                       |
|----------|----------------------------|-------------------------------|
| search   | btn btn-success            | run a server-side search      |
| clear    | btn btn-danger             | full reset of a toolbar       |
| add      | btn btn-primary            | create a record (modal)       |
| import   | btn btn-warning            | bulk import                   |
| filter   | btn btn-outline-secondary  | open a filter panel           |

New role: add the row here first, then to the helper map, then a
`UiHelperTest` assertion.

## Rule 2: Records toolbar anatomy

`components/records_toolbar.php`. One Bootstrap grid row:
keyword input (grows) | Filters dropdown | two btn-groups separated by gap
(search actions: Search + Clear; record actions: Add + Import, gated by
`$canEdit`). Never one crammed btn-group, never w-100/h-100 stretching.

## Rule 3: Filter panel

Wide `.dropdown-menu` (`.records-filter-panel`) with side-by-side columns,
`data-bs-auto-close="outside"`. Checkboxes and radios live-apply (debounced
in JS); NO Apply or Reset buttons inside the panel. Long option lists get a
type-to-narrow input (`[data-records-narrow]`). Stock Bootstrap only; no
drill-in submenus.

## Rule 4: Pills and the one-role-per-control rule

Applied filters render as pills (`components/filter_pills.php` container, JS
renders the pills). Exactly three clear controls, no overlap:
checkbox/radio applies or unapplies; pill x removes one filter; toolbar
Clear resets everything (keyword + filters + sort). No "Clear all" link, no
panel Reset.

## Rule 5: Dual search wording

Toolbar input searches the whole database server-side, placeholder
"Search entire database (incl. members)...". The in-table DataTables input
filters loaded rows only, placeholder "Filter loaded results...". Keep both
placeholders verbatim when retrofitting other tabs.

## Retrofit status

- manage-records: done (this branch)
- lookups (sectors/services/categories), accounts, audit-trails,
  distribution tabs: pending; reuse records_toolbar/filter_pills with
  page-specific filter groups
```

- [ ] **Step 2: Append to the grep index in `.claude/skills/binan-conventions/SKILL.md`**

Add this row to the keyword table (after the views-bootstrap row at line 37):

```markdown
| button color, btn(), toolbar, filter panel, pills, dual search, design system | `binan-conventions/ui-design-system.md` |
```

- [ ] **Step 3: Commit**

```bash
git add docs/knowledge/binan-conventions/ui-design-system.md .claude/skills/binan-conventions/SKILL.md
git commit -m "docs(knowledge): UI design system conventions (buttons, toolbar, filters)"
```

---

### Task 6: Final verification and review

**Files:** none new.

- [ ] **Step 1: Full suite + routes**

Run: `vendor/bin/phpunit && php spark routes > /dev/null && echo ROUTES-OK`
Expected: tests PASS, `ROUTES-OK` printed.

- [ ] **Step 2: Re-run the browser smoke checklist from Task 4 Step 3**

All items pass, including the viewer-role check and audit-trails page unaffected (its own copy of the old toolbar markup is untouched by design).

- [ ] **Step 3: CodeRabbit review**

Run in background and wait for completion (large diffs take minutes):
`coderabbit review --base main --agent`

Triage per CLAUDE.md: verify each finding, fix in-scope genuine bugs, re-run `vendor/bin/phpunit`, park pre-existing/out-of-scope findings in a GitHub issue citing the PR and branch.

- [ ] **Step 4: PR**

```bash
git push -u origin feat/manage-records-ui
gh pr create --repo janobles/binan_accesscard --base main --title "Manage Records toolbar/filter redesign + design system foundation" --body "..."
```

PR body summarizes the spec decisions (link the spec file) and the smoke checklist results. End body with the standard generated-with footer.
