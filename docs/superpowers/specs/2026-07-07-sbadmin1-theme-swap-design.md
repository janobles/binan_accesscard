# SB Admin 1 Theme Swap — Design

**Date:** 2026-07-07
**Status:** Approved (experimental branch)
**Context:** `docs/knowledge/sbadmin/target-theme.md` (decision 2026-07-06: target = SB Admin 1; SB Admin 2 rejected — Bootstrap 4.6 conflicts with repo's 5.3.3 base) and `docs/knowledge/sbadmin/adapter.md` (current reality: homegrown adapter, not a vendored theme).

## Goal

Replace the homegrown `public/css/sb-admin-adapter.css` shell with the genuine
vendored SB Admin 1 theme (startbootstrap-sb-admin v7.x, Bootstrap 5), at
**pure upstream defaults** — no Biñan green re-skin, no custom chrome — so the
baseline is visible and customization needs can be quantified afterward.

## Decisions

1. **Scope: full theme swap.** Vendor SB Admin 1's compiled assets, migrate
   layout markup to the `#layoutSidenav` frame, delete the adapter.
2. **Vendoring stays static-file.** Frontend assets in this repo are committed
   files under `public/assets/` (Bootstrap 5.3.3, jQuery 3.7.1,
   bootstrap-icons, DataTables, chart.js, html5-qrcode). Composer is PHP-only
   and cannot sensibly vendor frontend; no npm build step is wanted. SB Admin 1
   follows the same convention: commit `dist/css/styles.css` and
   `dist/js/scripts.js` from the upstream GitHub release to
   `public/assets/sb-admin/`.
3. **Upstream `styles.css` replaces `bootstrap.min.css` in dashboard shells.**
   Upstream ships Bootstrap (~5.2.x) compiled into `styles.css`; loading both
   would double-define Bootstrap. Dashboard contexts load `styles.css` alone.
   The login page keeps the existing plain Bootstrap 5.3.3. The
   5.2-vs-5.3 delta is acceptable for this experiment.
4. **Icons: keep bootstrap-icons.** SB Admin 1's `.sb-nav-link-icon` wrapper is
   icon-agnostic; existing `bi-*` markup stays. Font Awesome not vendored.
5. **Custom CSS purge: adapter only, plus drop shadows everywhere.**
   - Delete `sb-admin-adapter.css` (shell fully replaced).
   - Page CSS files (`managerecord.css`, `familymodal.css`, etc.) keep their
     functional/layout rules, **except**: remove all `box-shadow` rules
     (~15 across `accounts.css`, `login.css`, `familymodal.css`,
     `lookupmanagement.css`, `session-timeout.css`, `scanner-scan.css`).
     SB Admin 1's live demo uses clean borders, not elevation — match that.
   - No tokens stub needed: only 2 page-CSS references to adapter variables
     (`managerecord.css:569,598`, `var(--ui-font-sm, 0.8rem)`) and both carry
     fallbacks.

## Changes

### 1. Vendor assets
- `public/assets/sb-admin/css/styles.css` — upstream compiled theme.
- `public/assets/sb-admin/js/scripts.js` — sidebar-toggle behavior.
- Source: latest v7.x release of
  https://github.com/StartBootstrap/startbootstrap-sb-admin (`dist/`).

### 2. Markup migration (per target-theme.md migration map)
Files: `app/Views/Admin/layout.php`, `app/Views/Employee/layout.php`,
`app/Views/Scanner/layout.php`, `app/Views/Viewer/layout.php`,
`app/Views/components/dashboard_sidebar.php`.

- `#wrapper` / `#content-wrapper` / `#content` → `body.sb-nav-fixed` +
  `#layoutSidenav` / `#layoutSidenav_nav` / `#layoutSidenav_content`.
- Brand moves from sidebar (`.sidebar-brand*`) to `.sb-topnav .navbar-brand`.
- `.sidebar` + `.bg-gradient-primary` → `.sb-sidenav accordion sb-sidenav-dark`
  (upstream dark default — no re-skin).
- `.sidebar-heading` → `.sb-sidenav-menu-heading`; nav links become
  `.nav-link` with `bi-*` icons inside `<div class="sb-nav-link-icon">`.
- `#sidebarToggle` becomes the topnav button; upstream `scripts.js` toggles
  `sb-sidenav-toggled` on `<body>`.
- Content column: `<main><div class="container-fluid px-4">` + footer
  `py-4 bg-light mt-auto`.

### 3. Asset manifest (`app/Helpers/asset_helper.php`)
- `head` context (consumed only by the 4 dashboard shells): replace
  `assets/bootstrap/css/bootstrap.min.css` with
  `assets/sb-admin/css/styles.css` (bootstrap-icons entry stays). The scanner
  shell merges `head` + `admin` + `scanner`, so it inherits the theme.
- `admin`/`employee`/`viewer` contexts: remove `css/sb-admin-adapter.css`.
- Dashboard script contexts: add `assets/sb-admin/js/scripts.js`.
- `login` context unchanged (has its own plain Bootstrap 5.3.3 entry).

### 4. Deletions
- `public/css/sb-admin-adapter.css`.
- All `box-shadow` declarations in remaining `public/css/*.css`.

## Risks & rollback

- Experimental branch off freshly synced `main` (fetch/reset first — local
  main is known to lag).
- DataTables bootstrap5 build should style fine against upstream `styles.css`
  (Bootstrap-5-compatible); verify tables visually.
- Pages will look plain/upstream-default by design — visual triage of what to
  re-customize is the point of the exercise.

## Verification

- `vendor/bin/phpunit` before and after (change should be CSS/markup-neutral
  to tests).
- `php spark routes` unchanged.
- Manual smoke via `php spark serve`: login → role redirect → admin, employee,
  viewer, scanner dashboards; family create/edit modal; DataTables pages;
  sidebar toggle; audit trail page.

## Out of scope

- Biñan green re-skin (later, quantified from this baseline).
- Trimming page CSS beyond shadows.
- Font Awesome, npm tooling, SCSS pipeline.
