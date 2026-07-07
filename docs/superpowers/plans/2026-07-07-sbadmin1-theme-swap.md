# SB Admin 1 Theme Swap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the homegrown `sb-admin-adapter.css` dashboard shell with the genuine vendored SB Admin 1 theme (startbootstrap-sb-admin v7, Bootstrap 5) at pure upstream defaults.

**Architecture:** Vendor upstream compiled `styles.css` + `scripts.js` as static files under `public/assets/sb-admin/` (repo convention — no npm/composer for frontend). Swap the `head` CSS manifest entry from `bootstrap.min.css` to `styles.css` (upstream bundles Bootstrap), migrate the 4 dashboard layout shells and shared sidebar to SB Admin 1's `#layoutSidenav` frame, delete the adapter, and strip drop shadows (SB Admin 1 uses flat borders).

**Tech Stack:** CodeIgniter 4 views/helpers, SB Admin 1 v7.x (Bootstrap 5), bootstrap-icons (kept — no Font Awesome).

**Spec:** `docs/superpowers/specs/2026-07-07-sbadmin1-theme-swap-design.md`

## Global Constraints

- No migrations, no schema changes, no composer/npm frontend packages — assets are committed static files under `public/assets/`.
- Pure upstream SB Admin 1 defaults: dark sidenav, no Biñan green re-skin, no new custom chrome CSS.
- Icons stay `bi-*` (bootstrap-icons). Do NOT vendor Font Awesome.
- Page CSS files keep all functional rules; remove ONLY elevation drop shadows. KEEP focus-ring `box-shadow` rules (accessibility) and `box-shadow: none` overrides.
- Login page keeps plain Bootstrap 5.3.3 (`login` manifest context untouched except its drop shadow).
- Preserve existing element IDs consumed by JS: `#dashboard-sidebar`, `#sidebarToggle`, `#familyModal`, `#familyModalBody`, `#dashboard-page-title`.
- PHP 8.2+, match existing view style. No tests exist for markup/CSS; verification = `vendor/bin/phpunit` (must stay green), `php spark routes`, and grep assertions per task. Final visual smoke is the user's.
- Commit after every task.

---

### Task 1: Branch + vendor SB Admin 1 assets

**Files:**
- Create: `public/assets/sb-admin/css/styles.css`
- Create: `public/assets/sb-admin/js/scripts.js`

**Interfaces:**
- Produces: asset paths `assets/sb-admin/css/styles.css` and `assets/sb-admin/js/scripts.js` consumed by Task 2's manifest.

- [ ] **Step 1: Sync main and branch** (local main is known to lag merged PRs)

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/binan_accesscard
git fetch origin && git checkout main && git reset --hard origin/main
git checkout -b feature/sbadmin1-theme-swap
```

- [ ] **Step 2: Download upstream release and copy dist assets**

```bash
cd /tmp && curl -sL https://github.com/StartBootstrap/startbootstrap-sb-admin/archive/refs/tags/v7.0.7.tar.gz -o sbadmin.tar.gz && tar xzf sbadmin.tar.gz
```

If the tag 404s, list tags and take the newest v7.x:
`git ls-remote --tags https://github.com/StartBootstrap/startbootstrap-sb-admin | tail -5`

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/binan_accesscard
mkdir -p public/assets/sb-admin/css public/assets/sb-admin/js
cp /tmp/startbootstrap-sb-admin-7.0.7/dist/css/styles.css public/assets/sb-admin/css/styles.css
cp /tmp/startbootstrap-sb-admin-7.0.7/dist/js/scripts.js public/assets/sb-admin/js/scripts.js
```

- [ ] **Step 3: Verify the files are the real thing**

Run: `head -5 public/assets/sb-admin/css/styles.css && grep -c 'sb-sidenav' public/assets/sb-admin/css/styles.css && grep -n 'sb-sidenav-toggled' public/assets/sb-admin/js/scripts.js`
Expected: Bootstrap banner comment in CSS; `sb-sidenav` count > 20; `scripts.js` contains the `sb-sidenav-toggled` toggle + localStorage persistence.

- [ ] **Step 4: Commit**

```bash
git add public/assets/sb-admin && git commit -m "chore(assets): vendor SB Admin 1 v7.0.7 compiled css/js"
```

---

### Task 2: Asset manifest swap + delete adapter

**Files:**
- Modify: `app/Helpers/asset_helper.php:32-73` (styles manifest) and `:93-149` (scripts manifest)
- Delete: `public/css/sb-admin-adapter.css`

**Interfaces:**
- Consumes: `assets/sb-admin/css/styles.css`, `assets/sb-admin/js/scripts.js` (Task 1).
- Produces: `asset_styles('head')` returns styles.css first; `asset_scripts('core')` includes scripts.js. Layouts (Tasks 3–5) rely on these — no layout changes needed to load the theme.

- [ ] **Step 1: Swap `head` context CSS**

In `asset_styles()`, change:

```php
'head' => [
    'assets/bootstrap/css/bootstrap.min.css',
    'assets/bootstrap-icons/font/bootstrap-icons.min.css',
],
```

to:

```php
'head' => [
    'assets/sb-admin/css/styles.css',
    'assets/bootstrap-icons/font/bootstrap-icons.min.css',
],
```

(`head` is consumed only by the 4 dashboard shells; `login` context keeps its own `assets/bootstrap/css/bootstrap.min.css` — leave it.)

- [ ] **Step 2: Remove adapter from role contexts**

Delete the line `'css/sb-admin-adapter.css',` from the `admin`, `employee`, and `viewer` arrays (3 occurrences).

- [ ] **Step 3: Add scripts.js to `core` scripts context**

```php
'core' => [
    'assets/jquery/jquery-3.7.1.min.js',
    'assets/bootstrap/js/bootstrap.bundle.min.js',
    'assets/sb-admin/js/scripts.js',
],
```

Keep `bootstrap.bundle.min.js`: upstream `scripts.js` only handles the sidenav toggle and does not bundle Bootstrap JS. (`core` is used only by dashboard shells; login loads its own scripts.)

- [ ] **Step 4: Delete the adapter stylesheet**

```bash
git rm public/css/sb-admin-adapter.css
```

- [ ] **Step 5: Verify no dangling references**

Run: `grep -rn 'sb-admin-adapter' app/ public/ && echo DANGLING || echo CLEAN`
Expected: `CLEAN`
Run: `vendor/bin/phpunit`
Expected: green (same pass/skip counts as on main).

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(theme): load SB Admin 1 styles/scripts, drop homegrown adapter"
```

---

### Task 3: Sidebar component + shared topnav partial

**Files:**
- Modify: `app/Views/components/dashboard_sidebar.php` (full rewrite of markup, same variables)
- Create: `app/Views/Partials/dashboard-topnav.php`

**Interfaces:**
- Consumes: same view variables the sidebar already receives (`$sidebarScannerOnly`, `$navActive`, `$canManageAccounts`, `$sidebarRoleClass`, `$sidebarUserUrl`, `$activeTab`).
- Produces: `components/dashboard_sidebar` now renders an SB Admin 1 `<nav class="sb-sidenav">` (must be placed inside `#layoutSidenav_nav` by layouts). New partial `Partials/dashboard-topnav` renders the `.sb-topnav` bar; layouts pass `['user' => $user, 'username' => $username, 'accountLevelLabel' => $accountLevelLabel, 'brandUrl' => <string>]` (plus optional `accountSettingsUrl`/`accountSettingsMode` passthrough where a layout already sets them for `topbar-account-menu`).

- [ ] **Step 1: Rewrite `dashboard_sidebar.php`**

Keep the PHP defaults block (lines 1–23) verbatim. Replace ALL markup below it with the SB Admin 1 sidenav. Brand moves to the topnav (Task 3 Step 2), so the sidebar has no brand/toggle anymore:

```php
<?php if ($sidebarScannerOnly): ?>
    <nav class="sb-sidenav accordion sb-sidenav-dark <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">QR Code</div>
                <a class="nav-link <?= $activeTab === 'scan' ? 'active' : '' ?>" href="<?= site_url('scanner/scan') ?>"><div class="sb-nav-link-icon"><i class="bi bi-upc-scan" aria-hidden="true"></i></div>Scan</a>
                <a class="nav-link <?= $activeTab === 'manage' ? 'active' : '' ?>" href="<?= site_url('scanner/manage') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></div>Management</a>
                <a class="nav-link <?= $activeTab === 'reports' ? 'active' : '' ?>" href="<?= site_url('scanner/reports') ?>"><div class="sb-nav-link-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></div>Reports</a>
            </div>
        </div>
    </nav>
<?php else: ?>
    <nav class="sb-sidenav accordion sb-sidenav-dark <?= esc($sidebarRoleClass) ?>" id="dashboard-sidebar">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>"><div class="sb-nav-link-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>Dashboard</a>
                <div class="sb-sidenav-menu-heading">Records</div>
                <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?>" href="<?= site_url('admin/manage-records') ?>"><div class="sb-nav-link-icon"><i class="bi bi-people" aria-hidden="true"></i></div>Manage Records</a>
                <div class="sb-sidenav-menu-heading">Reference Data</div>
                <a class="nav-link <?= esc($navActive['sectors'] ?? '') ?>" href="<?= site_url('admin/sectors') ?>"><div class="sb-nav-link-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>Sector Management</a>
                <a class="nav-link <?= esc($navActive['services'] ?? '') ?>" href="<?= site_url('admin/services') ?>"><div class="sb-nav-link-icon"><i class="bi bi-grid" aria-hidden="true"></i></div>Services and Programs</a>
                <a class="nav-link <?= esc($navActive['categories'] ?? '') ?>" href="<?= site_url('admin/categories') ?>"><div class="sb-nav-link-icon"><i class="bi bi-tags" aria-hidden="true"></i></div>Manage Categories</a>
                <div class="sb-sidenav-menu-heading">QR Code</div>
                <a class="nav-link <?= esc($navActive['cards'] ?? '') ?>" href="<?= site_url('admin/cards') ?>"><div class="sb-nav-link-icon"><i class="bi bi-qr-code" aria-hidden="true"></i></div>Generate</a>
                <a class="nav-link <?= esc($navActive['scanner'] ?? '') ?>" href="<?= site_url('scanner/scan') ?>"><div class="sb-nav-link-icon"><i class="bi bi-upc-scan" aria-hidden="true"></i></div>Scan</a>
                <a class="nav-link <?= esc($navActive['scanner-manage'] ?? '') ?>" href="<?= site_url('scanner/manage') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></div>Management</a>
                <a class="nav-link <?= esc($navActive['scanner-reports'] ?? '') ?>" href="<?= site_url('scanner/reports') ?>"><div class="sb-nav-link-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></div>Reports</a>
                <div class="sb-sidenav-menu-heading">Administration</div>
                <?php if ($canManageAccounts): ?>
                <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?>" href="<?= site_url('admin/accounts') ?>"><div class="sb-nav-link-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></div>Account Management</a>
                <?php endif; ?>
                <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?>" href="<?= site_url('admin/audit-trails') ?>"><div class="sb-nav-link-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>Audit Trails</a>
            </div>
        </div>
    </nav>
<?php endif; ?>
```

Also update the doc-comment variable list: `$sidebarUserUrl` is no longer used here (brand moved to topnav) — note that in the comment but keep accepting/defaulting it so callers don't break.

- [ ] **Step 2: Create `app/Views/Partials/dashboard-topnav.php`**

```php
<?php
/**
 * SB Admin 1 top navigation bar, shared by all dashboard shells.
 *
 * Variables:
 * - $brandUrl            string brand link target
 * - $user, $username, $accountLevelLabel  passed through to Partials/topbar-account-menu
 * - $accountSettingsUrl, $accountSettingsMode  optional passthrough (see topbar-account-menu)
 */
$brandUrl = $brandUrl ?? site_url('admin/dashboard');
$accountMenuData = ['user' => $user ?? [], 'username' => $username ?? 'User', 'accountLevelLabel' => $accountLevelLabel ?? 'Account'];
if (isset($accountSettingsUrl)) {
    $accountMenuData['accountSettingsUrl'] = $accountSettingsUrl;
}
if (isset($accountSettingsMode)) {
    $accountMenuData['accountSettingsMode'] = $accountSettingsMode;
}
?>
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="<?= esc($brandUrl, 'attr') ?>">
        <img src="<?= asset_url('assets/image/binan.png') ?>" alt="City of Binan Logo" height="24" class="me-2">Bi&ntilde;an Access Card MIS
    </a>
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-controls="dashboard-sidebar">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <ul class="navbar-nav ms-auto me-3 me-lg-4">
        <?= view('Partials/topbar-account-menu', $accountMenuData) ?>
    </ul>
</nav>
```

- [ ] **Step 3: Syntax check**

Run: `php -l app/Views/components/dashboard_sidebar.php && php -l app/Views/Partials/dashboard-topnav.php`
Expected: `No syntax errors detected` twice.

(Pages will render broken until Task 4 migrates the layouts — that's expected mid-branch; do NOT try to keep old and new frames working simultaneously.)

- [ ] **Step 4: Commit**

```bash
git add app/Views/components/dashboard_sidebar.php app/Views/Partials/dashboard-topnav.php
git commit -m "feat(theme): SB Admin 1 sidenav component and shared topnav partial"
```

---

### Task 4: Migrate the four layout shells to #layoutSidenav

**Files:**
- Modify: `app/Views/Admin/layout.php:66-93` and `:263-266`
- Modify: `app/Views/Employee/layout.php` (same frame, lines ~33-60 and closers before `</body>`)
- Modify: `app/Views/Viewer/layout.php` (same frame, lines ~40-70 and closers)
- Modify: `app/Views/Scanner/layout.php` (same frame, lines ~30-45 and closers)

**Interfaces:**
- Consumes: `components/dashboard_sidebar` (SB Admin nav) and `Partials/dashboard-topnav` from Task 3.
- Produces: all dashboard pages render inside `body.sb-nav-fixed > .sb-topnav + #layoutSidenav`.

Each of the 4 layouts currently has this frame (identical pattern; only the sidebar-view arguments, `asset_styles`/`asset_scripts` context names, and topbar title differ):

```html
<body>
<div id="wrapper">
    <?= view('components/dashboard_sidebar', [ ...role args... ]) ?>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm">
                <button id="sidebarToggleTop" ...>...</button>
                <div class="topbar-title"><div><h1 id="dashboard-page-title"><?= esc($pageTitle) ?></h1></div></div>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item topbar-divider d-none d-sm-block"></li>
                    <?= view('Partials/topbar-account-menu', [...]) ?>
                </ul>
            </nav>
            <main class="container-fluid dashboard-content">
                ... page content, flash alerts, $activePage switch ...
            </main>
        </div>
    </div>
</div>
... modals ...
... scripts ...
</body>
```

- [ ] **Step 1: Migrate `app/Views/Admin/layout.php`**

Replace the opening frame (`<body>` through the end of the old `<nav class="navbar ...topbar...">...</nav>` block, i.e. lines 66–91) with:

```php
<body class="sb-nav-fixed">
<?= view('Partials/dashboard-topnav', [
    'brandUrl' => $sidebarUserUrl,
    'user' => $user,
    'username' => $username,
    'accountLevelLabel' => $accountLevelLabel,
]) ?>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <?= view('components/dashboard_sidebar', [
            'navActive' => $navActive,
            'canManageAccounts' => $canManageAccounts,
            'sidebarRoleClass' => $sidebarRoleClass,
            'sidebarUserUrl' => $sidebarUserUrl,
            'sidebarScannerOnly' => false,
        ]) ?>
    </div>
    <div id="layoutSidenav_content">
```

Then change the `<main>` opener (old line 93) to:

```php
        <main class="container-fluid px-4 dashboard-content">
            <h1 class="mt-4" id="dashboard-page-title"><?= esc($pageTitle) ?></h1>
```

(The page-title `<h1>` moves from the old topbar into the content column, SB Admin 1 style. Keep the `id` — do not drop it.)

And replace the frame closers (old lines 263–266):

```php
            </main>
        </div>
    </div>
</div>
```

with:

```php
            </main>
    </div>
</div>
```

(one wrapper div fewer: old frame had `#wrapper > #content-wrapper > #content`; new frame is `#layoutSidenav > #layoutSidenav_content` — `</main>` closes, then `#layoutSidenav_content`, then `#layoutSidenav`.)

Everything between `<main>` and `</main>` (flash alerts, `$activePage` switch, sub-view calls) stays byte-identical. Modals and script tags after the frame stay where they are.

- [ ] **Step 2: Verify Admin layout structure**

Run: `php -l app/Views/Admin/layout.php && grep -c 'layoutSidenav\|sb-nav-fixed' app/Views/Admin/layout.php && grep -c 'id="wrapper"\|content-wrapper\|sidebarToggleTop\|topbar-title' app/Views/Admin/layout.php`
Expected: no syntax errors; first grep ≥ 4; second grep = 0.

- [ ] **Step 3: Repeat the identical migration for the other three layouts**

Same transformation, with per-layout specifics:

- `app/Views/Employee/layout.php` — brand URL: `site_url('employee/dashboard')`; sidebar args unchanged from its current `view('components/dashboard_sidebar', ...)` call.
- `app/Views/Viewer/layout.php` — brand URL: `site_url('viewer/dashboard')`; sidebar args unchanged.
- `app/Views/Scanner/layout.php` — brand URL: `site_url('scanner/scan')`; sidebar args unchanged (`'sidebarScannerOnly' => true` variant passes `activeTab`). If a layout passes `accountSettingsUrl`/`accountSettingsMode` to `topbar-account-menu` today, pass the same keys through to `Partials/dashboard-topnav`.

Read each file first — the topbar/account-menu argument lists differ slightly per role; preserve exactly what each currently passes.

- [ ] **Step 4: Verify all four + tests**

Run: `for f in Admin Employee Viewer Scanner; do php -l app/Views/$f/layout.php; done && grep -rln 'id="wrapper"\|sidebarToggleTop' app/Views && echo LEFTOVER || echo CLEAN`
Expected: 4× no syntax errors, then `CLEAN`.
Run: `vendor/bin/phpunit`
Expected: green.

- [ ] **Step 5: Commit**

```bash
git add app/Views && git commit -m "feat(theme): migrate dashboard shells to SB Admin 1 layoutSidenav frame"
```

---

### Task 5: Shadow purge + retire old sidebar-toggle JS

**Files:**
- Modify: `public/css/login.css:50`, `public/css/lookupmanagement.css:251,336`, `public/css/accounts.css:9,414`, `public/css/familymodal.css:95,899,1267`, `public/css/session-timeout.css:20`
- Modify: `app/Views/Admin/layout.php`, `app/Views/Employee/layout.php`, `app/Views/Viewer/layout.php`, `app/Views/Scanner/layout.php`, `app/Views/Scanner/reports.php`, `app/Views/Scanner/scan.php` (shadow utility classes)
- Modify: `public/assets/js/dashboard/view-interactions.js:21-94,227`

**Interfaces:**
- Consumes: upstream `scripts.js` (Task 1) now owns the sidebar toggle via `#sidebarToggle` + `sb-sidenav-toggled`.
- Produces: nothing downstream; final cleanup task.

- [ ] **Step 1: Remove elevation drop shadows from page CSS**

Delete ONLY these `box-shadow` declaration lines (elevation shadows):
`login.css:50`, `lookupmanagement.css:251`, `lookupmanagement.css:336`, `accounts.css:9`, `accounts.css:414`, `familymodal.css:95`, `familymodal.css:899`, `familymodal.css:1267`, `session-timeout.css:20`.

KEEP these (focus rings / resets — do not touch): `login.css:97`, `login.css:123`, `scanner-scan.css:6`, `familymodal.css:619`, `lookupmanagement.css:266`, `accounts.css:106`.

Verify: `grep -rn 'box-shadow' public/css/ | grep -v '0 0 0' | grep -v 'none'`
Expected: no output.

- [ ] **Step 2: Strip Bootstrap shadow utility classes from views**

In the 6 view files listed above, remove `shadow-sm`, `shadow`, and `shadow-lg` tokens from `class="..."` attributes (~30 occurrences; e.g. `class="stat-card ... card shadow-sm h-100 py-2"` → `class="stat-card ... card h-100 py-2"`). Cards keep their default Bootstrap border — that's the SB Admin 1 flat look.

Verify: `grep -rn 'shadow' app/Views | grep -v '\.css'`
Expected: no output.

- [ ] **Step 3: Remove `bindDashboardSidebar()` from view-interactions.js**

Upstream `scripts.js` now handles the toggle (`#sidebarToggle` click → `sb-sidenav-toggled` on body, persisted in localStorage). In `public/assets/js/dashboard/view-interactions.js`:
- Delete the whole `bindDashboardSidebar` function (lines 21–94).
- Delete its call `bindDashboardSidebar();` (line 227).
- Update the file's header comment: drop any sidebar mention.

Verify: `grep -n 'bindDashboardSidebar\|sidebarToggle' public/assets/js/dashboard/view-interactions.js`
Expected: no output. Also `node --check public/assets/js/dashboard/view-interactions.js` → exits 0 (skip if node absent; then eyeball braces).

- [ ] **Step 4: Full verification**

```bash
vendor/bin/phpunit
php spark routes > /dev/null && echo ROUTES-OK
grep -rn 'sb-admin-adapter\|bg-gradient-primary\|sidebar-brand\|sidebar-heading\|sidebar-divider' app/ public/css/ && echo LEFTOVER || echo CLEAN
```

Expected: phpunit green, `ROUTES-OK`, `CLEAN`.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(theme): flat SB Admin 1 look — drop shadows and legacy sidebar toggle"
```

---

### Task 6: Smoke-test handoff

- [ ] **Step 1: Serve and report**

Start `php spark serve` (use the intl-enabled `php`, not XAMPP's — see repo memory) and tell the user the branch is ready for visual triage. Checklist for the user (executor cannot see rendered pages):

- login → role redirect (login page should look unchanged)
- admin, employee, viewer dashboards: dark sidenav, dark topnav with brand, flat cards
- sidebar toggle collapses/expands and persists across reloads
- family create/edit modal opens and lays out correctly
- DataTables pages (manage records, audit trails) styled correctly
- scanner scan/manage/reports pages
- no browser-console 404s for CSS/JS

No commit; this task produces the report back to the user.
