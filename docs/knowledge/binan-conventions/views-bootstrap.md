# Views & Bootstrap

**Scope:** layout shells, partials, styling rules. Bootstrap **v5.3.3**
vendored at `public/assets/bootstrap/css/bootstrap.min.css:1` — pins in
`docs/knowledge/sources.md`.

## Rule 1: Pages plug into a role layout shell — never standalone `<html>`

Shells: `app/Views/Admin/layout.php:68`, `Employee/layout.php`,
`Viewer/layout.php`. Each shell owns `<html>`, head assets, sidebar, topbar,
and swaps the per-page view in by `$activePage`:

```php
<?= view('Family/list', $recordListData) ?>
```

(`app/Views/Admin/layout.php:222`; same pattern for accounts `:208`,
audit-trails `:226`, lookups `:236`.)

Standalone `<html>` is correct ONLY for the shells themselves,
`app/Views/Auth/login.php:1`, and `app/Views/errors/html/` pages.

## Rule 2: Shared UI fragments are partials

- `app/Views/components/dashboard_sidebar.php:1` — sidebar, consumed by shells
  (`app/Views/Admin/layout.php:68`).
- `app/Views/Partials/topbar-account-menu.php:1`,
  `Partials/sector-label-list.php:1` — cross-role fragments
  (`app/Views/Admin/layout.php:89`).

Repeated markup across two views = extract a partial, render with
`view('Partials/...', [...])`.

**Card/table panels use the props-only components** (SB Admin 1 card
anatomy: card-header icon+title > card-body > optional card-footer):

- `app/Views/components/card.php:1` — generic shell; body content comes
  from a named view (`bodyView` + `bodyData`) or `bodyHtml`. JS scope
  hooks (e.g. `data-*-management-root`) pass through `attrs`.
- `app/Views/components/data_table.php:1` — columns/rows table card;
  cell values are RAW HTML, caller esc()'s every dynamic part.
- `app/Views/components/table_footer.php:1` — shared "Showing X–Y of Z"
  + Previous/Next pagination row, passed as `card`'s `footer`.

Canonical consumers: `app/Views/Family/list.php:26` (card + body
partial), `app/Views/Admin/reports-body.php:79` (chart cards + data_table).
New panels MUST use these components, not hand-rolled card markup.

## Rule 3: CSS loads via `asset_styles()` — Bootstrap first, adapter, then page CSS

Canonical chain — `app/Views/Admin/layout.php:62`:

```php
<?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
<link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
```

with the lists defined in `app/Helpers/asset_helper.php:34`: vendored
SB Admin 1 theme (`assets/sb-admin/css/styles.css`, Bootstrap compiled in)
→ bootstrap-icons → DataTables (bootstrap5 build) → per-page CSS
(`public/css/<page>.css`, e.g. `accounts.css`, `managerecord.css`). New
page styles go in a page CSS file registered there — never a new `<link>`
hand-added to a shell.

## Rule 4: Style with Bootstrap utilities/components, not inline styles

Canonical: views compose Bootstrap 5 classes (`card`, `table`, `btn`,
`dropdown`, spacing/flex utilities) plus SB Admin 1's `sb-*` shell classes
(`docs/knowledge/sbadmin/target-theme.md`).

**Anti-pattern:** inline `style="..."` attributes (past offenders tracked
and fixed in `docs/knowledge/violations.md`). Exceptions: PDF views
(`app/Views/Scanner/pdf/report.php:1`) and framework error pages.

**Why:** theme changes retile the shells and components; views that stick
to Bootstrap + component classes migrate for free, inline styles have to
be hunted down.

## Rule 5: Components Bootstrap does NOT ship — build from utilities, not fake classes

Bootstrap 5.3 has **no stepper/wizard and no empty-state component**. When a
page needs one:

- **Empty state:** compose utilities — centered `py-5` block, big muted
  bootstrap-icon (`display-3 text-secondary`), bold title, `text-muted small`
  hint. Canonical: `app/Views/Scanner/scan.php:39` (`#emptyState`, which also
  doubles as the lookup-error surface by swapping icon/text).
- **Stepper:** prefer NOT building one. Numbered field labels
  ("1. Aid type…", "2. Scan…") plus an attention state on the pending field
  read just as well without a custom component
  (`app/Views/Scanner/scan.php:8`, `.scan-attn` / `.scan-muted` in
  `public/css/scanner-scan.css:4`).
- Page-specific classes live in that page's CSS file (Rule 3) and build on
  Bootstrap CSS variables (`--bs-primary`, `--bs-success-bg-subtle`, …) so
  theming survives the SB Admin migration.

**Reviewer false positive to ignore:** `h-100` is a plain Bootstrap sizing
utility used by the house style (`app/Views/Admin/reports-body.php:98`), NOT
an SB-Admin-Pro demo class. The Pro-only markers this repo bans are
`border-left-*` and `text-xs text-uppercase`
(`tests/unit/ReportsViewTest.php:31`).
