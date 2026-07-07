# MVC Boundaries

**Scope:** who decides vs who builds. Controllers route and decide;
`DashboardPageBuilder` owns dashboard view-data assembly; models own queries.

## Rule 1: Controllers decide, libraries build

Dashboard controllers are thin dispatchers — one line per page, no data
assembly.

Canonical — `app/Controllers/Admin/DashboardController.php:51`:

```php
return (new DashboardPageBuilder($this->request))->renderAdminPage('dashboard');
```

Every admin/employee/viewer page route follows this exact shape (`:64`, `:86`,
`:99`, and the Employee/Viewer dashboard controllers).

**Anti-pattern seen in repo:** `app/Controllers/Families/FamilyController.php:1`
has grown to 1,700+ lines mixing CRUD decisions with Excel import, QR
handling, and modal payload assembly — see the 🟠 entry in
`docs/knowledge/violations.md`. New logic of that kind belongs in a library
(compare `app/Libraries/FamilyRecordWriter.php:1`, which extracts the
member/service/audit write sequence).

**Why:** one place to debug view data (CLAUDE.md: "start debugging here"),
and page dispatch stays readable.

## Rule 2: View-data assembly lives in DashboardPageBuilder

All dashboard page data is assembled by `DashboardPageBuilder`, keyed by page
name — never inline in a controller.

Canonical — `app/Libraries/DashboardPageBuilder.php:36`:

```php
public function renderAdminPage(string $activePage): string|RedirectResponse
```

with `buildAdminViewData()` (`:62`), `buildAdminRecordListViewData()` (`:223`),
`renderEmployeePage()` (`:484`), `renderViewerPage()` (`:571`) as the
per-role entry points. Adding a dashboard page = add a branch here, not a
data-assembly block in the controller.

**Boundary note:** modal/partial endpoints (e.g. account form, family modal)
legitimately `return view(...)` from their controllers — the rule governs
dashboard *pages*, not small partials. But the partial's *data shaping*
should still live in a helper or library (see
`app/Helpers/family_modal_helper.php:12`, `family_modal_prepare()`).

**Why:** dashboard pages share layout scaffolding (sidebar state, search,
session account); assembling it once in the builder keeps the shells and
per-page views consistent.

## Rule 3: Queries live in models, not controllers or libraries

Controllers and libraries call model methods; they do not build queries.

Canonical: `DashboardPageBuilder` pulls from `DashboardModel`, `SearchModel`,
`MemberModel`, `AuditTrailsModel` (`app/Libraries/DashboardPageBuilder.php:4`
imports) — it composes their results, it never touches the query builder.
Details and the model inventory: `docs/knowledge/binan-conventions/models.md`.

**Why:** query logic is testable and reusable only when it lives with its
table's model.
