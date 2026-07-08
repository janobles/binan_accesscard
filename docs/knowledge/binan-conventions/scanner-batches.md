# Scanner Batches & Kiosk Shell

**Scope:** distribution batches, per-scanner performance stats, and the
kiosk-vs-dashboard shell split in the scanner module.

## Rule 1: Batch = one giving event; at most one open

`distribution_batch` (dump V15) holds one row per giving event. `closed_at
IS NULL` marks the single open batch; the invariant is enforced in
`App\Models\Scanner\DistributionBatchModel::open()` (refuses when one is
already open), not by a DB constraint. Closing a batch is the manual
statistics reset — the next batch starts from zero.

`aid_distribution.batch_id` stamps every handout with the batch that was
open when it was logged. `batch_id NULL` = pre-batch history; batch-scoped
views never include it.

## Rule 2: Batch lifecycle is Admin/Developer only, and audited

Open/close live in `Scanner\ManageController::openBatch()/closeBatch()`
(`POST scanner/batches/open`, `POST scanner/batches/close/{id}`) behind
`RoleAccess::requireRole(['Admin', 'Developer'])` — stricter than the
module's page guard. Both write `audit_trails` rows. The UI is the
"Batches" tab on `scanner/manage`.

## Rule 3: Scanning is blocked without an open batch

- `scanner/setting` renders a paused state when no batch is open.
- `ScanController::scan()` redirects to `scanner/setting` when the batch is
  missing or `?aid_type` is not an active aid type.
- `ScanController::logAid()` returns **409** when no batch is open (covers
  a batch closed mid-session). Every insert stamps the open batch's id.

## Rule 4: Kiosk shell vs dashboard shell

Two shells in `app/Views/Scanner/`:

- **Kiosk shell** — `kiosk-layout.php`: full-viewport, no sidebar/topbar;
  slim header (batch name · aid-type badge · live `#myBatchCount` counter ·
  change-type · logout). Used by the scan flow: `setting.php`, `scan.php`.
  New scanner-facing scan-flow pages go here. Time-and-motion rules apply:
  no per-scan keypresses added, page fits viewport without scrolling.
- **Dashboard shell** — `layout.php`: SB-Admin frame with scanner-only
  sidebar. Used by `reports.php`, `manage.php` (back-office pages).

The scan flow is: `scanner/setting` (pick aid type, once per session) →
`scanner/scan?aid_type=N`. The scan page carries the aid type as a
server-filled hidden input + `AID_TYPE_NAME` JS constant — no in-page
dropdown.

## Rule 5: Performance stats are batch-scoped and role-filtered

`AidStatsModel` methods take a trailing `?int $batchId`; a chosen batch
wins over the from/to date window (the controller clears the dates).
`perScanner(int $batchId, ?int $onlyUserId)` returns
`{userID, scanner, handouts, families}` rows — `families` =
`COUNT(DISTINCT control_no)`. The Scanner role sees only its own row:
`ReportsController` passes `$onlyUserId` server-side (never hide rows
client-side). The PDF's per-scanner table is Admin/Developer only.

The live counter on the scan page updates from the `myBatchCount` field in
the `scanner/log` JSON response
(`AidDistributionModel::familiesForUserInBatch()`).

**Caveat:** the `.env` developer account has no `users` row (`user_id` 0),
so its handouts store `userID NULL` and appear as "Unknown" in perScanner /
keep the live counter at 0. Pre-existing auth design, not a stats bug; test
per-scanner features with real `users`-table accounts.
