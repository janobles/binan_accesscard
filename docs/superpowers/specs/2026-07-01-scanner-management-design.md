# Scanner Management (Phase 1) — Design

**Branch:** `feat/qr-access-cards`
**Date:** 2026-07-01
**Predecessor:** `docs/superpowers/specs/2026-07-01-scanner-module-design.md`
**Summary of predecessor:** `docs/superpowers/summary/2026-07-01-scanner-module-summary.md`

## Goal

Give the Scanner module a clear two-surface shape and its first real management
back-office:

- **Scan** = point-of-service, one family at a time (house-to-house or a queue):
  scan a QR, verify the person, log the aid claim, and see *that family's* aid
  history.
- **Manage** = global back-office overview: search/browse *all* logged
  distributions across all families, void a wrong entry, and manage the list of
  aid types.

This is the "management-first" half of the larger plan. **Reports/statistics are
a separate, later spec** and serve a different purpose (analytics), not this one.

## Why management before reports

Reports slice aid distribution by aid type, barangay, and received/not-received.
Those charts are meaningless on thin data with only three hardcoded aid types.
Aid-type CRUD is a hard dependency for the report selectors, and Manage is the
layer that *produces and grooms* the data reports later visualize. Build the
data-producing layer first.

## Manage vs Scan vs Reports (purpose boundaries)

- **Scan** — single QR / single family. Verify + log + this-family history.
  Operational, field device.
- **Manage** — aggregated, row-level records across all families. Search a
  family, filter, and correct (void) individual claims; manage aid types.
  Operational, desk. **Mutates** records.
- **Reports** (later) — aggregates, charts, percentages, barangay slices,
  export. **Read-only** analytics. No row-level editing.

Manage does not clash with Reports: Manage edits individual records, Reports
summarizes them.

## Scope

**In scope (Phase 1):**

1. **Scan tab: log-in-place + identity enrichment.** Logging a distribution
   moves *into* the Scan tab (it is the point-of-service action). The scan panel
   also gains `birthday` + `sex` per member for identity verification. This
   family's aid history continues to show inline.
2. **Manage tab: global distributions + aid types.** A back-office hub with
   two sections: (a) a searchable/filterable table of **all** aid distributions
   with a **void** action, and (b) **Aid Types CRUD**.

**Explicitly out of scope (deferred to later specs):**

- Reports / statistics / charts / report generation.
- A full-family-info modal (#5 in the original rundown). **Dropped by design** —
  see "Decision: no full-info modal."
- Excel → `qr_control` bulk migration tooling.

## Nav

Scanner nav becomes: **`Scan` | `Manage`**. The disabled `Reports` stub stays
disabled (re-enabled by its own future spec). The current standalone log screen
(`Scanner/manage.php` "Log Distribution") is refactored away — see below.

---

## Component 1 — Scan tab (log-in-place + identity fields)

Today the Scan tab only verifies, then *links out* to `scanner/manage` to log
(`logDistLink.href = .../scanner/manage?control_no=...`). That indirection is the
source of the "how is Manage's log different from Scan?" confusion. Consolidate:

- **Move logging into the Scan tab.** After a successful lookup, the scan panel
  shows the log-aid form inline (claimant dropdown from `familyMembers`, aid-type
  dropdown from `AidTypeModel::active()`, claim date). Submitting posts to the
  existing `ScanController::logAid()` and refreshes this family's history in
  place. No redirect to a separate screen.
- **Identity enrichment.** Widen `MemberModel::familyMembers(int $headId)` select
  from `memberID, firstname, lastname, relationship` to also include `birthday,
  sex` (both exist on `member`: `birthday` date, `sex` enum('Male','Female')).
  Render birthday + sex on each member row so the scanner can confirm the
  claimant's identity against the person present. All values continue to pass
  through the existing client-side `esc()` helper before `innerHTML` injection
  (no new XSS surface).
- **This-family history** already renders inline (`historyFor(controlNo)`); keep
  it. It answers "has this household received this week/month/year?" — no rows =
  not yet.

`ScanController::logAid()` itself is unchanged server-side (already validates,
verifies the claimant belongs to the resolved family, inserts `aid_distribution`,
writes `audit_trails`, returns refreshed history). Only its entry point moves
from the standalone `manage.php` screen into the Scan panel.

---

## Component 2 — Manage tab (global back-office)

New `Scanner/manage.php` is repurposed from "log one distribution" into a global
hub with two sections (Bootstrap nav-pills or stacked cards — **Bootstrap /
SB-Admin components only**, project non-negotiable), rendered via the Scanner
dashboard dispatch.

### 2a. All distributions — search + void

- **Model:** extend `App\Models\Scanner\AidDistributionModel` with a global
  listing method (e.g. `allDistributions(): array`) joining `aid_type.name`,
  member name (claimant), and family head, newest first. Reuse the existing
  `family-datatable.js` / DataTables stack (already wired via the asset manifest)
  for client-side search/filter/sort — same pattern the Admin manage-records
  table uses.
- **View:** a DataTable of every claim: family head, claimant, aid type, claim
  date, scanned-by (`userID` → username), with a per-row **Void** action.
- **Void = hard-delete + audit.** `aid_distribution` has no `dt_deleted` column
  and we cannot add one (no-migrations / SQL-dump-source-of-truth rule), so
  voiding a wrong entry deletes the row and writes an `audit_trails` entry
  recording what was voided (aidID, family, aid type, claimant, original
  scanner). Correcting a mis-scan = void here, then re-log in the Scan tab.
  Add `void(int $aidId): bool` to the model.

### 2b. Aid Types CRUD

Reuses the existing **Lookups CRUD pattern** (sectors/services/categories under
`app/Controllers/Lookups/`): create, archive (soft-delete via `dt_deleted`),
restore, delete, each writing an `audit_trails` row. Aid types are **name-only**
— no description, no quota. `aid_type` already has exactly the needed columns
(`aid_type_id`, `name`, `dt_created`, `dt_deleted`); **no schema change, no
migration.**

- **Model — `AidTypeModel` (extend existing):** add alongside `active()`:
  - `all(): array` — active + archived (active/archived flagged) for the table.
  - `create(string $name): int` — insert, stamping `dt_created`.
  - `archive(int $id): bool` — set `dt_deleted = now()`.
  - `restore(int $id): bool` — set `dt_deleted = null`.
  - `delete(int $id): bool` — hard delete; controller/UI must refuse when the
    type has any `aid_distribution` rows (archive is the safe default). Archived
    types drop out of `active()` (gone from the scan dropdown) but stay joinable
    in history.
- **View:** table of aid types (name + status) + "Add" modal + per-row
  archive/restore/delete, mirroring `app/Views/Lookups/categories.php`.
- **Dropdown wiring:** no change — the Scan log form reads
  `AidTypeModel::active()`, which reflects managed types automatically.

### Controller

A `Scanner\ManageController` (or methods grouped on the existing scanner
controller) handles the Manage-tab mutations. Guard **every** action with
`RoleAccess::requireRole(['Scanner', 'Admin', 'Developer'])` (the role list the
rest of the module inlines). Every mutation (aid-type create/archive/restore/
delete, distribution void) writes an `audit_trails` row with a human-readable
label. Actions redirect back with a flash message.

### Routes — `app/Config/Routes.php` (scanner group)

```
GET  scanner/manage                          -> Manage hub page (dashboard dispatch)
POST scanner/aid-types/create                -> aid-type create
POST scanner/aid-types/archive/(:num)        -> aid-type archive/$1
POST scanner/aid-types/restore/(:num)        -> aid-type restore/$1
POST scanner/aid-types/delete/(:num)         -> aid-type delete/$1
POST scanner/distributions/void/(:num)       -> distribution void/$1
```

The existing `scanner/log` (POST) and `scanner/lookup` (GET) stay; the Scan tab
now posts to `scanner/log` inline instead of navigating to a separate screen.

---

## Decision: no full-info modal (#5 dropped)

From the scanner role's actual workflow — confirm the person matches the card,
pick the claimant, hand over aid, log it, avoid double-claims — the fields that
matter are **name, relationship, birthday, sex, and aid history** (shown in the
Scan tab). The Admin "View Record" modal additionally exposes civil status,
contact number, religion, education, job, monthly income, and sectors:
**social-worker/admin profiling data irrelevant to distributing aid**, and every
extra field is more PII surfaced to a front-line role.

Beyond privacy, a full-info modal is a **security liability**. The scanner module
deliberately decouples `control_no` from `headID` and returns generic 404s so a
Scanner cannot enumerate families. Reusing the Admin `family/view/(:num)` route
(keyed by `headID`) and merely adding the Scanner role to
`requireFamilyViewAccess()` would let a Scanner loop `headID = 1..N` and read
every family's full PII — reintroducing exactly the enumeration the design
prevents. Avoiding the modal avoids the hole entirely. (If a full view is ever
justified, it must go through a **control_no-keyed** scanner endpoint that
resolves control→head server-side and 404s on unregistered codes, never a
raw-headID route.)

---

## Security posture

- Every Manage action guarded per-action via
  `RoleAccess::requireRole(['Scanner', 'Admin', 'Developer'])`.
- Every mutation (aid-type CRUD, distribution void) writes an `audit_trails`
  row (project rule: every data mutation is audited). Void is especially
  audit-sensitive because it hard-deletes a distribution — the audit row is the
  only remaining record of the voided claim.
- No new PII enumeration surface: identity fields are added only to the
  already-authorized post-scan family payload; no full-info / headID-keyed route
  is added.
- Pre-existing, app-wide (not introduced here): the global CSRF filter is
  commented out in `app/Config/Filters.php`. Flagged for awareness, consistent
  with prior specs.

## Testing

Consistent with the suite's skip-without-DB posture (guard/contract tests, no DB
round-trips):

- `AidTypeModelTest` — extend to cover `all/create/archive/restore/delete`
  contracts.
- `AidDistributionModelTest` — extend to cover `allDistributions()` and
  `void()` contracts.
- `ManageControllerTest` (new) — assert the role-guard literal
  `['Scanner', 'Admin', 'Developer']` on each action and that mutations invoke
  the audit logger; assert `void` deletes + audits.
- `MemberModelTest` (or existing coverage) — assert `familyMembers()` selects
  `birthday` and `sex`.
- Run `vendor/bin/phpunit` before and after. Smoke-test: (1) Scan a registered
  control number → member rows show birthday + sex → log a claim inline → this
  family's history updates; (2) Manage → add an aid type → it appears in the scan
  dropdown → archive it → it disappears from the dropdown but stays in history;
  (3) Manage → search all distributions → void an entry → row gone, audit row
  written.

## Deferred (next spec)

- Aid distribution **Reports** (charts, %, barangay selectors, report
  generation) — the read-only analytics half, unblocked by managed aid types and
  a populated distribution table.
- Excel → `qr_control` bulk migration (parallels the family Excel import landing
  on `Mel-import-branch`).
