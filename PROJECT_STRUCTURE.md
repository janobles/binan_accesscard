# Project Structure

This project follows the normal CodeIgniter 4 layout. The database source of truth is `C:\Users\Acer\Downloads\accesscardV3.0.sql`; no app migrations are used.

## Backend

The backend keeps the default CodeIgniter 4 layout, but controllers and models are
grouped into **feature subnamespaces** so each slice (Auth, Accounts, Families,
Lookups, Audit, Admin, Employee) is self-contained and easy to navigate. Routes in
`app/Config/Routes.php` target these namespaces directly (e.g. `Admin\DashboardController::dashboard`).

### Controllers (`app/Controllers/`)

- `Auth/AuthController.php` — login, logout, and session keep-alive.
- `Workspace/Home.php` — role-based dashboard routing and page dispatch for both
  admin and employee shells. Page rendering is delegated to
  `app/Libraries/DashboardPageBuilder.php`; this controller only decides which
  page/role to show. Uses `HomeRoleAccessTrait` for session/role helpers.
- `Accounts/AccountController.php` — Developer-only staff account creation and
  enable/disable. Employee accounts are stored as `User` role to match the SQL dump.
- `Families/FamilyController.php` — validates and saves head/member records to
  `member`, selected services to `member_services`, and audit logs to `audit_trails`.
- `Lookups/SectorController.php`, `Lookups/ServiceController.php` — create/update/
  archive/restore/delete mutations for the `sector` and `services` lookup tables.
  These back the live `admin/sectors` and `admin/services` management screens.
- `BaseController.php`, `HomeRoleAccessTrait.php`, `Concerns/` — cross-cutting base
  class and shared traits.

### Models (`app/Models/`)

- `Auth/UserModel.php` — user login, password hashing, and account creation.
- `Families/MemberModel.php` — head of family and family member records.
- `Families/MemberServiceModel.php` — rows in the `member_services` table.
- `Families/FamilyFormOptionsModel.php` — form dropdown options from `sector`,
  `services`, and the exact allowed table values.
- `Audit/AuditTrailsModel.php` — audit trail inserts and audit list queries.
- `Lookups/SectorModel.php` — the `sector` lookup table.
- `Lookups/ServiceModel.php` — the `services` lookup table: admin CRUD/archival and
  per-member/sector eligibility lookups (the single model for `services`).
- `DashboardModel.php`, `SearchModel.php`, `ViewLayoutModel.php` — shared
  cross-feature query/data-assembly helpers (kept in the root namespace).

### Shared libraries (`app/Libraries/`)

- `DashboardPageBuilder.php` — assembles all dashboard view data; the main entry
  point when debugging what a page renders.
- `SessionAuditLogger.php`, `RoleAccess.php`, `SectorIds.php` — auth/audit and
  domain helpers used across slices.

## Views

- `app/Views/Auth/login.php`  
  Login form.

- `app/Views/Admin/layout.php`  
  Developer/Admin shell; swaps in `accounts.php`, `audit-trails.php`, and the
  `Family/` and `Lookups/` views by active page. The sector and service lookup
  screens live in `Lookups/sectors.php` and `Lookups/services.php`.

- `app/Views/Employee/layout.php`  
  Employee workspace shell.

- `app/Views/Family/`  
  Family record views: `form.php` (+ `entry.php` data-prep wrapper),
  `head-fields.php`, `member-fields.php`, `member-summary.php`, `list.php`, `view.php`.

- `app/Views/Lookups/`  
  Sector/service management (`sectors.php`, `services.php`), the family-form
  `picker.php`, and the add/edit modals.

- `app/Views/components/search-bar.php`  
  Shared search bar partial.

## Database

- `C:\Users\Acer\Downloads\accesscardV3.0.sql`  
  Source database structure.

- `app/Database/Seeds/AccessCardSeeder.php`  
  Test login accounts only. It does not add tables or columns.

## Public Assets

- `public/assets/css/mis.css`  
  Shared dashboard/workspace styling.

  Shared dashboard/workspace JavaScript.

- `public/assets/image/`  
  Biñan logo, CSWD logo, and background images.
