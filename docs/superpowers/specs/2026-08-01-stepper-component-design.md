# Stepper component, breadcrumbs, and the control-number step

## Problem

Three separate rough edges left over from moving family entry and bulk import out
of modals and onto full pages.

1. **Bulk import fakes a stepper.** `Family/import-upload.php:16` and
   `Family/import-review.php:33` each hand-roll a three-item strip out of
   `nav-pills segmented-tabs` with `disabled` spans. The markup is duplicated
   across the two files, it reuses the tab primitive for something that is not a
   tab, and it advertises a step `3. Done` that has no page: commit redirects
   straight to `records`.

2. **Data entry has no sense of place.** `Family/entry.php` renders a sticky
   `list-group` rail of four anchors. Two of those anchors, Sectors and Services,
   are not page sections at all: `_fields.php:192` renders that block "identical
   for the head and for every member", and the head's copy merely happens to carry
   the `section-sectors` / `section-services` ids. The rail therefore promotes two
   sub-blocks of one person into page-level sections.

3. **No breadcrumbs anywhere.** Arriving at Data Entry or Import from Manage
   Records replaces the whole page with no indication of what was left behind.
   `grep -rn breadcrumb app/Views` returns nothing.

Alongside these: the control-number field on entry is an input-group with an icon
(`entry.php:17-22`) while every other field on the page is a plain `form-control`,
its value is mirrored into a readonly field inside `_fields.php:369-371`, and the
QR code the number produces is never shown even though
`App\Libraries\Qr\QrImageGenerator` already renders one for the printed card.

Bootstrap ships no stepper component, so one has to be built.

## Approach

Build one presentational view component modelled on CoreUI's stepper markup,
styled after GitHub's new-repository form, and use it in both places. Take
CoreUI's class taxonomy; do not take its ARIA or its JavaScript.

CoreUI's stepper is a **tab widget**: `role="tab"`, `aria-selected`,
`aria-controls`, `role="tabpanel"`, panes toggled `.active.show`, driven by a JS
engine with `next()` / `prev()` / `finish()` and a `linear` option. That is
correct for their case, where steps are panes hidden and shown inside one
document. Neither of ours is that case:

- **Bulk import** steps are separate URLs (`records/import` then
  `records/import/review/{id}`). There is no tablist and no pane in the document
  for `aria-controls` to point at. The correct pattern is a progress indicator: a
  `<nav>` wrapping an `<ol>`, with `aria-current="step"` on the current item.
- **Data entry** shows every section at once, deliberately. Tab semantics assert
  that exactly one pane is visible, which would be false. The correct pattern is
  anchor links plus `aria-current="step"`, which is what Bootstrap's scrollspy
  already drives.

Neither case needs step-advance JS, so the component ships without any.

CoreUI models roughly active and disabled. Two more states are needed here: `done`
(import step 1 once the file is staged; an entry section whose required fields are
filled) and `error` (an import file with blocking rows; an entry section holding
an invalid field). A class combination for four states is soup, so state rides on
a `data-state` attribute.

Visual restraint comes from GitHub rather than CoreUI. GitHub's indicators are
muted neutral circles carrying no per-state colour; the section heading is the
emphasis. Colour is spent only where it means something.

## The component

`app/Views/components/stepper.php`. Presentational only: it computes no state and
loads no JavaScript.

```php
<?= view('components/stepper', [
    'orientation' => 'vertical',      // 'horizontal' | 'vertical'
    'label'       => 'Record sections',
    'steps'       => [
        ['label' => 'Control Number', 'href' => '#section-control', 'state' => 'done'],
        ['label' => 'Head of Family', 'href' => '#section-head', 'state' => 'current'],
        ['label' => 'Household Members', 'href' => '#section-members'],
    ],
]) ?>
```

Rendered markup:

```html
<nav class="stepper stepper-vertical" aria-label="Record sections">
  <ol class="stepper-steps">
    <li class="stepper-step" data-state="current">
      <a class="stepper-step-link" href="#section-head" aria-current="step">
        <span class="stepper-step-indicator" aria-hidden="true">2</span>
        <span class="stepper-step-label">Head of Family</span>
      </a>
    </li>
  </ol>
</nav>
```

Contract:

- `orientation` is `horizontal` or `vertical`; anything else falls back to
  `horizontal`.
- `label` is the `aria-label` on the `<nav>` and is required; it defaults to
  `Progress` when omitted. Import passes `Import progress`, entry passes
  `Record sections`.
- Each step is `['label' => string, 'href' => ?string, 'state' => ?string]`.
- `href` present renders an `<a>`; absent renders `<span class="stepper-step-link">`.
  That single branch is the whole difference between the entry rail (every step
  clickable, non-linear) and the import indicator (nothing clickable, because the
  steps are separate pages).
- `state` is one of `upcoming | current | done | error`, defaulting to `upcoming`.
  Only `current` also receives `aria-current="step"`. At most one step should be
  `current`; the component does not enforce this, the caller decides.
- Step numbers come from the loop index. Callers never pass a number.
- The indicator is `aria-hidden="true"`. The `<ol>` already conveys order, and a
  screen reader reading "1 1 Head of Family" is noise.
- `done` and `error` prepend a visually hidden "Completed, " / "Needs attention, "
  to the label, so state is never conveyed by colour alone.
- Labels are escaped.

## Styling

Lives in `public/css/theme.css` beside `.segmented-tabs`; both are app primitives
rather than page skins.

Indicators are neutral by default: a muted outlined circle, section label at
heading weight beside it. `current` gains a ring rather than a filled colour.
`done` fills subtly and shows `bi-check`; `error` uses the danger colour and
`bi-exclamation`. No other state carries colour.

`.stepper-horizontal` is a flex row: indicator centred with the label directly
beneath it, connector line drawn between indicators at circle-centre height via
`::after` on each `<li>` except the last. `.stepper-steps` gets
`overflow-x: auto` so a narrow viewport scrolls rather than squashing; there is no
mobile-only variant.

`.stepper-vertical` is a flex column spine: indicator at the left, label beside
it, section content indented to align with the label, connector line running down
via `::before` and stopping at the last step. Content sits in a capped-width
column, wider than GitHub's ~720px because the members table and sector grids need
the room, but capped rather than edge to edge.

`prefers-reduced-motion` is respected. No transitions are load-bearing.

## Bulk import (horizontal)

Both strips are replaced by `view('components/stepper', ['orientation' =>
'horizontal', ...])`. Step 3 "Done" is deleted: two real steps, `Upload` then
`Review and Fix`. Commit continues to raise the existing confirm dialog
(`import-review.js:105`, which reuses the layout's `#familyActionModal`) and then
redirect to Family Records with a success flash. No new route or controller
action.

States:

- `import-upload.php`: Upload `current`, Review `upcoming`.
- `import-review.php`: Upload `done`, Review `current`, or Review `error` when
  `$counts['blocking'] > 0`, so a file that cannot be committed says so in the
  page chrome and not only in the severity pills below.

Neither page passes `href`, so both render as non-clickable spans. Backwards
navigation from Review is Cancel, which discards staging via `data-cancel-url`; a
stepper link that silently abandoned a staged job would be a trap.

The severity filter pills at `import-review.php:52` stay `segmented-tabs` and are
untouched. They filter, they do not track progress, and keeping them visually
distinct from the stepper is the point.

## Data entry (vertical spine)

`Family/entry.php` keeps its architecture: every section on one page, non-linear,
no gating. The `col-lg-3` rail and `col-lg-9` content split is replaced by a single
full-width column laid out as a spine, which gives the members table and sector
grids roughly a quarter more width. Scrollspy still runs, marking the current
section.

Three steps, correcting the current four:

```
1  Control Number      2  Head of Family      3  Household Members
```

Sectors and services stay where they belong, inside each person's block, for the
head and for every member alike. The stray `section-sectors` and `section-services`
anchors are removed from `_fields.php`.

The control-number gate (`entry.php:15-25`) moves inside the form as
`#section-control`, step 1 of the spine. Its input-group wrapper is removed: a
plain `<label>`, `form-control`, and `form-text` status line, matching every other
field on the page. The readonly mirror at `_fields.php:369-371` is deleted; the
hidden `data-entry-control-number` input at `_fields.php:378` remains as the form's
carrier of the value.

Inside step 1: a `row` with the field in `col-md-6 col-lg-5` and the QR preview in
`col-md-6`. Reading order is field, then status, then QR, so an empty placeholder
never leads. On narrow viewports the columns stack in that same order.

Non-linear navigation stays safe because the rail only informs while the Save
button enforces: Save is disabled while required fields are missing.

### Sticky action bar

The Save button pinned under the old rail (`entry.php:36`) moves to a sticky bottom
action bar spanning the content column. Today's rail stacks above the form at
mobile widths, which puts Save at the very top of the page and forces the officer
to scroll back past the whole household to reach it.

The bar is `position: sticky` inside the content column so it does not fight the
SB Admin sidebar at desktop widths, with `padding-bottom` on the form so the last
field is never trapped underneath. Save and Cancel are right-aligned, styled like
GitHub's footer action. The left side of the bar carries the reason Save is
blocked, for example "2 required fields missing", linking to the first offending
field. Today the button simply goes dead with no explanation.

## QR preview

A bare `<img>`: no card, no rounded container, fixed width, `alt="QR code for
control number N"`, hidden until there is a number to encode.

**Entry page.** No new route. `FamilyController::qrAvailability()`
(`FamilyController.php:1139`) already authorises the caller, validates the number,
and checks uniqueness. Its JSON response gains a `qr` field holding
`QrImageGenerator::dataUri()` of `$settings->qrUrlPrefix . $control`, the same
payload `QrCardPdfGenerator.php:125` encodes onto the printed card, so preview and
card provably encode the same string. `manage-family-modal.js`, which owns this
page, sets the image source from it.

The field is populated only when `available` is true. A number already taken never
renders a code, since a card may already exist for it. If the request fails or the
field is absent the image stays hidden and the number still validates: the QR is
never a gate on saving.

**Edit page.** `Family/profile.php` has no control-number input at all; it shows
the number as a badge in the head card header (`profile.php:35`). The QR renders in
that card beside the badge, from a `qrDataUri` assembled by
`DashboardPageBuilder`, with no async check and no new field.

## Breadcrumbs

Rendered by the layout, above the `<h1>` at `layout.php:74`. Above rather than
below: breadcrumbs are ancestor context, so they precede the title, and the h1
stays the first thing after them. This is what GOV.UK, Polaris, Atlassian, GitHub,
and Material all do; SB Admin's stock templates place it below the h1 and are the
outlier.

Source of truth is `app/Config/Navigation.php`, which already drives the sidebar,
the page title, and `RoleNavFilter`. Each entry in `Navigation::UNLISTED` gains a
`parent` key naming another page key:

```
records-entry   => parent: records    "Family Records › New Family Record"
records-import  => parent: records    "Family Records › Import Family Records"
records-profile => parent: records    "Family Records › Family Profile"
records-edit    => parent: records    "Family Records › Edit Family Record"
records-update  => parent: records    "Family Records › Edit Family Record"
```

Crumb text comes from the existing `UNLISTED_TITLES` for the leaf and from the
parent's `label` for the ancestor. The layout emits
`<nav aria-label="breadcrumb"><ol class="breadcrumb">` only when the active page
declares a parent; the final crumb is `.active` with `aria-current="page"` and is
not a link. Pages listed in the sidebar get no breadcrumb, since the sidebar
already marks them active and `Dashboard › Dashboard` is noise.

Adding a future unlisted page inherits breadcrumbs by declaring a parent, with no
view edit, which is the "adding a page means adding a manifest entry" rule the
project already holds.

## Out of scope

- The edit page (`Family/profile.php`) keeps its card layout. It covers one
  household, has no control-number step, and has no tabs to replace; converting it
  to the spine is separate work. It gains the breadcrumb and the QR only.
- `Family/profile-view.php`, the read-only print page, is unchanged.
- `components/page_tabs.php` is unchanged and still serves genuinely tabbed pages.
- The import severity pills are unchanged.

## Edge cases

- Import at 390px: two horizontal steps fit, with `overflow-x: auto` as the guard.
- Entry with no members: step 3 renders `upcoming`, never `error`. A household of
  one is valid.
- `qrAvailability` failing or offline: no `qr` field, image stays hidden, number
  still validates, save unaffected.
- The truncation sentinel `_form_end` must remain the last named field in the entry
  form (`entry.php:58`).
- `data-family-entry-form` must remain on the entry container so
  `manage-family-modal.js` still wires up the member row controls and AJAX submit.

## Testing

Unit tests:

- Stepper component: renders `<a>` when `href` is given and `<span>` when it is
  not; emits `aria-current="step"` on exactly one step; numbers steps from the
  loop index rather than caller input; passes `data-state` through and defaults it
  to `upcoming`; escapes labels.
- Breadcrumbs: a page declaring a parent emits the nav with the parent linked and
  the leaf unlinked and marked `aria-current="page"`; a page without a parent emits
  nothing.
- `qrAvailability`: response carries a `data:image/png;base64,` string in `qr` when
  the number is available; the field is absent when the number is taken and absent
  on the 422 validation path.

`vendor/bin/phpunit` green before and after. `composer lint` clean.

Visual verification with Playwright against the dev server, signed in as
developer: Data Entry, Import Upload, and Import Review at desktop and 390px,
compared against Manage Records as the design source of truth.
