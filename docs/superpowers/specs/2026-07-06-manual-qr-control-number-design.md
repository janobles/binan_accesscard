# Manual QR Control Numbers — Design

**Date:** 2026-07-06
**Status:** Approved (pending spec review)

## Problem

A family head can print a QR access card that the scanner then rejects as
"QR control number is not registered." Reproduced on a live dataset: head
MADRID printed with control number 3, but scanning it (or entering 2) fails.

### Root cause

The card printer and the scanner use **different sources of truth** for the
control number:

| Subsystem | Source | Fallback |
|-----------|--------|----------|
| Card print (`MemberModel::headsForCards()` → `QrCardPdfGenerator`) | `qr_control.control_no` | **falls back to `memberID`** when the head has no `qr_control` row |
| Scanner (`ScanController::lookup()`, `QrCardController::lookup()` → `QrControlModel::headForControl()`) | `qr_control` **only** | none → "not registered" |

Only two paths write `qr_control`:
- **Excel import** (`FamilyRecordWriter::persistFamily()` with a `controlNo` from
  the sheet's "QR Number" column).
- Nothing else.

**Manual "Add family"** (`FamilyController::store()` → `persistFamily()` with no
`controlNo`) never creates a mapping. Such a head still prints a card — using its
`memberID` as a fake control number — that the scanner cannot resolve.

Observed data fits exactly:
- TIBAY (control 1, real import) → has `qr_control` row → scans fine.
- MADRID (control 3, manually added, future birthday) → no `qr_control` row →
  card prints `memberID` 3 → scanner rejects.
- "Skipped 2" → `memberID` 2 is an auto-increment gap (a rolled-back import or a
  deleted member); control 2 was never registered, so rejecting it is correct.

The stale comment at `QrCardController.php:22-23` ("control number derived from
memberID … no stored code") is the fossil of the original design; the scanner was
later built on the `qr_control` table and the two were never reconciled.

## Decision

`qr_control` is the **single source of truth** for control numbers. The
`memberID` fallback is removed everywhere, and both manual family forms capture
and maintain the number.

## Changes

### 1. Drop the print fallback

- `MemberModel::headsForCards()`
  - Only return heads that have a `qr_control` row (exclude
    `qc.control_no IS NULL`). The existing `LEFT JOIN` stays for ordering, but the
    result set is filtered to mapped heads.
  - Remove the `?? (int) $row['memberID']` fallback at line 417; `controlNo` is
    always the real `control_no`.
- `QrCardPdfGenerator` — remove the now-dead `['controlNo'] ?? ['memberID']`
  fallbacks (lines 33, 34, 62, 63, 119). `controlNo` is always present.
- Consequence: `QrCardController::card($memberID)` reprint of an unmapped head
  returns 404 (correct — there is nothing scannable to print yet). Batch
  generation silently excludes unmapped heads; the printed count matches the
  scannable set.

### 2. Manual Add captures the number

- Add a **required** "QR Number" field to the family modal (head step).
- Validation: required, digits only, and **not already assigned** to another head
  (`qr_control.control_no` is the primary key, so a DB uniqueness check backs this).
- `FamilyController::store()` passes the field through as `persistFamily()`'s
  existing `$controlNo` argument → `QrControlModel::assign()` writes the row.
  The writer already supports this; only wiring is new.

### 3. Manual Edit maintains the number

- Same field, pre-filled with the head's current `control_no` on `editFamily()`.
- New `QrControlModel` method to **upsert** the head's mapping: insert when the
  head is an orphan, update when the number changed, reject a number owned by a
  different head.
- `FamilyController::update()` calls it inside its existing transaction. This is
  the backfill path for pre-existing orphans (e.g. MADRID): open → enter 3 → save
  → scannable.

### 4. Aid-history safety: lock after first claim

`aid_distribution.control_no` is a **denormalized int with no foreign key** —
written at claim time and queried by value (`AidDistributionModel::historyFor()`).
Editing a head's number does **not** cascade; old aid rows keep the old number and
become orphaned from both the new number and the head.

Therefore the QR Number field on Edit is:
- **Editable** when no `aid_distribution` row exists under the head's current
  `control_no` (orphan backfill, or typo fix before any claim).
- **Read-only** (with a note) once aid has been recorded under that number, so a
  lossy edit is impossible.

Cost: one `COUNT` on `aid_distribution` by the head's current `control_no` when
rendering and when validating the edit.

### 5. Error handling / UX

- Duplicate number → inline field error ("QR Number 3 is already assigned to
  another family"), HTTP 422, not a 500.
- Locked field → rendered read-only with an explanatory note; a submitted change
  to a locked number is rejected server-side (defense in depth, in case the field
  is tampered with).

## Out of scope

- Excel import path is unchanged (already writes `qr_control`).
- No schema/migration changes (SQL dump is the source of truth per project rules).
- History does **not** follow an edited number (explicitly rejected in favor of
  the lock).

## Testing

- `MemberModel::headsForCards()` excludes heads without a `qr_control` row and no
  longer emits `memberID` as a control number.
- Manual Add with a QR Number writes a `qr_control` row; the head then resolves
  via `QrControlModel::headForControl()`.
- Manual Add rejects a blank, non-digit, or already-taken number.
- Manual Edit backfills an orphan head's number; the head becomes scannable.
- Manual Edit rejects a number owned by another head.
- Manual Edit locks the field (server-side rejects a change) when aid history
  exists under the current number.
- Smoke: login → add family with QR number → generate card → scan → resolves to
  the family.

## Files touched

- `app/Models/Families/MemberModel.php` — `headsForCards()` filter + fallback removal.
- `app/Libraries/Qr/QrCardPdfGenerator.php` — remove dead fallbacks.
- `app/Models/Scanner/QrControlModel.php` — upsert-for-head method; helper to
  check aid history / current mapping.
- `app/Controllers/Families/FamilyController.php` — `store()` wires `controlNo`;
  `update()` upserts; `editFamily()` supplies current number + lock flag;
  validation rules.
- `app/Views/Family/family-modal.php` — QR Number field (add + edit, lock state).
- Tests under `tests/`.
