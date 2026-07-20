# Admin Workspace Reorganization — Design

**Date:** 2026-07-20
**Scope:** Admin workspace (full reorg) + minimal ripple into Employee/Viewer (dead-alias removal, Viewer reference-data merge). Scanner kiosk untouched.

## Problem

The admin sidebar has 11 links under 5 headings. Four of them are near-identical
lookup CRUD pages (Sectors, Services, Categories, Aid Types). Batches and
Distributions split one workflow across two pages. Reports duplicates the
Dashboard's "stats landing page" role. The routes file carries dead aliases
(`manage-families`, `manage-members`, `family-entry`) that all resolve to the
same Manage Records page. Several pages show content nobody acts on.

## Goals

- Sidebar an official can scan in one glance: one link per module.
- Every page shows only what its user acts on. No vanity numbers, no
  duplicated content.
- Bootstrap 5 components only (valid Bootstrap markup, SB Admin 1 theme,
  design system from Manage Records / PR #23).
- No schema changes, no new mutation endpoints, audit-trail behavior unchanged.

## New sidebar (7 links, 4 headings; was 11 links, 5 headings)

| Heading | Link | Route |
|---|---|---|
| Core | Dashboard | `admin/dashboard` |
| Records | Manage Records | `admin/manage-records` |
| | Reference Data | `admin/reference-data` |
| Aid Distribution | Generate Cards | `admin/cards` |
| | Distribution | `admin/distribution` |
| Administration | Account Management | `admin/accounts` |
| | Audit Trails | `admin/audit-trails` |

## 1. Dashboard absorbs Reports

`admin/dashboard` becomes the single stats/analytics landing page. The
`admin/reports` GET page dies; `ReportsController::stats` and `::pdf` remain as
AJAX/PDF endpoints under `admin/reports/*`.

**Content (curated — see Content Cuts):**

1. Stat tile row (4 tiles): Total Records, Registered Members,
   Received aid ("1,204 of 3,410" — QR-holder total folded into the subtitle),
   Coverage %.
2. Distribution analytics section: batch selector + Refresh + Download PDF
   toolbar (from `reports-body.php`), coverage-by-barangay bar chart,
   handouts-by-aid-type small table, per-kiosk performance table.
3. Recent Records table (5 rows, each row links to the family view).

**Cut from the merged page:**

- "Active Sectors" and "Services and Programs" stat cards — counts of static
  reference rows; nobody acts on them.
- Recent Activity table — duplicates the Audit Trails page one click away.
- Received-vs-waiting pie chart — repeats the KPI tiles as a two-value pie.
- Handouts-by-aid-type bar chart — replaced by a small table (few aid types;
  a table reads faster).
- On-screen coverage-by-barangay fallback table — kept for print/no-JS only
  (`d-none d-print-table` on screen).

**Behavior:** the 5-second live poll runs only while the selected batch is
open; closed batches are static data. `buildReportsData()` runs when
`activePage === 'dashboard'`.

## 2. Reference Data page (merges 4 pages)

New GET `admin/reference-data` renders Bootstrap `nav-tabs`:
Sectors | Services | Categories | Aid Types. Tab panes reuse the existing
body fragments (`Lookups/sectors-body.php`, `services-body.php`,
`categories-body.php`, `Admin/aidtypes-body.php`).

- Active tab selected via `?tab=` query param (default `sectors`);
  deep-linkable and back-button safe.
- Server-side pagination links inside each tab carry the `tab` param.
- All existing POST endpoints (`admin/sectors/*`, `admin/services/*`,
  `admin/categories/*`, `admin/aidtypes/*`) unchanged; their redirects point
  back to `admin/reference-data?tab=<x>`.
- Old GET pages `admin/sectors`, `admin/services`, `admin/categories`,
  `admin/aidtypes` are removed.

## 3. Distribution page (merges Batches + Distributions)

New GET `admin/distribution` with tabs: Batches | Distribution Log. Panes
reuse `Admin/distribution-batches-body.php` and
`Admin/distribution-distributions-body.php`.

- POST endpoints (`admin/batches/open`, `admin/batches/close/(:num)`,
  `admin/distributions/void/(:num)`) unchanged; redirects return to the
  right tab.
- Old GET routes `admin/batches` and `admin/distributions` are removed.

## 4. Account Management: one table — already implemented

Verified during planning: `Admin/accounts.php` already merges the four role
arrays into a single table with client-side role/status filter pills
(`accounts.php:12`, toolbar at `accounts.php:51`). No change needed. The
builder keeps exposing the four arrays (`adminAccounts`, `employeeAccounts`,
`viewerAccounts`, `scannerAccounts`) as the view's input contract.

## 5. Route cleanup

Delete (no redirects — internal tool, canonical URLs only):

- `admin/manage-families`, `admin/manage-family` (bare GET),
  `admin/family-entry`, `admin/manage-members`
- `admin/sectors|services|categories|aidtypes` GET pages (POST groups stay)
- `admin/batches`, `admin/distributions`, `admin/reports` GET pages
  (`admin/reports/stats`, `admin/reports/pdf` stay)
- `employee/family-entry`, `employee/manage-families`
- `viewer/manage-families`

The `manage-family/*` action group (list/data/template/import/create/view/
edit/update/archive/restore) stays in full for admin and employee.

## 6. Viewer ripple (minimal)

`viewer/sectors` and `viewer/services` collapse into one read-only
`viewer/reference-data` page with two tabs (Sectors | Services). No other
Viewer layout changes.

## 7. Plumbing

- `DashboardPageBuilder`: new `activePage` cases `reference-data` and
  `distribution`; reports data builds under `dashboard`; account list merged;
  per-page data gating preserved (only the active page's heavy queries run —
  lookup tabs may all build on page load, tables are small).
- `ViewLayoutModel`: `pageTitle()` and `navActive()` maps updated to the new
  page keys.
- `Admin/layout.php`: body switch consolidated to the new page set.
- `components/dashboard_sidebar.php`: new 7-link nav.

## Error handling

Unchanged: role guards via `RoleAccess::requireRole()` per page/action;
removed routes 404 (CI4 default); tab param falls back to the first tab when
unknown.

## Testing / verification

- `php spark routes` — every route resolves; removed routes gone.
- `vendor/bin/phpunit` before and after; update tests referencing removed
  routes.
- Playwright against dev server: log in, screenshot Dashboard,
  Reference Data (all 4 tabs), Distribution (both tabs), Accounts, at desktop
  and 390 px widths; compare against Manage Records (design source of truth).
- Smoke: login, role redirect, family create/update, audit log creation,
  lookup CRUD from the new tabs, batch open/close, void distribution,
  account enable/disable from the merged table.

## Risks

- Tests and views referencing old route names must be swept
  (`grep` for each removed URI).
- Pagination links inside tabs must preserve `?tab=`.
- Merged dashboard length: with the content cuts it targets roughly one
  screen; verify at 390 px.

## Out of scope

- Employee/Viewer layout redesigns beyond items above.
- Scanner kiosk pages.
- Reports redesign beyond the merge (parked previously; charts stay Chart.js).
