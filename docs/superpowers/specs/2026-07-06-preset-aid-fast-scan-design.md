# Pre-set Subsidy Type + Fast-Scan Distribution - Design

**Date:** 2026-07-06
**Module:** Scanner (subsidy distribution)
**Status:** Approved for planning

## Problem

At a point-of-service, families line up to claim aid. The current Scan flow makes
the scanner pick the **subsidy type** *after* every scan, inside the per-family Log
Distribution form. With a long queue this per-scan dropdown selection is a
time-and-motion bottleneck: the subsidy type is the same for the whole distribution
event, yet it is re-chosen for each family.

## Goal

Set the subsidy type **once**, before scanning, then rapid-fire scan family after
family. Each scan resolves the family, defaults the claimant, and needs only a
single **Confirm** tap to log - subsidy type already locked in.

## Decisions (from brainstorming)

- **Pre-set scope:** subsidy type **only**. Date still auto-fills to today per scan.
  No named "distribution event" concept, no new table.
- **Claimant:** defaults to the **family head**, editable per scan (dropdown of
  members) in case someone else claims.
- **Confirmation:** **one-tap Confirm** after scan (not auto-log). Guards against
  mis-scans while staying fast.
- **Duplicate guard:** if the family already claimed the **pre-set subsidy type on
  today's date**, show a **warning** but still allow override via Confirm.

## Non-goals

- No DB schema change. Existing `aid_distribution` table already carries
  `control_no`, `memberID`, `aid_type_id`, `claim_date`, `userID` - sufficient.
- No new route, controller, or model method (see Backend).
- No named event / location / session-notes feature.
- No change to Manage or Reports tabs.
- No new UI framework, CDN, or JS library. Reuse the existing SB-Admin +
  Bootstrap 5 + Bootstrap Icons stack and vanilla JS already in `scan.php`.

## User flow

1. Scanner opens **Scan**. A sticky top card shows **"Distributing: [subsidy type ▾]"**.
   Until a subsidy type is chosen, scanning/lookup is blocked with an inline hint.
2. Scanner picks the subsidy type once. It stays set for every subsequent scan.
3. Scanner scans a QR (camera) or enters the control number.
4. Family card renders: head name + address, **claimant dropdown defaulted to the
   head**, today's date, Aid History, and one prominent **Confirm** button.
5. If the family already claimed the pre-set subsidy type **today**, a warning banner
   ("Already claimed [aid] today") shows and the Confirm button takes a warning
   style - override is deliberate, not accidental.
6. Scanner taps **Confirm** → POST logs the handout + audit row (existing
   transactioned path) → success flash → card clears → focus returns to the
   control-number input, subsidy type still set. Ready for the next family.

## Architecture

### Frontend - `app/Views/Scanner/scan.php` (primary change)

Markup (Bootstrap / SB-Admin components only):

- **Sticky setup card** at top: `card` containing a `form-select` (`#sessionAidType`,
  populated from the existing `$aidTypes`) for the pre-set subsidy type, plus the
  existing control-number `input-group` and camera button moved beneath it.
- The per-scan **Log Distribution form's subsidy-type `select` is removed.** The
  hidden `aid_type_id` is instead set from `#sessionAidType` at Confirm time.
- **Claimant** dropdown stays, but is auto-selected to the head after lookup.
- **Confirm** button replaces "Log Distribution"; label reflects state (normal
  `btn btn-success`; warning `btn btn-warning` when duplicate detected).
- **Duplicate banner**: an `alert alert-warning` (`#dupAlert`) toggled from JS.

JS (vanilla, in the `scripts` section, extending current code):

- Guard: `lookup()` and the camera/Go handlers refuse to run if
  `#sessionAidType` has no value; show an inline hint on the setup card instead.
- After a successful lookup, auto-select the head in `#memberID`.
- **Duplicate detection is client-side** - the `lookup` response already returns
  `history` rows carrying `aid_type` (name) and `claim_date`. Compare against the
  selected subsidy type's **id** and **today's date**; if a match exists, show
  `#dupAlert` and switch Confirm to warning style. No backend call added.
  - Note: match on subsidy-type **id** (not name). Aid-type names are NOT unique in
    `aid_type` (no is_unique rule, no unique index), so history rows must expose
    `aid_type_id` - added by Task 1.
- On Confirm submit, set the hidden `aid_type_id` from `#sessionAidType` before
  building the `FormData`, then POST to the existing `scanner/log` unchanged.
- After success: clear the family panel, reset the control input, refocus it.
  Keep `#sessionAidType` untouched.

### Backend - minimal change

- `AidDistributionModel::historyFor()` gains `aid_type_id` in its select so the
  client can do id-based duplicate detection. Everything else untouched.
- `ScanController::logAid()` already validates and inserts `control_no`,
  `memberID`, `aid_type_id`, `claim_date`, `userID` inside a transaction with an
  audit row. The pre-set flow supplies the same POST fields, so the controller,
  routes, and audit path are untouched.
- `ScanController::scan()` already passes `$aidTypes` to the view - reused to
  populate the sticky selector.

## Error handling

- **No subsidy type set:** lookup/scan blocked; inline hint on the setup card.
- **Duplicate (same subsidy type, today):** warning banner + warning-styled Confirm;
  override allowed.
- **Lookup failure / unregistered QR / network error:** unchanged from current
  `lookupAlert` handling.
- **Log failure:** unchanged - server returns field/general errors rendered in
  `#fieldErrors`; the 500 transaction-rollback path is preserved.

## Testing

- Existing PHP unit suite (`vendor/bin/phpunit`) must stay green - backend
  change is one select column, so no controller/model test changes expected.
- Manual smoke test (per CLAUDE.md):
  1. Login as Scanner → Scan tab.
  2. Confirm scanning is blocked until a subsidy type is chosen.
  3. Set subsidy type; scan/enter a control number; verify claimant defaults to head.
  4. Confirm → verify `aid_distribution` row + `audit_trails` row written.
  5. Re-scan the same family same subsidy type → verify duplicate warning shows and
     override still logs.
  6. Change claimant to a non-head member → verify it logs against that member.
  7. Verify subsidy type stays set across consecutive scans; input refocuses.

## Risk / open items

- **Aid-type name uniqueness** does NOT hold, so client-side duplicate detection
  matches on `aid_type_id` (added to `historyFor()` in Task 1), not on name.
