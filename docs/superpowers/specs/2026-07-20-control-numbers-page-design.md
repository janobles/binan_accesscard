# Control Numbers page + Aid→Subsidy relabel — design

**Date:** 2026-07-20
**Status:** Approved (brainstorming)
**Supersedes UI of:** `app/Views/Cards/batch_form.php` (the old "Generate Cards" page)

This spec covers two related relabeling changes shipped together:

1. **Control Numbers page** — rebuild the "Generate Cards" page (below).
2. **Aid → Subsidy relabel** — rename the user-facing word "aid" to "subsidy"
   across the app (see the final section). Display text only; no DB/schema.

## Goal

Replace the two-dropdown "Generate Cards" page with a more comprehensive
**Control Numbers** page for issuing printable QR access cards. Give the operator
precise control over *who* gets a card and a read-only preview of the exact
selection before committing to a PDF.

## Scope

- Rename the feature "Generate Cards" → **Control Numbers** (sidebar + page).
- Two segmented modes: **Batch** and **Single card**.
- Batch: filter by barangay and/or control-number range; live preview table.
- Single card: searchable head picker OR exact control number → one card.
- Drop the sector filter entirely.
- Generate actions use the blue `btn-primary` (printing is not record creation),
  never the add-green `#198754`.
- PDF stays the actual output; there is no PDF preview.

Route (`admin/cards`), the PDF/ZIP generator, the control-number bijection, and
the audit behavior are unchanged except where noted.

## Non-goals

- No schema/migration changes (SQL dump is source of truth).
- No edit/archive/row actions in the preview table — it is read-only.
- No change to the scanner, `qr_control` semantics, or `ControlNumber`.

## UI

House style: toolbar/controls above the card, Bootstrap grid (`.row`/`.col-md-*`,
`.g-*` gutters, full-width stack at 390px), `segmented-tabs` (`page_tabs.php`
nav-pills pattern) to switch modes client-side (no reload).

```
Control Numbers                         (heading + one-line help)
[ Batch | Single card ]                 ← segmented-tabs

┌─ card ───────────────────────────────────────────────┐
 BATCH (default):
   [ Barangay ▾ ] [ From # ] [ To # ]
   help: "Leave all blank to print every active head."
   ── preview ──────────────────────────────────────────
   "N cards will be generated"
   | Control # | Head name | Barangay |   (read-only, first ~50)
   ...and N more
   [ Generate cards ]  (btn-primary)     status →

 SINGLE:
   [ Head — searchable (col-8) ] [ Control # (col-4) ]
   help: "Pick a head OR type an exact control number."
   [ Generate card ]  (btn-primary)      status →
└───────────────────────────────────────────────────────┘
```

### Batch mode

- Fields: **Barangay** (canonical `FamilyProfilingFormV2::barangays()` dropdown,
  "All barangays" default), **From #** and **To #** (numeric control-number
  bounds, either or both optional).
- Filters AND-combine. Blank From/To = open-ended bound. All blank = every active
  head with a `qr_control` mapping (existing behavior).
- **Preview table** (read-only): columns Control # · Head name · Barangay.
  - Live-updates on filter change (debounced ~300 ms) via the shared heads
    endpoint. Shows the total count in the header ("N cards will be generated").
  - DOM cap: render first ~50 rows, then "…and N more" — never dump a 500-row
    table.
  - Empty state: "No heads match — adjust filters." Generate disabled.
  - Over `QrCardSettings->maxQuantity`: inline warning, Generate disabled (mirror
    the server-side guard so the client fails early with the same message).
- **Generate cards** posts the same barangay + range filter to `batch()`. What the
  preview shows is exactly what prints (same `headsForCards` selection).

### Single card mode

- **Head** searchable field: type ≥2 chars → autocomplete list of matching active
  heads (name — #controlNo — barangay); pick one.
- **Control #** exact input as an alternative. Head pick and exact number are
  mutually exclusive; whichever is set resolves to one head.
- Resolve control → memberID client-side, then reuse the existing
  `GET admin/cards/card/{memberID}` route (single-card generation + reprint audit).
  No new single-card server path needed.
- No preview table (one card; the picker already shows who).

## Backend

### New endpoint — heads search / preview

`GET admin/cards/heads` → `Cards\QrCardController::heads()`, role-guarded
(`Developer`, `Admin`) like its siblings. Returns JSON:

```json
{ "count": 123, "rows": [ { "memberID": 42, "controlNo": 42, "name": "Dela Cruz, Juan", "barangay": "Canlalay" } ] }
```

- Serves **both** the Single-card autocomplete and the Batch preview.
- Query params: `q` (name keyword, autocomplete), `barangay`, `from`, `to`
  (batch preview). `count` is the full match size; `rows` is capped (≈50 for
  preview, ≈15 for autocomplete via a `limit` param).
- Backed by `MemberModel::headsForCards()` (same selection the PDF uses) plus an
  optional keyword filter, so preview and output can never diverge.

### `MemberModel::headsForCards()`

- Add `controlFrom` / `controlTo` int filters → `qr_control.control_no`
  `>=` / `<=` bounds.
- Add optional `keyword` filter (name LIKE) for the Single-card autocomplete.
- Add `limit` support (autocomplete/preview caps); return an accurate total count
  separately for the preview header (either a sibling count method or a `count`
  return — decided in the plan).
- Keep `memberID` and `barangay`. The `sectorID` branch is no longer exercised by
  the UI; keep the parameter for now (no caller passes it) — removal is optional
  cleanup, deferred to the plan to keep the diff surgical.

### `QrCardController::batch()`

- Read `from` / `to` POST params → `controlFrom` / `controlTo` filter alongside
  `barangay`. Stop reading `sectorID`.
- Keep the `maxQuantity` guard, the empty-selection 400, the single audit row, and
  the `X-CSRF-TOKEN` refresh header.
- Audit scope string reflects the new filter (barangay + range).

### Sidebar

`dashboard_sidebar.php`: label "Generate Cards" → "Control Numbers"; href/icon
unchanged.

## Audit

Unchanged. Batch writes ONE `audit_trails` row via `AuditTrailsModel::logAction`
with the filter scope; single-card generation uses the existing reprint audit in
`card()`.

## Testing

- `MemberModel::headsForCards()` with `controlFrom`/`controlTo`/`keyword` returns
  the expected heads (range bounds inclusive; keyword narrows).
- `heads()` endpoint: role guard, JSON shape, count vs capped rows, empty result.
- `batch()` with a range filter generates and writes one audit row; empty match →
  400; over-max → 400.
- Playwright: Batch preview updates on filter change and matches the generated
  count; Single-card picker resolves and downloads; both buttons are blue; desktop
  + 390px layouts match the Manage Records house style.

## Fold-in: relabel "Aid" → "Subsidy" (user-facing)

Rename the visible word **"aid" → "subsidy"** everywhere it appears to the user.
Rationale: "subsidy" reads unambiguously as government assistance; "aid" is
vaguer. Preserve the surrounding phrasing and capitalization, e.g.:

| Before                 | After                     |
|------------------------|---------------------------|
| Aid Distribution       | Subsidy Distribution      |
| Aid Type / Aid Types   | Subsidy Type / Subsidy Types |
| Received Aid           | Received Subsidy          |
| Aid Coverage           | Subsidy Coverage          |
| Aid History            | Subsidy History           |
| Aid Distribution Report| Subsidy Distribution Report |
| "aid already recorded" | "subsidy already recorded" |
| "Choose an aid type…"  | "Choose a subsidy type…"  |

### In scope (display text only)

- **Views** (`app/Views/**`): table headers, empty states, labels, placeholders,
  option text, section headings, KPI/nav labels, the scanner `pdf/report.php`
  output.
- **KPI / nav labels built in code**: `app/Views/Admin/layout.php` label arrays,
  `DashboardPageBuilder` and any controller/library that assembles a user-visible
  label or flash/error message (e.g. "Choose an aid type for this batch.").
- **Audit-trail strings** passed to `AuditTrailsModel::logAction()` (e.g.
  "Logged aid distribution", "Voided aid distribution #N"). New rows will read
  "subsidy"; old rows keep "aid" (mixed history is acceptable). Update any test
  asserting the old wording.

### Explicitly NOT touched (per "don't touch DB", surgical scope)

- DB schema, table names (`aid_distribution`, `aid_type`), column names, enum
  values — the SQL dump is the source of truth.
- Route URLs / segments (`admin/aidtypes`, `admin/distribution`) — visible label
  changes, the URL does not.
- PHP identifiers: class/method/variable names, model names (`AidStatsModel`,
  `AidDistributionModel`), `$aid*` vars, `getAid*()`, config keys.
- Code comments — mechanical, not user-facing; leave unless trivially adjacent.

### Approach

Not a blind global sed — each hit is triaged: relabel only strings a user reads,
leave every identifier/URL/schema reference. Grep surface to work through:

```
grep -rniE '\baid\b' app/Views app/Controllers app/Libraries --include='*.php'
```
filtered to string literals / template text, skipping identifiers, `aid_*`,
`aidtypes`, and URL builders.

### Testing (relabel)

- `vendor/bin/phpunit` green; update audit-assertion tests to "subsidy".
- Playwright sweep: sidebar, dashboard KPIs, distribution tabs, scanner Aid
  History panel, and the reports PDF all read "Subsidy"; no route 404s (URLs
  unchanged).

## Open decisions (resolved in the plan, not blocking)

1. Preview count: a sibling `countHeadsForCards()` vs a `count` field folded into
   one method. Lean toward a small dedicated count for clarity.
2. `headsForCards` `sectorID` param: keep (no churn) vs delete (dead once UI drops
   it). Default: keep, note as deferred cleanup.
