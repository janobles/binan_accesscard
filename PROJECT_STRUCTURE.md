# Project Structure

Normal CodeIgniter 4 layout. The database source of truth is `accesscardV19.sql`
(with `accesscardV18.sql` kept as the base its patch applies to); no app migrations
are used.

## Navigation and URL space

- `app/Config/Navigation.php` - the manifest. One entry per page (key, label, icon,
  route, heading, roles), plus the `UNLISTED` pages that carry no sidebar link
  (record entry, import, profile, update). Single source of truth for the sidebar,
  page titles, and per-page role access.
- `app/Filters/RoleNavFilter.php` (alias `roleNav`) - gates a route on its manifest
  key. A role with no entry gets a 404, not a redirect.
- Routes carry no role prefix. One page, one URI: `dashboard`, `records`,
  `reference-data`, `cards`, `distribution`, `accounts`, `audit-trails`, plus
  `records/entry`, `records/import`, `records/{id}`. The `scanner/` kiosk keeps its
  own space.

## Backend

Controllers and models are grouped into **feature subnamespaces** so each slice
(Auth, Accounts, Families, Cards, Lookups, Audit, Admin, Scanner) is self-contained.
`app/Config/Routes.php` targets these namespaces directly
(e.g. `Admin\DashboardController::dashboard`).

### Controllers (`app/Controllers/`)

- `Auth/AuthController.php` - login, logout, and session keep-alive.
- `Admin/DashboardController.php` - the ONE dashboard controller for every staff
  role: `dashboard`, `records`, `reference-data`, `cards`, `accounts`,
  `audit-trails`. It decides which page to show; view data is assembled by
  `Libraries/DashboardPageBuilder.php`. The role-parallel `Employee\` and `Viewer\`
  dashboard controllers are deleted.
- `Accounts/AccountController.php` - Developer-only staff account creation and
  enable/disable. `Accounts/ProfileController.php` - self-service My Account.
- `Families/FamilyController.php` - family create (`records/entry`), the profile
  page (`records/{id}`, display and edit in one surface), update, archive, restore.
  Writes `member`, `member_services`, and an `audit_trails` row per mutation.
- `Families/FamilyImportController.php` - Excel template download and the import
  wizard: upload page, queued staging job, status polling, review page, commit,
  cancel, and the per-cell restage endpoint.
- `Families/FamilyDataTableController.php` - server-side DataTables endpoint for
  Family Records (one row per household); shaping delegated to
  `FamilyDataTablePresenter`.
- `Families/FamilyRequestContext.php` - trait with the shared access guards and the
  JSON/partial error helpers for the three Families controllers.
- `Cards/QrCardController.php` - access-card batch generation, card PDF, QR lookup.
- `Lookups/SectorController.php`, `ServiceController.php`, `CategoryController.php` -
  create/update/archive/restore/delete for the lookup tables behind
  `reference-data`.
- `Admin/SubsidyTypesController.php` - Admin/Developer-only subsidy-type CRUD under
  `reference-data/subsidy-types/*`.
- `Admin/DistributionController.php` - batch open/close (a subsidy type is bound to
  the batch at open) and distribution void, under `distribution/*`.
- `Admin/ReportsController.php` - distribution reports (combined totals, per-kiosk
  drilldown, PDF export), batch-scoped.
- `Scanner/ScanController.php` - kiosk-only one-action scan flow (`scanner/scan`,
  `POST scanner/log`, `scanner/performance`, `scanner/stats`), rendered in the green
  kiosk shell, not the dashboard layout.

### Models (`app/Models/`)

- `Auth/UserModel.php` - login, password hashing, account creation.
- `Families/MemberModel.php`, `MemberServiceModel.php`,
  `FamilyFormOptionsModel.php` - household records, service assignments, and the
  form's option lists.
- `Audit/AuditTrailsModel.php` - audit inserts and audit list queries.
- `Lookups/SectorModel.php`, `ServiceModel.php`, `CategoryModel.php` - the lookup
  tables, their CRUD/archival, and eligibility lookups.
- `Scanner/SubsidyTypeModel.php`, `SubsidyDistributionModel.php`
  (table `subsidy_distribution`, key `distribution_id`), `SubsidyStatsModel.php`,
  `DistributionBatchModel.php`, `QrControlModel.php` - subsidy types, the
  single-open-batch invariant, handout logging, and batch/per-kiosk stats.
- `Jobs/JobQueueModel.php` - the `job_queue` table behind the Excel import.
- `DashboardModel.php`, `SearchModel.php`, `ViewLayoutModel.php` - shared query
  helpers; `ViewLayoutModel` now only supplies the shell's mode-banner label.

### Shared libraries (`app/Libraries/`)

- `DashboardPageBuilder.php` - assembles all dashboard view data and renders
  `layout.php` through one `renderPage()`; the entry point when debugging a page.
- `FamilyDataTablePresenter.php` - shapes Family Records rows (one household per
  row, with its member count) into the DataTables cell map and JSON envelope.
- `FamilyModalDataBuilder.php`, `FamilyRecordWriter.php`,
  `FamilyRecordWriteException.php` - family form data assembly and the write path.
- `FamilyExcelTemplate.php`, `FamilyExcelImporter.php`, `ImportStagingStore.php`,
  `ImportReviewPresenter.php` (its `people` list drives the review table),
  `ImportReviewChangeLog.php`, `ImportFamilyModalBuilder.php` - the Excel import.
- `Qr/` - control numbers, QR images, and card PDFs.
- `RoleAccess.php`, `SessionAccount.php`, `SessionAuditLogger.php`,
  `ActiveSessionRegistry.php`, `SectorIds.php`, `ViewFormatter.php` - auth/audit and
  domain helpers used across slices.

## Views

- `app/Views/layout.php` - the ONE dashboard shell (topnav, sidebar from the
  manifest, one `$bodyView`). The three role-prefixed layouts are deleted.
- `app/Views/components/dashboard_sidebar.php` - rendered from
  `Config\Navigation::linksFor($role)`.
- `app/Views/Pages/` - `dashboard.php`, `reference-data.php`, `distribution.php`.
- `app/Views/Family/` - `list.php`/`list-body.php` (Family Records),
  `entry.php` (Data Entry), `profile.php` (Family Profile), `_fields.php` (the field
  set both share), `import-upload.php` and `import-review.php` (the wizard's two
  steps), `family-modal.php`, `row-actions.php`, `action-confirm-modal.php`.
- `app/Views/Admin/` - accounts, audit trails, subsidy types, and the distribution
  bodies/modals.
- `app/Views/Lookups/` - sector/service/category management bodies and modals, and
  the family-form `picker.php`.
- `app/Views/Accounts/`, `Partials/`, `components/` - account modals, topnav and
  label partials, and the shared card/modal components.
- `app/Views/Scanner/kiosk-layout.php` - green, full-viewport kiosk shell used only
  by `scan.php` and `performance.php`.
- `app/Views/Auth/login.php`, `session_conflict.php` - standalone shells.

## Database

- `accesscardV19.sql` - the schema and reference seed rows (import it to set up).
- `sql/patches/` - incremental patches, including `v19-subsidy-rename.sql`.
- `app/Database/Seeds/` - test login accounts only; never tables or columns.

## Public Assets

- `public/assets/css/` - dashboard, scanner, and component styling.
- `public/assets/js/dashboard/` - per-page behavior: `family-datatable.js`,
  `family-list.js`, `manage-family-modal.js`, `family-import.js` (upload page and
  progress toasts), `import-review.js` (the per-person review table), and the modal
  loaders.
- `public/assets/bootstrap/`, `datatables/`, `jquery/`, `sb-admin/` - vendored.
- `public/assets/image/` - Biñan logo, CSWD logo, backgrounds.
