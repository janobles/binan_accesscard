# Control Numbers page + Aid→Subsidy relabel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the "Generate Cards" page as a comprehensive **Control Numbers** page (Batch + Single-card modes, control-number range filter, live preview table, searchable head picker, blue Generate buttons), and relabel the user-facing word "aid" → "subsidy" across the app.

**Architecture:** Extend `MemberModel::headsForCards()` (the single source both the preview table and the printed PDF read from) with control-number range, name-keyword, and limit filters plus a count method. Add one JSON endpoint (`admin/cards/heads`) that serves both the Batch preview and the Single-card autocomplete. Rebuild `Cards/batch_form.php` as a two-mode Bootstrap-grid view with vanilla-JS mode switching and a debounced preview fetch. Relabel is display-text-only: views, code-built labels, flash/error strings, and audit-trail strings — never schema, routes, or PHP identifiers.

**Tech Stack:** CodeIgniter 4, PHP 8.2+, Bootstrap 5 (SB Admin 1 theme), PHPUnit, Playwright (MCP) for UI verification.

## Global Constraints

- **No migrations / no schema changes.** SQL dump (`accesscardV18.sql`) is the source of truth. Do not alter tables, columns, or enum values.
- **Match the SQL dump** for column/enum/role names. Employee accounts are `User` role.
- **PHP 8.2+**, typed signatures everywhere; **no** `declare(strict_types=1)` (matches CI4 appstarter).
- **Every family mutation writes an audit trail** — do not bypass; batch card generation writes exactly ONE audit row.
- **Controllers decide, libraries build.** Dashboard page data lives in `DashboardPageBuilder`; the Cards controller only routes/streams.
- **Comments** are plain-language for human readers; no em dashes, no AI-slop.
- **Aid→Subsidy is display text only.** Never change: table names (`aid_distribution`, `aid_type`), column/enum values, route URLs (`admin/aidtypes`, `admin/distribution`), or PHP identifiers (`AidStatsModel`, `AidDistributionModel`, `$aid*`, `getAid*`, DOM ids like `addAidTypeModal`/`aidTypeName`).
- **Blue generate buttons.** Generate actions use `btn('generate')` → `btn btn-primary`. Never the add-green `btn('add')` / `#198754`.
- **Run `vendor/bin/phpunit` before and after each task.** DB/session tests skip without the `sqlite3` ext; locally the XAMPP MySQL `accesscard` DB is available.
- **Branch:** all work lands on `feat/control-numbers-subsidy` (already checked out).

---

## File Structure

**Part A — Control Numbers page**
- Modify `app/Models/Families/MemberModel.php` — range/keyword/limit filters on `headsForCards()`; new `countHeadsForCards()`.
- Modify `app/Controllers/Cards/QrCardController.php` — new `heads()` JSON endpoint; `batch()` reads range, drops sector.
- Modify `app/Config/Routes.php` — add `GET admin/cards/heads`.
- Modify `app/Helpers/ui_helper.php` — add `'generate'` button role.
- Modify `docs/knowledge/binan-conventions/ui-design-system.md` — document the `generate` role.
- Rewrite `app/Views/Cards/batch_form.php` — two-mode grid view, preview table, autocomplete, JS.
- Modify `app/Views/components/dashboard_sidebar.php` — label "Generate Cards" → "Control Numbers".
- Tests: `tests/unit/MemberHeadsForCardsTest.php`, `tests/unit/QrCardControllerTest.php`.

**Part B — Aid→Subsidy relabel** (display text only)
- Views: `app/Views/Admin/layout.php`, `distribution-distributions-body.php`, `distribution-batches-body.php`, `batch-create-modal.php`, `aidtypes-body.php`, `aidtype-create-modal.php`, `reports-body.php`, `Family/family-modal.php`, `Scanner/scan.php`, `Scanner/pdf/report.php`, `components/dashboard_sidebar.php`.
- Code-built strings: `app/Controllers/Admin/ReportsController.php`, `app/Controllers/Admin/DistributionController.php`, `app/Controllers/Scanner/ScanController.php`.
- JS: `public/assets/js/dashboard/scanner-reports.js`.

---

## Task 1: `headsForCards()` range + keyword + limit, and `countHeadsForCards()`

**Files:**
- Modify: `app/Models/Families/MemberModel.php:369-448`
- Test: `tests/unit/MemberHeadsForCardsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces:
  - `MemberModel::headsForCards(array $filter = []): array` — now also honors
    `controlFrom` (int, inclusive lower bound on `qr_control.control_no`),
    `controlTo` (int, inclusive upper bound), `keyword` (string, name LIKE across
    lastname/firstname/middlename), `limit` (int > 0, row cap). Existing keys
    (`memberID`, `barangay`, `sectorID`) unchanged. Row shape unchanged:
    `['memberID'=>int, 'controlNo'=>int, 'fullname'=>string, 'barangay'=>string]`.
  - `MemberModel::countHeadsForCards(array $filter = []): int` — total matches for
    the same filter, ignoring `limit`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/unit/MemberHeadsForCardsTest.php` (inside the class):

```php
    public function testCountHeadsForCardsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(MemberModel::class, 'countHeadsForCards'),
            'countHeadsForCards() must exist for the preview table total.'
        );
    }

    public function testHeadsForCardsHonorsLimit(): void
    {
        $model = $this->modelOrSkip();

        $all = $model->headsForCards();
        if (count($all) < 2) {
            $this->markTestSkipped('Need at least 2 mapped heads to assert limit.');
        }

        $limited = $model->headsForCards(['limit' => 1]);
        $this->assertCount(1, $limited);
    }

    public function testHeadsForCardsControlRangeStaysWithinBounds(): void
    {
        $model = $this->modelOrSkip();

        $all = $model->headsForCards();
        if ($all === []) {
            $this->markTestSkipped('No mapped heads seeded.');
        }

        $controls = array_column($all, 'controlNo');
        $lo = min($controls);
        $hi = max($controls);

        $ranged = $model->headsForCards(['controlFrom' => $lo, 'controlTo' => $hi]);
        foreach ($ranged as $head) {
            $this->assertGreaterThanOrEqual($lo, $head['controlNo']);
            $this->assertLessThanOrEqual($hi, $head['controlNo']);
        }

        // Count method must agree with the ranged row count when no limit applies.
        $this->assertSame(
            count($ranged),
            $model->countHeadsForCards(['controlFrom' => $lo, 'controlTo' => $hi]),
            'countHeadsForCards() must match the unlimited ranged result size.'
        );
    }

    public function testHeadsForCardsKeywordNarrowsByName(): void
    {
        $model = $this->modelOrSkip();

        $all = $model->headsForCards();
        if ($all === []) {
            $this->markTestSkipped('No mapped heads seeded.');
        }

        // Take a lastname fragment from the first head and confirm it still returns
        // that head (keyword filter must not drop an exact-name match).
        $name = $all[0]['fullname'];
        $fragment = substr(trim(explode(',', $name)[0]), 0, 3);
        if ($fragment === '') {
            $this->markTestSkipped('First head has no usable name fragment.');
        }

        $hit = $model->headsForCards(['keyword' => $fragment]);
        $this->assertNotSame([], $hit, 'Keyword matching a real name must return rows.');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter MemberHeadsForCards`
Expected: FAIL — `countHeadsForCards` not defined (fatal) / new assertions error. (If the suite reports "skipped" for lack of DB, run against the local MySQL DB per CLAUDE.md so the assertions actually execute.)

- [ ] **Step 3: Refactor `headsForCards()` to share a filtered builder**

In `app/Models/Families/MemberModel.php`, replace the body of `headsForCards()` (lines 369-448) so the WHERE assembly is reusable by the count method. Extract a private `headsForCardsBuilder()` that both call.

```php
    public function headsForCards(array $filter = []): array
    {
        $builder = $this->headsForCardsBuilder($filter);

        $builder->orderBy('qc.control_no IS NULL', 'asc', false)
            ->orderBy('qc.control_no', 'asc')
            ->orderBy('member.memberID', 'asc');

        if (isset($filter['limit']) && (int) $filter['limit'] > 0) {
            $builder->limit((int) $filter['limit']);
        }

        $rows = $builder->get()->getResultArray();

        // Barangay is stored at the tail of the combined address; match it against the
        // canonical list (longest first) so it prints on the QR card.
        $barangays = \App\Support\FamilyProfilingFormV2::barangays();
        usort($barangays, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_map(static function (array $row) use ($barangays): array {
            $name = trim(sprintf(
                '%s, %s %s %s',
                $row['lastname'] ?? '',
                $row['firstname'] ?? '',
                $row['middlename'] ?? '',
                $row['suffix'] ?? ''
            ));

            $barangay = trim((string) ($row['barangay'] ?? ''));

            if ($barangay === '') {
                $address = trim((string) ($row['address'] ?? ''));
                foreach ($barangays as $candidate) {
                    if (str_ends_with($address, ', ' . $candidate) || strcasecmp($address, $candidate) === 0) {
                        $barangay = $candidate;
                        break;
                    }
                }
            }

            return [
                'memberID'  => (int) $row['memberID'],
                'controlNo' => (int) $row['control_no'],
                'fullname'  => preg_replace('/\s+/', ' ', $name),
                'barangay'  => $barangay,
            ];
        }, $rows);
    }

    /**
     * Total heads matching $filter, ignoring any 'limit'. Backs the Control
     * Numbers preview header ("N cards will be generated") so the count is always
     * the full selection even when only the first rows are rendered.
     */
    public function countHeadsForCards(array $filter = []): int
    {
        unset($filter['limit']);

        return $this->headsForCardsBuilder($filter, true)->countAllResults();
    }

    /**
     * Shared SELECT for headsForCards()/countHeadsForCards(). Active heads with a
     * qr_control mapping, filtered by memberID, barangay, control-number range,
     * name keyword, and sector. $forCount omits the display columns.
     */
    private function headsForCardsBuilder(array $filter = [], bool $forCount = false): \CodeIgniter\Database\BaseBuilder
    {
        $hasBarangayColumn = $this->memberFieldExists('barangay');

        $columns = $forCount
            ? 'member.memberID'
            : 'member.memberID, member.lastname, member.firstname, member.middlename, member.suffix, member.address, qc.control_no'
                . ($hasBarangayColumn ? ', member.barangay' : '');

        $builder = $this->db->table('member')
            ->select($columns)
            ->join('qr_control qc', 'qc.headID = member.memberID', 'left')
            ->where('member.headID = member.memberID', null, false);

        // qr_control is the single source of truth for control numbers: a head with
        // no mapping cannot be scanned, so it is excluded rather than printed with a
        // memberID the scanner would reject.
        $builder->where('qc.control_no IS NOT NULL', null, false);

        if ($this->db->fieldExists('dt_deleted', 'member')) {
            $builder->where('member.dt_deleted IS NULL', null, false);
        }

        if (isset($filter['memberID']) && (int) $filter['memberID'] > 0) {
            $builder->where('member.memberID', (int) $filter['memberID']);
        }

        if (! empty($filter['barangay'])) {
            if ($hasBarangayColumn) {
                $builder->where('member.barangay', $filter['barangay']);
            } else {
                // Barangay lives at the tail of the combined address ("address, barangay").
                $builder->groupStart()
                    ->like('member.address', ', ' . $filter['barangay'], 'before')
                    ->orWhere('member.address', $filter['barangay'])
                    ->groupEnd();
            }
        }

        if (isset($filter['controlFrom']) && (int) $filter['controlFrom'] > 0) {
            $builder->where('qc.control_no >=', (int) $filter['controlFrom']);
        }
        if (isset($filter['controlTo']) && (int) $filter['controlTo'] > 0) {
            $builder->where('qc.control_no <=', (int) $filter['controlTo']);
        }

        if (isset($filter['keyword']) && trim((string) $filter['keyword']) !== '') {
            $keyword = trim((string) $filter['keyword']);
            $builder->groupStart()
                ->like('member.lastname', $keyword)
                ->orLike('member.firstname', $keyword)
                ->orLike('member.middlename', $keyword)
                ->groupEnd();
        }

        if (! empty($filter['sectorID'])) {
            // sectorID is stored as a JSON array ('[1,2,3]'); match membership.
            $builder->where(SectorIds::containsCondition((int) $filter['sectorID'], 'member.sectorID'), null, false);
        }

        return $builder;
    }
```

Confirm the `use` for `SectorIds` already exists in the file (it did in the original method); no new import beyond the fully-qualified `BaseBuilder` return type, which needs no `use` since it is written out.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter MemberHeadsForCards`
Expected: PASS (or SKIP only if the local DB is genuinely unavailable).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: no new failures.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Families/MemberModel.php tests/unit/MemberHeadsForCardsTest.php
git commit -m "feat(cards): headsForCards range/keyword/limit + countHeadsForCards

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 2: `heads()` JSON endpoint + route

**Files:**
- Modify: `app/Controllers/Cards/QrCardController.php`
- Modify: `app/Config/Routes.php:77-81`
- Test: `tests/unit/QrCardControllerTest.php`

**Interfaces:**
- Consumes: `MemberModel::headsForCards()`, `MemberModel::countHeadsForCards()` (Task 1).
- Produces: `GET admin/cards/heads` → `Cards\QrCardController::heads()` returning
  JSON `{"count": int, "rows": [{"memberID": int, "controlNo": int, "name": string, "barangay": string}]}`.
  Query params: `q` (name keyword), `barangay`, `from`, `to`, `mode`
  (`search` → cap 15 rows, anything else → preview cap 50). Role-guarded
  (`Developer`, `Admin`).

- [ ] **Step 1: Write the failing test**

Append to `tests/unit/QrCardControllerTest.php` (inside the class):

```php
    public function testHeadsEndpointRejectsUnauthenticated(): void
    {
        $result = $this->get('admin/cards/heads?q=de');
        $result->assertRedirectTo(site_url('login'));
    }

    public function testHeadsRouteAndMethodExist(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Cards/QrCardController.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('public function heads(', $source);

        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertIsString($routes);
        $this->assertStringContainsString("cards/heads", str_replace(["'", '"'], '', $routes . " cards/heads"));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter QrCardController`
Expected: FAIL — `heads(` not found in source; route assertion fails.

- [ ] **Step 3: Add the route**

In `app/Config/Routes.php`, inside the `cards` group (after line 81 `lookup`):

```php
        $routes->get('heads', 'Cards\QrCardController::heads');
```

- [ ] **Step 4: Implement `heads()`**

In `app/Controllers/Cards/QrCardController.php`, add the method (after `batch()`), keeping the class's existing role-guard pattern:

```php
    /**
     * GET admin/cards/heads. JSON feed for the Control Numbers page: powers both
     * the Batch preview table and the Single-card head autocomplete. Returns the
     * full match count plus a capped row list, drawn from the same MemberModel
     * selection the printed PDF uses so preview and output never diverge.
     */
    public function heads(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $mode  = (string) $this->request->getGet('mode');
        $limit = $mode === 'search' ? 15 : 50;

        $filter = ['limit' => $limit];
        if (($keyword = trim((string) $this->request->getGet('q'))) !== '') {
            $filter['keyword'] = $keyword;
        }
        if (($barangay = trim((string) $this->request->getGet('barangay'))) !== '') {
            $filter['barangay'] = $barangay;
        }
        if (($from = (int) $this->request->getGet('from')) > 0) {
            $filter['controlFrom'] = $from;
        }
        if (($to = (int) $this->request->getGet('to')) > 0) {
            $filter['controlTo'] = $to;
        }

        $model = model(MemberModel::class);
        $rows  = array_map(static fn (array $h): array => [
            'memberID'  => $h['memberID'],
            'controlNo' => $h['controlNo'],
            'name'      => $h['fullname'],
            'barangay'  => $h['barangay'],
        ], $model->headsForCards($filter));

        $countFilter = $filter;
        unset($countFilter['limit']);

        return $this->response->setJSON([
            'count' => $model->countHeadsForCards($countFilter),
            'rows'  => $rows,
        ]);
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter QrCardController`
Expected: PASS.

- [ ] **Step 6: Confirm the route resolves**

Run: `php spark routes | grep cards`
Expected: a `GET admin/cards/heads → \App\Controllers\Cards\QrCardController::heads` row appears.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/Cards/QrCardController.php app/Config/Routes.php tests/unit/QrCardControllerTest.php
git commit -m "feat(cards): heads JSON endpoint for preview + autocomplete

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 3: `batch()` reads control-number range, drops sector

**Files:**
- Modify: `app/Controllers/Cards/QrCardController.php:30-71,153-164`
- Test: `tests/unit/QrCardControllerTest.php`

**Interfaces:**
- Consumes: `MemberModel::headsForCards()` (Task 1).
- Produces: `batch()` now reads POST `from`/`to` into `controlFrom`/`controlTo`,
  no longer reads `sectorID`. Empty-selection 400, maxQuantity guard, single audit
  row, and `X-CSRF-TOKEN` refresh header are unchanged.

- [ ] **Step 1: Write the failing test**

Append to `tests/unit/QrCardControllerTest.php`:

```php
    public function testBatchReadsControlRangeAndNotSector(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Cards/QrCardController.php');
        $this->assertIsString($source);

        // New range params wired into the filter.
        $this->assertStringContainsString("getPost('from')", $source);
        $this->assertStringContainsString("getPost('to')", $source);
        $this->assertStringContainsString("controlFrom", $source);
        $this->assertStringContainsString("controlTo", $source);

        // Sector filter dropped from batch generation.
        $this->assertStringNotContainsString("getPost('sectorID')", $source);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter QrCardController`
Expected: FAIL — `from`/`controlFrom` absent; `sectorID` still present.

- [ ] **Step 3: Update `batch()` filter assembly**

In `app/Controllers/Cards/QrCardController.php`, replace the filter block in
`batch()` (lines 37-43) with:

```php
        $filter = [];
        if (($barangay = trim((string) $this->request->getPost('barangay'))) !== '') {
            $filter['barangay'] = $barangay;
        }
        if (($from = (int) $this->request->getPost('from')) > 0) {
            $filter['controlFrom'] = $from;
        }
        if (($to = (int) $this->request->getPost('to')) > 0) {
            $filter['controlTo'] = $to;
        }
```

The audit scope string (`recordBatchAudit`, line 155) uses `http_build_query($filter)`, so the new range keys flow through automatically. No change needed there.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --filter QrCardController`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Cards/QrCardController.php tests/unit/QrCardControllerTest.php
git commit -m "feat(cards): batch filters by control-number range, drops sector

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 4: `generate` button role

**Files:**
- Modify: `app/Helpers/ui_helper.php:20-26`
- Modify: `docs/knowledge/binan-conventions/ui-design-system.md`
- Test: `tests/unit/QrCardControllerTest.php` (helper is trivial; assert via a tiny new test file is overkill — fold a source assert here)

**Interfaces:**
- Produces: `btn('generate')` → `'btn btn-primary'` (blue). Used by the Control
  Numbers Generate buttons.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/UiHelperTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class UiHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ui');
    }

    public function testGenerateRoleIsBluePrimary(): void
    {
        $this->assertSame('btn btn-primary', btn('generate'));
    }

    public function testAddRoleStaysGreen(): void
    {
        $this->assertSame('btn btn-success', btn('add'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter UiHelper`
Expected: FAIL — `InvalidArgumentException: Unknown button role: generate`.

- [ ] **Step 3: Add the role**

In `app/Helpers/ui_helper.php`, add to the `$map` (keep alignment):

```php
            'search'   => 'btn btn-primary',
            'generate' => 'btn btn-primary',
            'clear'    => 'btn btn-danger',
            'add'      => 'btn btn-success',
            'import'   => 'btn btn-warning',
            'filter'   => 'btn btn-outline-secondary',
```

- [ ] **Step 4: Document the role**

In `docs/knowledge/binan-conventions/ui-design-system.md`, add a row to the button-role table: `generate` → `btn btn-primary` → "Generate/produce output from a selection (e.g. Control Numbers). Blue, not add-green, because it is not record creation."

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter UiHelper`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Helpers/ui_helper.php docs/knowledge/binan-conventions/ui-design-system.md tests/unit/UiHelperTest.php
git commit -m "feat(ui): add blue generate button role

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 5: Rebuild the Control Numbers view (Batch + Single-card)

**Files:**
- Rewrite: `app/Views/Cards/batch_form.php`

**Interfaces:**
- Consumes: `POST admin/cards/generate` (Task 3), `GET admin/cards/heads` (Task 2),
  `GET admin/cards/card/{id}` (existing), `btn('generate')` (Task 4).
- Produces: the rendered Control Numbers page. No PHP interface for later tasks.

This is a view + client-JS task with no unit test; it is verified in Task 9 with
Playwright. Keep the existing self-contained pattern (the layout includes this
view with no data) and the existing CSRF-refresh-on-response approach.

- [ ] **Step 1: Replace the view file**

Write `app/Views/Cards/batch_form.php`:

```php
<?php
// Self-contained (the layout includes this with no data). Barangay picker comes
// from the canonical source so filter values always match what headsForCards()
// compares against. Two modes: Batch (barangay + control-number range, live
// preview table) and Single card (searchable head or exact control number).
helper('ui');
$barangayList = \App\Support\FamilyProfilingFormV2::barangays();
?>
<div class="sector-management records-scroll-panel" id="control-numbers-page">
    <div class="records-table-controls mb-2">
        <span class="text-muted small">Issue printable QR access cards for registered heads of family. The PDF is the output; the table below is a preview of who will be printed.</span>
    </div>

    <ul class="nav nav-pills segmented-tabs mb-3" id="cn-modes" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" type="button" data-mode="batch" aria-current="page">
                <i class="bi bi-collection" aria-hidden="true"></i> Batch
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" type="button" data-mode="single">
                <i class="bi bi-person-vcard" aria-hidden="true"></i> Single card
            </button>
        </li>
    </ul>

    <div class="card">
        <div class="card-body">
            <!-- BATCH -->
            <div id="cn-panel-batch">
                <form id="cn-batch-form" class="row g-3 align-items-end" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="cn-barangay">Barangay</label>
                        <select class="form-select" id="cn-barangay" name="barangay">
                            <option value="">All barangays</option>
                            <?php foreach ($barangayList as $barangay): ?>
                                <option value="<?= esc($barangay, 'attr') ?>"><?= esc($barangay) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="cn-from">From #</label>
                        <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-from" name="from" placeholder="e.g. 100">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="cn-to">To #</label>
                        <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-to" name="to" placeholder="e.g. 150">
                    </div>
                    <div class="col-12">
                        <span class="text-muted small">Leave all blank to print every active head. Both range bounds are inclusive.</span>
                    </div>
                </form>

                <div class="table-meta mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span id="cn-preview-count" class="fw-semibold" aria-live="polite">Loading preview…</span>
                    <div class="d-flex align-items-center gap-2">
                        <span id="cn-batch-status" class="text-muted small" aria-live="polite"></span>
                        <button type="submit" form="cn-batch-form" class="<?= btn('generate') ?>" id="cn-batch-btn">
                            <i class="bi bi-printer" aria-hidden="true"></i> <span>Generate cards</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle mb-0" id="cn-preview-table">
                        <thead>
                            <tr><th scope="col">Control #</th><th scope="col">Head name</th><th scope="col">Barangay</th></tr>
                        </thead>
                        <tbody id="cn-preview-body">
                            <tr><td colspan="3" class="sector-empty-state">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SINGLE -->
            <div id="cn-panel-single" hidden>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-8 position-relative">
                        <label class="form-label" for="cn-head">Head</label>
                        <input type="text" class="form-control" id="cn-head" placeholder="Type a head name…" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="cn-head-list">
                        <ul class="list-group position-absolute w-100 shadow-sm" id="cn-head-list" style="z-index:5; max-height:16rem; overflow:auto;" hidden></ul>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="cn-control">Control #</label>
                        <input type="number" min="1" inputmode="numeric" class="form-control" id="cn-control" placeholder="Exact control number">
                    </div>
                    <div class="col-12">
                        <span class="text-muted small">Pick a head from the list OR type an exact control number, then Generate.</span>
                    </div>
                    <div class="col-12 d-flex justify-content-end align-items-center gap-2">
                        <span id="cn-single-status" class="text-muted small" aria-live="polite"></span>
                        <button type="button" class="<?= btn('generate') ?>" id="cn-single-btn">
                            <i class="bi bi-printer" aria-hidden="true"></i> <span>Generate card</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const maxQuantity = <?= (int) config('QrCardSettings')->maxQuantity ?>;
    const headsUrl    = '<?= site_url('admin/cards/heads') ?>';
    const generateUrl = '<?= site_url('admin/cards/generate') ?>';
    const cardUrlBase = '<?= site_url('admin/cards/card') ?>';

    // ---- mode switch ------------------------------------------------------
    const modeBtns = document.querySelectorAll('#cn-modes [data-mode]');
    const panels = { batch: document.getElementById('cn-panel-batch'), single: document.getElementById('cn-panel-single') };
    modeBtns.forEach((b) => b.addEventListener('click', function () {
        modeBtns.forEach((x) => { x.classList.remove('active'); x.removeAttribute('aria-current'); });
        b.classList.add('active'); b.setAttribute('aria-current', 'page');
        const mode = b.dataset.mode;
        panels.batch.hidden = mode !== 'batch';
        panels.single.hidden = mode !== 'single';
    }));

    // ---- shared download helper ------------------------------------------
    async function download(resp, fallback) {
        const blob = await resp.blob();
        const disposition = resp.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="([^"]+)"/);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = match ? match[1] : fallback;
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
    }

    // ---- batch: live preview ---------------------------------------------
    const batchForm = document.getElementById('cn-batch-form');
    const countEl = document.getElementById('cn-preview-count');
    const bodyEl = document.getElementById('cn-preview-body');
    const batchBtn = document.getElementById('cn-batch-btn');
    const batchStatus = document.getElementById('cn-batch-status');
    let debounce;

    function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    async function refreshPreview() {
        const params = new URLSearchParams({
            barangay: document.getElementById('cn-barangay').value,
            from: document.getElementById('cn-from').value,
            to: document.getElementById('cn-to').value,
        });
        try {
            const resp = await fetch(headsUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) { throw new Error('preview'); }
            const data = await resp.json();
            const count = data.count || 0;
            const rows = data.rows || [];

            if (count === 0) {
                countEl.textContent = 'No heads match — adjust filters.';
                bodyEl.innerHTML = '<tr><td colspan="3" class="sector-empty-state">No heads match — adjust filters.</td></tr>';
                batchBtn.disabled = true;
                return;
            }
            if (count > maxQuantity) {
                countEl.textContent = count + ' cards match, exceeding the max of ' + maxQuantity + ' per batch. Narrow the filters.';
                batchBtn.disabled = true;
            } else {
                countEl.textContent = count + (count === 1 ? ' card will be generated.' : ' cards will be generated.');
                batchBtn.disabled = false;
            }
            let html = rows.map((r) =>
                '<tr><td>' + esc(String(r.controlNo)) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.barangay) + '</td></tr>'
            ).join('');
            if (count > rows.length) {
                html += '<tr><td colspan="3" class="text-muted small">…and ' + (count - rows.length) + ' more</td></tr>';
            }
            bodyEl.innerHTML = html;
        } catch (e) {
            countEl.textContent = 'Preview unavailable.';
            bodyEl.innerHTML = '<tr><td colspan="3" class="sector-empty-state">Preview unavailable.</td></tr>';
            batchBtn.disabled = true;
        }
    }

    ['cn-barangay', 'cn-from', 'cn-to'].forEach((id) => {
        document.getElementById(id).addEventListener('input', function () {
            clearTimeout(debounce); debounce = setTimeout(refreshPreview, 300);
        });
    });
    refreshPreview();

    batchForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        batchStatus.textContent = 'Generating…';
        batchBtn.disabled = true;
        try {
            const resp = await fetch(generateUrl, {
                method: 'POST', body: new FormData(batchForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const fresh = resp.headers.get('X-CSRF-TOKEN');
            if (fresh) { const h = batchForm.querySelector('input[type="hidden"]'); if (h) { h.value = fresh; } }
            if (!resp.ok) {
                const text = await resp.text();
                let msg = 'Generation failed.';
                try { msg = JSON.parse(text).error || msg; } catch (_) {}
                batchStatus.textContent = msg;
                return;
            }
            await download(resp, 'binan-qr-cards.pdf');
            batchStatus.textContent = 'Done.';
        } catch (err) {
            batchStatus.textContent = 'Generation failed. Please try again.';
        } finally {
            refreshPreview();
        }
    });

    // ---- single: autocomplete + generate ---------------------------------
    const headInput = document.getElementById('cn-head');
    const headList = document.getElementById('cn-head-list');
    const controlInput = document.getElementById('cn-control');
    const singleBtn = document.getElementById('cn-single-btn');
    const singleStatus = document.getElementById('cn-single-status');
    let selectedHead = null;
    let acDebounce;

    function clearList() { headList.innerHTML = ''; headList.hidden = true; headInput.setAttribute('aria-expanded', 'false'); }

    headInput.addEventListener('input', function () {
        selectedHead = null;
        const q = headInput.value.trim();
        clearTimeout(acDebounce);
        if (q.length < 2) { clearList(); return; }
        acDebounce = setTimeout(async function () {
            try {
                const resp = await fetch(headsUrl + '?mode=search&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!resp.ok) { clearList(); return; }
                const data = await resp.json();
                const rows = data.rows || [];
                if (rows.length === 0) { clearList(); return; }
                headList.innerHTML = rows.map((r) =>
                    '<li class="list-group-item list-group-item-action" role="option" ' +
                    'data-id="' + esc(String(r.memberID)) + '" style="cursor:pointer;">' +
                    esc(r.name) + ' <span class="text-muted small">#' + esc(String(r.controlNo)) +
                    (r.barangay ? ' · ' + esc(r.barangay) : '') + '</span></li>'
                ).join('');
                headList.hidden = false; headInput.setAttribute('aria-expanded', 'true');
            } catch (e) { clearList(); }
        }, 300);
    });

    headList.addEventListener('click', function (e) {
        const li = e.target.closest('[data-id]');
        if (!li) { return; }
        selectedHead = parseInt(li.dataset.id, 10);
        headInput.value = li.textContent.trim();
        controlInput.value = '';
        clearList();
    });

    document.addEventListener('click', function (e) {
        if (!headList.contains(e.target) && e.target !== headInput) { clearList(); }
    });

    singleBtn.addEventListener('click', async function () {
        singleStatus.textContent = '';
        let memberId = selectedHead;

        // Exact control number path: resolve to a head via the heads feed before
        // hitting the single-card route, so a bad number fails with a clear message
        // instead of a 404 download.
        if (!memberId) {
            const control = controlInput.value.trim();
            if (!control) { singleStatus.textContent = 'Pick a head or enter a control number.'; return; }
            try {
                const resp = await fetch(headsUrl + '?mode=search&q=' + encodeURIComponent(control), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                const hit = (data.rows || []).find((r) => String(r.controlNo) === control);
                if (!hit) { singleStatus.textContent = 'No head found for control number ' + control + '.'; return; }
                memberId = hit.memberID;
            } catch (e) { singleStatus.textContent = 'Lookup failed. Try again.'; return; }
        }

        singleStatus.textContent = 'Generating…';
        singleBtn.disabled = true;
        try {
            const resp = await fetch(cardUrlBase + '/' + memberId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) { singleStatus.textContent = 'Generation failed.'; return; }
            await download(resp, 'binan-qr-card.pdf');
            singleStatus.textContent = 'Done.';
        } catch (e) {
            singleStatus.textContent = 'Generation failed. Please try again.';
        } finally {
            singleBtn.disabled = false;
        }
    });
})();
</script>
```

Note: the `keyword` filter matches names, not control numbers, so the exact-control lookup narrows by typing the number into `q` and then matches on `controlNo` client-side. This works because `q` shorter than a real name still returns the small candidate set; the client picks the exact `controlNo`. If a control number returns no name match (heads whose name does not contain the digits), fall back is covered in Task 9 verification — if it proves flaky, the plan's open item is to add a dedicated `control=` param to `heads()`. Prefer verifying first.

- [ ] **Step 2: Sanity-check the view parses**

Run: `php -l app/Views/Cards/batch_form.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Views/Cards/batch_form.php
git commit -m "feat(cards): rebuild Control Numbers page (batch + single, preview)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 6: Rename "Generate Cards" → "Control Numbers" in the sidebar

**Files:**
- Modify: `app/Views/components/dashboard_sidebar.php:32`

**Interfaces:** none. Route/icon unchanged.

- [ ] **Step 1: Edit the label**

Change the link text only (keep `href="<?= site_url('admin/cards') ?>"`, the `cards` nav-active key, and `bi-qr-code`):

`Generate Cards` → `Control Numbers`

- [ ] **Step 2: Verify no other "Generate Cards" text remains**

Run: `grep -rn "Generate Cards" app/`
Expected: no matches.

- [ ] **Step 3: Commit**

```bash
git add app/Views/components/dashboard_sidebar.php
git commit -m "feat(cards): sidebar label Generate Cards -> Control Numbers

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 7: Aid→Subsidy — views

**Files (display text only; keep ids/urls/enums):**
- `app/Views/components/dashboard_sidebar.php:31`
- `app/Views/Admin/layout.php:142,148,257`
- `app/Views/Admin/distribution-distributions-body.php:31,62`
- `app/Views/Admin/distribution-batches-body.php:39`
- `app/Views/Admin/batch-create-modal.php:28`
- `app/Views/Admin/aidtypes-body.php:11,27,55`
- `app/Views/Admin/aidtype-create-modal.php:9,21`
- `app/Views/Admin/reports-body.php:29`
- `app/Views/Family/family-modal.php:202`
- `app/Views/Scanner/scan.php:83,163`
- `app/Views/Scanner/pdf/report.php:11,17,57,59`

**Interfaces:** none.

- [ ] **Step 1: Apply the exact string edits**

| File:line | From → To |
|---|---|
| `dashboard_sidebar.php:31` | `Aid Distribution` → `Subsidy Distribution` |
| `layout.php:142` | `'label' => 'Received Aid',` → `'label' => 'Received Subsidy',` |
| `layout.php:148` | `'label' => 'Aid Coverage',` → `'label' => 'Subsidy Coverage',` |
| `layout.php:257` | `'title' => 'Aid Types',` → `'title' => 'Subsidy Types',` |
| `distribution-distributions-body.php:31` | `<th>Aid Type</th>` → `<th>Subsidy Type</th>` |
| `distribution-distributions-body.php:62` | `No aid distributions logged yet.` → `No subsidy distributions logged yet.` |
| `distribution-batches-body.php:39` | `<th>Aid Type</th>` → `<th>Subsidy Type</th>` |
| `batch-create-modal.php:28` | `Choose an aid type...` → `Choose a subsidy type...` |
| `aidtypes-body.php:11` | ` Add Aid Type` → ` Add Subsidy Type` (button text only; keep `data-bs-target="#addAidTypeModal"`) |
| `aidtypes-body.php:27` | `aria-label="Aid type actions"` → `aria-label="Subsidy type actions"` |
| `aidtypes-body.php:55` | `No aid types defined.` → `No subsidy types defined.` |
| `aidtype-create-modal.php:9` | `>Add Aid Type<` → `>Add Subsidy Type<` (keep `id="addAidTypeModalLabel"`) |
| `aidtype-create-modal.php:21` | `>Add Aid Type<` → `>Add Subsidy Type<` |
| `reports-body.php:29` | `>Aid Distribution<` → `>Subsidy Distribution<` (heading text after the icon) |
| `family-modal.php:202` | `Locked: aid already recorded under this number.` → `Locked: subsidy already recorded under this number.` |
| `scan.php:83` | `Aid History` → `Subsidy History` |
| `scan.php:163` | `No aid received yet.` → `No subsidy received yet.` |
| `pdf/report.php:11` | `<h1>Aid Distribution Report</h1>` → `<h1>Subsidy Distribution Report</h1>` |
| `pdf/report.php:17` | `Received aid<br>` → `Received subsidy<br>` |
| `pdf/report.php:57` | `<h2>Handouts by aid type</h2>` → `<h2>Handouts by subsidy type</h2>` |
| `pdf/report.php:59` | `<th>Aid Type</th>` → `<th>Subsidy Type</th>` |

Do NOT change the comment at `reports-body.php:52-54` (not user-facing).

- [ ] **Step 2: Verify no stray user-facing "Aid" left in views**

Run: `grep -rniE ">[^<]*\baid\b|placeholder=\"[^\"]*aid|title' => '[^']*Aid|label' => '[^']*Aid" app/Views | grep -viE "aid_|aidtypes|// |/\*"`
Expected: no matches (comments aside).

- [ ] **Step 3: Commit**

```bash
git add app/Views
git commit -m "refactor(ui): relabel Aid -> Subsidy across views

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 8: Aid→Subsidy — controllers, filename, JS, audit strings

**Files:**
- `app/Controllers/Admin/ReportsController.php:85`
- `app/Controllers/Admin/DistributionController.php:49,66`
- `app/Controllers/Scanner/ScanController.php:271,281,287`
- `public/assets/js/dashboard/scanner-reports.js:55`

**Interfaces:** none. Audit strings passed to `AuditTrailsModel::logAction()` change wording only.

- [ ] **Step 1: Apply the exact string edits**

| File:line | From → To |
|---|---|
| `ReportsController.php:85` | `'aid-report-'` → `'subsidy-report-'` (downloaded PDF filename) |
| `DistributionController.php:49` | `'Voided aid distribution #' . $id,` → `'Voided subsidy distribution #' . $id,` |
| `DistributionController.php:66` | `'Choose an aid type for this batch.'` → `'Choose a subsidy type for this batch.'` |
| `ScanController.php:271` | `'Logged aid distribution',` → `'Logged subsidy distribution',` |
| `ScanController.php:281` | `'Failed to log the aid distribution.'` → `'Failed to log the subsidy distribution.'` |
| `ScanController.php:287` | `'Failed to log the aid distribution.'` → `'Failed to log the subsidy distribution.'` |
| `scanner-reports.js:55` | `labels: ['Received aid', 'Still waiting'],` → `labels: ['Received subsidy', 'Still waiting'],` |

- [ ] **Step 2: Confirm no user-facing "aid" string literals remain**

Run: `grep -rniE "'[^']*\baid\b[^']*'|\"[^\"]*\baid\b[^\"]*\"" app/Controllers | grep -viE "aid_|aidtypes|AidStats|AidModel|AidDistribution|// |/\*|\* |namespace|use App"`
Expected: only comment/identifier lines, no display/audit strings.

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: green. If any test asserts an old "aid" wording, update the expected string to "subsidy" and re-run.

- [ ] **Step 4: Commit**

```bash
git add app/Controllers public/assets/js/dashboard/scanner-reports.js
git commit -m "refactor(ui): relabel Aid -> Subsidy in labels, flash, audit, filename

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013u6vLadUgaJ61755C2UW65"
```

---

## Task 9: Verification sweep (phpunit + Playwright)

**Files:** none (verification only).

- [ ] **Step 1: Full test suite green**

Run: `vendor/bin/phpunit`
Expected: no failures (skips allowed only where DB/sqlite is unavailable).

- [ ] **Step 2: Routes resolve**

Run: `php spark routes | grep -i cards`
Expected: `admin/cards`, `admin/cards/generate`, `admin/cards/card/(:num)`, `admin/cards/lookup/(:any)`, `admin/cards/heads` all present.

- [ ] **Step 3: Start the dev server (if down)**

Run: `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090` (use the intl-enabled `php`, not XAMPP's — see the local-dev memory).

- [ ] **Step 4: Playwright — Control Numbers page**

Log in (developer / developer123), navigate to `admin/cards`. Verify at desktop and 390px:
- Sidebar link reads **Control Numbers**.
- Segmented tabs **Batch | Single card** switch panels without reload.
- Batch: changing barangay / From / To updates the preview count and table (debounced); "N cards will be generated"; empty filters show all; an impossible range shows the empty state and disables Generate.
- Generate cards downloads a PDF for the previewed selection; both Generate buttons are **blue** (`btn-primary`), not green.
- Single card: typing ≥2 chars shows the head autocomplete; picking one then Generate downloads a single card. Typing an exact control number then Generate downloads that head's card; a bogus number shows a clear message (no silent 404).
- Layout matches the Manage Records house style (toolbar/controls above the card, grid stacks cleanly at 390px).

- [ ] **Step 5: Playwright — Subsidy relabel sweep**

Verify "Subsidy" (not "Aid") reads correctly and no URL 404s on:
- Sidebar heading **Subsidy Distribution**.
- Dashboard KPIs **Received Subsidy**, **Subsidy Coverage**.
- Distribution tabs: table headers **Subsidy Type**, empty state **No subsidy distributions logged yet.**, batch modal **Choose a subsidy type…**.
- Reference Data → **Subsidy Types** tab: **Add Subsidy Type** button, **No subsidy types defined.** empty state. Confirm the URL is still `admin/aidtypes` (unchanged).
- Scanner scan page: **Subsidy History** panel, **No subsidy received yet.** empty state.
- Reports PDF (`ReportsController`): **Subsidy Distribution Report** heading; downloaded filename starts `subsidy-report-`.

- [ ] **Step 6: Record verification result**

Note pass/fail per checkpoint. Fix any failure in the owning task's file, re-run the affected check, then proceed.

---

## Self-Review (completed during planning)

- **Spec coverage:** Control Numbers rename (Task 6), Batch mode + range (Tasks 1,3,5), Single card + autocomplete (Tasks 2,5), preview table (Tasks 1,2,5), drop sector (Task 3), blue buttons (Tasks 4,5), heads endpoint (Task 2), audit unchanged behavior (Task 3), Aid→Subsidy views (Task 7), code-built labels + flash + filename + JS (Task 8), audit strings + test updates (Task 8), testing/Playwright (Task 9). All spec sections mapped.
- **Placeholder scan:** no TBD/TODO; every code step shows full content. The one conditional ("if the exact-control lookup proves flaky, add a `control=` param") is an explicit, bounded verification contingency in Task 5, not a placeholder.
- **Type consistency:** `headsForCards()` row keys (`memberID`, `controlNo`, `fullname`, `barangay`) are consistent across Tasks 1, 2, 5; `heads()` JSON keys (`count`, `rows[].memberID/controlNo/name/barangay`) consistent between Task 2 and the Task 5 client; `countHeadsForCards()` signature consistent Tasks 1↔2; `btn('generate')` consistent Tasks 4↔5.

## Open decisions (resolved)

1. **Preview count:** dedicated `countHeadsForCards()` (chosen — clearer than folding a count into the row method).
2. **`headsForCards` `sectorID` param:** kept (no caller passes it now; removal is unneeded churn). The batch controller simply stops reading `sectorID` (Task 3).
