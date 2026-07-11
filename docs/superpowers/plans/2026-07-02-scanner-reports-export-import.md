# Scanner Reports/Statistics + PDF Export + Excel Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Reports/Statistics tab to the QR Code (Scanner) module with chart.js visualizations and a PDF summary export, then merge the Excel family-import feature from `Mel-import-branch` and fix any breakage.

**Architecture:** A read-only `AidStatsModel` produces three date-scoped datasets (received-vs-not, by-barangay, by-aid-type). `ReportsController` routes the tab + PDF; the view renders `.stat-card` KPI tiles plus chart.js canvases (data passed as JSON), with plain summary tables as the no-JS/print fallback. `ReportsPdfGenerator` (dompdf, mirroring `QrCardPdfGenerator`) renders those same tables to a one-page PDF. The Excel import arrives via a full git merge, resolved and repaired last.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, chart.js (vendored UMD), dompdf (installed), phpoffice/phpspreadsheet (arrives with merge), Bootstrap 5 + SB-Admin adapter, Bootstrap Icons, PHPUnit.

## Implementation summary (status)

**Status: COMPLETE.** All of §A/§B (Reports tab, chart.js visualizations, PDF
export) and §C (Excel family-import merge from `Mel-import-branch`) shipped on
`feat/qr-access-cards`. Full suite green: **72 tests, 4 skipped**.

Delivered as planned:
- `AidStatsModel`, `ReportsController` (+ routes), `Scanner/reports.php` view,
  vendored chart.js + scanner asset context, sidebar Reports link, and the
  dompdf `ReportsPdfGenerator` one-page export.
- Excel family import merged in (`a4ddb26`), bringing `phpoffice/phpspreadsheet`,
  the `job_queue` table/schema, `JobQueueModel`, and reusable family-modal work.

Deviations from the plan:
- **Queue worker:** the merge shipped a full generic background worker
  `app/Commands/QueueWork.php` (`php spark queue:work`) instead of the plan's
  placeholder `DrainJobQueue`/`jobs:drain`. The placeholder was never created.
- **Schema dump:** source of truth advanced to `accesscardV14.sql`
  (schema + reference seed only; no family/QR data baked in).

## Global Constraints

- **No migrations.** Schema source of truth = `accesscardV14.sql`. New tables (e.g. `job_queue`) load as SQL dumps, never as CI4 migrations.
- **Every family mutation writes `audit_trails`** via `Audit\AuditTrailsModel`. Reports/export are read-only (no audit); import MUST audit.
- **Controllers decide, libraries/models build.** All querying lives in models/libraries.
- **PHP 8.2+**, strict existing namespace/style conventions.
- **UI house style only** (spec "UI style contract"): `stat-card`, `sector-management records-scroll-panel`, `records-search-panel`, `records-search-row records-lookup-search`, `table-meta records-table-controls`, `nav nav-tabs manage-tabs`, `badge bg-light text-dark border`, `.btn .records-search-action`, Bootstrap Icons (`bi-*`). **NEVER** `border-left-*`, `card h-100`, `text-xs text-uppercase` (SB-Admin-Pro demo-only, forbidden).
- **Assets load through the manifest** `app/Helpers/asset_helper.php` — no hardcoded `<script>`/`<link>` in layouts.
- **Role guard** literal `['Scanner', 'Admin', 'Developer']` inline per-action (test-pinned convention).
- **No-DB test posture:** model methods try/catch → safe empty shape; tests are contract/route/grep, no live-DB round-trips.
- **Run `vendor/bin/phpunit` before and after each task.** Baseline: 55 tests, 4 skipped. (Final suite after implementation + Mel import merge: 72 tests, 4 skipped, all green.)

---

## File structure

**§A/§B create:**
- `app/Models/Scanner/AidStatsModel.php` — three read-only stat queries.
- `app/Controllers/Scanner/ReportsController.php` — `index` (tab) + `pdf` (export).
- `app/Views/Scanner/reports.php` — Reports tab.
- `app/Views/Scanner/pdf/report.php` — PDF body.
- `app/Views/Scanner/pdf/_styles.php` — PDF inline styles.
- `app/Libraries/Scanner/ReportsPdfGenerator.php` — dompdf render + stream bytes.
- `public/vendor/chart.js/chart.umd.min.js` — vendored chart.js (`git add -f`).
- `public/assets/js/dashboard/scanner-reports.js` — builds the three charts.
- `public/css/scanner-reports.css` — chart-card grid sizing only.
- Tests: `tests/unit/AidStatsModelTest.php`, `ReportsControllerTest.php`, `ReportsViewTest.php`, and manifest assertions in an existing/new test.

**§A/§B modify:**
- `app/Config/Routes.php` — 2 new scanner routes.
- `app/Helpers/asset_helper.php` — `scanner` context (styles + scripts).
- `app/Views/Scanner/layout.php` — merge `scanner` asset context.
- `app/Views/components/dashboard_sidebar.php` — Reports link.

**§C:** git merge of `origin/Mel-import-branch` (files listed in spec §C).

---

## Task 1: AidStatsModel — date-scoped stat queries

**Files:**
- Create: `app/Models/Scanner/AidStatsModel.php`
- Test: `tests/unit/AidStatsModelTest.php`

**Interfaces:**
- Produces:
  - `receivedVsNot(?string $from = null, ?string $to = null): array` → `['total'=>int,'received'=>int,'notReceived'=>int,'coverage'=>int]`
  - `byBarangay(?string $from = null, ?string $to = null): array` → `list<array{barangay:string,total:int,received:int,coverage:int}>`
  - `byAidType(?string $from = null, ?string $to = null): array` → `list<array{aid_type:string,count:int}>`
  - `coverage` = integer percent `round(received/total*100)`, `0` when `total===0`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Scanner\AidStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidStatsModelTest extends CIUnitTestCase
{
    public function testReceivedVsNotReturnsExpectedKeys(): void
    {
        $out = (new AidStatsModel())->receivedVsNot();
        $this->assertSame(['total', 'received', 'notReceived', 'coverage'], array_keys($out));
        foreach ($out as $v) {
            $this->assertIsInt($v);
        }
    }

    public function testByBarangayReturnsArray(): void
    {
        $this->assertIsArray((new AidStatsModel())->byBarangay('2026-01-01', '2026-01-31'));
    }

    public function testByAidTypeReturnsArray(): void
    {
        $this->assertIsArray((new AidStatsModel())->byAidType());
    }

    public function testMethodsAcceptNullRangeWithoutError(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->byBarangay(null, null));
        $this->assertIsArray($m->byAidType(null, null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/AidStatsModelTest.php`
Expected: FAIL — `Class "App\Models\Scanner\AidStatsModel" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Read-only aid-distribution statistics for the Reports tab. Every method is
 * scoped to an optional [from, to] claim_date window and returns a safe empty
 * shape on any DB error, matching the scanner module's no-DB test posture.
 * "Received" is defined at the family (head) level: a family counts as having
 * received aid when any scan under its control_no produced a distribution row.
 */
class AidStatsModel extends Model
{
    protected $table         = 'qr_control';
    protected $primaryKey    = 'control_no';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    /** Applies the optional date window to aid_distribution.claim_date. */
    private function applyRange($builder, ?string $from, ?string $to)
    {
        if ($from !== null && $from !== '') {
            $builder->where('aid_distribution.claim_date >=', $from . ' 00:00:00');
        }
        if ($to !== null && $to !== '') {
            $builder->where('aid_distribution.claim_date <=', $to . ' 23:59:59');
        }

        return $builder;
    }

    private static function pct(int $received, int $total): int
    {
        return $total === 0 ? 0 : (int) round($received / $total * 100);
    }

    /** Family-level received vs not-yet counts + coverage percent. */
    public function receivedVsNot(?string $from = null, ?string $to = null): array
    {
        try {
            $total = (int) $this->db->table('qr_control')
                ->countAllResults();

            $b = $this->db->table('qr_control')
                ->select('qr_control.headID')
                ->join('aid_distribution', 'aid_distribution.control_no = qr_control.control_no')
                ->groupBy('qr_control.headID');
            $this->applyRange($b, $from, $to);
            $received = count($b->get()->getResultArray());

            return [
                'total'       => $total,
                'received'    => $received,
                'notReceived' => max(0, $total - $received),
                'coverage'    => self::pct($received, $total),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'received' => 0, 'notReceived' => 0, 'coverage' => 0];
        }
    }

    /** Per-barangay family totals + received + coverage. */
    public function byBarangay(?string $from = null, ?string $to = null): array
    {
        try {
            // Total families per barangay (head's barangay).
            $totals = $this->db->table('qr_control')
                ->select("COALESCE(NULLIF(TRIM(member.barangay), ''), 'Unspecified') AS barangay,"
                    . ' COUNT(DISTINCT qr_control.headID) AS total')
                ->join('member', 'member.memberID = qr_control.headID', 'left')
                ->groupBy('barangay')
                ->get()->getResultArray();

            // Received families per barangay, within the date window.
            $rb = $this->db->table('qr_control')
                ->select("COALESCE(NULLIF(TRIM(member.barangay), ''), 'Unspecified') AS barangay,"
                    . ' COUNT(DISTINCT qr_control.headID) AS received')
                ->join('member', 'member.memberID = qr_control.headID', 'left')
                ->join('aid_distribution', 'aid_distribution.control_no = qr_control.control_no');
            $this->applyRange($rb, $from, $to);
            $recv = [];
            foreach ($rb->groupBy('barangay')->get()->getResultArray() as $r) {
                $recv[$r['barangay']] = (int) $r['received'];
            }

            $out = [];
            foreach ($totals as $t) {
                $total    = (int) $t['total'];
                $received = $recv[$t['barangay']] ?? 0;
                $out[] = [
                    'barangay' => $t['barangay'],
                    'total'    => $total,
                    'received' => $received,
                    'coverage' => self::pct($received, $total),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Handout counts per aid type, within the date window, busiest first. */
    public function byAidType(?string $from = null, ?string $to = null): array
    {
        try {
            $b = $this->db->table('aid_type')
                ->select('aid_type.name AS aid_type, COUNT(aid_distribution.aidID) AS count')
                ->join('aid_distribution', 'aid_distribution.aid_type_id = aid_type.aid_type_id', 'left');
            $this->applyRange($b, $from, $to);
            $rows = $b->groupBy('aid_type.aid_type_id')
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
}
```

Note: `applyRange` on the `byAidType` left-join narrows the join via `where`;
because the outer table is `aid_type`, a type with zero in-range handouts still
appears with `count = 0` only if no date filter drops the NULL side — acceptable
for the chart (zero-count types are harmless). Do not "fix" by moving the date
predicate into the join condition; keep it a `where`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/AidStatsModelTest.php`
Expected: PASS (4 tests). Without a DB, methods return the safe empty shapes and the key/array assertions still hold.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scanner/AidStatsModel.php tests/unit/AidStatsModelTest.php
git commit -m "feat(scanner): AidStatsModel date-scoped aid-distribution stats"
```

---

## Task 2: ReportsController + routes

**Files:**
- Create: `app/Controllers/Scanner/ReportsController.php`
- Modify: `app/Config/Routes.php` (scanner group, after the `manage` routes)
- Test: `tests/unit/ReportsControllerTest.php`

**Interfaces:**
- Consumes: `AidStatsModel` (Task 1).
- Produces:
  - `ReportsController::index(): ResponseInterface|string`
  - `ReportsController::pdf(): ResponseInterface` (body added in Task 6; stub here returns a 200 placeholder so the route resolves).
  - `private normalizeDates(): array{0:?string,1:?string}` — reads `from`/`to`, validates `YYYY-MM-DD`, swaps if `from > to`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsControllerTest extends CIUnitTestCase
{
    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        $this->src = file_get_contents(APPPATH . 'Controllers/Scanner/ReportsController.php');
    }

    public function testGuardsWithScannerRoleList(): void
    {
        $this->assertStringContainsString("['Scanner', 'Admin', 'Developer']", $this->src);
    }

    public function testHasIndexAndPdfActions(): void
    {
        $this->assertStringContainsString('public function index(', $this->src);
        $this->assertStringContainsString('public function pdf(', $this->src);
    }

    public function testPassesStatsToView(): void
    {
        $this->assertStringContainsString('AidStatsModel', $this->src);
        $this->assertStringContainsString("view('Scanner/reports'", $this->src);
    }

    public function testRoutesResolve(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertStringContainsString("ReportsController::index", $routes);
        $this->assertStringContainsString("ReportsController::pdf", $routes);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/ReportsControllerTest.php`
Expected: FAIL — `file_get_contents(...ReportsController.php): Failed to open stream`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Controllers/Scanner/ReportsController.php`:

```php
<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Scanner\AidStatsModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner Reports tab: read-only aid-distribution statistics with chart.js
 * visualizations and a one-page PDF summary export. No mutations, no audit.
 * Scanner/Admin/Developer only, guarded per action.
 */
class ReportsController extends BaseController
{
    /** GET scanner/reports — the statistics dashboard. */
    public function index(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        [$from, $to] = $this->normalizeDates();
        $stats       = model(AidStatsModel::class);
        $summary     = $stats->receivedVsNot($from, $to);
        $byBarangay  = $stats->byBarangay($from, $to);
        $byAidType   = $stats->byAidType($from, $to);

        $role      = RoleAccess::normalizeRole((string) session()->get('role'));
        $canManage = in_array($role, ['Developer', 'Admin'], true);

        return view('Scanner/reports', [
            'activeTab'         => 'reports',
            'pageTitle'         => 'Reports',
            'username'          => session('username') ?? 'Scanner',
            'from'              => $from,
            'to'                => $to,
            'summary'           => $summary,
            'byBarangay'        => $byBarangay,
            'byAidType'         => $byAidType,
            'currentRole'       => $role,
            'canManageAccounts' => $canManage,
            'sidebarRoleClass'  => $canManage ? 'developer' : 'admin',
            'sidebarUserUrl'    => site_url('admin/dashboard'),
            'navActive'         => ['scanner-reports' => 'active'],
        ]);
    }

    /** GET scanner/reports/pdf — one-page summary (body added in Task 6). */
    public function pdf(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->response->setBody('PDF export pending.');
    }

    /**
     * Reads from/to query params, keeps only valid YYYY-MM-DD values, and swaps
     * them when from is later than to. Either side may be null (open-ended).
     *
     * @return array{0:?string,1:?string}
     */
    private function normalizeDates(): array
    {
        $clean = static function (?string $v): ?string {
            $v = trim((string) $v);
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
            return ($d !== false && $d->format('Y-m-d') === $v) ? $v : null;
        };

        $from = $clean($this->request->getGet('from'));
        $to   = $clean($this->request->getGet('to'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
```

Modify `app/Config/Routes.php` — inside the `scanner` group, after the `distributions/void` line, add:

```php
    $routes->get('reports', 'Scanner\ReportsController::index');
    $routes->get('reports/pdf', 'Scanner\ReportsController::pdf');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/unit/ReportsControllerTest.php`
Expected: PASS (4 tests).

Run: `php spark routes | grep reports`
Expected: two rows — `scanner/reports` and `scanner/reports/pdf`.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Scanner/ReportsController.php app/Config/Routes.php tests/unit/ReportsControllerTest.php
git commit -m "feat(scanner): ReportsController index + pdf routes, date normalization"
```

---

## Task 3: Reports view (KPI tiles + fallback tables + chart canvases)

**Files:**
- Create: `app/Views/Scanner/reports.php`
- Test: `tests/unit/ReportsViewTest.php`

**Interfaces:**
- Consumes: view-vars from Task 2 (`from`, `to`, `summary`, `byBarangay`, `byAidType`).
- Produces: DOM anchors the chart JS reads (Task 4): `#chartReceived`, `#chartBarangay`, `#chartAidType`, and a `<script id="reportsData" type="application/json">` block.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsViewTest extends CIUnitTestCase
{
    private string $html;

    protected function setUp(): void
    {
        parent::setUp();
        $this->html = file_get_contents(APPPATH . 'Views/Scanner/reports.php');
    }

    public function testUsesHouseStyleClasses(): void
    {
        foreach (['stat-card', 'sector-management', 'records-search-panel', 'nav nav-tabs manage-tabs'] as $cls) {
            $this->assertStringContainsString($cls, $this->html, "missing house class: {$cls}");
        }
    }

    public function testUsesBootstrapIcons(): void
    {
        $this->assertMatchesRegularExpression('/class="bi bi-[a-z-]+/', $this->html);
    }

    public function testForbidsSbAdminProComponents(): void
    {
        $this->assertStringNotContainsString('border-left-', $this->html);
        $this->assertStringNotContainsString('text-xs text-uppercase', $this->html);
    }

    public function testHasChartAnchorsAndDataBlock(): void
    {
        foreach (['chartReceived', 'chartBarangay', 'chartAidType', 'reportsData'] as $id) {
            $this->assertStringContainsString($id, $this->html);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/ReportsViewTest.php`
Expected: FAIL — cannot open `Views/Scanner/reports.php`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Views/Scanner/reports.php`:

```php
<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php
/* Reports/Statistics tab. Reuses the same house style as Scanner/manage.php:
   nav-tabs header, sector-management panels, stat-card KPI tiles. Charts are
   progressive enhancement over the fallback summary tables (which also feed
   the PDF). All server data esc()'d. */
$rangeLabel = ($from || $to)
    ? 'Showing ' . ($from ? esc($from) : 'the beginning') . ' to ' . ($to ? esc($to) : 'today')
    : 'Showing all dates';
?>

<ul class="nav nav-tabs manage-tabs mb-0" role="tablist">
  <li class="nav-item"><a class="nav-link" href="<?= site_url('scanner/scan') ?>">Scan</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= site_url('scanner/manage') ?>">Manage</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= site_url('scanner/reports') ?>">Reports</a></li>
</ul>

<div class="sector-management records-scroll-panel">

  <!-- Date range + PDF export -->
  <div class="records-search-panel">
    <form class="records-search-row records-lookup-search" method="get" action="<?= site_url('scanner/reports') ?>">
      <label for="fromDate" class="form-label mb-0">From</label>
      <input class="form-control" type="date" id="fromDate" name="from" value="<?= esc($from ?? '', 'attr') ?>">
      <label for="toDate" class="form-label mb-0">To</label>
      <input class="form-control" type="date" id="toDate" name="to" value="<?= esc($to ?? '', 'attr') ?>">
      <button class="btn btn-primary records-search-action" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i><span>Apply</span></button>
      <a class="btn btn-outline-secondary records-search-action" href="<?= site_url('scanner/reports') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
      <a class="btn btn-outline-danger records-search-action" href="<?= site_url('scanner/reports/pdf') . (($from || $to) ? '?from=' . esc($from ?? '', 'url') . '&to=' . esc($to ?? '', 'url') : '') ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>Download PDF report</span></a>
    </form>
    <p class="text-muted small mb-0 mt-2"><?= $rangeLabel ?></p>
  </div>

  <!-- KPI tiles -->
  <div class="stat-card-row d-flex flex-wrap gap-3 my-3">
    <article class="stat-card"><p>Families with a QR</p><strong><?= esc((string) $summary['total']) ?></strong></article>
    <article class="stat-card"><p>Received aid</p><strong><?= esc((string) $summary['received']) ?></strong></article>
    <article class="stat-card"><p>Still waiting</p><strong><?= esc((string) $summary['notReceived']) ?></strong></article>
    <article class="stat-card"><p>Coverage</p><strong><?= esc((string) $summary['coverage']) ?>%</strong></article>
  </div>

  <!-- Charts -->
  <div class="row g-3 reports-charts">
    <div class="col-lg-4">
      <div class="sector-management reports-chart-card">
        <h6>Families that received aid vs still waiting</h6>
        <canvas id="chartReceived" height="220"></canvas>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="sector-management reports-chart-card">
        <h6>Coverage by barangay (percent)</h6>
        <canvas id="chartBarangay" height="220"></canvas>
      </div>
    </div>
    <div class="col-lg-12">
      <div class="sector-management reports-chart-card">
        <h6>Number of handouts by aid type</h6>
        <canvas id="chartAidType" height="180"></canvas>
      </div>
    </div>
  </div>

  <!-- No-JS / print fallback summary tables -->
  <div class="reports-fallback mt-3">
    <table class="table table-sm manage-record-table align-middle w-100">
      <thead><tr><th>Barangay</th><th>Families</th><th>Received</th><th>Coverage</th></tr></thead>
      <tbody>
        <?php foreach ($byBarangay as $b): ?>
          <tr>
            <td><?= esc($b['barangay']) ?></td>
            <td><?= esc((string) $b['total']) ?></td>
            <td><?= esc((string) $b['received']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= esc((string) $b['coverage']) ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($byBarangay === []): ?>
          <tr><td colspan="4" class="text-center text-muted">No data for this range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script id="reportsData" type="application/json"><?= json_encode([
    'received'  => $summary,
    'barangay'  => $byBarangay,
    'aidType'   => $byAidType,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?= $this->endSection() ?>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/unit/ReportsViewTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Views/Scanner/reports.php tests/unit/ReportsViewTest.php
git commit -m "feat(scanner): Reports view - KPI tiles, chart canvases, fallback tables"
```

---

## Task 4: chart.js vendored + manifest scanner context + chart JS/CSS

**Files:**
- Create: `public/vendor/chart.js/chart.umd.min.js` (download, `git add -f`)
- Create: `public/assets/js/dashboard/scanner-reports.js`
- Create: `public/css/scanner-reports.css`
- Modify: `app/Helpers/asset_helper.php` (add `scanner` to `asset_styles` and `asset_scripts`)
- Modify: `app/Views/Scanner/layout.php` (merge `scanner` context)
- Test: `tests/unit/ReportsAssetsTest.php`

**Interfaces:**
- Consumes: `#reportsData` JSON + `#chartReceived|#chartBarangay|#chartAidType` (Task 3), global `Chart` from chart.js.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsAssetsTest extends CIUnitTestCase
{
    public function testScannerScriptContextOrdersChartBeforeInit(): void
    {
        $scripts = asset_scripts('scanner');
        $chart   = array_search('vendor/chart.js/chart.umd.min.js', $scripts, true);
        $init    = array_search('assets/js/dashboard/scanner-reports.js', $scripts, true);
        $this->assertNotFalse($chart, 'chart.js missing from scanner scripts');
        $this->assertNotFalse($init, 'scanner-reports.js missing from scanner scripts');
        $this->assertLessThan($init, $chart, 'chart.js must load before scanner-reports.js');
    }

    public function testScannerStyleContextHasReportsCss(): void
    {
        $this->assertContains('css/scanner-reports.css', asset_styles('scanner'));
    }

    public function testLayoutMergesScannerContext(): void
    {
        $layout = file_get_contents(APPPATH . 'Views/Scanner/layout.php');
        $this->assertStringContainsString("asset_scripts('scanner')", $layout);
        $this->assertStringContainsString("asset_styles('scanner')", $layout);
    }

    public function testChartJsVendored(): void
    {
        $this->assertFileExists(FCPATH . 'vendor/chart.js/chart.umd.min.js');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/ReportsAssetsTest.php`
Expected: FAIL — `scanner` context returns `[]`; vendored file missing.

- [ ] **Step 3: Write minimal implementation**

Download chart.js UMD build (v4, no dependency):

```bash
mkdir -p public/vendor/chart.js
curl -L -o public/vendor/chart.js/chart.umd.min.js https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js
```

In `app/Helpers/asset_helper.php`, add to the `asset_styles` manifest array:

```php
            'scanner' => [
                'css/scanner-reports.css',
            ],
```

and to the `asset_scripts` manifest array:

```php
            'scanner' => [
                'vendor/chart.js/chart.umd.min.js',
                'assets/js/dashboard/scanner-reports.js',
            ],
```

In `app/Views/Scanner/layout.php`, change the styles loop source from
`array_merge(asset_styles('head'), asset_styles('admin'))` to
`array_merge(asset_styles('head'), asset_styles('admin'), asset_styles('scanner'))`,
and the scripts loop source from
`array_merge(asset_scripts('core'), asset_scripts('admin'))` to
`array_merge(asset_scripts('core'), asset_scripts('admin'), asset_scripts('scanner'))`.

Create `public/css/scanner-reports.css`:

```css
/* Reports tab: chart-card sizing only. Reuses shared panel styling; does not
   restyle shared classes. */
.reports-chart-card {
    padding: 1rem;
    height: 100%;
}
.reports-chart-card h6 {
    margin-bottom: .75rem;
    font-weight: 600;
}
.reports-chart-card canvas {
    max-width: 100%;
}
.stat-card-row .stat-card {
    flex: 1 1 12rem;
}
```

Create `public/assets/js/dashboard/scanner-reports.js`:

```javascript
/* Builds the three Reports-tab charts from the #reportsData JSON block.
   No-ops when chart.js or the canvases are absent (e.g. Scan/Manage pages). */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') {
        return;
    }
    var el = document.getElementById('reportsData');
    if (!el) {
        return;
    }

    var data;
    try {
        data = JSON.parse(el.textContent || '{}');
    } catch (e) {
        return;
    }

    function ctx(id) {
        var c = document.getElementById(id);
        return c ? c.getContext('2d') : null;
    }

    var received = ctx('chartReceived');
    if (received && data.received) {
        new Chart(received, {
            type: 'doughnut',
            data: {
                labels: ['Received aid', 'Still waiting'],
                datasets: [{
                    data: [data.received.received || 0, data.received.notReceived || 0],
                    backgroundColor: ['#1cc88a', '#e74a3b']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }

    var barangay = ctx('chartBarangay');
    if (barangay && Array.isArray(data.barangay)) {
        new Chart(barangay, {
            type: 'bar',
            data: {
                labels: data.barangay.map(function (b) { return b.barangay; }),
                datasets: [{
                    label: 'Coverage %',
                    data: data.barangay.map(function (b) { return b.coverage; }),
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, max: 100 } },
                plugins: { legend: { display: false } }
            }
        });
    }

    var aidType = ctx('chartAidType');
    if (aidType && Array.isArray(data.aidType)) {
        new Chart(aidType, {
            type: 'bar',
            data: {
                labels: data.aidType.map(function (a) { return a.aid_type; }),
                datasets: [{
                    label: 'Handouts',
                    data: data.aidType.map(function (a) { return a.count; }),
                    backgroundColor: '#f6c23e'
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    }
})();
```

- [ ] **Step 4: Run test + verify chart loads**

Run: `vendor/bin/phpunit tests/unit/ReportsAssetsTest.php`
Expected: PASS (4 tests).

Run: `php spark serve` and open `scanner/reports` as a Scanner user — three charts render, or empty charts + "No data" without seeded data. Check the browser console: no `Chart is not defined`.

- [ ] **Step 5: Commit**

```bash
git add -f public/vendor/chart.js/chart.umd.min.js
git add app/Helpers/asset_helper.php app/Views/Scanner/layout.php public/css/scanner-reports.css public/assets/js/dashboard/scanner-reports.js tests/unit/ReportsAssetsTest.php
git commit -m "feat(scanner): vendor chart.js, scanner asset context, Reports charts"
```

---

## Task 5: Sidebar Reports link

**Files:**
- Modify: `app/Views/components/dashboard_sidebar.php` (scanner-only branch, after the Manage link)

**Interfaces:**
- Consumes: `$activeTab` (`'reports'` set active).

- [ ] **Step 1: Locate the scanner Manage link**

Run: `grep -n "scanner/manage\|scanner/scan\|activeTab" app/Views/components/dashboard_sidebar.php`
Expected: the scanner-only branch renders Scan and Manage `nav-link`s using `$activeTab`.

- [ ] **Step 2: Add the Reports link**

Immediately after the Manage `<li>…</li>` in the scanner-only branch, add:

```php
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'reports' ? 'active' : '' ?>" href="<?= site_url('scanner/reports') ?>"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Reports</span></a>
        </li>
```

Match the exact `<li>`/`<a>` markup already used by the adjacent Scan/Manage links (copy their structure; the class + href + icon + label above are the only differences).

- [ ] **Step 3: Verify**

Run: `php spark serve`, log in as Scanner, confirm a "Reports" item appears under QR Code and is highlighted on `scanner/reports`.

- [ ] **Step 4: Commit**

```bash
git add app/Views/components/dashboard_sidebar.php
git commit -m "feat(scanner): Reports link in scanner sidebar"
```

---

## Task 6: PDF export

**Files:**
- Create: `app/Libraries/Scanner/ReportsPdfGenerator.php`
- Create: `app/Views/Scanner/pdf/report.php`
- Create: `app/Views/Scanner/pdf/_styles.php`
- Modify: `app/Controllers/Scanner/ReportsController.php` (`pdf()` real body)
- Test: `tests/unit/ReportsPdfGeneratorTest.php`

**Interfaces:**
- Consumes: `AidStatsModel` datasets, same shapes as Task 1.
- Produces: `ReportsPdfGenerator::generate(array $summary, array $byBarangay, array $byAidType, ?string $from, ?string $to): string` — returns PDF bytes.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ReportsPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class ReportsPdfGeneratorTest extends CIUnitTestCase
{
    public function testGeneratesPdfBytes(): void
    {
        $bytes = (new ReportsPdfGenerator())->generate(
            ['total' => 3, 'received' => 2, 'notReceived' => 1, 'coverage' => 67],
            [['barangay' => 'Poblacion', 'total' => 3, 'received' => 2, 'coverage' => 67]],
            [['aid_type' => 'Rice', 'count' => 5]],
            '2026-01-01',
            '2026-01-31'
        );
        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/unit/ReportsPdfGeneratorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Create `app/Views/Scanner/pdf/_styles.php`:

```php
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  h1 { font-size: 18px; margin: 0 0 2px; }
  .sub { color: #666; font-size: 10px; margin: 0 0 12px; }
  .kpis td { padding: 6px 10px; border: 1px solid #ddd; text-align: center; }
  .kpis .n { font-size: 16px; font-weight: bold; }
  table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.data th, table.data td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
  table.data th { background: #f4f4f4; }
  .bar { background: #4e73df; height: 8px; display: inline-block; vertical-align: middle; }
  h2 { font-size: 13px; margin: 14px 0 4px; }
</style>
```

Create `app/Views/Scanner/pdf/report.php`:

```php
<?php
/* Server-side PDF body (dompdf, no JS). Same numbers as the Reports tab; the
   barangay coverage is drawn as CSS bars in lieu of a chart. */
$window = ($from || $to)
    ? ($from ?: 'start') . ' to ' . ($to ?: 'today')
    : 'All dates';
?>
<?= $this->include('Scanner/pdf/_styles') ?>
<h1>Aid Distribution Report</h1>
<p class="sub">City of Bi&ntilde;an CSWD &middot; <?= esc($window) ?> &middot; Generated <?= esc(date('Y-m-d H:i')) ?></p>

<table class="kpis" style="width:100%; border-collapse:collapse;">
  <tr>
    <td>Families with a QR<br><span class="n"><?= esc((string) $summary['total']) ?></span></td>
    <td>Received aid<br><span class="n"><?= esc((string) $summary['received']) ?></span></td>
    <td>Still waiting<br><span class="n"><?= esc((string) $summary['notReceived']) ?></span></td>
    <td>Coverage<br><span class="n"><?= esc((string) $summary['coverage']) ?>%</span></td>
  </tr>
</table>

<h2>Coverage by barangay</h2>
<table class="data">
  <thead><tr><th>Barangay</th><th>Families</th><th>Received</th><th>Coverage</th></tr></thead>
  <tbody>
  <?php foreach ($byBarangay as $b): ?>
    <tr>
      <td><?= esc($b['barangay']) ?></td>
      <td><?= esc((string) $b['total']) ?></td>
      <td><?= esc((string) $b['received']) ?></td>
      <td><span class="bar" style="width: <?= (int) $b['coverage'] ?>px;"></span> <?= esc((string) $b['coverage']) ?>%</td>
    </tr>
  <?php endforeach; ?>
  <?php if ($byBarangay === []): ?>
    <tr><td colspan="4">No data for this range.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<h2>Handouts by aid type</h2>
<table class="data">
  <thead><tr><th>Aid type</th><th>Handouts</th></tr></thead>
  <tbody>
  <?php foreach ($byAidType as $a): ?>
    <tr><td><?= esc($a['aid_type']) ?></td><td><?= esc((string) $a['count']) ?></td></tr>
  <?php endforeach; ?>
  <?php if ($byAidType === []): ?>
    <tr><td colspan="2">No data for this range.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
```

Create `app/Libraries/Scanner/ReportsPdfGenerator.php`:

```php
<?php

namespace App\Libraries\Scanner;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the Scanner Reports summary (KPIs + per-barangay + per-aid-type
 * tables) into a one-page US-Letter PDF. Server-side, no chart.js: the barangay
 * coverage is drawn as CSS bars. Mirrors Qr\QrCardPdfGenerator's dompdf setup.
 */
final class ReportsPdfGenerator
{
    /**
     * @param array{total:int,received:int,notReceived:int,coverage:int} $summary
     * @param list<array{barangay:string,total:int,received:int,coverage:int}> $byBarangay
     * @param list<array{aid_type:string,count:int}> $byAidType
     */
    public function generate(array $summary, array $byBarangay, array $byAidType, ?string $from, ?string $to): string
    {
        $html = view('Scanner/pdf/report', [
            'summary'    => $summary,
            'byBarangay' => $byBarangay,
            'byAidType'  => $byAidType,
            'from'       => $from,
            'to'         => $to,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
```

Replace the `pdf()` body in `app/Controllers/Scanner/ReportsController.php`:

```php
    /** GET scanner/reports/pdf — one-page summary of the current date window. */
    public function pdf(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        [$from, $to] = $this->normalizeDates();
        $stats       = model(AidStatsModel::class);

        $bytes = (new \App\Libraries\Scanner\ReportsPdfGenerator())->generate(
            $stats->receivedVsNot($from, $to),
            $stats->byBarangay($from, $to),
            $stats->byAidType($from, $to),
            $from,
            $to
        );

        $name = 'aid-report-' . ($from ?: 'start') . '_' . ($to ?: 'today') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($bytes);
    }
```

- [ ] **Step 4: Run tests + smoke the download**

Run: `vendor/bin/phpunit tests/unit/ReportsPdfGeneratorTest.php tests/unit/ReportsControllerTest.php`
Expected: PASS.

Run: `php spark serve`, visit `scanner/reports/pdf` as a Scanner — a PDF downloads and opens with the KPIs + two tables.

- [ ] **Step 5: Commit**

```bash
git add app/Libraries/Scanner/ReportsPdfGenerator.php app/Views/Scanner/pdf/report.php app/Views/Scanner/pdf/_styles.php app/Controllers/Scanner/ReportsController.php tests/unit/ReportsPdfGeneratorTest.php
git commit -m "feat(scanner): PDF summary export via dompdf"
```

- [ ] **Step 6: Full suite checkpoint (end of §A/§B)**

Run: `vendor/bin/phpunit`
Expected: all green, 4 skipped, count = baseline 55 + new tests. Fix any regression before §C. (Actual final: 72 tests.)

---

## Task 7: Merge Mel-import-branch (Excel family import) + fix breakage

This task is a git merge, not new code. Do NOT hand-copy files — merge the branch
so history is preserved. Work on a checkpoint so a bad merge is recoverable.

**Files:** whole-branch merge; conflicts expected in `app/Config/Routes.php`,
`composer.json`, `composer.lock` only (verified: merge base `d4f0c5b`, 3-file overlap).

- [ ] **Step 1: Pre-merge safety + baseline**

```bash
git status                      # working tree clean except pre-existing untracked artifacts
vendor/bin/phpunit              # record baseline pass count
git branch backup/pre-import-merge   # recovery point
git fetch origin
```

- [ ] **Step 2: Merge (no squash)**

```bash
git merge origin/Mel-import-branch
```

Expected: conflicts in `app/Config/Routes.php`, `composer.json`, `composer.lock`.

- [ ] **Step 3: Resolve conflicts**

- `app/Config/Routes.php` — keep BOTH route groups: the `scanner` group (scan/lookup/log/manage/aid-types/distributions/reports) AND the families import routes from Mel. Union, no deletions.
- `composer.json` `require` — union all deps:

```json
        "chillerlan/php-qrcode": "^6.0",
        "codeigniter4/framework": "^4.7",
        "dompdf/dompdf": "^3.1",
        "phpoffice/phpspreadsheet": "^5.8",
        "php": "^8.2"
```

- `composer.lock` — do not hand-merge. After the json is resolved:

```bash
git checkout --theirs composer.lock   # take Mel's lock as a base
composer update --lock                 # reconcile to the unioned composer.json
```

```bash
git add app/Config/Routes.php composer.json composer.lock
```

- [ ] **Step 4: Install deps + verify routes**

```bash
composer install
php spark routes
```

Expected: `composer install` pulls phpspreadsheet; every route resolves, including the families import route AND `scanner/reports`.

- [ ] **Step 5: Load the job_queue schema (no migration)**

The import enqueues jobs into a `job_queue` table delivered as `sql/job_queue.sql`.

```bash
mysql -uroot accesscard < sql/job_queue.sql
```

Expected: table created. (Per non-negotiables, this is a SQL dump, not a CI4 migration.)

- [ ] **Step 6: Run the full suite, fix breakage**

```bash
vendor/bin/phpunit
```

If red:
- The lookups refactor (`a2f44fb`) may break `SectorModel`/`ServiceModel`/`CategoryModel` tests or `MemberModel` expectations. For each failure, read the failing assertion, reconcile the model/view against the SQL dump's exact column/enum names (per non-negotiables the dump wins), and update the test only if the behavior legitimately changed.
- `FamilyDataTableTest` was already updated to inspect the manifest — confirm the merged `asset_helper.php` still wires the merged helper calls.
Fix until green. Commit the merge only when the suite passes:

```bash
git commit    # completes the merge commit
```

- [ ] **Step 7: Confirm import audits + add synchronous drain for Mac dev**

The bundled worker is PowerShell (Windows). For macOS dev, provide an in-process
drain so an import completes without PowerShell.

- Verify the importer's write path (`FamilyRecordWriter`) writes an `audit_trails`
  row per created family (grep for `AuditTrailsModel`/`logAction` in
  `app/Libraries/FamilyExcelImporter.php` and `FamilyRecordWriter.php`). If it
  does not, wire it through the same audit call `FamilyController` uses — this is
  a non-negotiable.
> **Superseded at implementation:** the Mel-import merge shipped a full generic
> background worker, `app/Commands/QueueWork.php` (`php spark queue:work`), which
> drains `job_queue` and resumes crashed `processing` jobs via the handler
> registered in `Config\Queue`. Use `php spark queue:work` for the in-process
> drain instead of the placeholder `jobs:drain` command below. The
> `DrainJobQueue`/`jobs:drain` code was NOT created — it is retained here only as
> the original design intent.

- Add a spark command to drain the queue in-process. Create
  `app/Commands/DrainJobQueue.php`:

```php
<?php

namespace App\Commands;

use App\Models\Jobs\JobQueueModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Drains pending job_queue rows in-process (dev/macOS substitute for the
 * Windows PowerShell worker). Runs each queued FamilyImportJob to completion.
 */
class DrainJobQueue extends BaseCommand
{
    protected $group       = 'Jobs';
    protected $name        = 'jobs:drain';
    protected $description  = 'Run all pending queued jobs in-process.';

    public function run(array $params): void
    {
        $queue = model(JobQueueModel::class);
        $count = 0;
        while (($job = $queue->reserveNext()) !== null) {
            $queue->runJob($job);   // use the queue's existing execute path
            $count++;
        }
        CLI::write("Drained {$count} job(s).", 'green');
    }
}
```

Note: use the ACTUAL method names on the merged `JobQueueModel` (inspect it first —
`reserveNext`/`runJob` above are placeholders for whatever the merged model
exposes; wire to the real reserve + execute methods). If the model already has a
one-shot runner, call that instead of reimplementing the loop.

- [ ] **Step 8: Dry-run the 500-row template**

```bash
php spark serve
```

- Log in as Admin, open the family import modal, upload `family-import-template_filled_500.xlsx`.
- Drain: `php spark queue:work` (shipped worker; `jobs:drain` was superseded).
- Verify imported members exist and are audited. Mind the known dump-v13 5x
  duplication caveat when checking counts (dedupe by `memberID <= 500`).

- [ ] **Step 9: Commit the dev drain command**

Superseded — no separate commit. The generic `QueueWork` worker arrived with the
Mel-import merge (`php spark queue:work`); no `DrainJobQueue` was added.

- [ ] **Step 10: Final full-suite checkpoint**

```bash
vendor/bin/phpunit
php spark routes
```

Expected: all green; every route resolves. Reports tab + PDF + import all work.

---

## Self-review notes

- **Spec coverage:** §A → Tasks 1–5; §B → Task 6; §C → Task 7. UI style contract → enforced by `ReportsViewTest` (Task 3) + manifest test (Task 4). Audit-on-import → Task 7 Step 7. No-migration → Task 7 Step 5 loads `job_queue.sql` as a dump.
- **Placeholders:** the only intentional "inspect the real method names" note is Task 7 Step 7 (`JobQueueModel` API is unknown until the merge lands — cannot be pinned pre-merge); every other step ships complete code.
- **Type consistency:** `receivedVsNot/byBarangay/byAidType` shapes are identical across model (Task 1), controller (Task 2), view (Task 3), and PDF (Task 6). `coverage` is int percent everywhere. Chart anchors `chartReceived/chartBarangay/chartAidType` + `reportsData` match between view (Task 3) and JS (Task 4).
```
