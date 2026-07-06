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
