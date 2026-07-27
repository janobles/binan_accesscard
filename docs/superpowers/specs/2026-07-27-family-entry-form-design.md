# Family Data Entry Form — Uppercase, Bootstrap Rework, Member UX

**Date:** 2026-07-27
**Scope:** the CSWD family profiling form (`app/Views/Family/family-modal.php` and its
helper, CSS, and JS). Head of family plus household members, sectors, and services.

## Problem

The entry form is the primary data-capture surface for the whole application: a head of
family is profiled, given a QR control number that stands in for the not-yet-produced
access card, and categorized against reference data (sectors, services). Members are
profiled with the same personal fields because program eligibility differs per person.

Three problems, audited 2026-07-27:

1. **Casing is inconsistent across the stack.** Storage is Title Case, the records table
   displays UPPERCASE, and the form shows whatever was typed. As a government form the
   record should be uppercase throughout.
2. **The form runs a parallel implementation of Bootstrap's form system.** Custom CSS
   restyles `input`/`select`/`textarea` by bare element selector, three controls carry no
   Bootstrap class at all, checkboxes are hand-styled, validation is hand-rolled, and the
   step tabs have invalid ARIA.
3. **Member entry does not scale.** Each member renders 13 fields plus the full sector and
   service lists, always expanded, with no label association and no way to collapse.

## Audit findings

Line references are as of commit `c6ecdb4`.

### Casing

| Layer | Behavior | Location |
|---|---|---|
| Storage | Title Case | `MemberFieldNormalizer.php:67,81` |
| List display | UPPERCASE | `FamilyDataTablePresenter.php:35,38,57` |
| Form input | as typed | no transform |
| "Other" freetext | Title Case, client-side | `manage-family-modal.js:71-80` |

### Bootstrap

- `familymodal.css:597-619` reimplements `.form-control` / `.form-select` via bare element
  selectors. Consequence: `family-modal.php:219` (QR), `:247` (address), and `:251`
  (barangay) carry no Bootstrap class and only render correctly because of that CSS. The
  two are coupled and must change together.
- `.family-choice` uses the `form-check` class but overrides its layout and styles the raw
  `input[type=checkbox]` (`:701-712`) instead of `.form-check-input`.
- Validation is hand-rolled: `.family-field-error` plus manual `is-invalid` toggling
  (`js:272-288`), with `.is-invalid { border-color: … !important }` (`:621`) existing only
  to beat the custom CSS.
- Step tabs give buttons `role="tab"` but the wrapper is `role="toolbar"`, not
  `role="tablist"` (`:199-209`).
- Footer packs six unrelated actions into one `.btn-group` (`:389-400`), mixing a `btn-sm`
  link into a default-size group.
- `.family-option-box` is fixed `height: 15.6rem` (`:657`).

### Form and data quality

- Member field labels have no `for`/`id`: the render helper only emits ids when given an
  `idPrefix`, which head passes (`:240`) and members do not (`:52-61`).
- Member cards are always expanded with no summary. The `max_input_vars` truncation guard
  (`:185-188`, `_form_end` sentinel at `:406`) is evidence this already breaks in the field.
- Contact number has no pattern (`family_modal_helper.php:72`).
- Birthday drives sector eligibility but no age is shown, so eligibility errors surface only
  after submit.
- Relationship (`:64`) is not `required` though semantically mandatory.
- No "no middle name" affordance; the `NO_DATA_TOKENS` list in the normalizer is evidence
  workers type placeholders into blanks.
- **Submit-hang:** Save is shown only on the Members tab (`js:1086`), so the form submits
  while the Head pane is `display:none`. A natively-invalid head field then blocks submit
  with no visible message. The async QR check parks
  `setCustomValidity('Checking whether…')` (`js:343`) and clears it only on success, so a
  failed or in-flight request can wedge the save silently.
- **Salary cast:** income options are brackets but storage goes through
  `moneyOrNull()` (`:99-106`), which casts to float. To be verified for data loss.

## Decisions

| Decision | Choice |
|---|---|
| Uppercase scope | Storage and display, with a one-time backfill |
| Bootstrap scope | Full: controls, validation, and tabs |
| Delivery | Three sequential branches |
| jQuery in this form | No. Stays vanilla `fetch` |
| Contact format | Mobile (11-digit) or Biñan landline |

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
  and relationship.
- `FamilyDataTablePresenter.php:35,38,57`: remove the three now-redundant `mb_strtoupper`
  calls. Storage becomes authoritative.
- `familymodal.css`: `text-transform: uppercase` on the form's text inputs so the worker sees
  caps while typing. Visual only — the server value is what counts.
- `sql/patches/v18-uppercase-names.sql`: one-time `UPDATE … SET col = UPPER(col)` across the
  name and address columns, following the `sql/patches/v17-indexes.sql` convention. Not a
  migration; the no-migrations rule holds. The exact column list is confirmed against
  `accesscardV18.sql` before writing.
- Verify `moneyOrNull()` against what `FamilyFormOptionsModel:148` yields from
  `income_ranges`. If bracket labels are being cast lossily, fix it here — same class of
  data-correctness concern.

`mb_strtoupper` handles ñ→Ñ correctly under UTF-8.

**Verification:** `vendor/bin/phpunit`; then create a family with lowercase input through the
UI and confirm the DB row, the records table, the QR card, and the edit form all agree.

**Audit trail:** unaffected. Values change before write, not the write path.

---

## Branch 2 — Bootstrap rework and client-side validation

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

The form becomes `class="needs-validation" novalidate`.

`novalidate` also resolves the submit-hang: the browser stops adjudicating, `checkValidity()`
still works in JS, and the form controls what is shown and where focus lands. The same
mechanism makes collapsed accordion members safe in Branch 3.

- `setFieldError()` (`js:272-288`) keeps its logic but writes into an `.invalid-feedback` div
  carrying an `id`, with `aria-describedby` on the field pointing at it. `.family-field-error`
  and its CSS are removed.
- Validation runs on `submit` and on per-field `blur`, not only on Next. Today only head
  fields are gated (`validateHeadStep:1016`).
- Member completeness mirrors `firstIncompleteMember()` client-side: the same six fields
  (birthday, sex, civil status, education, job, monthly income) and the same
  skip-if-unnamed rule from `hasMemberData()`. The server keeps its copy.
- QR uniqueness stays async `fetch`, but `setCustomValidity` is cleared on network failure
  and on timeout, not only on success.
- On submit, focus moves to the first invalid field *and* switches to its tab.

### Tabs

The `role="toolbar"` wrapper (`:199`) becomes `ul.nav.nav-pills.segmented-tabs` with
`role="tablist"`; buttons keep `role="tab"`; panes are already `.tab-pane`. This matches the
repo's segmented-tabs standard.

`data-bs-toggle="tab"` is deliberately **not** added: step 2 is gated on step 1 validating,
and Bootstrap's Tab plugin switches unconditionally. `showStep()` stays in control; only
markup and ARIA are corrected.

### Components

- QR field → `.input-group.has-validation` with a status addon (spinner while checking,
  tick or cross after), making the async check legible.
- Contact → `.input-group` with a `+63` addon.
- Sector and service boxes get a filter input. They render N+1 times with no way to search.
- No `.form-floating` — it fights the dense four-column grid.

### Footer

The six-button `.btn-group` (`:389-400`) splits into left (`Close`, `Clear`) and right
(`Previous`, `Next`, `Save`), fixing the `btn-sm`-inside-default-group geometry break on the
Print QR link.

### Verification

`vendor/bin/phpunit`; then Playwright against the dev server — log in as
`developer/developer123`, snapshot both tabs at desktop and 390px, compare against Manage
Records. Assert specifically that submitting with an empty head field while on the Members
tab now surfaces an error (today it hangs silently), and that a bad member row errors at the
field.

---

## Branch 3 — Members and field guards

### Label association

Pass `$fieldPrefix . 'Member' . $i` as `idPrefix` for member rows. This works with the
`<template>` unchanged, because `addMemberRow` already runs `replace(/__INDEX__/g, …)` over
the whole innerHTML (`js:1105`), so the placeholder inside generated ids is swapped along with
the field names. The relationship select (`:64`) gains an id and a real `<label for>`.

### Collapsible members

A Bootstrap accordion, one `.accordion-item` per member, with **no `data-bs-parent`** so
items stay open independently and a worker can compare two members at once. New rows start
expanded; existing rows load collapsed.

The header summary updates live from the row's own fields —
`Member 1 — DELA CRUZ, JUAN · SON` — falling back to `Member 1 — (unnamed)` while empty.

Two constraints the markup must respect:

- The accordion toggle is a `<button>` inside a form and needs explicit `type="button"`, or
  it submits the record. Bootstrap's documented example omits `type` because it is not shown
  in a form context.
- `.accordion-header` cannot hold the existing *Set as Head* and *Remove* buttons without
  nesting them inside the toggle button. They move to the top-right of `.accordion-body`.

This branch runs third because a collapsed `.accordion-body` is `display:none`, so a
`required` field inside it is unreachable to native validation. Branch 2's `novalidate` plus
JS-driven validation is the prerequisite. On submit, any member card holding an invalid field
auto-expands before focus moves to it.

### Field guards

- **Age.** Computed from the birthday input and shown as `.form-text` beside it ("Age: 67").
  The eligibility rule then fires at the checkbox rather than on submit. To avoid duplicating
  thresholds in JS, `FamilyAgeEligibility` gains a public static accessor for its bounds and
  the view renders them as `data-min-age` / `data-max-age` on the affected checkboxes, which
  already carry `data-sector-code`. Current rules: sector `B` and category `Bata (Children)`
  are under-18; sector `SC` and category `Senior Citizen` are 60-and-over
  (`FamilyAgeEligibility.php:10-13,58-64`). `selectionError()` stays authoritative and
  unchanged.
- **Relationship.** Marked `required`.
- **Middle name.** A "No middle name" checkbox that clears and disables the field.
- **Contact number.** Accepts an 11-digit mobile (`09XXXXXXXXX`) or a Biñan landline
  (7-8 digits, optional `(049)` prefix), with `inputmode="numeric"`. Remains optional. The
  looser rule is deliberate: a mobile-only pattern would block a worker from saving an edit
  to an older record whose landline they never touched.

### Verification

`vendor/bin/phpunit`; Playwright at desktop and 390px with a multi-member household —
confirm collapse and expand, live header summaries, auto-expand on invalid submit, and that
the accordion toggle does not submit the form.

## Out of scope

- Duplicate-person detection across QR numbers. Only QR uniqueness is checked today. Real
  gap, separate spec.
- The Excel import review screen, beyond what Branch 1's normalizer change reaches.
- Pre-existing dead code noted during the audit but not created by this work.
