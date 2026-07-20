# Admin Workspace Reorg Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Shrink the admin sidebar from 11 links to 7 by merging Reports into Dashboard, the four lookup pages into a tabbed Reference Data page, and Batches + Distributions into a tabbed Distribution page; delete dead route aliases; minimal Viewer ripple.

**Architecture:** CodeIgniter 4 MVC. Controllers decide which page; `Libraries/DashboardPageBuilder.php` assembles all view data; `Views/Admin/layout.php` switches its body on `$activePage`. Tabs are **server-side**: Bootstrap `nav-tabs` markup whose links are plain anchors carrying `?tab=`, full page reload per tab, only the active tab's pane is rendered (so per-page query gating survives unchanged).

**Tech Stack:** PHP 8.2+, CodeIgniter 4, Bootstrap 5 (SB Admin 1 theme), Chart.js, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-20-admin-reorg-design.md`

## Global Constraints

- No migrations; no schema changes. SQL dump (`accesscardV18.sql`) is schema truth.
- Every family mutation writes an audit trail — this plan touches none of that code; keep it that way.
- Controllers decide, libraries build: page data assembly stays in `DashboardPageBuilder`.
- Valid Bootstrap 5 markup only; match the Manage Records design system (`components/toolbar`, `components/card`, `btn()` helper, pills).
- Typed PHP signatures; no `declare(strict_types=1)`.
- Plain-language human comments; no em dashes in comments.
- Removed routes get **no** redirects (approved decision).
- Existing POST mutation endpoints keep their URLs; only their success/error redirect targets change.
- Tab keys: `sectors|services|categories|aidtypes` (Reference Data), `batches|log` (Distribution), `sectors|services` (Viewer Reference Data).

---

### Task 1: Route contract test (fails first, passes at the end)

This test is the acceptance contract for the whole reorg. It will FAIL until
Tasks 2-6 land; that is expected. Run it after every task to watch failures
shrink.

**Files:**
- Create: `tests/unit/AdminReorgRoutesTest.php`

**Interfaces:**
- Produces: route→handler expectations later tasks must satisfy:
  - GET `admin/reference-data` → `Admin\DashboardController::referenceData`
  - GET `admin/distribution` → `Admin\DistributionController::distribution`
  - GET `viewer/reference-data` → `Viewer\DashboardController::referenceData`

- [ ] **Step 1: Write the test** (pattern copied from `tests/unit/FamilyRoutesTest.php`)

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class AdminReorgRoutesTest extends CIUnitTestCase
{
    private array $getRoutes;
    private array $postRoutes;

    protected function setUp(): void
    {
        parent::setUp();
        $routes = Services::routes(true);
        require APPPATH . 'Config/Routes.php';
        $this->getRoutes  = $routes->getRoutes('GET');
        $this->postRoutes = $routes->getRoutes('POST');
    }

    /** New merged pages resolve to their controllers. */
    public function testMergedPagesResolve(): void
    {
        $expected = [
            ['admin/reference-data', $this->getRoutes, 'DashboardController::referenceData'],
            ['admin/distribution', $this->getRoutes, 'DistributionController::distribution'],
            ['admin/dashboard', $this->getRoutes, 'DashboardController::dashboard'],
            ['viewer/reference-data', $this->getRoutes, 'DashboardController::referenceData'],
        ];

        foreach ($expected as [$path, $routes, $handler]) {
            $this->assertArrayHasKey($path, $routes, $path);
            $this->assertStringContainsString($handler, (string) $routes[$path], $path);
        }
    }

    /** Old page GETs and dead aliases are gone. */
    public function testRemovedRoutesAreGone(): void
    {
        $removed = [
            'admin/sectors', 'admin/services', 'admin/categories', 'admin/aidtypes',
            'admin/batches', 'admin/distributions', 'admin/reports',
            'admin/manage-families', 'admin/manage-members', 'admin/family-entry',
            'admin/manage-family',
            'employee/family-entry', 'employee/manage-families',
            'viewer/sectors', 'viewer/services', 'viewer/manage-families',
        ];

        foreach ($removed as $path) {
            $this->assertArrayNotHasKey($path, $this->getRoutes, $path);
        }
    }

    /** Mutation endpoints and report data endpoints survive untouched. */
    public function testMutationAndDataEndpointsSurvive(): void
    {
        $expectedPost = [
            'admin/sectors/create', 'admin/services/create', 'admin/categories/create',
            'admin/aidtypes/create', 'admin/batches/open', 'admin/distributions/void/([0-9]+)',
        ];

        foreach ($expectedPost as $path) {
            $this->assertArrayHasKey($path, $this->postRoutes, $path);
        }

        $this->assertArrayHasKey('admin/reports/stats', $this->getRoutes);
        $this->assertArrayHasKey('admin/reports/pdf', $this->getRoutes);
        $this->assertArrayHasKey('admin/manage-family/data', $this->getRoutes);
    }
}
```

- [ ] **Step 2: Run it, confirm the right failures**

Run: `vendor/bin/phpunit tests/unit/AdminReorgRoutesTest.php`
Expected: `testMergedPagesResolve` FAILS (`admin/reference-data` missing), `testRemovedRoutesAreGone` FAILS (`admin/sectors` still present), `testMutationAndDataEndpointsSurvive` PASSES.

- [ ] **Step 3: Commit**

```bash
git add tests/unit/AdminReorgRoutesTest.php
git commit -m "test: route contract for admin workspace reorg"
```

---

### Task 2: Reference Data page (merges Sectors, Services, Categories, Aid Types)

**Files:**
- Modify: `app/Config/Routes.php` (admin group)
- Modify: `app/Controllers/Admin/DashboardController.php`
- Modify: `app/Controllers/Admin/AidTypesController.php`
- Modify: `app/Controllers/Lookups/SectorController.php`, `ServiceController.php`, `CategoryController.php` (redirect targets only)
- Modify: `app/Libraries/DashboardPageBuilder.php`
- Modify: `app/Models/ViewLayoutModel.php`
- Modify: `app/Views/Admin/layout.php`
- Modify: `app/Views/components/dashboard_sidebar.php`
- Modify: `app/Views/Lookups/sectors.php`, `services.php`, `categories.php` (tab param plumbing)

**Interfaces:**
- Consumes: Task 1's expected handler `Admin\DashboardController::referenceData`.
- Produces: `DashboardPageBuilder` view-data keys `referenceTab` (string, one of `sectors|services|categories|aidtypes`) and existing `sectorListData`/`serviceListData`/`categoryListData`/`aidTypes` now gated on `activePage === 'reference-data'` + matching tab. Wrapper views accept optional `tabParam` (string) merged into pagination/search URLs. Shared tab-strip view `components/page_tabs` (also used by Tasks 3 and 6): params `tabs` (array of `['key','label','icon']`), `active` (string), `baseUrl` (string).

- [ ] **Step 1: Routes.** In the `admin` group of `app/Config/Routes.php`: delete the four page GETs (`sectors`, `services`, `categories`, `aidtypes` at lines 28-30, 76). Keep every POST group exactly as is. Add:

```php
$routes->get('reference-data', 'Admin\DashboardController::referenceData');
```

- [ ] **Step 2: Controller.** In `app/Controllers/Admin/DashboardController.php`: delete `sectors()`, `services()`, `categories()` and their orphaned partial renderers `renderSectorsPartial()`, `renderServicesPartial()`, `renderCategoriesPartial()` (nothing fetches them: the lookup JS only POSTs). Add:

```php
/**
 * GET `admin/reference-data`. One page for the four lookup tables
 * (Sectors, Services, Categories, Aid Types), switched by ?tab=.
 * Mutations still post to the Lookups\* and AidTypes controllers.
 */
public function referenceData(): string|RedirectResponse
{
    return (new DashboardPageBuilder($this->request))->renderAdminPage('reference-data');
}
```

In `app/Controllers/Admin/AidTypesController.php`: delete `index()` (page now lives in the shell); replace every `redirect()->to('admin/aidtypes')` with `redirect()->to('admin/reference-data?tab=aidtypes')` (7 occurrences, lines 39-86).

- [ ] **Step 3: Redirect sweep in lookup controllers.**

Run: `grep -rn "admin/sectors\|admin/services\|admin/categories" app/Controllers/Lookups`
For every `redirect()->to(site_url('admin/<x>'))` hit, change the target to `site_url('admin/reference-data?tab=<x>')`. Do NOT touch route definitions or view files in this step.

- [ ] **Step 4: Builder.** In `app/Libraries/DashboardPageBuilder.php` `buildAdminViewData()`:

Add tab resolution near the top:

```php
$referenceTab = (string) $this->request->getGet('tab');
$referenceTab = in_array($referenceTab, ['sectors', 'services', 'categories', 'aidtypes'], true)
    ? $referenceTab : 'sectors';
$isReference = $activePage === 'reference-data';
```

Change the three list-data gates (lines 103-111) and the aidTypes gate (line 200) to:

```php
$sectorListData = $isReference && $referenceTab === 'sectors'
    ? $this->buildLookupListData($sectorModel, 'admin/reference-data', 'sectorID')
    : [];
$serviceListData = $isReference && $referenceTab === 'services'
    ? $this->buildLookupListData($serviceModel, 'admin/reference-data', 'serviceID')
    : [];
$categoryListData = $isReference && $referenceTab === 'categories'
    ? $this->buildLookupListData(new CategoryModel(), 'admin/reference-data', 'categoryID')
    : [];
```

```php
'aidTypes' => $isReference && $referenceTab === 'aidtypes' ? model(AidTypeModel::class)->all() : [],
```

In the returned array add `'referenceTab' => $referenceTab,` and replace the four navActive keys `sectors`/`services`/`categories`/`aidtypes` with one:

```php
'reference-data' => $layoutModel->navActive($activePage, 'reference-data'),
```

- [ ] **Step 5: Titles.** In `app/Models/ViewLayoutModel.php` `pageTitle()`: remove the `sectors`, `services`, `categories`, `aidtypes` arms; add `'reference-data' => 'Reference Data',`.

- [ ] **Step 6: Shared tab strip.** Create `app/Views/components/page_tabs.php` (server-side Bootstrap nav-tabs; anchors reload the page with ?tab=):

```php
<?php
/**
 * Server-side Bootstrap tab strip. Each tab is a plain link that reloads the
 * page with ?tab=<key>; only the active pane is rendered by the caller.
 *
 * Params: $tabs array of ['key' => string, 'label' => string, 'icon' => string],
 *         $active string, $baseUrl string (page URL without query).
 */
$tabs = $tabs ?? [];
$active = $active ?? '';
$baseUrl = $baseUrl ?? '';
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $tab): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab['key'] === $active ? 'active' : '' ?>"
           <?= $tab['key'] === $active ? 'aria-current="page"' : '' ?>
           href="<?= site_url($baseUrl) ?>?tab=<?= esc($tab['key'], 'attr') ?>">
            <i class="bi bi-<?= esc($tab['icon'], 'attr') ?>" aria-hidden="true"></i>
            <?= esc($tab['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
```

- [ ] **Step 7: Layout pane.** In `app/Views/Admin/layout.php`: delete the four blocks `$activePage === 'sectors'|'services'|'categories'|'aidtypes'` and add one block that renders the tab strip plus ONLY the active tab's existing pane content (move the old blocks' inner `view()` calls here verbatim, including the aidtypes card + `Admin/aidtype-create-modal`):

```php
<?php if ($activePage === 'reference-data'): ?>
    <?= view('components/page_tabs', [
        'tabs' => [
            ['key' => 'sectors', 'label' => 'Sectors', 'icon' => 'diagram-3-fill'],
            ['key' => 'services', 'label' => 'Services & Programs', 'icon' => 'grid-fill'],
            ['key' => 'categories', 'label' => 'Categories', 'icon' => 'tags-fill'],
            ['key' => 'aidtypes', 'label' => 'Aid Types', 'icon' => 'box-seam'],
        ],
        'active' => $referenceTab ?? 'sectors',
        'baseUrl' => 'admin/reference-data',
    ]) ?>
    <?php if (($referenceTab ?? 'sectors') === 'sectors'): ?>
        <?php /* former sectors block content, unchanged, goes here */ ?>
    <?php elseif ($referenceTab === 'services'): ?>
        <?php /* former services block content */ ?>
    <?php elseif ($referenceTab === 'categories'): ?>
        <?php /* former categories block content */ ?>
    <?php elseif ($referenceTab === 'aidtypes'): ?>
        <?php /* former aidtypes card + aidtype-create-modal */ ?>
    <?php endif; ?>
<?php endif; ?>
```

- [ ] **Step 8: Tab param in wrapper views.** In each of `app/Views/Lookups/sectors.php`, `services.php`, `categories.php`: the pagination closure and toolbar form must carry the tab. Add near the top (after `$listRoute`):

```php
$tabParam = (string) ($tabParam ?? '');
```

Add `'tab' => $tabParam,` as the FIRST entry of the `array_filter([...])` params in the page-URL closure and the clear-URL closure, and append a hidden input to the toolbar's `hiddenHtml`:

```php
'hiddenHtml' => ($tabParam !== '' ? '<input type="hidden" name="tab" value="' . esc($tabParam, 'attr') . '">' : '')
    . ($perPage !== 25 ? '<input type="hidden" name="per_page" value="' . esc((string) $perPage, 'attr') . '">' : ''),
```

Then pass `'tabParam' => 'sectors'` (respectively `services`, `categories`) from the layout block where each wrapper view is rendered. The builder's `listRoute` is already `admin/reference-data` (Step 4), so search, clear, status pills, per-page, and pagination all round-trip to the right tab.

- [ ] **Step 9: Sidebar.** In `app/Views/components/dashboard_sidebar.php`: replace the four Reference Data links (lines 31-34) with one:

```php
<a class="nav-link <?= esc($navActive['reference-data'] ?? '') ?>" href="<?= site_url('admin/reference-data') ?>"><div class="sb-nav-link-icon"><i class="bi bi-collection" aria-hidden="true"></i></div>Reference Data</a>
```

(Heading "Reference Data" becomes part of the Records section in Task 5's final sidebar pass; leave headings alone for now.)

- [ ] **Step 10: Verify**

Run: `vendor/bin/phpunit tests/unit/AdminReorgRoutesTest.php`
Expected: `testMergedPagesResolve` still fails only on `admin/distribution` and `viewer/reference-data`; `testRemovedRoutesAreGone` still fails only on batches/distributions/reports/aliases/viewer entries; sectors/services/categories/aidtypes assertions now pass.
Run: `vendor/bin/phpunit` (full) — no new failures.
Run: `php spark routes | grep -i "reference-data\|sectors\|services\|categories\|aidtypes"` — one GET page route, POST groups intact.
Manual: log in as developer, load `admin/reference-data`, click all 4 tabs, search + paginate inside Sectors tab, confirm URL keeps `tab=sectors`, create a sector via modal, confirm redirect returns to the Sectors tab with flash message.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat(admin): merge lookup pages into tabbed Reference Data page"
```

---

### Task 3: Distribution page (merges Batches + Distributions)

**Files:**
- Modify: `app/Config/Routes.php`
- Modify: `app/Controllers/Admin/DistributionController.php`
- Modify: `app/Libraries/DashboardPageBuilder.php`
- Modify: `app/Models/ViewLayoutModel.php`
- Modify: `app/Views/Admin/layout.php`
- Modify: `app/Views/components/dashboard_sidebar.php`

**Interfaces:**
- Consumes: `components/page_tabs` from Task 2 (`tabs`/`active`/`baseUrl` params).
- Produces: builder view-data key `distributionTab` (string, `batches|log`); handler `Admin\DistributionController::distribution` per Task 1.

- [ ] **Step 1: Routes.** Replace the `batches` and `distributions` groups' GET pages: delete `$routes->get('', ...)` inside both groups (keep `open`, `close`, `void` POSTs), and add above them:

```php
$routes->get('distribution', 'Admin\DistributionController::distribution');
```

- [ ] **Step 2: Controller.** In `DistributionController`: replace the two page methods `batches()` and `distributions()` with one:

```php
/**
 * GET `admin/distribution`. Batches and the distribution log share one page,
 * switched by ?tab= (batches|log).
 */
public function distribution(): string|RedirectResponse
{
    return (new DashboardPageBuilder($this->request))->renderAdminPage('distribution');
}
```

Update redirect targets: `admin/batches` → `admin/distribution?tab=batches` (5 occurrences, lines 68-94), `admin/distributions` → `admin/distribution?tab=log` (3 occurrences, lines 48-58).

- [ ] **Step 3: Builder.** In `buildAdminViewData()`:

```php
$distributionTab = (string) $this->request->getGet('tab');
$distributionTab = in_array($distributionTab, ['batches', 'log'], true) ? $distributionTab : 'batches';
$isBatches       = $activePage === 'distribution' && $distributionTab === 'batches';
$isDistributions = $activePage === 'distribution' && $distributionTab === 'log';
```

(`$isBatches`/`$isDistributions` already gate `batches`, `activeBatch`, `activeAidTypes`, `distributions` — no other change.) Add `'distributionTab' => $distributionTab,` to the return array; replace navActive keys `batches`/`distributions` with `'distribution' => $layoutModel->navActive($activePage, 'distribution'),`.

- [ ] **Step 4: Titles.** `ViewLayoutModel::pageTitle()`: remove `batches` and `distributions` arms; add `'distribution' => 'Aid Distribution',`.

- [ ] **Step 5: Layout pane.** In `Admin/layout.php`: delete the `batches` and `distributions` blocks; add one `distribution` block with the tab strip and the two former blocks' content as panes:

```php
<?php if ($activePage === 'distribution'): ?>
    <?= view('components/page_tabs', [
        'tabs' => [
            ['key' => 'batches', 'label' => 'Batches', 'icon' => 'collection'],
            ['key' => 'log', 'label' => 'Distribution Log', 'icon' => 'clipboard-check-fill'],
        ],
        'active' => $distributionTab ?? 'batches',
        'baseUrl' => 'admin/distribution',
    ]) ?>
    <?php if (($distributionTab ?? 'batches') === 'batches'): ?>
        <?php /* former batches block content (card + batch-create-modal), unchanged */ ?>
    <?php else: ?>
        <?php /* former distributions block content (toolbar + card + footer), unchanged */ ?>
    <?php endif; ?>
<?php endif; ?>
```

- [ ] **Step 6: Sidebar.** Replace the Batches and Distributions links (lines 37-38) with:

```php
<a class="nav-link <?= esc($navActive['distribution'] ?? '') ?>" href="<?= site_url('admin/distribution') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></div>Distribution</a>
```

- [ ] **Step 7: Verify**

Run: `vendor/bin/phpunit tests/unit/AdminReorgRoutesTest.php`
Expected: `admin/distribution` assertion passes; `admin/batches`/`admin/distributions` removal assertions pass.
Manual: open `admin/distribution`, switch tabs, open a batch (confirm redirect returns to Batches tab), void nothing (just confirm the Log tab renders).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(admin): merge batches and distributions into tabbed Distribution page"
```

---

### Task 4: Dashboard absorbs Reports (with content cuts)

**Files:**
- Modify: `app/Config/Routes.php`
- Modify: `app/Controllers/Admin/ReportsController.php`
- Modify: `app/Libraries/DashboardPageBuilder.php`
- Modify: `app/Views/Admin/layout.php` (dashboard block)
- Modify: `app/Views/Admin/reports-body.php`
- Modify: `public/assets/js/dashboard/scanner-reports.js`
- Modify: `app/Views/components/dashboard_sidebar.php`
- Modify: `app/Models/ViewLayoutModel.php` (if a `reports` arm exists — check; otherwise no change)

**Interfaces:**
- Consumes: `buildReportsData()` (existing, `DashboardPageBuilder.php:406`), keys `reportsBatches`, `reportsBatchId`, `reportsBatchName`, `reportsSummary` (`total|received|notReceived|coverage`), `reportsByBarangay`, `reportsByAidType`, `reportsPerScanner`.
- Produces: builder view-data key `reportsBatchOpen` (bool — selected batch still open, controls live polling).

- [ ] **Step 1: Routes + controller.** Delete `$routes->get('reports', 'Admin\ReportsController::index');` (keep `reports/stats` and `reports/pdf`). In `ReportsController` delete the `index()` method (page renders via dashboard now); `stats()` and `pdf()` untouched.

- [ ] **Step 2: Builder.** In `buildAdminViewData()` change the reports gate (line 121) from `'reports'` to `'dashboard'`:

```php
$reportsData = $activePage === 'dashboard'
    ? $this->buildReportsData($batchModel)
    : [ /* same empty defaults as now */ ];
```

Add to the empty defaults and to `buildReportsData()`'s return a new key:

```php
'reportsBatchOpen' => false,
```

In `buildReportsData()`, set it from the selected batch row (`closed_at === null`). Add `'reportsBatchOpen' => $reportsData['reportsBatchOpen'],` to the main return array. Remove the `'reports'` navActive key.

- [ ] **Step 3: Trim the dashboard block in `Admin/layout.php`.** Content cuts per spec:

  - Delete the "Active Sectors" and "Services and Programs" `stat_card` calls (lines 137-148).
  - Delete the Recent Activity table (`$recentAuditRows` build + its `data_table` call, lines 159-167 and 179-188).
  - Keep the Recent Records table; change its `headerActions` to a View All link to Manage Records:

```php
'headerActions' => '<a class="btn btn-sm panel-action" href="' . site_url('admin/manage-records') . '"><i class="bi bi-arrow-right" aria-hidden="true"></i><span>View All</span></a>',
```

  - After the stat-card section, render the distribution analytics body:

```php
<?= view('Admin/reports-body', []) ?>
```

  (All `reports*` variables are already in scope from the builder.)

- [ ] **Step 4: Rework `Admin/reports-body.php`.** Keep: batch selector toolbar (form `action` changes from `site_url('admin/reports')` to `site_url('admin/dashboard')`), Refresh + Download PDF buttons, per-kiosk table, coverage-by-barangay bar chart card. Change/cut:

  - KPI tiles: replace the four tiles with two that join the dashboard's Total Records + Registered Members row (move these two INTO the dashboard's `overview-stats` section is not needed — keep them in `reports-stats` directly below, same `stat_card` component):

```php
<?= view('components/stat_card', [
    'label' => 'Received aid',
    'value' => $reportsSummary['received'] . ' of ' . $reportsSummary['total'],
    'icon' => 'check-circle-fill',
    'variant' => 'stat-card--members',
]) ?>
<?= view('components/stat_card', [
    'label' => 'Coverage',
    'value' => ((string) $reportsSummary['coverage']) . '%',
    'icon' => 'pie-chart-fill',
    'variant' => 'stat-card--services',
]) ?>
```

  - Delete the received-vs-waiting pie card (`chartReceived`) and the aid-type chart card (`chartAidType`).
  - Replace the aid-type chart with a small table (same `data_table` component):

```php
<?php
$aidTypeRows = [];
foreach ($reportsByAidType as $t) {
    $aidTypeRows[] = [esc($t['label'] ?? $t['aid_type'] ?? ''), esc((string) ($t['count'] ?? $t['handouts'] ?? 0))];
}
?>
<?= view('components/data_table', [
    'icon' => 'box-seam',
    'title' => 'Handouts by aid type',
    'columns' => ['Aid type', 'Handouts'],
    'rows' => $aidTypeRows,
    'emptyMessage' => 'No handouts in this batch yet.',
    'tableClass' => 'table manage-record-table align-middle w-100 mb-0',
    'cardClass' => 'reports-fallback',
]) ?>
```

  (Check the actual `reportsByAidType` row keys in `buildReportsData()` before writing the loop; use the real key names.)

  - Barangay fallback table: keep for print/no-JS, hide on screen: change its wrapping card class by adding a wrapper `<div class="d-none d-print-block">` around the `data_table` call.
  - Inline poll script: wrap the `setInterval` in a batch-open guard so closed batches stay static, and drop tile IDs that no longer exist:

```php
var batchOpen = <?= ! empty($reportsBatchOpen) ? 'true' : 'false' ?>;
```

```js
if (batchOpen) {
  setInterval(function () {
    if (document.visibilityState === 'visible') { poll(); }
  }, 5000);
}
```

  and in `apply()` keep only the tiles that still exist (`stat-card--members` = received-of-total string, `stat-card--services` = coverage):

```js
if (d.received) {
  setTile('stat-card--members', d.received.received + ' of ' + d.received.total);
  setTile('stat-card--services', d.received.coverage + '%');
}
```

- [ ] **Step 5: Chart JS guards.** In `public/assets/js/dashboard/scanner-reports.js`: make each chart init conditional on its canvas existing (`document.getElementById('chartBarangay')` etc.) and make `ReportsCharts.update()` skip absent charts. Read the file first; add null guards only where missing — the pie/aid-type canvases are gone from the admin dashboard but this file may also serve the scanner kiosk stats page, so guards (not deletions) are the correct change.

- [ ] **Step 6: Sidebar.** Delete the Reports link (line 39 of `dashboard_sidebar.php`).

- [ ] **Step 7: Verify**

Run: `vendor/bin/phpunit` — route test's `admin/reports` removal now passes; no other failures.
Manual: load `admin/dashboard`. Confirm: 4 tiles total (Records, Members, Received-of-total, Coverage), batch selector switches batches via reload, barangay chart renders, aid-type table renders, per-kiosk table renders, Recent Records shows with View All, no Recent Activity, no pie. Select a CLOSED batch: no network polling in devtools. Download PDF works. Scanner kiosk stats page (`scanner/performance`) still renders its charts.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(admin): merge reports into dashboard, cut low-value widgets"
```

---

### Task 5: Dead alias cleanup + final sidebar shape

**Files:**
- Modify: `app/Config/Routes.php`
- Modify: `app/Controllers/Admin/DashboardController.php`
- Modify: `app/Controllers/Employee/DashboardController.php`
- Modify: `app/Views/components/dashboard_sidebar.php`
- Check: `app/Views/Employee/layout.php` (nav links to removed aliases)

**Interfaces:**
- Consumes: nothing new.
- Produces: final 7-link sidebar under 4 headings.

- [ ] **Step 1: Routes.** Delete these lines from `Routes.php`: `admin/family-entry`, `admin/manage-members`, `admin/manage-families`, the bare `$routes->get('', ...)` inside admin `manage-family` group, `employee/family-entry`, `employee/manage-families`, `viewer/manage-families`, and the bare GET inside employee `manage-family` group. Keep every other `manage-family/*` route.

- [ ] **Step 2: Orphan methods.** Delete `Admin\DashboardController::familyEntry()` and `::manageMembers()`. Delete `Employee\DashboardController::familyEntry()` (check name via `grep -n "familyEntry\|manageFamilies" app/Controllers/Employee/DashboardController.php` and remove only what the deleted routes pointed at).

- [ ] **Step 3: Reference sweep.**

Run: `grep -rn "family-entry\|manage-families\|manage-members" app/ public/assets/js tests/ --include='*.php' --include='*.js'`
Expected: zero hits in views/JS. Fix any stragglers (nav links in `Employee/layout.php` are the likely spot).

- [ ] **Step 4: Final sidebar.** Rewrite the nav body of `dashboard_sidebar.php` to the approved shape (full replacement of the `<div class="nav">` contents):

```php
<div class="sb-sidenav-menu-heading">Core</div>
<a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><div class="sb-nav-link-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>Dashboard</a>
<div class="sb-sidenav-menu-heading">Records</div>
<a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>Manage Records</a>
<a class="nav-link <?= esc($navActive['reference-data'] ?? '') ?>" href="<?= site_url('admin/reference-data') ?>"><div class="sb-nav-link-icon"><i class="bi bi-collection" aria-hidden="true"></i></div>Reference Data</a>
<div class="sb-sidenav-menu-heading">Aid Distribution</div>
<a class="nav-link <?= esc($navActive['cards'] ?? '') ?>" href="<?= site_url('admin/cards') ?>"><div class="sb-nav-link-icon"><i class="bi bi-qr-code" aria-hidden="true"></i></div>Generate Cards</a>
<a class="nav-link <?= esc($navActive['distribution'] ?? '') ?>" href="<?= site_url('admin/distribution') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></div>Distribution</a>
<div class="sb-sidenav-menu-heading">Administration</div>
<?php if ($canManageAccounts): ?>
<a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><div class="sb-nav-link-icon"><i class="bi bi-person-fill-gear" aria-hidden="true"></i></div>Account Management</a>
<?php endif; ?>
<a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>Audit Trails</a>
```

- [ ] **Step 5: Verify**

Run: `vendor/bin/phpunit tests/unit/AdminReorgRoutesTest.php`
Expected: all removal assertions except `viewer/sectors`/`viewer/services` now pass.
Run: `php spark routes` — resolves cleanly, no orphaned handlers.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore(routes): remove dead admin/employee aliases, final 7-link sidebar"
```

---

### Task 6: Viewer reference-data merge

**Files:**
- Modify: `app/Config/Routes.php` (viewer group)
- Modify: `app/Controllers/Viewer/DashboardController.php`
- Modify: `app/Libraries/DashboardPageBuilder.php` (`renderViewerPage` area, lines ~651-716)
- Modify: `app/Views/Viewer/layout.php`

**Interfaces:**
- Consumes: `components/page_tabs` (Task 2); wrapper views' `tabParam` plumbing (Task 2).
- Produces: handler `Viewer\DashboardController::referenceData` per Task 1.

- [ ] **Step 1: Routes.** In the viewer group: delete `sectors` and `services` GETs, add:

```php
$routes->get('reference-data', 'Viewer\DashboardController::referenceData');
```

- [ ] **Step 2: Controller.** Replace `sectors()` and `services()` with:

```php
/** GET `viewer/reference-data`. Read-only Sectors and Services lists, switched by ?tab=. */
public function referenceData(): string|RedirectResponse
{
    return $this->pageBuilder()->renderViewerPage('reference-data');
}
```

- [ ] **Step 3: Builder.** Read `renderViewerPage()`/its view-data assembly first (lines 651-716). Mirror the admin pattern: resolve `$referenceTab` from `?tab=` (allowed: `sectors|services`, default `sectors`); build `sectorListData` when tab is sectors, `serviceListData` when services, with `listRoute` `'viewer/reference-data'`; replace the viewer navActive keys for sectors/services with `reference-data`; pass `referenceTab` and `tabParam` through. Page title: reuse `'reference-data' => 'Reference Data'`.

- [ ] **Step 4: Viewer layout.** In `app/Views/Viewer/layout.php`: replace the sectors/services body blocks with one `reference-data` block using `components/page_tabs` (tabs: Sectors, Services; `baseUrl` `viewer/reference-data`) and render only the active pane, passing `'canManage' => false` exactly as the current blocks do. Update the viewer nav links (this layout owns its own nav) to a single Reference Data link.

- [ ] **Step 5: Verify**

Run: `vendor/bin/phpunit` — full suite green, ALL AdminReorgRoutesTest assertions pass.
Manual: log in as a viewer account, open `viewer/reference-data`, both tabs render read-only (no Add/Edit buttons).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(viewer): merge sectors and services into read-only Reference Data page"
```

---

### Task 7: Full verification pass

**Files:** none created; fixes only if verification fails.

- [ ] **Step 1: Suites**

Run: `vendor/bin/phpunit`
Expected: green.
Run: `php spark routes`
Expected: every route resolves; none of the removed URIs listed.

- [ ] **Step 2: Reference sweep (final)**

Run: `grep -rn "admin/sectors'\|admin/services'\|admin/categories'\|admin/aidtypes'\|admin/batches'\|admin/distributions'\|admin/reports'" app/ public/assets/js`
Expected: hits only inside POST endpoint paths (`admin/sectors/create` etc.) and JS comments describing those POSTs. Anything else is a missed link — fix it.

- [ ] **Step 3: Playwright visual pass** (dev server at `app.baseURL`, e.g. `http://localhost:8090`; start with `php spark serve` if down; login developer/developer123). Snapshot/screenshot at desktop and 390px widths:

  - `admin/dashboard` (open batch selected AND closed batch selected)
  - `admin/reference-data` all four tabs
  - `admin/distribution` both tabs
  - `admin/accounts`, `admin/audit-trails`, `admin/manage-records`
  - `viewer/reference-data` both tabs

Compare against Manage Records (design source of truth): toolbar placement, card anatomy, pill styling, no horizontal scroll at 390px.

- [ ] **Step 4: Smoke flows** — login, role redirects (admin → dashboard, viewer → viewer dashboard), family create/update writes audit trail, sector create from Reference Data tab, batch open/close from Distribution tab, account disable/enable, PDF download.

- [ ] **Step 5: Commit any fixes**

```bash
git add -A
git commit -m "fix: reorg verification follow-ups"
```

- [ ] **Step 6: CodeRabbit review** (repo rule before merging): `coderabbit review --base main --agent`, triage per `superpowers:receiving-code-review` — verify each finding against the code and CLAUDE.md non-negotiables, fix genuine in-scope bugs, re-run `vendor/bin/phpunit`, park the rest in a GitHub issue citing the PR and branch.
