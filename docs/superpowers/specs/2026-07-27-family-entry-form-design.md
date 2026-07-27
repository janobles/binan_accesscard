# Family Data Entry Form — Uppercase, Bootstrap Rework, Member UX

**Date:** 2026-07-27
**Scope:** the CSWD family profiling form (`app/Views/Family/family-modal.php` and its
helper, CSS, and JS). Head of family plus household members, sectors, and services.

## Problem

The entry form is the primary data-capture surface for the whole application: a head of
family is profiled, given a QR control number that stands in for the not-yet-produced access
card, and categorized against reference data (sectors, services). Members are profiled with
the same personal fields because program eligibility differs per person.

Three problems, audited 2026-07-27:

1. **Casing is inconsistent across the stack.** Storage is Title Case, the records table
   displays UPPERCASE, and the form shows whatever was typed. As a government form the
   record should be uppercase throughout.
2. **The form runs a parallel implementation of Bootstrap's form system.** Custom CSS
   restyles `input`/`select`/`textarea` by bare element selector, three controls carry no
   Bootstrap class at all, checkboxes are hand-styled, validation is hand-rolled, and the
   step tabs have invalid ARIA.
3. **The layout does not scale to a real household.** A two-step tab wizard hides the head
   behind a tab, which forced a duplicate read-only head summary onto step 2. Every member
   renders 13 inputs plus both full checkbox lists, always expanded, with no label
   association.

## Audit findings

Line references are as of commit `c6ecdb4`.

### Casing

| Layer | Behavior | Location |
|---|---|---|
| Storage (names, address) | Title Case | `MemberFieldNormalizer.php:67,81` |
| Storage (civil status, education, job, religion) | as posted, trim only | `FamilyController.php:725-731` via `nullableText()` |
| List display | UPPERCASE | `FamilyDataTablePresenter.php:35,38,57` |
| Form input | as typed | no transform |
| "Other" freetext | Title Case, client-side | `manage-family-modal.js:71-80` |

`civilstatus`, `education`, `job`, and `religion` are free-text columns
(`accesscardV18.sql:124-132`), not foreign keys. Their dropdown options come from
`FamilyProfilingFormV2` as PHP constants, in Title Case.

**Consequence for this work:** these four are free-text *columns* but are populated from
dropdowns — the worker picks, never types. They are therefore out of scope for stored
uppercasing (see the "Uppercase applies to typed fields" decision). Had they been included,
the option lists would have had to change in lockstep: the option-selected check is an exact
string compare (`family_modal_helper.php:58`), so a stored `ELEMENTARY GRADUATE` matching no
option would silently fall back to "Select" and lose a saved value on edit.

`sex` additionally cannot be uppercased without a wide blast radius: it is validated by
`in_list[Male,Female]` in `FamilyController:817,826` and `MemberModel:29`, is the importer's
canonical return (`FamilyExcelImporter:1107`), and is a dropdown list in the Excel template
(`FamilyExcelTemplate:144`).

### Layout

- The Head/Members tab split (`family-modal.php:199-209`) hides the head while members are
  entered, which is why `:331-363` renders a read-only "Current Record Head" block
  duplicating all nine head fields. The tabs created the problem the summary patches.
- That block is worse than a duplicate: its values are `.family-summary-value` divs, and
  `familymodal.css:597-611` styles them with the **same rule as real inputs**. Read-only data
  is dressed as editable fields, and it costs a full screen of scrolling before the member
  list is reachable. Deleting the tabs removes the block's reason to exist.
- Two steps is below the threshold where a wizard earns its cost. Step 2 needs step 1
  *visible*, which is exactly what a wizard prevents.
- **Submit-hang:** Save is shown only on the Members tab (`js:1086`), so the form submits
  while the Head pane is `display:none`. A natively-invalid head field then blocks submit
  with no visible message. The async QR check parks
  `setCustomValidity('Checking whether…')` (`js:343`) and clears it only on success, so a
  failed or in-flight request can wedge the save silently. Root cause is the hidden pane,
  not the validation call.
- Member cards are always expanded with no summary. The `max_input_vars` truncation guard
  (`:185-188`, `_form_end` sentinel at `:406`) is evidence this already breaks in the field.

### Bootstrap

- `familymodal.css:597-619` reimplements `.form-control` / `.form-select` via bare element
  selectors. Consequence: `family-modal.php:219` (QR), `:247` (address), and `:251`
  (barangay) carry no Bootstrap class and only render correctly because of that CSS. The two
  are coupled and must change together.
- `.family-choice` uses the `form-check` class but overrides its layout and styles the raw
  `input[type=checkbox]` (`:701-712`) instead of `.form-check-input`.
- Validation is hand-rolled: `.family-field-error` plus manual `is-invalid` toggling
  (`js:272-288`), with `.is-invalid { border-color: … !important }` (`:621`) existing only to
  beat the custom CSS.
- Step tabs give buttons `role="tab"` but the wrapper is `role="toolbar"` (`:199-209`).
- Footer packs six unrelated actions into one `.btn-group` (`:389-400`), mixing a `btn-sm`
  link into a default-size group.
- `.family-option-box` is fixed `height: 15.6rem` (`:657`).

### Form and data quality

- Member field labels have no `for`/`id`: the render helper only emits ids when given an
  `idPrefix`, which head passes (`:240`) and members do not (`:52-61`).
- Contact number has no pattern (`family_modal_helper.php:72`).
- The computed age is never displayed, so a checkbox disabled on age grounds reads as broken
  rather than explained.
- `refreshAgeEligibility()` silently clears selections: `js:872` sets `input.checked = false`
  when a choice becomes ineligible. A mistyped birth year wipes the worker's sector ticks with
  no message, and correcting the date does not restore them.
- Age thresholds are hardcoded in JS (`age < 18`, `age >= 60`, codes `B`/`SC`, categories
  `bata (children)` / `senior citizen`, `js:848-861`), duplicating
  `FamilyAgeEligibility.php:10-13,58-64`.
- Relationship (`:64`) is not `required` though semantically mandatory.
- No "no middle name" affordance; the `NO_DATA_TOKENS` list in the normalizer is evidence
  workers type placeholders into blanks.

### Redundant labels

The modal already carries a CSS band-aid for this: `familymodal.css:483-489` hides both the
modal footer and `.family-entry-header` — the form's own `<h2>` title (`:141-145`) — because
the modal header already says the same thing. It is rendered by PHP, then hidden by CSS.

| Redundant element | Why | Action |
|---|---|---|
| `<h2>` "New Family Record" (`:141-145`) | Modal header states it | Delete element and the CSS hiding it |
| `<h3>` "Personal Information" (`:231`) | First block of a form about a person | Delete |
| `<h3>` "Family Members" + helper text (`:366-367`) | Heading, helper text, and the Add Member button all say it | Keep one |
| `<strong>` "Member" per card (`:45`) | Replaced by the row's actual name | Delete |
| "Sectors" / "Services and Programs Available" at two heading levels (`:71,101,261,295`) | Rendered once for head and once per member | Keep only as the collapse toggle label |
| "Current Record Head" summary (`:331-363`) | Nine fields duplicating the head card | Deleted with the tabs |

The same container-label-plus-subcontainer-label pattern appears elsewhere in the app. Out of
scope here; worth its own pass.

### Investigated and dismissed

- **Salary is not lossy.** The select posts `value`, not `label`
  (`FamilyFormOptionsModel:49-62`), so `moneyOrNull('13000')` yields `13000.0` cleanly. Noted
  for elsewhere: the stored number is the bracket's *upper bound*, so `Salary` means "top of
  declared bracket", not actual income — reports summing it will overstate. Out of scope.
- **Age eligibility is already enforced client-side.** `refreshAgeEligibility()`
  (`js:831-881`) computes each person's age from their own birthday and disables ineligible
  sector and service checkboxes with an explanatory tooltip. An earlier draft of this spec
  claimed eligibility errors surfaced only on submit; that was wrong. What remains is
  displaying the age, fixing the silent uncheck, and de-duplicating the thresholds.
- **Modal vs. full page.** The modal is effectively page-sized already
  (`familymodal.css:447-449`: `min(76rem, …)` by `min(47rem, …)`). It stays a modal: for
  high-volume entry from paper forms, keeping the record list underneath with no navigation
  or page load is the larger time-and-motion win, and the draft dialog (`js:393-410`) already
  covers the dismissal-loses-work risk. The problem was the amount of content, not the
  container.

## Decisions

| Decision | Choice |
|---|---|
| Uppercase scope | Stored for typed fields (names, address, "Other" freetext); displayed for picked values |
| Bootstrap scope | Full: controls, validation, and layout |
| Tabs | Removed. Single scrolling form |
| Head layout | Always-expanded card at top; sector/service block expanded on create, collapsed on edit |
| Container | Stays a modal. The content volume was the problem, not the container |
| Member layout | Read-only compact rows; inline expand to edit one row at a time |
| Delivery | Three sequential branches |
| jQuery in this form | No. Stays vanilla `fetch` |
| Contact format | Mobile (11-digit) or Biñan landline |
| Reference data casing | Unchanged. Sector/service names and category headings stay Title Case |
| Dump version | Stays V18. No schema or seed change is triggered |

**Dump version.** The option lists are PHP constants in `FamilyProfilingFormV2`, not DB
reference tables, and the backfill is an `UPDATE` of `member` row data, not schema. So
`accesscardV18.sql` is untouched. If any later step does change schema or seeded reference
rows, cut V19 and update the memory note — flag it before doing so, do not decide silently.

**Uppercase applies to typed fields.** On Philippine government forms the all-caps convention
exists for hand-printing legibility and record matching — it governs the boxes where a person
writes a surname, first name, middle name, and address. Choice fields have no caps tradition
because nothing is typed into them; `Male ☐ Female ☐` is pre-printed form text and the encoder
ticks a box. Where caps appear on printed output (a PSA certificate rendering `MALE`), that is
a rendering decision, not how the value was captured.

Applied here:

| Field group | Stored | Displayed |
|---|---|---|
| Names, address | UPPERCASE | uppercase |
| "Other" freetext (typed religion, job, education, civil status, relationship) | UPPERCASE | uppercase |
| Sex, civil status, education, job, religion, relationship, suffix, barangay (picked) | canonical, unchanged | uppercase via CSS |

This keeps the DB honest about what a worker actually entered, removes the lockstep
option-list change and its silent-value-loss failure mode, requires no `in_list`, importer, or
Excel-template changes, and shrinks the backfill to the columns workers type.

**Reference data casing.** Uppercasing applies to what a worker types, not to system-provided
labels. Sector and service names and the service category headings come from DB reference
tables and stay Title Case; `sectorLabel()` and `serviceLabel()` already uppercase the code
portion, giving `SC - Senior Citizen`. The distinction is useful — reference labels read as
system-provided rather than worker-entered — and it keeps the work clear of seed changes.

**Ordering.** The code change and the backfill can land separately without risk: the importer
lowercases its duplicate-comparison keys (`FamilyExcelImporter::normalizeText`, `:1652-1655`)
and every DB name lookup uses `LOWER(member.firstname)` (`MemberModel:164,251,278`), so
duplicate detection is case-insensitive on both sides throughout the window. (Those `LOWER()`
calls prevent index usage — pre-existing, unchanged by this work.)

**Tests.** `MemberFieldNormalizerTest` and `FamilyExcelImporterTest` assert Title Case
(`'Dela Cruz'`) in a dozen-plus places. Updating them is part of Branch 1.

**jQuery.** jQuery 3.7.1 loads globally (`asset_helper.php:93`) and
`dashboard-modal-loader.js` uses it, but `manage-family-modal.js` is vanilla throughout and
already Ajax-submits via `window.fetch` (`js:1258`, `:351`). Introducing `$.ajax` here would
make it the one mixed-style file in the dashboard. Keep it vanilla.

**Client vs server validation.** Client-side validation is UX only; anyone can POST past it.
`FamilyController::rulesForEntryType()` and `firstIncompleteMember()` remain unchanged and
authoritative. The client work makes errors visible at the field instead of after a
round-trip.

**Bootstrap reference.** Component markup is taken from Context7 `/websites/getbootstrap_5_3`,
pinned in `docs/knowledge/sources.md:25` to match the vendored 5.3.3 copy.

---

## Branch 1 — Uppercase

**Goal:** one casing everywhere, sourced from storage.

- `MemberFieldNormalizer::cleanName()` and `cleanAddress()`: swap
  `mb_convert_case(…, MB_CASE_TITLE)` for `mb_strtoupper(…, 'UTF-8')`. Character allowlists
  and whitespace collapsing are unchanged. This is the single choke point — the manual form
  and the Excel importer both delegate here, so one edit covers both paths.
- `manage-family-modal.js:71-80`: `cleanOtherValue()` drops its title-case regex for
  `.toUpperCase()`, covering the "Other" freetext on religion, job, education, civil status,
  and relationship. This is typed text, so it is stored uppercase.
- Dropdown-picked values (sex, civil status, education, job, religion, relationship, suffix,
  barangay) are **not** touched: no option-list edits, no `in_list` changes, no importer or
  Excel-template changes. They render uppercase via the CSS below.
- `FamilyDataTablePresenter.php:35,38,57`: remove the three now-redundant `mb_strtoupper`
  calls. Storage becomes authoritative.
- `familymodal.css`: `text-transform: uppercase` on the form's text inputs **and selects**, so
  typed text shows caps while typing and picked values render caps without changing what is
  stored. Visual only — the server value is what counts.
- `sql/patches/v18-uppercase-names.sql`: one-time `UPDATE … SET col = UPPER(col)` across the
  name and address columns only, following the `sql/patches/v17-indexes.sql` convention. Not
  a migration; the no-migrations rule holds. The exact column list is confirmed against
  `accesscardV18.sql` before writing, and the patch is reviewed with the user before it runs.

`mb_strtoupper` handles ñ→Ñ correctly under UTF-8. Barangay needs no special handling —
`splitAddressBarangay` already compares with `strcasecmp`.

**Verification:** `vendor/bin/phpunit`; create a family with lowercase input through the UI
and confirm the DB row, records table, QR card, and edit form all agree; then **reopen a
saved record and confirm every dropdown still shows its stored value** — picked values must
survive the round-trip untouched.

**Audit trail:** unaffected. Values change before write, not the write path.

---

## Branch 2 — Bootstrap rework, layout, and client-side validation

### Remove the tabs

The Head/Members tab split goes away in favor of one scrolling form: head card at top,
members below. This deletes the duplicate "Current Record Head" block (`:331-363`) and
`renderHeadSummary()` in JS, since the head is now simply visible.

The `showStep()` / `validateHeadStep()` gate machinery is removed with it. Head completeness
becomes ordinary validation on save.

This also makes the submit-hang structurally impossible: with no tabs there is no hidden
pane, so no required field is ever unreachable at submit time.

### Head card

Personal fields always visible in a compact card. The sector and service checkbox lists —
the single longest block in the form — collapse to a text summary
(`Sectors: SC, PWD (2 selected)`) with an expand control. Same interaction the member rows
use, for consistency.

**Default state depends on mode:** expanded on create, collapsed on edit. During new entry
the worker is reading a paper form and ticking boxes, so sectors and services are the primary
task and collapsing them costs a click at the worst moment. When editing an existing record
they are reference information. The view already knows which mode it is in — `$modalMode` is
`'create'` or `'update'` (`family_modal_helper.php:83`).

### Redundant headings

Delete the labels listed in the audit's "Redundant labels" table, including the `<h2>` that
`familymodal.css:483-489` currently hides — remove both the element and the CSS rule rather
than leaving a hidden element in the DOM.

### Set as Head

`promoteMemberToHead()` (`js:1474`) swaps a member into the head slot. Under tabs this
happened across a tab boundary and was effectively invisible. On one scrolling page it
happens in front of the worker, so it needs defined behavior:

- Confirm before swapping — it rewrites who holds the QR card, which is the record's identity.
- The promoted member's row collapses out of the member list as the head card takes its values.
- The previous head becomes a member row, appended to the list, expanded so the worker can set
  its relationship (which the head record does not carry).
- Scroll to the head card after the swap so the result is visible.

### Controls

Atomic with the CSS deletion, since the unclassed controls depend on the CSS being removed:

- Add `form-control` to `family-modal.php:219` (QR) and `:247` (address); `form-select` to
  `:251` (barangay).
- Delete `familymodal.css:597-634` and the `:621` `!important`.
- Preserve the theme via CSS custom properties scoped to `.family-entry-form`
  (`--bs-border-color`, `--bs-body-color`, `--bs-form-control-bg`) rather than re-declaring
  the components. There is no Sass build — `bootstrap.min.css` is vendored — so custom
  properties are the closest available route to variable-based customization.
- Checkboxes become `.form-check-input` / `.form-check-label` with `for`/`id`; delete
  `:701-712`. Accent moves to `--bs-form-check-checked-bg-color`.
- `.family-option-box`: `height` → `max-height`.
- `family-form-hidden` → `d-none`.

### Validation

The form becomes `class="needs-validation" novalidate`, per Bootstrap's documented pattern —
`novalidate` suppresses the browser's own bubbles so Bootstrap's feedback styling is what the
worker sees. It is no longer load-bearing for the submit-hang; removing the tabs handles that.

- `setFieldError()` (`js:272-288`) keeps its logic but writes into an `.invalid-feedback` div
  carrying an `id`, with `aria-describedby` on the field pointing at it. `.family-field-error`
  and its CSS are removed.
- Validation runs on `submit` and on per-field `blur`.
- Member completeness mirrors `firstIncompleteMember()` client-side: the same six fields
  (birthday, sex, civil status, education, job, monthly income) and the same
  skip-if-unnamed rule from `hasMemberData()`. The server keeps its copy.
- QR uniqueness stays async `fetch`, but `setCustomValidity` is cleared on network failure
  and on timeout, not only on success.
- On submit, focus moves to the first invalid field, expanding its member row if closed.

### Components

- QR field → `.input-group.has-validation` with a status addon (spinner while checking, tick
  or cross after), making the async check legible.
- Contact → `.input-group` with a `+63` addon.
- Sector and service lists get a filter input.
- No `.form-floating` — it fights the dense four-column grid.

### Sector and service panels: flatten the nesting

Today these are four containers deep: modal body → `.family-sector-service` card →
`.family-option-box` (bordered, fixed `height: 15.6rem`, its own scrollbar) → the yellow
`.family-suggested` panel, which scrolls *inside* that box and consumes most of the visible
area. In practice about four checkboxes are visible at a time.

- Drop `.family-option-box` as a bordered container. The section card is the container; the
  list does not need a second border and its own scrollbar.
- **Sectors** is a fixed list of 10. It needs no scroll region at all — render it as a
  two-column `.form-check` grid (`col-6`), fully visible.
- **Services** become a Bootstrap accordion, one `.accordion-item` per category, with no
  `data-bs-parent` so several can stay open. Categories matching the ticked sectors
  auto-expand; the rest stay collapsed but present.
- Move `.family-suggested` out of the scroll region to a thin banner above the accordion, or
  drop it once auto-expand does the same job more directly.

**Services are deliberately not gated behind a sector tick.** Services are not a subset of
sectors: the Financial Assistance, Social Welfare Programs, and Emergency / Disaster
categories apply regardless of sector, and a person may have no sector at all. Hiding them
until a sector is ticked would conceal programs a worker legitimately needs. The accordion
gives the same "only what is relevant is expanded" compactness without hiding anything.

### Footer and button colors

The modal footer bypasses the `btn()` helper and hardcodes its own classes, which is why the
colors disagree with the rest of the app. The standard lives in `app/Helpers/ui_helper.php:21-25`:

```php
'search' => 'btn btn-primary',   // blue
'clear'  => 'btn btn-danger',    // red
'add'    => 'btn btn-success',   // green
'import' => 'btn btn-warning',   // yellow
```

Current mismatch: `family-modal.php:398` makes **Next `btn-success`**, the same green as Add
Member, so two unrelated actions compete as the green one while Save is the only blue.

Target mapping:

| Button | Class | Reason |
|---|---|---|
| Save | `btn btn-primary` | The commit action. Blue is the primary per the standard |
| Add Member | `btn btn-success` | Matches `add` in the standard |
| Clear | `btn btn-danger` | Matches `clear`. Already correct |
| Close | `btn btn-outline-secondary` | Dismissal, not destructive |

`Previous`/`Next` disappear with the tabs, which removes the green-on-green clash outright.
The six-button `.btn-group` (`:389-400`) splits into left (`Close`, `Clear`) and right
(`Save`), also fixing the `btn-sm`-inside-default-group geometry break on the Print QR link.

**Close stays neutral, not yellow.** Yellow is `btn-warning`, which already means Import in
this app, and warning-yellow reads as "this is risky" — the opposite of the intent, since
closing is safe precisely because the draft is kept. To communicate that directly, add a
small "Draft saved" indicator beside the footer, updated when `saveDraftNow()` fires
(`js:617`). That states the guarantee instead of asking the worker to infer it from a color.

### Verification

`vendor/bin/phpunit`; then Playwright against the dev server — log in as
`developer/developer123`, snapshot at desktop and 390px, compare against Manage Records.
Assert specifically that submitting with an empty head field now surfaces an error at that
field (today it hangs silently).

---

## Branch 3 — Member rows and field guards

### Read-only member rows

Each saved member renders as a compact read-only row: joined full name
(`DELA CRUZ, JUAN P. JR.`), relationship, age, and sector badges, with Edit and Remove.
Clicking Edit expands that row — and only that row — into the full field set inline;
collapsing returns it to text.

Closed rows render **no inputs**. Their values ride along as hidden inputs so the existing
"rebuild the member list from the submission" contract in `FamilyController::update()` is
unchanged.

This is the main DOM reduction: today every member carries 13 inputs plus both complete
checkbox lists. The `max_input_vars` pressure that the truncation guard exists to survive
largely disappears. Keep the guard and the `_form_end` sentinel anyway — cheap insurance —
but they stop being load-bearing.

Redundant label/input pairs go the same way: closed rows show values, not labels.

### Label association

For the expanded editor, pass `$fieldPrefix . 'Member' . $i` as `idPrefix`. This works with
the `<template>` unchanged, because `addMemberRow` already runs `replace(/__INDEX__/g, …)`
over the whole innerHTML (`js:1105`), so the placeholder inside generated ids is swapped
along with the field names. The relationship select (`:64`) gains an id and a real
`<label for>`.

### Field guards

- **Age.** Eligibility enforcement already exists (`refreshAgeEligibility`, `js:831-881`);
  this is three fixes to it, not a rewrite.
  1. Display the computed age as `.form-text` beside the birthday field ("Age: 67") and in
     the closed row summary, so a disabled checkbox reads as explained rather than broken.
  2. Stop silently clearing selections. Instead of `input.checked = false` (`js:872`), keep
     the tick, mark the choice invalid with a visible message, and let it resolve itself when
     the birthday is corrected. Server-side `selectionError()` already blocks a genuinely bad
     save, so nothing unsafe gets through.
  3. De-duplicate the thresholds. `FamilyAgeEligibility` gains a public static accessor for
     its bounds; the view renders them as `data-min-age` / `data-max-age` on the affected
     checkboxes, which already carry `data-sector-code`. JS reads the attributes instead of
     hardcoding `18` / `60` / `B` / `SC`. `selectionError()` stays authoritative and unchanged.
- **Relationship.** Marked `required`.
- **Middle name.** A "No middle name" checkbox that clears and disables the field.
- **Contact number.** Accepts an 11-digit mobile (`09XXXXXXXXX`) or a Biñan landline
  (7-8 digits, optional `(049)` prefix), with `inputmode="numeric"`. Remains optional. The
  looser rule is deliberate: a mobile-only pattern would block a worker from saving an edit
  to an older record whose landline they never touched.

### Verification

`vendor/bin/phpunit`; Playwright at desktop and 390px with a multi-member household —
confirm rows read as text when closed, expand to edit one at a time, auto-expand on invalid
submit, Set as Head swaps visibly and confirms first, and that a household large enough to
have previously tripped the truncation guard now saves.

## Preserve: the import-fix flow

This same view is reused by the Excel import review screen, which is easy to break silently.
Every branch must keep working, and Playwright coverage must include this path, not only the
Add/Edit path:

- the blocking and warning alert block (`:161-176`) and `importFieldIssues` (`:140`)
- the `qrLocked` readonly QR state and its explanatory note (`:215-227`)
- the `import_family_no` (`:191-193`) and `import_row` (`:195-197`) hidden fields

## Out of scope

- Duplicate-person detection across QR numbers. Only QR uniqueness is checked today. Real
  gap, separate spec.
- `Salary` storing bracket upper bounds rather than actual income — affects reporting, not
  entry.
- The Excel import review screen, beyond what Branch 1's normalizer change reaches.
- Pre-existing dead code noted during the audit but not created by this work.
