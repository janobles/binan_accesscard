# Target Theme: SB Admin 1

**Decision (spec 2026-07-06): SB Admin 1** — startbootstrap-sb-admin v7+,
Bootstrap 5-based. https://startbootstrap.com/template/sb-admin •
https://github.com/StartBootstrap/startbootstrap-sb-admin

**SB Admin 2 rejected:** pinned to Bootstrap 4.6; would fight the repo's
Bootstrap 5.3.3 base. Do not reopen.

This doc is forward-looking: markup shapes below come from the upstream
SB Admin 1 template (URLs, not repo cites); repo cites appear only for
current-state references.

## SB Admin 1 conventions (v7, Bootstrap 5)

Page frame (upstream `dist/index.html`):

```html
<body class="sb-nav-fixed">
  <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">...</nav>
  <div id="layoutSidenav">
    <div id="layoutSidenav_nav">
      <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
          <div class="nav">
            <div class="sb-sidenav-menu-heading">Core</div>
            <a class="nav-link" href="#"><div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>Dashboard</a>
          </div>
        </div>
        <div class="sb-sidenav-footer">...</div>
      </nav>
    </div>
    <div id="layoutSidenav_content">
      <main><div class="container-fluid px-4">...</div></main>
      <footer class="py-4 bg-light mt-auto">...</footer>
    </div>
  </div>
</body>
```

Content patterns:
- Page header: `<h1 class="mt-4">` + `<ol class="breadcrumb mb-4">`.
- Cards: stock BS5 `card` with `card-header` (`<i>` icon + title), `mb-4`.
- Tables: plain `<table>` inside a card body, enhanced by DataTables —
  matches the repo's existing DataTables bootstrap5 build
  (`app/Helpers/asset_helper.php:38`).
- Theming via `sb-sidenav-dark` / `sb-sidenav-light` variants + SCSS
  variables.

## Migration map (adapter → SB Admin 1)

| Current (adapter.md) | SB Admin 1 |
|---|---|
| `#wrapper` flex frame | `#layoutSidenav` / `#layoutSidenav_nav` / `#layoutSidenav_content` |
| `.sidebar` + `.bg-gradient-primary` | `.sb-sidenav sb-sidenav-dark` (re-skin to Biñan green via SCSS/vars) |
| `.sidebar-brand*` | `.sb-topnav .navbar-brand` (brand moves to topbar) |
| `.sidebar-heading` | `.sb-sidenav-menu-heading` |
| `#sidebarToggle` | `#sidebarToggle` button in topnav (`sb-sidenav-toggled` on body) |
| custom topbar markup in shells | `.sb-topnav` |

Keep the `--sb-*` token approach when porting — map SB Admin 1's SCSS
variables onto the existing tokens rather than scattering colors.

## Status

The swap SHIPPED on branch `feature/sbadmin1-theme-swap`: shells use
`body.sb-nav-fixed` + `#layoutSidenav` (`app/Views/Admin/layout.php:66`),
the adapter is deleted (`adapter.md`), and panels render through the
card/data_table components
(`docs/knowledge/binan-conventions/views-bootstrap.md`). The inline-style
non-conformers previously listed here were fixed — see
`docs/knowledge/violations.md`.
