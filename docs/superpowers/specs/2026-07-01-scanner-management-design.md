# Scanner Management (Phase 1) — Design

**Branch:** `feat/qr-access-cards`
**Date:** 2026-07-01
**Predecessor:** `docs/superpowers/specs/2026-07-01-scanner-module-design.md`
**Summary of predecessor:** `docs/superpowers/summary/2026-07-01-scanner-module-summary.md`

## Goal

Give the Scanner module its first management surface: let staff **manage the
list of aid types** that the scan log-aid dropdown draws from, and **enrich the
scan panel** with enough identity fields that a front-line scanner can verify
the person standing in front of them. This is the "management-first" half of the
larger plan; **Reports/statistics are a separate, later spec.**

## Why management before reports

Reports slice aid distribution by aid type, barangay, and received/not-received.
Those charts are meaningless on thin data with only three hardcoded aid types.
Aid-type CRUD is a hard dependency for the report selectors, and it is the layer
that *produces* the data reports later visualize. Build the data-producing layer
first.

## Scope

**In scope (Phase 1):**

1. **Aid Types CRUD tab** — add / archive / restore / delete aid types.
2. **Scan-panel enrichment** — show `birthday` + `sex` per member for identity
   verification.

**Explicitly out of scope (deferred to later specs):**

- Reports / statistics / charts / report generation.
- A global History tab (per-family aid history already shows in the scan panel;
  broader slicing belongs to Reports).
- A full-family-info modal (#5 in the original rundown). **Dropped by design** —
  see "Decision: no full-info modal."
- Excel → `qr_control` bulk migration tooling.

## Nav

Scanner nav becomes: **`Scan` | `Aid Types`**. The disabled `History` and
`Reports` stubs stay disabled (Reports is re-enabled by its own future spec).

---

## Component 1 — Aid Types CRUD

Reuses the existing **Lookups CRUD pattern** (the sectors/services/categories
controllers under `app/Controllers/Lookups/`): create, archive (soft-delete via
`dt_deleted` timestamp), restore, delete, each writing an `audit_trails` row.

Aid types are **name-only** — no description, no quota. `aid_type` already has
exactly the needed columns (`aid_type_id`, `name`, `dt_created`, `dt_deleted`);
**no schema change, no migration** (consistent with the project's
no-migrations / SQL-dump-source-of-truth rule).

### Model — `app/Models/Scanner/AidTypeModel.php` (extend existing)

Add alongside the existing `active()`:

- `all(): array` — active + archived, for the management table (active first, or
  flagged), so archived rows can be shown/restored.
- `create(string $name): int` — insert, stamping `dt_created`; returns new id.
- `archive(int $id): bool` — set `dt_deleted = now()`.
- `restore(int $id): bool` — set `dt_deleted = null`.
- `delete(int $id): bool` — hard delete (only meaningful for an
  already-archived, never-referenced type; see integrity note).

Keep `allowedFields` as-is (`name`, `dt_deleted`); manage `dt_created` explicitly
on insert (model has `useTimestamps = false`).

**Referential integrity:** `aid_distribution.aid_type_id` references
`aid_type`. Archive (soft-delete) is the safe default and MUST be preferred —
it keeps historical distributions readable. Hard `delete()` is exposed to mirror
the Lookups pattern but the controller should refuse (or the UI should hide it)
when the type has any `aid_distribution` rows. Archived types are excluded from
`active()` so they no longer appear in the scan dropdown, but remain joinable in
history.

### Controller — `app/Controllers/Scanner/AidTypeController.php` (new)

Mirrors `Lookups/CategoryController` in shape:

- Guard **every** action with
  `RoleAccess::requireRole(['Scanner', 'Admin', 'Developer'])` (same role list
  the rest of the Scanner module inlines).
- Actions: `create()`, `archive(int $id)`, `restore(int $id)`, `delete(int $id)`
  — all POST, all validate (`name`: required, reasonable max length, unique
  among active), all write `audit_trails` via `Audit/AuditTrailsModel` with a
  human-readable label (e.g. `aid type "Rice" #2`), then redirect back with a
  flash message.
- The list page itself is rendered by the Scanner dashboard dispatch (below),
  not by this controller.

### Routes — `app/Config/Routes.php`

Under the existing `scanner` group:

```
GET  scanner/aid-types                      -> list page (dashboard dispatch)
POST scanner/aid-types/create               -> AidTypeController::create
POST scanner/aid-types/archive/(:num)       -> AidTypeController::archive/$1
POST scanner/aid-types/restore/(:num)       -> AidTypeController::restore/$1
POST scanner/aid-types/delete/(:num)        -> AidTypeController::delete/$1
```

### View — `app/Views/Scanner/aid-types.php` (new)

Mirrors `app/Views/Lookups/categories.php`: a Bootstrap / SB-Admin table of aid
types (name + status), an "Add" button opening a Bootstrap modal, and per-row
archive/restore/delete actions. **Bootstrap + SB-Admin components only** (project
non-negotiable). Uses the same `asset_styles`/`asset_scripts`/`asset_url`
helpers and the shared role-aware sidebar.

### Wiring the dropdown

No change needed: the scan log-aid form already reads
`AidTypeModel::active()`, which now reflects managed types automatically.

---

## Component 2 — Scan-panel enrichment (identity fields)

The scan panel currently renders each member as name (+ relationship). Add
**birthday** and **sex** so the scanner can confirm the claimant's identity
against the person present.

- **Model:** widen `MemberModel::familyMembers(int $headId)` select from
  `memberID, firstname, lastname, relationship` to also include `birthday, sex`
  (both exist on `member`: `birthday` date, `sex` enum('Male','Female')).
- **Payload:** `ScanController::lookup()` already returns
  `members => familyMembers($headId)`; the new fields flow through unchanged.
- **View:** in `app/Views/Scanner/scan.php`, extend the `membersList` row
  rendering to show birthday + sex under/next to each member name. All values
  continue to pass through the existing client-side `esc()` helper before
  `innerHTML` injection (no new XSS surface).

No new endpoint, no new route, no PII beyond what the scanner already sees for
the family they just scanned.

---

## Decision: no full-info modal (#5 dropped)

From the scanner role's actual workflow — confirm the person matches the card,
pick the claimant, hand over aid, log it, avoid double-claims — the fields that
matter are **name, relationship, birthday, sex, and aid history** (already shown
or added in Component 2). The Admin "View Record" modal additionally exposes
civil status, contact number, religion, education, job, monthly income, and
sectors: **social-worker/admin profiling data irrelevant to distributing aid**,
and every extra field is more PII surfaced to a front-line role.

Beyond privacy, a full-info modal is a **security liability**. The scanner module
deliberately decouples `control_no` from `headID` and returns generic 404s so a
Scanner cannot enumerate families. Reusing the Admin `family/view/(:num)` route
(keyed by `headID`) and merely adding the Scanner role to
`requireFamilyViewAccess()` would let a Scanner loop `headID = 1..N` and read
every family's full PII — reintroducing exactly the enumeration the design
prevents. Avoiding the modal avoids the hole entirely. (Should a full view ever
be justified later, it must go through a **control_no-keyed** scanner endpoint
that resolves control→head server-side and 404s on unregistered codes, never a
raw-headID route.)

---

## Security posture

- Every Aid Types action guarded per-action via
  `RoleAccess::requireRole(['Scanner', 'Admin', 'Developer'])`.
- Every Aid Types mutation writes an `audit_trails` row (project rule: every
  data mutation is audited).
- No new PII enumeration surface: Component 2 adds fields only to the
  already-authorized post-scan family payload; no full-info / headID-keyed route
  is added.
- Pre-existing, app-wide (not introduced here): the global CSRF filter is
  commented out in `app/Config/Filters.php`. Flagged for awareness, consistent
  with prior specs.

## Testing

Consistent with the suite's skip-without-DB posture (guard/contract tests, no DB
round-trips):

- `AidTypeModelTest` — extend to cover the new `all/create/archive/restore/
  delete` method contracts.
- `AidTypeControllerTest` (new) — assert the role-guard literal
  `['Scanner', 'Admin', 'Developer']` on each action and that mutations invoke
  the audit logger.
- `MemberModelTest` (or existing coverage) — assert `familyMembers()` selects
  `birthday` and `sex`.
- Run `vendor/bin/phpunit` before and after; smoke-test: aid-type add → appears
  in scan dropdown → archive → disappears from dropdown but stays in history;
  scan a registered control number → member rows show birthday + sex.

## Deferred (next spec)

- Aid distribution **Reports** (charts, %, barangay selectors, report
  generation) — the data-consuming half, now unblocked by managed aid types.
- Excel → `qr_control` bulk migration (parallels the family Excel import landing
  on `Mel-import-branch`).
