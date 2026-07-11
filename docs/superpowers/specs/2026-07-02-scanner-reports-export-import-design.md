# Scanner Reports/Statistics + PDF Export + Excel Import — Design

**Branch:** `feat/qr-access-cards`
**Date:** 2026-07-02
**Predecessor specs:**
- `docs/superpowers/specs/2026-07-01-scanner-module-design.md`
- `docs/superpowers/specs/2026-07-01-scanner-management-design.md`

Both predecessor summaries list **"Aid distribution Reports (charts, %, barangay
selectors)"** and **"Excel bulk migration"** as the explicit next deferral. This
spec picks those up.

## Goal

Add a third surface to the QR Code (Scanner) module — a read-only
**Reports/Statistics** tab that shows, in plain non-technical language, how aid
distribution is going: how many families received aid vs still waiting, coverage
by barangay, and handouts by aid type, all scoped to a from–to date range. From
that tab the user can **download a one-page PDF summary report**. Separately,
**merge the Excel family-import feature** from `Mel-import-branch` into this
branch and repair any breakage.

Non-negotiables carried in: no migrations (schema = `accesscardV13.sql`), every
family mutation audited, controllers decide / libraries build, PHP 8.2+.

## Scope

Three parts, sequenced:

- **§A Reports tab + charts** (chart.js)
- **§B PDF export** (dompdf, already installed)
- **§C Excel import merge** (full merge of `Mel-import-branch`, then fix breakage)

Build order: **§A → §B first (one commit set), §C second.** §C is a git merge
whose fallout is isolated from the chart work by doing it last.

---

## UI style contract (READ FIRST — do not invent components)

The previous session's recurring failure was inventing SB-Admin-Pro components
(e.g. `border-left-*` KPI cards) that **do not exist anywhere in this system**.
This tab MUST reuse the existing house style, verbatim:

| Need | Use this existing pattern | Seen in |
|------|---------------------------|---------|
| KPI / count tile | `<article class="stat-card"><p>Label</p><strong>value</strong></article>` | `Admin/layout.php:128`, `Employee/layout.php`, `Viewer/layout.php` |
| Tab bar | `<ul class="nav nav-tabs manage-tabs">` | `Scanner/manage.php` |
| Panel / pane wrapper | `<div class="sector-management records-scroll-panel">` | `Scanner/manage.php:20` |
| Search band | `.records-search-panel > .records-search-row.records-lookup-search` | `Scanner/manage.php:22` |
| Toolbar (filters/clear) | `.table-meta > .records-table-controls` | `Scanner/manage.php:35` |
| Badge | `<span class="badge bg-light text-dark border">` | `Scanner/manage.php:72` |
| Status badge | `.sector-status-badge .sector-status-active|archived` | `Scanner/manage.php:125` |
| Primary action button | `.btn .btn-primary .records-search-action` + `<i class="bi bi-…">` | `Scanner/manage.php:111` |
| Icons | **Bootstrap Icons** (`bi-*`) only — project ships **no Font Awesome** | scanner-module summary "Deviations" |

Rules:
- **No SB-Admin-Pro `border-left-*`, `card h-100`, `text-xs text-uppercase`
  patterns.** They live only in the unused demo `public/index.html`, not in any
  real view.
- New CSS goes in a small dedicated file (`public/css/scanner-reports.css`) and
  reuses existing custom properties/spacing; it does not restyle shared classes.
- Assets load through the **manifest** (`app/Helpers/asset_helper.php`) — no
  hardcoded `<script>`/`<link>` in the layout (scanner-module summary,
  "Asset loading centralized").

---

## §A — Reports tab + charts

### A1. Route + sidebar

- Route: `GET scanner/reports` → `Scanner\ReportsController::index`.
- Route: `GET scanner/reports/pdf` → `Scanner\ReportsController::pdf` (see §B).
- Sidebar: add a **Reports** link under the existing "QR Code" heading in
  `app/Views/components/dashboard_sidebar.php` (scanner-only branch), next to
  Scan and Manage. Icon `bi-bar-chart-line`. `activeTab === 'reports'` active
  state; `navActive['scanner-reports']`.

### A2. Controller

`Scanner\ReportsController::index` — mirrors `ManageController::index`:
- Guard `RoleAccess::requireRole(['Scanner','Admin','Developer'])` (inline
  literal, test-pinned, matching module convention).
- Read `from`/`to` from query (`?from=YYYY-MM-DD&to=YYYY-MM-DD`). Validate/normalize:
  both optional; if only one given, treat the open end as unbounded; if `from > to`,
  swap. Invalid dates → ignored (falls back to all-time).
- Call the new `AidStatsModel` three times, pass results **both** as PHP (for the
  `.stat-card` tiles + no-JS fallback tables) **and** as `json_encode`'d datasets
  (for chart.js) into `view('Scanner/reports', …)`.
- Same sidebar/role view-vars block as `ManageController::index`
  (`currentRole`, `canManageAccounts`, `sidebarRoleClass`, `navActive`, etc.).

Controller decides + routes; **all querying lives in the model** (per
"controllers decide, libraries build").

### A3. Model — `app/Models/Scanner/AidStatsModel.php` (new)

`returnType array`, `useTimestamps false`, no writes. Every method takes
`?string $from = null, ?string $to = null` and applies the range to
`aid_distribution.claim_date`. All wrapped in try/catch → safe empty shape, to
match the suite's no-DB posture (`allDistributions()` precedent).

- `receivedVsNot($from,$to): array`
  → `['total' => N, 'received' => R, 'notReceived' => N-R, 'coverage' => pct]`.
  `total` = distinct `qr_control.headID` count (families issued a QR).
  `received` = distinct `qr_control.headID` that have ≥1 `aid_distribution` row
  in range (join `aid_distribution.control_no = qr_control.control_no`).
  "Received" is defined at the **family (head) level** — any member scan under
  the family's `control_no` counts for the family.
- `byBarangay($from,$to): array` — list of
  `['barangay' => str, 'total' => N, 'received' => R, 'coverage' => pct]`,
  ordered by barangay. Families grouped by `member(head).barangay`
  (`qr_control.headID → member.memberID → member.barangay`); blank barangay
  bucketed as `"Unspecified"`.
- `byAidType($from,$to): array` — list of
  `['aid_type' => name, 'count' => N]` over `aid_type` joined to in-range
  `aid_distribution`, active + archived types that have any handout, ordered by
  count desc.

### A4. View — `app/Views/Scanner/reports.php` (new)

Extends `Scanner/layout`, `activeTab => 'reports'`. Compact dashboard layout:

1. **Date-range band** — top, inside a `.records-search-panel` band: two
   `<input type="date">` (From / To), an **Apply** button (`.records-search-action`),
   and a **Clear** button (`bi-x-lg`). Plain GET form to `scanner/reports`
   (server re-queries; no AJAX). Shows the active window as text
   ("Showing Jan 1 to Jan 31" / "Showing all dates").
2. **KPI row** — four `.stat-card` tiles: **Families with a QR**,
   **Received aid**, **Still waiting**, **Coverage** (percent). Plain literal
   labels, no jargon, no em dashes.
3. **Charts grid** — compact, responsive Bootstrap `.row`/`.col` grid of chart
   cards, each a `.sector-management` panel with a short `<h?>`/caption:
   - **Doughnut** — "Families that received aid vs still waiting."
   - **Horizontal bar** — "Coverage by barangay (percent)."
   - **Bar** — "Number of handouts by aid type."
   Each `<canvas>` carries its dataset via a `data-…` JSON attribute or an inline
   `<script type="application/json">` block that `scanner-reports.js` reads.
4. **No-JS / print fallback** — under each canvas (or in a `<noscript>`-adjacent
   collapsible), a small `.manage-record-table` summary table with the same
   numbers, so the page is meaningful without JS and reusable by the PDF (§B).

All server data through PHP `esc()`. No inline event handlers.

### A5. chart.js — vendored, manifest-loaded

- Download chart.js UMD build to
  `public/vendor/html5-qrcode/`-style location: `public/vendor/chart.js/chart.umd.min.js`
  (real file; the only ref today is the demo `index.html` CDN line, unused).
  Commit with `git add -f` (the bare `vendor/` `.gitignore` rule swallows
  `public/vendor/`, same as the html5-qrcode precedent).
- New JS: `public/assets/js/dashboard/scanner-reports.js` — reads the datasets
  from the DOM and builds the three charts. Guards on `typeof Chart` and on the
  canvas existing (no-ops on Scan/Manage pages).
- **Manifest**: add a `scanner` context to `asset_styles` (→ `css/scanner-reports.css`)
  and `asset_scripts` (→ `vendor/chart.js/chart.umd.min.js` then
  `assets/js/dashboard/scanner-reports.js`, order significant). Update
  `Scanner/layout.php` to merge `asset_styles('scanner')` / `asset_scripts('scanner')`
  alongside the existing `admin` context. chart.js loading on Scan/Manage too is
  acceptable (small, guarded no-op).
- New CSS: `public/css/scanner-reports.css` — only chart-card sizing / grid gaps,
  reusing existing spacing tokens. Does not touch shared classes.

---

## §B — PDF export

- Button on the Reports tab: **"Download PDF report"** (`.btn` +
  `bi-file-earmark-pdf`), a link to `scanner/reports/pdf?from=&to=` carrying the
  same date window as the screen.
- `ReportsController::pdf` — same guard + date handling as `index`; builds the
  same three `AidStatsModel` datasets; hands them to a new library.
- Library `app/Libraries/Scanner/ReportsPdfGenerator.php` — mirrors
  `app/Libraries/Qr/QrCardPdfGenerator.php` (Dompdf setup + `loadHtml` +
  `render` + stream). Renders a **new print view**
  `app/Views/Scanner/pdf/report.php`.
- `Scanner/pdf/report.php` — header (title, "City of Biñan CSWD", date window,
  generated timestamp), the four KPI numbers, and the three **summary tables**
  (received/waiting, by-barangay coverage, by-aid-type). **No chart.js** — dompdf
  runs server-side with no JS; visualize with simple CSS bar rows (a filled div
  proportional to percent) reusing the same numbers. Styles inline / in
  `Scanner/pdf/_styles.php` sibling, matching the `Cards/pdf/_styles.php` pattern.
- Streams `application/pdf` as a download named
  `aid-report-<from>_<to>.pdf`. No audit row (read-only export).

---

## §C — Excel import merge (full merge + fix)

### Situation (verified)

`origin/Mel-import-branch` shares this branch's history: merge base is
`d4f0c5b`, and Mel is only **4 commits** ahead. Overlap with this branch = **3
files** (`app/Config/Routes.php`, `composer.json`, `composer.lock`). The 4
commits bundle two concerns:

- **Import** (`d983ed3`, `ce7449b`, `335285e`): `FamilyExcelImporter`,
  `FamilyExcelTemplate`, `FamilyRecordWriter` (+ `FamilyRecordWriteException`),
  `MemberFieldNormalizer`, `Jobs/*` + `Models/Jobs/JobQueueModel` + `sql/job_queue.sql`
  + PowerShell worker scripts (`scripts/*.ps1`), `Views/Family/import-modal.php`,
  `public/assets/js/dashboard/family-import.js`, `phpoffice/phpspreadsheet` dep.
  Import runs **async via a job queue** drained by a worker.
- **Lookups refactor** (`a2f44fb`): realigns sectors/services/categories to the
  CSWD form — rides along, not import-related; touches Lookups models/views.

### Plan

1. `git merge origin/Mel-import-branch` (full merge, no squash — preserves the
   4-commit history). Resolve the 3 conflicts by hand:
   - `Routes.php` — union both route groups (scanner + families import).
   - `composer.json` — union deps (dompdf/qrcode + phpspreadsheet).
   - `composer.lock` — regenerate via `composer update --lock` after json merge.
2. `composer install` — pull phpspreadsheet.
3. **Fix breakage** (this is the "fix if something breaks" part):
   - `php spark routes` — every route resolves.
   - `vendor/bin/phpunit` — full suite green (baseline pre-merge: 55 tests,
     4 skipped). Repair whatever the lookups refactor / schema drift breaks in
     `MemberModel` / Lookups models / their tests.
   - Verify Lookups pages (sectors/services/categories) still render and CRUD.
   - Confirm imported rows still write `audit_trails` (non-negotiable) — if the
     importer bypasses the audit path, wire it through `FamilyRecordWriter` →
     the same audit call `FamilyController` uses.
4. **Async worker fallback for Mac dev.** The bundled worker is a PowerShell
   script (Windows). On this macOS dev box, provide a synchronous path or a
   PHP/spark drain command so an import can complete without PowerShell. Decision:
   add a `spark` command (or a "run now" synchronous mode in the importer) that
   drains `job_queue` in-process; document it. Do **not** add a DB migration —
   `sql/job_queue.sql` is applied the same way as the main dump.
5. **Dry-run** `family-import-template_filled_500.xlsx` end to end (upload →
   queue → drain → 500 member rows present, audited). Note the known dump-v13 5x
   duplication caveat when checking counts.

### Merge risks / notes

- `job_queue` table is new schema delivered as `sql/job_queue.sql` (not a
  migration) — must be loaded into the dev DB before import works.
- The lookups refactor may conflict semantically with any lookups work already
  on this branch; if the branch's Lookups tests fail, the refactor's version
  generally wins (it's the newer CSWD-form alignment) — verify against the SQL
  dump enum/column names.
- `.xlsx`/backup artifacts already sitting untracked in the working tree
  (`family-import-template_filled_500.xlsx`, `~$…xlsx`, `writable/backups/*`) are
  **not** part of this spec — leave them; do not commit stray binaries.

---

## Testing

Run `vendor/bin/phpunit` before and after each part. New tests, matching the
suite's **no-DB contract/route-resolution posture** (no live-DB round-trips):

- `AidStatsModelTest` — method contracts + return-shape keys for the three stat
  methods (safe-empty on no DB).
- `ReportsControllerTest` — route guard literal, `index`/`pdf` exist, pass the
  expected view-vars; date-range normalization (swap, one-sided, invalid).
- `ReportsViewTest` — grep the view for the mandated house classes
  (`stat-card`, `sector-management`, `records-search-panel`, `nav-tabs`,
  `bi-` icons) and assert **absence** of `border-left-` (guards against the
  invent-a-component regression).
- Manifest test — `scanner` context returns chart.js before `scanner-reports.js`.
- §C: keep the existing suite green; add an importer row-mapping unit test if the
  merged code ships without one.

Smoke flows: login as Scanner → Reports renders with charts → change date range →
numbers update → download PDF opens. Admin import modal → dry-run 500 rows.

## Deferred (out of scope)

- Time-series / "over time" chart (not requested).
- Excel **data** export (this spec's export is PDF only).
- Excel → `qr_control` bulk-mapping tooling (separate from family import).
- Re-enabling the app-wide CSRF filter (standing maintainer decision).
