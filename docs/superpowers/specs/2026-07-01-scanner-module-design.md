# Scanner Module — Aid Distribution Tracker — Design

**Date:** 2026-07-01
**Branch:** `feat/qr-access-cards`
**Builds on:** `docs/superpowers/specs/2026-06-29-qr-access-cards-design.md`
(QR card generation). This spec adds the **scanning / aid-distribution** side.

Biñan Access Card (CodeIgniter 4, City of Biñan CSWD). A mobile-first module that
lets **scanner-role** users record government-aid distribution against the paper QR
control numbers (1–100,000) handed to family heads.

## Goal

- Give the Scanner module its own sidebar label and shell with tabs.
- Scan a paper QR (hardware scanner, phone camera, or manual entry) → resolve the
  control number to the family head → show head + member details → show that QR's
  chronological aid history → log a new aid distribution (date, aid type, claimant).
- **No CI4 migrations.** New tables are hand-written into the SQL dump
  (`accesscardV13.sql`) and imported directly (per supervisor directive: the ban is
  on `php spark migrate` files, not on new tables existing). Code reads/writes them
  through normal Models.

## Scope

**In scope (this spec):** the scan flow — input, resolve, display, history view,
log a distribution.

**Deferred (later spec):** reports generation, history-log management screens, and
CRUD of aid types. This spec seeds aid types as fixed rows and only *reads* them.

## Why the old "control_no == memberID" trick does not apply

The QR-card feature (2026-06-29) derived the control number from the head's
`memberID` (a bijection, no stored mapping). That works when the system *mints* the
QR. Here the QRs are **pre-printed paper codes 1–100,000** physically handed to
families, and an encoder later pairs *whatever code a family holds* to their profile
in Excel. A control number is therefore an **arbitrary external assignment** —
control_no 55 is not family #55. This requires an explicit mapping table, and it
cleanly decouples the control number from `member.memberID` (which is shared by a
head and its members — the concern raised at kickoff).

## Data Model

Three tables, hand-written into `accesscardV13.sql` (no migration files):

```sql
-- Pre-loaded from the encoders' Excel (control_no ↔ family head). This spec reads
-- it; the Excel→DB migration itself is a separate operational task.
CREATE TABLE `qr_control` (
  `control_no` INT(11) NOT NULL,          -- 1..100000, the paper QR number
  `headID`     INT(11) NOT NULL,          -- member.memberID where headID = memberID
  `dt_created` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`control_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Isolated scanner-module lookup for the aid-type dropdown. Seeded now; CRUD is a
-- later spec. Kept separate from the existing `services` table per supervisor's
-- isolation directive (scanner role cannot reach the services/lookups tabs).
CREATE TABLE `aid_type` (
  `aid_type_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `dt_created`  TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `dt_deleted`  TIMESTAMP NULL DEFAULT NULL,   -- soft archive for later CRUD
  PRIMARY KEY (`aid_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `aid_type` (`name`) VALUES ('Financial'), ('Rice'), ('Grocery');

-- What the scan flow writes: one row per aid handout.
CREATE TABLE `aid_distribution` (
  `aidID`       INT(11) NOT NULL AUTO_INCREMENT,
  `control_no`  INT(11) NOT NULL,         -- the QR that claimed (FK qr_control)
  `memberID`    INT(11) NOT NULL,         -- head OR member who physically claimed
  `aid_type_id` INT(11) NOT NULL,         -- FK aid_type
  `claim_date`  DATE NOT NULL,            -- defaults to today at capture
  `userID`      INT(11) DEFAULT NULL,     -- scanner who logged it
  `dt_created`  TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`aidID`),
  KEY `idx_control_no` (`control_no`)     -- history lookups by QR
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- **History** for a QR = `aid_distribution` rows where `control_no = ?`, ordered by
  `claim_date` / `dt_created`. Empty result set = "no aid received yet."
- `memberID` records *which individual* claimed (head or any family member), so the
  same QR can show who came each time.

## Role & Shell

- **New role.** Add `scanner` to the `users.account_level` enum in the dump.
  `RoleAccess::normalizeRole()` maps `'scanner' => 'Scanner'`.
  `RoleAccess::redirectByRole()` sends a `Scanner` login to `scanner/scan`.
- **Dedicated mobile-first shell** `app/Views/Scanner/layout.php`, built with
  Bootstrap + SB Admin components only. Sidebar tabs: **Scan** (this spec);
  **Reports**, **History**, **Aid Types** rendered as disabled/stub links for the
  later spec.
- Scanner-role users see **only** this shell. Admin/Developer additionally get a
  "Scanner" sidebar link into the same module (oversight/testing).

## Scan Flow — `scanner/scan`

Single mobile-first page:

1. **Input** — one auto-focused text field. Accepts:
   - **Hardware scanner** (keyboard-wedge): types the number + Enter → submit.
   - **Manual entry**: type the number → submit.
   - **Camera**: "Scan with camera" button opens an html5-qrcode viewfinder; a
     decoded code fills the field and submits.
   All three collapse to a single integer control number.
2. **Resolve** — `QrControlModel` looks up `control_no` → `headID`. Unknown code →
   inline Bootstrap alert ("QR not registered"), no record shown.
3. **Display** — read-only panel: family head + members, reusing the field layout
   from the manage-records detail view, laid out in SB Admin cards.
4. **History** — `aid_distribution` list for this `control_no` (SB Admin list-group
   / table): date, aid type, claimant name. Empty → "No aid received yet."
5. **Log distribution** — form:
   - **claim_date** — date input, defaults to today.
   - **aid type** — dropdown from `aid_type` (non-archived).
   - **claimant** — dropdown of the head + each family member.
   On submit → insert `aid_distribution` + one `audit_trails` row → history section
   refreshes (AJAX) with the new entry.

## Backend

**Controller** `App\Controllers\Scanner\ScanController`
- `scan()` — GET, renders the shell + scan page.
- `lookup(int $controlNo)` — GET, JSON: `{head, members, history}` or 404 for an
  unregistered code.
- `logAid()` — POST: validates control_no / aid_type / claimant / date, inserts an
  `aid_distribution` row, writes an `audit_trails` entry, returns the refreshed
  history JSON.
- All actions guarded: `RoleAccess::requireRole(['Scanner','Admin','Developer'])`.

**Models** (`app/Models/Scanner/`)
- `QrControlModel` — `headForControl(int $controlNo): ?int`, plus a helper to fetch
  head + members for display (delegating to `MemberModel`).
- `AidDistributionModel` — `historyFor(int $controlNo): array`, `logAid(array)`.
- `AidTypeModel` — `active(): array` for the dropdown.
- Reuse `MemberModel` (family/member detail) and `Audit\AuditTrailsModel` (trail).

**Routes** — new `scanner` group in `app/Config/Routes.php`:
```
scanner/scan                 GET  -> Scanner\ScanController::scan
scanner/lookup/(:num)        GET  -> Scanner\ScanController::lookup/$1
scanner/log                  POST -> Scanner\ScanController::logAid
```

## Views (Bootstrap + SB Admin only)

- `app/Views/Scanner/layout.php` — mobile-first shell + tab sidebar.
- `app/Views/Scanner/scan.php` — input, result panel, history, log form.
- Reuse the manage-records detail field arrangement for the read-only head/member
  display (referenced from `app/Views/Family/family-modal.php`).

## Custom / Non-Bootstrap Components (flagged per request)

- **html5-qrcode** — a vendored MIT-licensed JS library, the **only** component
  beyond Bootstrap + SB Admin. Required because live phone-camera QR decoding must
  run client-side in the browser; PHP/CI4 runs server-side and cannot access the
  camera (a PHP QR lib only *generates* codes or decodes an *uploaded* image). The
  viewfinder is wrapped in a standard Bootstrap modal/card so the surrounding UI
  stays within the design system.
- Hardware-scanner and manual entry need **no** library — a plain text input.

## Error Handling

- Unknown / non-numeric control number → inline alert, no record leak.
- Camera permission denied / no camera → fall back to manual + hardware input with a
  clear message; the page never hard-depends on the camera.
- `logAid` validation failure → field-level errors, no partial insert.
- Missing family head for a mapped control_no (data drift) → generic "record
  unavailable" message, logged server-side.

## Testing (`vendor/bin/phpunit` before and after)

- `QrControlModelTest` — resolve control→head; unknown code → null.
- `AidDistributionModelTest` — `logAid` inserts; `historyFor` orders chronologically;
  empty history for an unclaimed QR.
- `AidTypeModelTest` — `active()` excludes archived rows; seeded rows present.
- `ScanControllerTest` — `lookup` JSON shape + 404; `logAid` inserts + writes audit;
  role guard rejects non-scanner/admin.
- `RoleAccess` — `scanner` normalizes to `Scanner`; `redirectByRole` targets
  `scanner/scan`.
- DB- and ext-dependent tests skip gracefully, consistent with the existing suite.

## Security / PII Note

Scanner users see the family head + full member details on a successful scan. This
is intentional and required to pick the claimant. Access is gated to the Scanner /
Admin / Developer roles; unregistered codes reveal nothing. (App-wide: the global
CSRF filter remains commented out — pre-existing posture, flagged for awareness.)

## Out of Scope (YAGNI)

- Reports generation, history-management screens, aid-type CRUD (later spec).
- Editing/voiding a logged distribution.
- The Excel→`qr_control` data migration tooling (operational, separate task).

## Summary (what this module adds)

- **New tables** (dump, not migrations): `qr_control` (pre-loaded control_no↔head),
  `aid_type` (seeded Financial/Rice/Grocery), `aid_distribution` (aid log).
- **New role** `scanner` (enum + `RoleAccess` mapping + login redirect), with a
  dedicated mobile-first shell that scanner users see exclusively.
- **New scan flow**: multi-source QR input → head/member display → per-QR aid
  history → log distribution, each write audit-trailed.
- **One custom component**: vendored `html5-qrcode` for camera scanning; everything
  else is Bootstrap + SB Admin.
- **Control number decoupled from memberID**, resolving the shared-ID concern.

### Note for the implementation summary

When this module is built, the implementation summary
(`docs/superpowers/summary/2026-07-01-scanner-module-summary.md`) must thoroughly
document **what was added, what was modified, and what is new** — every new file,
every touched existing file (Routes, RoleAccess, dump, sidebar/layout), the seeded
data, the vendored library, and the test coverage — matching the detail level of the
2026-06-29 QR-cards summary.

---

## Design Amendment (2026-07-01, post-e2e review)

After manual e2e testing, the user reversed two earlier decisions:

1. **No standalone mobile-first shell.** The bespoke `Scanner/layout.php` mobile
   shell (navbar + nav-pills) is a consistency violation. The Scanner module must
   render inside the **dashboard shell** — reusing the same SB-Admin frame as
   `Admin/layout.php` (`#wrapper` sidebar `navbar-nav bg-gradient-primary` +
   `#content-wrapper` topbar + `main.dashboard-content`, same `asset_styles`/
   `asset_scripts` helpers). Only the *content* of the scan tab is simplified for
   scan use. Scanner-role users get a **scanner-only sidebar** (Scan, Manage
   Distributions) and can never reach admin tabs — the role isolation is a
   security boundary.

2. **Scan is read-only; logging is a separate tab.** The scan tab resolves a QR
   to family head + members + chronological aid history — **read-only, no log
   form**. Logging an aid distribution moves to its own sidebar tab, the
   relabeled former "Aid Types" stub → **"Manage Distributions"**. That tab
   captures QR (prefillable from the scan view via `?control_no=`), claimant,
   date, and aid type, and POSTs to `scanner/log` (unchanged). Aid-type CRUD and
   Reports/History remain deferred stubs.

Backend deltas: `ScanController::manage()` (GET `scanner/manage`) renders the log
screen; `scan()` renders read-only (drops the inline `$aidTypes`); `lookup()` and
`logAid()` JSON endpoints are unchanged. Views: `Scanner/layout.php` rebuilt as a
dashboard shell; `Scanner/scan.php` stripped to read-only + a "Log distribution"
link; new `Scanner/manage.php` for logging. The vendored `html5-qrcode` and the
control_no↔head model layer are unchanged.
