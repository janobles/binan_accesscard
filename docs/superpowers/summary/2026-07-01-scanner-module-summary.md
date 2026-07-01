# Scanner Module — Implementation Summary

**Branch:** `feat/qr-access-cards`
**Date:** 2026-07-01
**Spec:** `docs/superpowers/specs/2026-07-01-scanner-module-design.md`
**Plan:** `docs/superpowers/plans/2026-07-01-scanner-module.md`

## Goal

Give front-line staff (a new `Scanner` role) a mobile-first page to scan a
family's paper QR access card (hardware scanner, manual entry, or phone
camera), pull up the family + prior aid history, and log a new aid
distribution — with a full audit trail and no leakage of PII for
unregistered/unknown codes.

## What was built

| Area | File(s) | Purpose |
|------|---------|---------|
| Schema | `accesscardV13.sql` | 3 new tables: `qr_control` (`control_no` PK, `headID`, `dt_created`) maps a paper QR number to a family head; `aid_type` (`aid_type_id` PK, `name`, `dt_created`, `dt_deleted`) seeded with **Financial, Rice, Grocery**; `aid_distribution` (`aidID` PK, `control_no`, `memberID`, `aid_type_id`, `claim_date`, `userID`, `dt_created`) logs each claim. Also adds `'scanner'` to `users.account_level` enum. |
| Control-number lookup | `app/Models/Scanner/QrControlModel.php` | `headForControl(int $controlNo): ?int` — resolves a scanned paper control number to a family `headID`, or `null` if unregistered. |
| Aid types | `app/Models/Scanner/AidTypeModel.php` | `active(): array` — non-archived (`dt_deleted IS NULL`) aid types for the log-aid dropdown. |
| Aid history/logging | `app/Models/Scanner/AidDistributionModel.php` | `logAid(array $data): int` inserts a claim; `historyFor(int $headId): array` joins `aid_type.name` + member name, newest first, for the family panel. |
| Controller | `app/Controllers/Scanner/ScanController.php` | `scan()` — GET, renders the scan page. `lookup(int $controlNo)` — GET JSON `{head, members, history}`; returns a generic 404 for unregistered/missing codes (no-leak). `logAid()` — POST; validates input, verifies the claimant belongs to the resolved family, inserts the `aid_distribution` row, writes `audit_trails`, returns refreshed history. All three actions guarded by `RoleAccess::requireRole(['Scanner','Admin','Developer'])`. |
| Routes | `app/Config/Routes.php` | New `scanner` group: `GET scanner/scan`, `GET scanner/lookup/(:num)`, `POST scanner/log`. |
| Views | `app/Views/Scanner/layout.php`, `app/Views/Scanner/scan.php` | Mobile-first Bootstrap/SB-Admin shell with tab nav (Scan active; Reports/History/Aid Types disabled stubs) using the project's `asset_styles`/`asset_scripts`/`asset_url` helpers and Bootstrap Icons. `scan.php` provides input (hardware scanner / manual entry / camera), the family panel, aid history list, and the log-aid form; AJAX calls `scanner/lookup` and `scanner/log`, with a client-side `esc()` helper escaping all AJAX response data before `innerHTML` injection. |
| Camera decode | `public/vendor/html5-qrcode/html5-qrcode.min.js` | Vendored MIT library (~375KB) — the **only** non-Bootstrap/SB-Admin component in the feature; live camera QR decoding must run client-side (PHP has no camera access). Committed with `git add -f` because the bare `vendor/` rule in `.gitignore` would otherwise swallow `public/vendor/`. |
| Role/redirect | `app/Libraries/RoleAccess.php` | `normalizeRole()` maps `'scanner' => 'Scanner'`; `redirectByRole()` sends the `Scanner` role to `scanner/scan` after login. |
| Claimant list | `app/Models/Families/MemberModel.php` | Added `familyMembers(int $headId): array` — head plus relatives, head first — used to populate the log-aid claimant dropdown. |
| Admin nav | `app/Views/Admin/layout.php` | Added a "Scanner" sidebar link (`bi-upc-scan` → `scanner/scan`) so Admin/Developer can reach the module directly. |
| Tests | `tests/unit/ScannerRoleTest.php`, `QrControlModelTest.php`, `AidTypeModelTest.php`, `AidDistributionModelTest.php`, `ScanControllerTest.php` | Guard/contract tests (role mapping, model method contracts, controller role-guard literals) consistent with the suite's skip-without-DB posture — no DB round-trips required to pass. |

## Core decision: control_no is decoupled from memberID

`qr_control` is a standalone mapping table (`control_no → headID`). The paper
QR numbers physically printed and distributed (1..100,000) are **arbitrary
external assignments** — they are not, and must not be treated as, the
`memberID`/`headID` of the family they're issued to. This directly resolves
the shared-ID concern raised at kickoff: a head shares its `memberID` with its
own family members, so if `control_no` had been made equal to `headID` (as in
the earlier QR-access-cards feature, which derives its control number as a
bijection of `headID`), a scanned code would ambiguously collide with member
ids. The `qr_control` indirection keeps the two numbering spaces independent
and lets paper stock be issued/reissued without any coupling to internal ids.

## Seed data

`aid_type` ships pre-seeded with three rows via `accesscardV13.sql`:
**Financial**, **Rice**, **Grocery** (each with `dt_created` set, `dt_deleted`
null). These populate the log-aid dropdown out of the box; aid-type CRUD
(add/archive/restore more types) is deferred (see below).

## Tests

New: `ScannerRoleTest`, `QrControlModelTest`, `AidTypeModelTest`,
`AidDistributionModelTest`, `ScanControllerTest`. All are non-DB guard/contract
tests (role-guard assertions, model method signatures/contracts), matching the
existing suite's posture of skipping gracefully without a live database
connection — no new DB-dependent test debt was introduced.

## Deviations worth noting

- **Font Awesome → Bootstrap Icons** — the project ships no Font Awesome
  assets, so the sidebar/tab icons use Bootstrap Icons (already available)
  instead.
- `ScanController` inlines the role-list literal `['Scanner','Admin','Developer']`
  rather than pulling from a shared constant; `ScanControllerTest` asserts
  against that literal substring directly.
- `getRoutes('GET')` is called with an explicit uppercase method string to
  match this project's CI4 version's routing API.
- `accesscardV13.sql` is also the commit that **first tracks the whole
  ~7351-line dump in git** — it had previously been untracked (present on
  disk, absent from history).

## Security posture

- Every Scanner action (`scan`, `lookup`, `log`) is guarded per-action via
  `RoleAccess::requireRole(['Scanner','Admin','Developer'])`.
- Unregistered or unmapped control numbers return a generic 404 — no
  distinguishing signal between "code not found" and "code found but not
  provisioned," preventing enumeration of valid vs. invalid codes.
- Every `logAid()` call writes an `audit_trails` row, consistent with the
  project's "every family mutation is audited" rule.
- The scan page's AJAX rendering is hardened against DOM-based XSS: all
  server-returned data is passed through a client-side `esc()` helper before
  being written via `innerHTML`.
- Pre-existing, app-wide (not introduced by this feature): the global CSRF
  filter is commented out in `app/Config/Filters.php`. Flagged for awareness,
  consistent with how the QR-access-cards feature flagged the same issue.

## Deferred (later spec)

- Aid distribution **reports** generation.
- **History-management** screens (the History tab is a disabled stub in the
  scan-page nav).
- **Aid-type CRUD** (create/archive/restore beyond the seeded three).
- The **Excel → `qr_control`** bulk data-migration tooling to bootstrap the
  mapping table from the real paper-card assignment spreadsheet.
