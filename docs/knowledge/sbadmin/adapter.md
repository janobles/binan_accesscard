# SB Admin Adapter (RETIRED)

The homegrown adapter stylesheet (`public/css/sb-admin-adapter.css`) was
**deleted** in the SB Admin 1 theme swap
(spec `docs/superpowers/specs/2026-07-07-sbadmin1-theme-swap-design.md`,
branch `feature/sbadmin1-theme-swap`). The shells now load the genuine
vendored theme `public/assets/sb-admin/css/styles.css:1` plus upstream
`public/assets/sb-admin/js/scripts.js:1` (sidebar toggle).

What replaced each adapter concern:

- Shell frame / sidebar / topnav classes → upstream SB Admin 1 markup
  (`docs/knowledge/sbadmin/target-theme.md`).
- Card/panel chrome → the props-only components
  `app/Views/components/card.php:1`, `app/Views/components/data_table.php:1`,
  `app/Views/components/table_footer.php:1`
  (see `docs/knowledge/binan-conventions/views-bootstrap.md`).
- Theme tokens (`--sb-*`/`--ui-*`) → gone; current baseline is pure upstream
  defaults. A future Biñan re-skin should reintroduce tokens on top of
  SB Admin 1's SCSS variables, not resurrect the adapter.

Do not reference `sb-admin-adapter.css`, `.sidebar-brand*`,
`.bg-gradient-primary`, or `#wrapper`/`#content-wrapper` in new work.
