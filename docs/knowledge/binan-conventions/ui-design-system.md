# UI Design System

**Scope:** button roles, records toolbar anatomy, filter panel + pills, dual
search. Reference implementation: `admin/manage-records`
(`app/Views/Family/list.php` + `components/records_toolbar.php`).
Spec receipt: `docs/superpowers/specs/2026-07-12-manage-records-ui-design.md`.

## Rule 1: Button colors come from btn()

`btn(string $role)` (`app/Helpers/ui_helper.php`, autoloaded) is the single
source of truth. Never hardcode a `btn-*` color class on a toolbar action.

| Role     | Classes                    | Meaning                       |
|----------|----------------------------|-------------------------------|
| search   | btn btn-primary            | run a server-side search      |
| clear    | btn btn-danger             | full reset of a toolbar       |
| add      | btn btn-success            | create a record (modal)       |
| import   | btn btn-warning            | bulk import                   |
| filter   | btn btn-outline-secondary  | open a filter panel           |

Buttons use stock Bootstrap colors only — theme.css must NOT re-tint
`.btn-primary` to Biñan green (that made Search and Add two competing
greens). Green buttons are Bootstrap's `#198754` success. Biñan green stays
on the shell (topnav/sidenav/links), never on buttons.

New role: add the row here first, then to the helper map, then a
`UiHelperTest` assertion.

## Rule 2: Records toolbar anatomy

`components/records_toolbar.php`. One Bootstrap grid row:
keyword input (grows) | Filters dropdown | two btn-groups separated by gap
(search actions: Search + Clear; record actions: Add + Import, gated by
`$canEdit`). Never one crammed btn-group, never w-100/h-100 stretching.

## Rule 3: Filter panel

`.dropdown-menu.records-filter-panel` (rules at the bottom of
`public/css/managerecord.css`) with `data-bs-auto-close="outside"`.
Checkboxes and radios live-apply (debounced in JS, see `FILTER_DEBOUNCE_MS`);
NO Apply or Reset buttons inside the panel. Long option lists get a
type-to-narrow input (`[data-records-narrow]`) and scroll inside a
viewport-capped `.records-filter-list`. Stock Bootstrap only; no drill-in
submenus.

Sizing is content-driven, never fixed: the base panel is
`width: max-content` capped at the viewport, so a lone Status group renders
as a small flyout. Only a genuinely multi-column panel (Manage Records'
sector/barangay/status) adds `.records-filter-panel--wide` for real width.

## Rule 4: Pills and the one-role-per-control rule

Applied filters render as pills (`components/filter_pills.php` container, JS
renders the pills). Exactly three clear controls, no overlap:
checkbox/radio applies or unapplies; pill x removes one filter; toolbar
Clear resets everything (keyword + filters + sort). No "Clear all" link, no
panel Reset.

## Rule 5: Dual search wording

Toolbar input searches the whole database server-side; its placeholder names
the entity so the scope is obvious per tab: "Search all family records...",
"Search all sectors...", "Search all services...", "Search all categories...",
"Search all audit logs...", "Search all my activity...". The in-card input
only searches what is already loaded — placeholder "Search this page..."
everywhere (single-source pages like accounts say "Search accounts...").

## Rule 6: In-card controls row

Follow Manage Records: page search on the LEFT, "Show N entries" on the
RIGHT (`.records-table-controls`, space-between). The page search is a small
input-group with an integrated `btn-primary` search-icon button. No "Search:"
label text.

## Retrofit status

- The toolbar always renders ABOVE the page's card (never inside it), pills
  row directly under it — see `Family/list.php` for the standard.
- manage-records: done (feat/manage-records-ui). AJAX flavor: filter panel +
  pills wired by `assets/js/dashboard/family-datatable.js`.
- lookups (sectors/services/categories), audit-trails, employee activity:
  done (feat/retrofit-toolbar-conventions) via
  `components/records_toolbar_server.php` — same Bootstrap-grid anatomy as
  records_toolbar, wired by the shared
  `assets/js/dashboard/records-filter-panel.js` (radios inside the GET form,
  change = submit, pills from server state). Options that mean "no filter"
  (Active default, All) get no pill label, so they never render pills.
- accounts: done, client mode — the list is fully loaded, so the panel radios
  carry `data-records-client` wiring (no submit; accounts-modal.js filters
  rows, records-filter-panel.js renders pills).
- distribution tabs: btn() roles + placeholder wording done; the
  distributions log keeps its client-side aid-type select (no server search
  to live-apply against). Batches tab has plain form buttons, not a toolbar —
  out of scope.
