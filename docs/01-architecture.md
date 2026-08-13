# Architecture

A normal CodeIgniter 4 application, with two deliberate departures from the
framework's defaults: the database schema lives in a SQL dump rather than in
migrations, and controllers are grouped by feature rather than sitting in one
flat directory.

Nothing else here will surprise you if you have used CodeIgniter before.

## Feature subnamespaces

`app/Controllers/` and `app/Models/` are grouped into subnamespaces, one per
slice of the system:

```
app/Controllers/
  Auth/          login, logout, session keep-alive
  Accounts/      staff account management and the self-service profile page
  Admin/         the dashboard, distribution, reports, subsidy types
  Families/      family records, the Excel import, the records datatable
  Cards/         access card generation and lookup
  Lookups/       the reference-data tables
  Scanner/       the distribution kiosk
  Concerns/      traits shared across the above
```

`app/Config/Routes.php` targets these namespaces directly:

```php
$routes->get('dashboard', 'Admin\DashboardController::dashboard');
```

The grouping is for navigation, not isolation. Nothing stops a `Families`
controller using a `Lookups` model, and several do. The point is that when you
are asked to change how the import works, everything about the import is in one
place.

`Concerns/` holds traits rather than controllers: `DashboardPartialsTrait` and
`LookupControllerTrait`, which give the several reference-data controllers their
shared CRUD behaviour.

## The request lifecycle

A request to a dashboard page passes through five stages.

**1. Route.** `app/Config/Routes.php` maps the URI to a controller method. Routes
carry no role prefix, so there is exactly one route per page. Chapter 10 covers
the URL space in full.

**2. Filters.** Two matter here.

`RoleNavFilter` (alias `roleNav`) gates the route on its navigation manifest key,
declared on the route as `['filter' => 'roleNav:records']`. A role with no
manifest entry for that key gets a 404, not a redirect, so the page's existence
does not leak to a role that should not have it.

`BatchScheduleFilter` (alias `batchSchedule`) runs before scanner and
distribution requests. It calls `DistributionBatchModel::reconcileSchedule()`, so
a plotted batch opens and closes on its own schedule. This is why there is no
scheduled task to install on the laptop that travels to a distribution venue: the
reconciliation happens on the next request that needs it. Chapter 15 has the
detail.

**3. Controller.** Decides which page to show. For dashboard pages that is
genuinely one line:

```php
return (new DashboardPageBuilder($this->request))->renderPage('dashboard');
```

**4. Library.** `app/Libraries/DashboardPageBuilder.php` gathers the model data,
assembles the view bundle, and renders the shell. It is the first place to look
when a dashboard page shows the wrong thing.

**5. View.** `app/Views/layout.php` is the one dashboard shell: topnav, a sidebar
rendered from the navigation manifest, and one `$bodyView` slot.

## Controllers decide, libraries build

The most important structural rule in the codebase, and the one that costs the
most to undo when it slips.

Controllers route and decide. Libraries assemble. Models query. A dashboard
controller method that grows a data-assembly block is the beginning of the
problem the rule exists to prevent.

The problem it prevents is concrete. Dashboard pages are shared across roles, and
before this rule there was a dashboard controller per role, plus a layout per
role. Three places to edit for one change, and they drifted. The role-parallel
`Employee\` and `Viewer\` controllers and their layouts are deleted; where a role
genuinely differs, an Encoder seeing only their own activity for instance, that
is a conditional inside the builder.

The Families slice is the worked example of the boundary. Decisions live in
`app/Controllers/Families/FamilyController.php` (create, update, archive,
restore), `FamilyImportController.php` (the import wizard), and
`FamilyDataTableController.php` (the datatable endpoint), sharing access guards
through the `FamilyRequestContext` trait. Building lives in
`app/Libraries/FamilyDataTablePresenter.php` (row shaping),
`FamilyModalDataBuilder.php` (modal view data), and `FamilyRecordWriter.php` (the
member, service, and audit write sequence).

## What lives where

### Controllers

| Controller | Owns |
|---|---|
| `Auth/AuthController.php` | login, logout, session keep-alive |
| `Admin/DashboardController.php` | every dashboard page for every staff role: `dashboard`, `records`, `reference-data`, `cards`, `accounts`, `audit-trails` |
| `Admin/DistributionController.php` | the Schedule tab's feed, save and delete endpoints, manual batch close, distribution void |
| `Admin/ReportsController.php` | distribution reports: combined totals, per-kiosk drilldown, PDF export |
| `Admin/SubsidyTypesController.php` | subsidy-type CRUD under `reference-data/subsidy-types/*` |
| `Accounts/AccountController.php` | staff account creation and enable/disable |
| `Accounts/ProfileController.php` | the self-service My Account page |
| `Families/FamilyController.php` | family create, the profile page, update, archive, restore |
| `Families/FamilyImportController.php` | the Excel template download and the whole import wizard |
| `Families/FamilyDataTableController.php` | the server-side datatable endpoint for Family Records |
| `Cards/QrCardController.php` | card batch generation, the card PDF, QR lookup |
| `Lookups/SectorController.php`, `ServiceController.php`, `CategoryController.php` | create, update, archive, restore and delete for the reference tables |
| `Scanner/ScanController.php` | the kiosk scan flow, rendered in the kiosk shell rather than the dashboard layout |

### Models

Grouped the same way. `Auth/UserModel.php` handles login and account creation.
`Families/` holds `MemberModel`, `MemberServiceModel`, and
`FamilyFormOptionsModel`. `Audit/AuditTrailsModel.php` owns audit inserts and the
audit list queries. `Lookups/` holds the three reference tables.

`Scanner/` is the largest group, and the names do not all match their tables:
`SubsidyTypeModel`, `SubsidyDistributionModel` (table `subsidy_distribution`, key
`distribution_id`), `SubsidyStatsModel`, `DistributionBatchModel`, and
`QrControlModel`. `DistributionBatchModel` carries the schedule logic:
`saveSchedule()`, `overlapping()`, `scheduledBetween()`, and
`reconcileSchedule()`.

`Jobs/JobQueueModel.php` is the queue behind the Excel import, covered in chapter
05. At the top level, `DashboardModel`, `SearchModel`, and `ViewLayoutModel` are
shared query helpers.

### Libraries

`app/Libraries/` is where assembly lives. Beyond `DashboardPageBuilder` and the
Families set already named:

- `Qr/` builds control numbers, QR images, and card PDFs.
- The import set: `FamilyExcelTemplate`, `FamilyExcelImporter`,
  `ImportStagingStore`, `ImportReviewPresenter`, `ImportReviewQuery`,
  `ImportReviewChangeLog`, and `ImportLookupCache`.
- `EligibilityBuilder` works out who a distribution batch serves.
- `BatchScheduleWindow` is pure open and close arithmetic for a scheduled batch.
  It touches no database and no framework state, and is called only from
  `DistributionBatchModel::reconcileSchedule()`. That purity is why it is
  straightforward to test.
- `RoleAccess`, `SessionAccount`, `SessionAuditLogger`, `ActiveSessionRegistry`,
  `SectorIds`, and `ViewFormatter` are the cross-cutting helpers.

### Views

`app/Views/layout.php` is the one dashboard shell. The sidebar comes from
`Config\Navigation::linksFor($role)` through
`app/Views/components/dashboard_sidebar.php`. Page bodies live in directories
matching their controllers: `Pages/`, `Family/`, `Admin/`, `Lookups/`,
`Accounts/`, plus shared `Partials/` and `components/`.

Two shells stand outside the dashboard: `Scanner/kiosk-layout.php`, a
full-viewport green shell used only by the scan and performance pages, and the
standalone `Auth/login.php`.

Chapter 20 covers views and the design system properly.

### Public assets

`public/assets/` holds vendored libraries (Bootstrap, DataTables, jQuery, SB
Admin, FullCalendar) and this application's own CSS and JavaScript. Per-page
behaviour lives in `public/assets/js/dashboard/`. Asset loading is context based,
so the FullCalendar bundle loads only on the distribution page's Schedule tab
rather than on every dashboard page.

## Rules

Copied from the conventions this codebase is held to. Terse on purpose.

**Scope:** who decides vs who builds. Controllers route and decide;
`DashboardPageBuilder` owns dashboard view-data assembly; models own queries.

### Rule 1: Controllers decide, libraries build

Dashboard controllers are thin dispatchers - one line per page, no data
assembly.

Canonical - `app/Controllers/Admin/DashboardController.php`:

```php
return (new DashboardPageBuilder($this->request))->renderPage('dashboard');
```

Every page route on that controller follows this exact shape, for every role: the
argument is the navigation-manifest key. The page method carries no role check
either; the route's `roleNav:<key>` filter is the gate (see
`docs/10-navigation-and-access.md`).

**Worked example:** the Families feature was split along this boundary
(2026-07, `refactor/mvc-cleanup`). Decisions live in three controllers -
`app/Controllers/Families/FamilyController.php:1` (CRUD),
`app/Controllers/Families/FamilyImportController.php:1` (Excel import),
`app/Controllers/Families/FamilyDataTableController.php:1` (DataTables
endpoint) - sharing guards via the
`app/Controllers/Families/FamilyRequestContext.php:1` trait. Building lives
in libraries: `app/Libraries/FamilyDataTablePresenter.php:1` (row/envelope
shaping), `app/Libraries/FamilyModalDataBuilder.php:1` (modal view data),
`app/Libraries/FamilyRecordWriter.php:1` (member/service/audit write
sequence). New logic of either kind goes to the matching side.

**Why:** one place to debug view data, and page dispatch stays readable.

### Rule 2: View-data assembly lives in DashboardPageBuilder

All dashboard page data is assembled by `DashboardPageBuilder`, keyed by page
name - never inline in a controller.

Canonical - `app/Libraries/DashboardPageBuilder.php`:

```php
public function renderPage(string $activePage): string|RedirectResponse
```

with `buildViewData()` and `buildRecordListViewData()` behind it. There is one
render path for every role: where a role really differs (an Encoder's dashboard
shows only their own activity, a Viewer's record list emits no edit actions) that is
a conditional inside the builder, not a parallel method. Adding a dashboard page =
add a branch here, not a data-assembly block in the controller.

A page reached outside the builder (`records/entry`, `records/{id}`,
`records/import`) may `return view('layout', [...])` from its controller with its
own `bodyData`, but the data shaping still belongs in a library or a
`*_view_data()` helper.

**Boundary note:** modal/partial endpoints (e.g. account form, family modal)
legitimately `return view(...)` from their controllers - the rule governs
dashboard *pages*, not small partials. But the partial's *data shaping*
should still live in a helper or library (see
`app/Helpers/family_modal_helper.php:12`, `family_modal_prepare()`).

**Why:** dashboard pages share layout scaffolding (sidebar state, search,
session account); assembling it once in the builder keeps the shells and
per-page views consistent.

### Rule 3: Queries live in models, not controllers or libraries

Controllers and libraries call model methods; they do not build queries.

Canonical: `DashboardPageBuilder` pulls from `DashboardModel`, `SearchModel`,
`MemberModel`, `AuditTrailsModel` (`app/Libraries/DashboardPageBuilder.php:4`
imports) - it composes their results, it never touches the query builder.
Details and the model inventory: `docs/02-database.md`.

**Why:** query logic is testable and reusable only when it lives with its
table's model.
