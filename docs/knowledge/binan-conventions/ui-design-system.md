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
| search   | btn btn-success            | run a server-side search      |
| clear    | btn btn-danger             | full reset of a toolbar       |
| add      | btn btn-primary            | create a record (modal)       |
| import   | btn btn-warning            | bulk import                   |
| filter   | btn btn-outline-secondary  | open a filter panel           |

New role: add the row here first, then to the helper map, then a
`UiHelperTest` assertion.

## Rule 2: Records toolbar anatomy

`components/records_toolbar.php`. One Bootstrap grid row:
keyword input (grows) | Filters dropdown | two btn-groups separated by gap
(search actions: Search + Clear; record actions: Add + Import, gated by
`$canEdit`). Never one crammed btn-group, never w-100/h-100 stretching.

## Rule 3: Filter panel

Wide `.dropdown-menu` (`.records-filter-panel`, rules at the bottom of
`public/css/managerecord.css`) with side-by-side columns and
`data-bs-auto-close="outside"`. Checkboxes and radios live-apply (debounced
in JS, see `FILTER_DEBOUNCE_MS`); NO Apply or Reset buttons inside the panel.
Long option lists get a type-to-narrow input (`[data-records-narrow]`).
Stock Bootstrap only; no drill-in submenus.

## Rule 4: Pills and the one-role-per-control rule

Applied filters render as pills (`components/filter_pills.php` container, JS
renders the pills). Exactly three clear controls, no overlap:
checkbox/radio applies or unapplies; pill x removes one filter; toolbar
Clear resets everything (keyword + filters + sort). No "Clear all" link, no
panel Reset.

## Rule 5: Dual search wording

Toolbar input searches the whole database server-side, placeholder
"Search entire database (incl. members)...". The in-table DataTables input
filters loaded rows only, placeholder "Filter loaded results...". Keep both
placeholders verbatim when retrofitting other tabs.

## Retrofit status

- manage-records: done (feat/manage-records-ui)
- lookups (sectors/services/categories), accounts, audit-trails,
  distribution tabs: pending; reuse records_toolbar/filter_pills with
  page-specific filter groups
