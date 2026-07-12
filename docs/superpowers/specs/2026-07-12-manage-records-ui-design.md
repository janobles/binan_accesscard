# Manage Records UI/UX + Design System Foundation

Date: 2026-07-12
Status: Approved design, pending implementation plan
Scope: admin/manage-records page (Family/list-body.php and its JS), plus a small
design system layer (conventions doc, btn() helper, two view components) that
later branches will apply to the other tabs.

## Problem

The manage-records toolbar has grown ad hoc:

- Two search inputs with no visible distinction. The top one searches the whole
  database server side (including family members, returning their family's row);
  the in-card DataTables box only filters rows already loaded. Users confuse them.
- Three separate filter dropdowns (sector, barangay, status) clutter the toolbar.
- Search, Clear, Add, and Import are crammed into one stretched btn-group with
  w-100/h-100 hacks, mixing search actions with record actions (flagged by
  supervisor as not Bootstrap faithful).
- QR NO. column is not sortable and the table has no defined default sort.
- No written UI conventions, so every tab styles buttons and toolbars differently.

Data scale: about 5k family records max. Table already uses server-side
DataTables AJAX paging against the indexed /data endpoint (V17 indexes), so the
client never loads the full dataset.

## Design decisions (agreed during brainstorming)

1. Flat multi-column filter panel, not a two-step drill-in menu. Bootstrap 5 has
   no native submenu; drill-in would need ~100+ lines of bespoke JS for three
   filter dimensions. A wide dropdown with side-by-side columns shows everything
   in one click using stock Bootstrap only.
2. Filters live-apply. Checking a box updates the table immediately (debounced
   about 350ms in JS). No Apply or Reset buttons inside the panel.
3. No redundant clear controls. Each control has exactly one role:
   - checkbox / status radio: apply or unapply one filter
   - pill x: remove one filter from outside the panel
   - toolbar Clear button: reset everything (keyword, filters, sort)
4. Buttons stay plain Bootstrap classes, standardized by a btn() role helper and
   a conventions doc. No button view component (single elements do not earn the
   indirection; composites do).
5. Keyword search stays explicit (Search button / Enter). Mixed model: live
   facets, explicit keyword. Matches common convention and current behavior.
6. Debounce is client-side JS only. CI4's Throttler is server-side rate limiting
   and is not needed for an authenticated admin hitting an indexed endpoint.

## Page layout

```
Toolbar (outside the card)
  [ Search entire database (incl. members)...    ] [ Filters v ]
                             [Search] [Clear] | [Add] [Import]
  Pills: [Sector: IP x] [Barangay: Canlalay x] [Status: Active x]

Card: Family Records
  [Filter loaded results...]              Show [25 v] entries
  QR NO.^ | HEAD/MEMBER NAME | SECTOR | ADDRESS | BIRTHDAY | ACTIONS
```

- Proper Bootstrap grid (row/col-12/col-md-*), no w-100 h-100 stretching.
- Two separate btn-groups with a gap: search actions (Search, Clear) and record
  actions (Add, Import). Add/Import render only when $canEdit.
- Stacks vertically on mobile via the grid.

## Filter panel

Wide dropdown-menu (min-width around 560px desktop, columns stack col-12
col-md-4 on mobile) containing three columns:

- SECTOR: checkbox list, scrollable (max-height + overflow-auto).
- BARANGAY: type-to-narrow text input above a scrollable checkbox list. The
  narrow input is a JS keyup that hides non-matching labels, nothing more.
- STATUS: radio group (Active / Archived / All).

Behavior:

- data-bs-auto-close="outside" keeps the panel open while checking boxes (same
  attribute the current per-filter dropdowns use).
- Each change fires the debounced table reload and re-renders the pills row.
- Pill format: "Sector: IP - Indigenous People x". Clicking x unchecks the
  matching input and reloads.

Filter dimensions on this page are exactly sector, barangay, status. Aid types,
programs, and batches belong to the distributions pages, which will reuse the
same components with different groups in a later branch.

## Dual search

- Top input: server-side keyword search. Placeholder "Search entire database
  (incl. members)...". Submits via the Search button or Enter. Matches member
  names and QR numbers; the result row is the member's family.
- In-card input: DataTables client-side search over loaded rows only.
  Placeholder "Filter loaded results...". Lives inside the card next to the
  "Show N entries" control.
- Placement plus placeholder wording is the disambiguation; no extra helper UI
  unless testing shows it is still confusing.

## Table changes

- QR NO. column becomes sortable; default sort is QR NO. ascending (1 to n).
  Requires the DataTables init to declare the default order and the /data
  endpoint to honor that sort column.
- ACTIONS column stays unsortable. Row actions (row-actions.php) are unchanged;
  actions-column UX is a later pass.

## Design system deliverables

| File | Purpose |
| --- | --- |
| docs/knowledge/binan-conventions/ui-design-system.md | Button role table, toolbar anatomy, filter panel + pills pattern, dual-search convention |
| app/Helpers/ui_helper.php with btn(string $role): string | Role to Bootstrap class map, about 15 lines |
| app/Views/components/records_toolbar.php | Search input + Filters panel + action groups. Props: route base, filter groups, current keyword/selections, canEdit |
| app/Views/components/filter_pills.php | Applied-filter pill row |

Button role map (initial):

| Role | Classes |
| --- | --- |
| search | btn btn-success |
| clear | btn btn-danger |
| add | btn btn-primary |
| import | btn btn-warning |
| filter toggle | btn btn-outline-secondary |

The doc is the source of truth; extend the map there first when new roles appear.

## Consumers and retrofit plan

Family/list-body.php is rewritten as the reference consumer of the new
components. Accounts, audit trails, and distribution tabs retrofit in a
follow-up branch once the pattern is proven here. Out of scope now.

## Backend impact

Minimal. The /data endpoint already accepts q, sectorID[], barangay[], and
status. Live-apply only calls it more often, absorbed by the debounce and V17
indexes. Expected new code is one small JS file (records-filter.js) for panel
behavior, pills, and debounce. No schema changes, no migrations, no new routes.

## Testing

- vendor/bin/phpunit before and after (no controller logic changes expected
  beyond view data assembly, which stays in DashboardPageBuilder).
- Manual smoke checklist:
  - keyword search by a member's name returns the family row
  - each filter type adds a pill and updates the table; pill x removes it
  - Clear resets keyword, filters, and sort in one click
  - default table order is QR NO. 1 to n; QR header toggles sort
  - toolbar stacks correctly on a narrow viewport
  - viewer role sees no Add/Import buttons; admin does
  - audit trail behavior untouched (read-only page changes only)
