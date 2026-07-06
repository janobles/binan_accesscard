# Routing & Feature Subnamespaces

**Scope:** where controllers live and how routes reach them.

## Rule 1: Controllers group into feature subnamespaces

`app/Controllers/` is organized by feature, not by verb: `Accounts`, `Admin`,
`Auth`, `Cards`, `Employee`, `Families`, `Lookups`, `Scanner`, `Viewer`, plus
cross-cutting `BaseController.php`, `HomeRoleAccessTrait.php`, and shared
traits in `Concerns/`.

A new controller goes into the feature directory whose data it owns — a
family-record endpoint belongs in `Families\FamilyController`, not a new
top-level controller.

**Why:** routes, models (`docs/knowledge/binan-conventions/models.md`), and
views mirror the same feature split, so one feature reads top-to-bottom.

## Rule 2: Routes target subnamespaces relative to `App\Controllers`

Routes name the subnamespaced controller directly — no `namespace` option, no
leading backslash. Canonical — `app/Config/Routes.php:12`:

```php
$routes->get('/', 'Auth\AuthController::index');
```

Workspace pages group by URL prefix with the same relative style —
`app/Config/Routes.php:20`:

```php
$routes->group('admin', static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Admin\DashboardController::dashboard');
    ...
});
```

**Anti-pattern:** `['namespace' => ...]` route options or fully-qualified
`\App\Controllers\...` strings — the repo never uses them; CI4 prepends the
default namespace to the relative reference.

## Rule 3: Dashboard page routes map 1:1 to dispatcher methods

Each shell page URL maps to one method on the role's `DashboardController`
(`app/Config/Routes.php:22` — `dashboard`, `accounts`, `family-entry`,
`manage-records`, `audit-trails`, `sectors`, `services`, `categories`, ...),
and each method is a one-line delegate to `DashboardPageBuilder`
(see `docs/knowledge/binan-conventions/mvc-boundaries.md`).

**Enforcement:** `tests/unit/DashboardControllerRoutingTest.php:21` asserts
the expected public page-action methods exist on each role's dashboard
controller — moving or renaming one fails loudly. Update that test when a
page route is added.

## Verification

After any route change:

```bash
php spark routes   # every route must resolve to a real controller method
vendor/bin/phpunit tests/unit/DashboardControllerRoutingTest.php
```
