# Views & Bootstrap

**Scope:** layout shells, partials, styling rules. Bootstrap **v5.3.3**
vendored at `public/assets/bootstrap/css/bootstrap.min.css:1` — pins in
`docs/knowledge/sources.md`.

## Rule 1: Pages plug into a role layout shell — never standalone `<html>`

Shells: `app/Views/Admin/layout.php:68`, `Employee/layout.php`,
`Viewer/layout.php`, `Scanner/layout.php`. Each shell owns `<html>`, head
assets, sidebar, topbar, and swaps the per-page view in by `$activePage`:

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

## Rule 3: CSS loads via `asset_styles()` — Bootstrap first, adapter, then page CSS

Canonical chain — `app/Views/Admin/layout.php:62`:

```php
<?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
<link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
```

with the lists defined in `app/Helpers/asset_helper.php:34`: vendored
Bootstrap → bootstrap-icons → DataTables (bootstrap5 build) →
`css/sb-admin-adapter.css` → per-page CSS (`public/css/<page>.css`, e.g.
`accounts.css`, `managerecord.css`). New page styles go in a page CSS file
registered there — never a new `<link>` hand-added to a shell.

## Rule 4: Style with Bootstrap utilities/components, not inline styles

Canonical: views compose Bootstrap 5 classes (`card`, `table`, `btn`,
`dropdown`, spacing/flex utilities) plus the adapter's SB-Admin classes
(`docs/knowledge/sbadmin/adapter.md`).

**Anti-pattern seen in repo:** inline `style="..."` attributes —
`app/Views/Family/list.php:47` and
`app/Views/Accounts/account-form-modal.php:44` (tracked in
`docs/knowledge/violations.md`). Exceptions: PDF views
(`app/Views/Scanner/pdf/report.php:1`) and framework error pages.

**Why:** the SB Admin 1 migration (`docs/knowledge/sbadmin/target-theme.md`)
retiles the shells; views that stick to Bootstrap + adapter classes migrate
for free, inline styles have to be hunted down.

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
utility used by the house style (`app/Views/Scanner/reports.php:53`), NOT an
SB-Admin-Pro demo class. The Pro-only markers this repo bans are
`border-left-*` and `text-xs text-uppercase`
(`tests/unit/ReportsViewTest.php:31`).
