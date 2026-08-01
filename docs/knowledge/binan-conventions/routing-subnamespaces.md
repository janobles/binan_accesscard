# Routing & Feature Subnamespaces

**Scope:** where controllers live, how routes reach them, and who may open a page.

## Rule 1: Controllers group into feature subnamespaces

`app/Controllers/` is organized by feature, not by verb: `Accounts`, `Admin`,
`Auth`, `Cards`, `Families`, `Lookups`, `Scanner`, plus cross-cutting
`BaseController.php`, `HomeRoleAccessTrait.php`, and shared traits in `Concerns/`.

A new controller goes into the feature directory whose data it owns - a
family-record endpoint belongs in `Families\FamilyController`, not a new
top-level controller.

There are no role-named controller directories. `Admin\DashboardController` is the
one dashboard controller for every staff role; the former `Employee\` and `Viewer\`
copies are deleted.

**Why:** routes, models (`docs/knowledge/binan-conventions/models.md`), and
views mirror the same feature split, so one feature reads top-to-bottom.

## Rule 2: Routes target subnamespaces relative to `App\Controllers`

Routes name the subnamespaced controller directly - no `namespace` option, no
leading backslash:

```php
$routes->get('/', 'Auth\AuthController::index');
```

**Anti-pattern:** `['namespace' => ...]` route options or fully-qualified
`\App\Controllers\...` strings - the repo never uses them; CI4 prepends the
default namespace to the relative reference.

## Rule 3: One page, one URL - no role prefix

The `admin/`, `employee/`, and `viewer/` URL prefixes are gone. Each page has a
single flat URI and declares the navigation-manifest key that decides which roles
may reach it:

```php
$routes->get('records', 'Admin\DashboardController::manageRecords', ['filter' => 'roleNav:records']);
```

`app/Config/Navigation.php` holds the manifest: one entry per page (key, label,
icon, route, heading, roles), plus an `UNLISTED` map for pages with no sidebar link
(`records-entry`, `records-import`, `records-profile`, `records-update`).
`App\Filters\RoleNavFilter` (alias `roleNav`) reads it and 404s a role with no
entry, so the response does not confirm that a page the caller may not use exists.

Adding a page means adding a manifest entry plus a route with its key. A controller
page method carries no `RoleAccess::requireRole()` call; the filter is the gate. A
typo in the key grants nobody, because an unknown key returns no roles.

The `scanner/` kiosk keeps its own URL space and its own shell.

**Anti-pattern:** a `routeBase` string threaded through controllers, libraries, and
views to rebuild a role-prefixed URL. It is deleted; build flat URLs directly
(`records/{id}`, `records/{id}/archive`).

## Rule 4: Dashboard page routes map 1:1 to dispatcher methods

Each page URL maps to one method on `Admin\DashboardController` (`dashboard`,
`manageRecords`, `referenceData`, `cards`, `accounts`, `auditTrails`), and each
method is a one-line delegate to `DashboardPageBuilder::renderPage('<manifest
key>')` (see `docs/knowledge/binan-conventions/mvc-boundaries.md`).

**Enforcement:**

- `tests/unit/RouteSpaceTest.php` asserts every page resolves at its flat URI, that
  no route carries a role prefix, and that guarded pages declare their manifest key.
- `tests/unit/NavigationManifestTest.php` asserts the manifest's shape, its role
  filtering, and that `RoleNavFilter` denies a role with no entry.
- `tests/unit/DashboardControllerRoutingTest.php` asserts the page-action methods
  exist - moving or renaming one fails loudly. Update it when a page is added.

## Verification

After any route change:

```bash
php spark routes   # every route must resolve to a real controller method
vendor/bin/phpunit tests/unit/RouteSpaceTest.php tests/unit/NavigationManifestTest.php
```
