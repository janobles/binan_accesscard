# Frontend

Server-rendered Bootstrap with the SB Admin 1 theme. No build step, no bundler,
no framework. Views compose Bootstrap classes, page-specific CSS lives in page
CSS files, and a small set of props-only components carries the shapes Bootstrap
does not ship.

Manage Records (`app/Views/Family/list.php`) is the design source of truth. When
a rule below is ambiguous, look at that page and match it.

## Which Bootstrap you are actually writing against

This trips people up, so it comes first.

The repository contains **two different Bootstrap CSS builds**, and which one is
loaded depends on the page.

| Context | CSS loaded | Bootstrap version |
|---|---|---|
| Dashboard pages (`head` context) | `assets/sb-admin/css/styles.css` | the build compiled into SB Admin v7.0.7 |
| Login (`login` context) | `assets/bootstrap/css/bootstrap.min.css` | 5.3.3 |
| All pages, JavaScript | `assets/bootstrap/js/bootstrap.bundle.min.js` | 5.3.3 |

The SB Admin stylesheet has Bootstrap compiled into it, and it is a pre-5.3
build. It contains none of 5.3's additions: no `--bs-emphasis-color`, no
`--bs-primary-bg-subtle`, no `color-scheme`, no `data-bs-theme`, and no
`.nav-underline`.

**So a 5.3-only class used on a dashboard page silently does nothing.** No error,
no warning, just an element that does not look the way the documentation said it
would. If you are copying a snippet out of the Bootstrap 5.3 docs and it has no
effect, check whether the class is a 5.3 addition before you look anywhere else.

Two more consequences worth holding onto. Anything you verify on the login page
is verified against a different stylesheet than the dashboard uses. And because
the JavaScript bundle is 5.3.3 while the dashboard CSS is not, JavaScript
behaviour and CSS support do not necessarily agree.

`docs/reference/version-pins.md` records this.

## The shells

There is exactly one dashboard shell, `app/Views/layout.php`, shared by every
staff role. It owns `<html>`, the head assets, the sidebar, and the topbar, and
renders the body view the caller names. Callers pass `activePage` (the manifest
key, which also supplies the page title), `role`, `bodyView`, and `bodyData`.

A page never branches on the role to pick a shell. The role-prefixed `Admin/`,
`Employee/`, and `Viewer/` layouts are deleted.

Standalone `<html>` is correct in exactly five places: `layout.php`, the scanner's
`kiosk-layout.php` and `simple-layout.php`, `Auth/login.php`, the PDF views, and
the framework error pages under `app/Views/errors/html/`. A page that grows its
own shell loses the sidebar and every convention on this page. The import review
did this once, and it was undone.

Chapter 15 covers the scanner shells and why they exist.

## The SB Admin theme

The theme is vendored genuine SB Admin 1 (`startbootstrap-sb-admin` v7.0.7),
loaded as `assets/sb-admin/css/styles.css` plus `assets/sb-admin/js/scripts.js`
for the sidebar toggle.

SB Admin 2 was considered and rejected: it is pinned to Bootstrap 4.6 and would
fight the repo's Bootstrap 5 base. That decision is closed.

There used to be a homegrown adapter stylesheet, `public/css/sb-admin-adapter.css`,
translating between hand-written markup and theme classes. **It was deleted.** Do
not reference it, and do not reference `.sidebar-brand*`, `.bg-gradient-primary`,
`#wrapper`, or `#content-wrapper` in new work. The shells use upstream SB Admin
markup directly: `sb-nav-fixed`, `sb-topnav`, `sb-sidenav`, `layoutSidenav_nav`,
`layoutSidenav_content`.

The `--sb-*` and `--ui-*` theme tokens went with the adapter. The current baseline
is pure upstream defaults, and a future Biñan re-skin should reintroduce tokens on
top of SB Admin's own SCSS variables rather than resurrect the adapter.

## How CSS loads

`app/Helpers/asset_helper.php` holds the manifest, and `layout.php` renders it.
The chain is the vendored SB Admin theme with Bootstrap compiled in, then
bootstrap-icons, then `css/theme.css`, then the DataTables bootstrap5 build, then
per-page CSS.

New page styles go in a page CSS file registered in that manifest. Never
hand-add a `<link>` to a shell.

Asset loading is context based, which is how the FullCalendar bundle loads only on
the distribution page's Schedule tab rather than on every dashboard page.

`public/css/theme.css` carries the header every other stylesheet is matched
against: what it styles, which manifest context loads it, and its scope boundary.

## Buttons

`btn(string $role)` in `app/Helpers/ui_helper.php` is the single source of truth
for button colour. **Never hardcode a `btn-*` colour class on a toolbar action.**

| Role | Classes | Meaning |
|---|---|---|
| `search` | `btn btn-primary` | run a server-side search |
| `generate` | `btn btn-primary` | produce output from a selection; blue rather than add-green, because it is not record creation |
| `clear` | `btn btn-danger` | full reset of a toolbar |
| `add` | `btn btn-success` | create a record |
| `import` | `btn btn-warning` | bulk import |
| `filter` | `btn btn-outline-secondary` | open a filter panel |
| `save` | `btn btn-primary` | commit a full-page form |

Buttons use stock Bootstrap colours only. **`theme.css` must not re-tint
`.btn-primary` to Biñan green.** That was tried, and it made Search and Add two
competing greens with no visual hierarchy between them. Green on a button is
Bootstrap's success `#198754`. Biñan green stays on the shell: topnav, sidenav,
links. Never on buttons.

Adding a role means three edits in order: this table, then the helper map, then a
`UiHelperTest` assertion.

## List page anatomy

Every list tab is the same composition as `Family/list.php`. No hand-rolled layout
markup, no page-specific layout CSS.

1. **The toolbar, above the card.** `components/records_toolbar` for the AJAX
   flavour, `components/records_toolbar_server` for everything else. The pills row
   renders directly beneath it. The toolbar is never inside the card.
2. **`components/card`** with stock `.card` chrome. Page CSS must never override
   the card border, radius, or background, re-pad the card body, or set
   `height: 100%`. That last one caused dead space under short tables.
3. **`components/table_controls`** as the first thing inside the body: page search
   on the left, "Show N entries" on the right. Never copy its markup inline.
4. **The table.** `.manage-record-table` typography is canonical: 0.7rem bold
   uppercase headers, 0.85rem cells, no bold name cells. Page CSS may add column
   widths and badges, nothing else.
5. **`components/table_footer`** as the card footer for server pagination.

Page CSS files hold only page-unique rules: badges, modals, column widths.
Anything about toolbars, control rows, card chrome, or cell typography belongs to
the shared layer. **If a rule would apply to two pages, it goes in the shared
layer.**

### The toolbar

One Bootstrap grid row: the keyword input grows to fill, then the Filters
dropdown, then two button groups separated by a gap. Search actions (Search,
Clear) in one, record actions (Add, Import, gated on `$canEdit`) in the other.

Never one crammed button group, and never `w-100` or `h-100` stretching.

### The filter panel

`.dropdown-menu.records-filter-panel` with `data-bs-auto-close="outside"`.
Checkboxes and radios live-apply, debounced in JavaScript. **There are no Apply or
Reset buttons inside the panel.** Long option lists get a type-to-narrow input and
scroll inside a viewport-capped list. Stock Bootstrap only, no drill-in submenus.

Sizing is content-driven and never fixed. The base panel is `width: max-content`
capped at the viewport, so a panel with one Status group renders as a small
flyout. Only a genuinely multi-column panel adds `.records-filter-panel--wide`.

### Pills, and one role per control

Applied filters render as pills. There are exactly three controls that clear
things, and they do not overlap:

- the checkbox or radio applies and unapplies its own filter,
- a pill's x removes that one filter,
- the toolbar Clear resets everything: keyword, filters, and sort.

No "Clear all" link, no panel Reset. Options that mean "no filter", such as the
Active default or All, get no pill at all.

### Search wording

Two search inputs with different scopes, and the placeholder is what tells them
apart.

The toolbar input searches the whole database server-side, so its placeholder
names the entity: "Search all family records...", "Search all sectors...",
"Search all services...", "Search all categories...", "Search all audit logs...",
"Search all my activity...".

The in-card input searches only what is already loaded: "Search this page..."
everywhere. Single-source pages such as accounts say "Search accounts...".

### The in-card controls row

Page search on the left, "Show N entries" on the right, spaced apart, built by
`components/table_controls`. The page search is a small input group with an
integrated `btn-primary` search-icon button and no "Search:" label.

Default page size is **25** everywhere, with options 10, 25, 50, and 100. That
default is written in three places that must agree:
`DashboardPageBuilder::recordsPerPage()`, the `table_controls` component, and the
view sentinels that strip `per_page` from URLs. Manage Records is a DataTables
grid rather than the shared component, so its native length control is forced to
small and muted in `managerecord.css` to read identically, with `pageLength` 25.

## Components

Shared fragments are partials; repeated markup across two views means extracting
one.

`components/dashboard_sidebar.php` renders every link, heading, and active state
from `Config\Navigation::linksFor($role)`. Never hand-write a nav item; add a
manifest entry (chapter 10).

`components/card.php` is the generic panel shell following SB Admin card anatomy:
header with icon and title, body, optional footer. Body content comes from a named
view or from `bodyHtml`, and JavaScript scope hooks pass through `attrs`. New
panels must use it rather than hand-rolled card markup.

A table is content, so it goes in a body partial: write the `<table>` there, wrap
it in `card`, put `table_controls` above and `table_footer` in the footer. There
is deliberately **no** table component taking rows as data. One existed, took
`list<list<raw HTML>>`, and pushed escaping onto every caller. It was deleted
rather than migrated onto.

### Components Bootstrap does not ship

Bootstrap has no stepper and no empty-state component. Build from utilities rather
than inventing fake theme classes.

**Empty state:** a centred `py-5` block, a large muted bootstrap-icon
(`display-3 text-secondary`), a bold title, and a `text-muted small` hint. The
scanner's empty state is canonical, and it doubles as the lookup-error surface by
swapping the icon and text.

**Stepper:** `app/Views/components/stepper.php` exists for genuine multi-step
flows, and it is presentational only. It computes no state and loads no
JavaScript: the caller decides which step is current, and step numbers come from
the loop. A step with an `href` renders as a link, and without one as a span. The
indicator is `aria-hidden` because the ordered list already conveys position, and
`done` and `error` states prefix a visually hidden word so state is never conveyed
by colour alone.

Prefer not building one where numbered labels would do. On the scanner, "1.
Subsidy type", "2. Scan" plus an attention state on the pending field reads just
as well.

## Escaping

Escape every dynamic value in a view. `esc($v, 'attr')` is the house default and
is required for a value landing in an unquoted attribute or assembled into markup
by hand.

Inside a double-quoted attribute, the default html context is equally safe and is
often the better choice. Both escape quotes and angle brackets, so neither is
injectable, but the attr context also encodes spaces and `#`. That turns an anchor
href into `&#x23;section-head` and makes the markup unreadable. The stepper
component documents this decision in its header, and it is the reference for the
distinction.

## Inline styles

Do not use `style="..."` attributes. Views compose Bootstrap utilities and
components plus SB Admin's shell classes.

The exceptions are the PDF views, where there is no stylesheet to load, and the
framework error pages.

The reasoning is migration cost: a theme change retiles the shells and components,
and views that stuck to Bootstrap classes migrate for free while inline styles
have to be hunted down one at a time. Past offenders are tracked in
`docs/reference/violations.md`.

Page-specific classes live in that page's CSS file and build on Bootstrap CSS
variables, so they survive a theme change.

## Density and accessibility

The interface is used by LGU staff spanning a wide range of ages and comfort with
computers, often for hours at a time on data-heavy screens. Two rules come out of
that and they pull against each other, so both are stated.

**Data-dense views can be compact.** Tables and dashboards use reduced padding and
13 to 14 pixel type to minimise scrolling and maximise what a power user can see
at once.

**Interactive elements keep an accessible floor.** Buttons and inputs maintain a
minimum 44-pixel touch target even in compact views. Compactness applies to rows
of data, never to the things people click.

Beyond that: no glassmorphism, no heavy shadows. Colour is never the only carrier
of meaning. Tokens rather than ad-hoc hex values.

## Tabs and page composition

Tabs are segmented tabs, `nav-pills` with `.segmented-tabs`, not folder tabs.

A page is one KPI row followed by labelled sections, with each section's actions
outside its card rather than inside it. Do not render a full-height empty chart as
a placeholder; an empty state is better than a chart of nothing.

## Interface copy

Text that ships in the interface follows the interface register in the
`writing-voice` skill, not the register of this handbook. Noun labels, help text
only where behaviour is invisible, and the user's vocabulary rather than the
schema's.

## A reviewer false positive to ignore

`h-100` is a plain Bootstrap sizing utility and is used by the house style. It is
not an SB Admin Pro demo class, and a reviewer flagging it is wrong. The Pro-only
markers this repo actually bans are `border-left-*` and `text-xs text-uppercase`.

## Rules

Copied from the conventions this codebase is held to. Terse on purpose.

### Design system

**Scope:** button roles, records toolbar anatomy, filter panel + pills, dual
search. Reference implementation: `/records`
(`app/Views/Family/list.php` + `components/records_toolbar.php`).
Spec receipt: `docs/superpowers/specs/2026-07-12-manage-records-ui-design.md`.

#### Rule 1: Button colors come from btn()

`btn(string $role)` (`app/Helpers/ui_helper.php`, autoloaded) is the single
source of truth. Never hardcode a `btn-*` color class on a toolbar action.

| Role     | Classes                    | Meaning                       |
|----------|----------------------------|-------------------------------|
| search   | btn btn-primary            | run a server-side search      |
| generate | btn btn-primary            | produce output from a selection (e.g. Control Numbers); blue, not add-green, because it is not record creation |
| clear    | btn btn-danger             | full reset of a toolbar       |
| add      | btn btn-success            | create a record (modal)       |
| import   | btn btn-warning            | bulk import                   |
| filter   | btn btn-outline-secondary  | open a filter panel           |
| save     | btn btn-primary            | commit a full-page form (e.g. Data Entry) |

Buttons use stock Bootstrap colors only - theme.css must NOT re-tint
`.btn-primary` to Biñan green (that made Search and Add two competing
greens). Green buttons are Bootstrap's `#198754` success. Biñan green stays
on the shell (topnav/sidenav/links), never on buttons.

New role: add the row here first, then to the helper map, then a
`UiHelperTest` assertion.

#### Rule 2: Records toolbar anatomy

`components/records_toolbar.php`. One Bootstrap grid row:
keyword input (grows) | Filters dropdown | two btn-groups separated by gap
(search actions: Search + Clear; record actions: Add + Import, gated by
`$canEdit`). Never one crammed btn-group, never w-100/h-100 stretching.

#### Rule 3: Filter panel

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

#### Rule 4: Pills and the one-role-per-control rule

Applied filters render as pills (`components/filter_pills.php` container, JS
renders the pills). Exactly three clear controls, no overlap:
checkbox/radio applies or unapplies; pill x removes one filter; toolbar
Clear resets everything (keyword + filters + sort). No "Clear all" link, no
panel Reset.

#### Rule 5: Dual search wording

Toolbar input searches the whole database server-side; its placeholder names
the entity so the scope is obvious per tab: "Search all family records...",
"Search all sectors...", "Search all services...", "Search all categories...",
"Search all audit logs...", "Search all my activity...". The in-card input
only searches what is already loaded - placeholder "Search this page..."
everywhere (single-source pages like accounts say "Search accounts...").

#### Rule 6: In-card controls row

Follow Manage Records: page search on the LEFT, "Show N entries" on the
RIGHT (space-between, built by `components/table_controls.php` out of
Bootstrap utilities). The page search is a small
input-group with an integrated `btn-primary` search-icon button. No "Search:"
label text.

The "Show N entries" control is small + muted text with a `form-select-sm`
select; default page size is **25** everywhere (options 10/25/50/100). Server
pages read it from `DashboardPageBuilder::recordsPerPage()` (default 25) and the
`table_controls` component defaults `perPage` to 25 too; view sentinels that
strip `per_page` from URLs compare against 25. Manage Records is a DataTables
grid, not the shared component - its native `.dt-length` label is forced to
small/muted in `managerecord.css` so it reads identically (pageLength 25).

#### Rule 7: List page anatomy (Manage Records is the source of truth)

Every list tab is the SAME composition as `Family/list.php` - no hand-rolled
layout markup, no page-specific layout CSS:

1. Toolbar ABOVE the card: `components/records_toolbar` (family, AJAX) or
   `components/records_toolbar_server` (everything else). Pills row renders
   with it.
2. `components/card` with stock `.card` chrome - page CSS must never override
   the card border/radius/background, re-pad the card-body, or set
   `height: 100%` (that caused the dead space under short tables).
3. First thing inside the body: `components/table_controls` (page search
   left, show-entries right). Never copy its markup inline.
4. The table: `.manage-record-table` typography is canonical (0.7rem/700
   uppercase th, 0.85rem td, no bold name cells). Page CSS may add column
   widths/badges only.
5. `components/table_footer` as the card footer for server pagination.

Page CSS files (`lookupmanagement/audittrails/accounts.css`) hold ONLY
page-unique rules: badges, modals, column widths. Anything about toolbars,
controls rows, card chrome, or cell typography belongs to the shared layer
(`managerecord.css` + the components). If a new rule would apply to two
pages, it goes in the shared layer.

### Views and Bootstrap

**Scope:** layout shells, partials, styling rules. The dashboard's Bootstrap comes
compiled into the vendored SB Admin build; the separately vendored
`public/assets/bootstrap/css/bootstrap.min.css:1` is 5.3.3 and loads only on the
login page. Pins in `docs/reference/version-pins.md`.

#### Rule 1: Pages plug into the one layout shell - never standalone `<html>`

There is exactly one dashboard shell, `app/Views/layout.php`, shared by every staff
role. It owns `<html>`, head assets, sidebar, and topbar, and renders the body view
the caller names:

```php
<?= view($bodyView, array_merge(['bodyView' => null, 'bodyData' => []], $bodyData ?? [])) ?>
```

Callers pass `activePage` (the manifest key, which also supplies the page title via
`Config\Navigation::titleFor()`), `role` (a normalized label), `bodyView`, and
`bodyData`. The role-prefixed `Admin/`, `Employee/`, and `Viewer/` layouts are
deleted; a page never branches on the role to pick a shell.

Standalone `<html>` is correct ONLY for `layout.php`,
`app/Views/Scanner/kiosk-layout.php`, `app/Views/Auth/login.php:1`, the PDF views,
and `app/Views/errors/html/` pages. A page that grows its own shell (the import
review used to) loses the sidebar and every convention below.

#### Rule 2: Shared UI fragments are partials

- `app/Views/components/dashboard_sidebar.php:1` - sidebar, consumed by
  `layout.php`. It takes `$role` and `$activePage` and renders every link, heading,
  and active state from `Config\Navigation::linksFor($role)`. Never hand-write a
  nav item here; add a manifest entry.
- `app/Views/Partials/topbar-account-menu.php:1` - cross-role fragment, shared
  by the admin shell and the scanner kiosk shell.

Repeated markup across two views = extract a partial, render with
`view('Partials/...', [...])`.

**Card/table panels use the props-only components** (SB Admin 1 card
anatomy: card-header icon+title > card-body > optional card-footer):

- `app/Views/components/card.php:1` - generic shell; body content comes
  from a named view (`bodyView` + `bodyData`) or `bodyHtml`. JS scope
  hooks (e.g. `data-*-management-root`) pass through `attrs`.
- `app/Views/components/table_footer.php:1` - shared "Showing X-Y of Z"
  + Previous/Next pagination row, passed as `card`'s `footer`.

Canonical consumers: `app/Views/Family/list.php:26` and
`app/Views/Admin/accounts.php:71` (card + body partial).
New panels MUST use these components, not hand-rolled card markup.

A table is content, so it goes in a body partial: write the `<table>` there
and wrap it in `card`, with `table_controls` above and `table_footer` as the
card's footer. There is deliberately no table component taking rows as data:
one existed, took `list<list<raw HTML>>`, and pushed escaping onto every
caller. It was deleted rather than migrated onto.

#### Rule 3: CSS loads via `asset_styles()` - theme first, then page CSS

Canonical chain - `app/Views/layout.php:52`:

```php
<?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
<link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
```

with the lists defined in `app/Helpers/asset_helper.php:34`: vendored
SB Admin 1 theme (`assets/sb-admin/css/styles.css`, Bootstrap compiled in)
then bootstrap-icons, `css/theme.css`, DataTables (bootstrap5 build), then
per-page CSS (`public/css/<page>.css`, e.g. `accounts.css`,
`managerecord.css`). New page styles go in a page CSS file registered there -
never a new `<link>` hand-added to a shell.

#### Rule 4: Style with Bootstrap utilities/components, not inline styles

Canonical: views compose Bootstrap 5 classes (`card`, `table`, `btn`,
`dropdown`, spacing/flex utilities) plus SB Admin 1's `sb-*` shell classes.

**Anti-pattern:** inline `style="..."` attributes (past offenders tracked
and fixed in `docs/reference/violations.md`). Exceptions: PDF views
(`app/Views/Scanner/pdf/report.php:1`) and framework error pages.

**Why:** theme changes retile the shells and components; views that stick
to Bootstrap + component classes migrate for free, inline styles have to
be hunted down.

#### Rule 5: Components Bootstrap does NOT ship - build from utilities, not fake classes

Bootstrap ships **no stepper/wizard and no empty-state component**. When a
page needs one:

- **Empty state:** compose utilities - centered `py-5` block, big muted
  bootstrap-icon (`display-3 text-secondary`), bold title, `text-muted small`
  hint. Canonical: `app/Views/Scanner/scan.php:39` (`#emptyState`, which also
  doubles as the lookup-error surface by swapping icon/text).
- **Stepper:** prefer NOT building one. Numbered field labels
  ("1. Subsidy type...", "2. Scan...") plus an attention state on the pending field
  read just as well without a custom component
  (`app/Views/Scanner/scan.php:8`, `.scan-attn` / `.scan-muted` in
  `public/css/scanner-scan.css:4`). Where a real one is needed,
  `app/Views/components/stepper.php` is presentational only.
- Page-specific classes live in that page's CSS file (Rule 3) and build on
  Bootstrap CSS variables (`--bs-primary`, ...) so theming survives a theme
  change. Note that 5.3-only variables such as `--bs-success-bg-subtle` are
  NOT available on dashboard pages; see the version section above.

**Reviewer false positive to ignore:** `h-100` is a plain Bootstrap sizing
utility used by the house style (`app/Views/Admin/batch-overview.php:73`), NOT
an SB-Admin-Pro demo class. The Pro-only markers this repo bans are
`border-left-*` and `text-xs text-uppercase` (the ReportsViewTest that
asserted this was retired with the old scanner shell, commit 9cd705e).
