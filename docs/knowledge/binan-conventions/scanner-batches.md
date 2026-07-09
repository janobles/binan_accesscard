# Scanner Batches & Kiosk Shell

**Scope:** distribution batches, per-scanner performance stats, and the
kiosk-vs-dashboard shell split in the scanner module.

## Rule 1: Batch = one giving event; at most one open; aid type bound at open

`distribution_batch` (dump V16; V15 + `aid_type_id`) holds one row per giving
event, now including the aid type distributed in it. `closed_at IS NULL` marks
the single open batch; the invariant is enforced in
`App\Models\Scanner\DistributionBatchModel::open()` (refuses when one is
already open), not by a DB constraint. Closing a batch is the manual
statistics reset — the next batch starts from zero. The admin picks the aid
type when opening the batch; the kiosk never picks an aid type — every scan
during that batch logs against `distribution_batch.aid_type_id`.

`aid_distribution.batch_id` stamps every handout with the batch that was
open when it was logged. `batch_id NULL` = pre-batch history; batch-scoped
views never include it.

## Rule 2: Batch/aid-type lifecycle is Admin/Developer only, and audited

Open/close and aid-type CRUD live in
`Admin\DistributionController::openBatch()/closeBatch()` (`POST
admin/batches/open`, `POST admin/batches/close/{id}`) behind
`RoleAccess::requireRole(['Admin', 'Developer'])`. Both write `audit_trails`
rows. The UI is the "Batches" tab on `admin/distribution`, rendered through
the shared admin shell (`DashboardPageBuilder::renderAdminPage()` +
`Admin/layout.php`) — this surface was moved out of the scanner module
entirely; the kiosk has no back-office pages.

## Rule 3: Scanning is blocked without an open batch

- `scanner/scan` renders an empty state (no redirect loop) when no batch is
  open.
- `ScanController::logAid()` returns **409** when no batch is open (covers
  a batch closed mid-session). Every insert stamps the open batch's id and
  aid type.

## Rule 4: Kiosk shell vs admin shell

- **Kiosk shell** — `app/Views/Scanner/kiosk-layout.php`: full-viewport,
  green-themed, no sidebar/topbar; slim header (batch name · aid-type badge ·
  live `#myBatchCount` counter · logout). Used by `scan.php` and
  `performance.php` — the kiosk's only two pages. Time-and-motion rules apply:
  no per-scan keypresses added, page fits viewport without scrolling.
- **Admin shell** — `Admin/layout.php`: the SB-Admin dashboard frame. Owns
  aid-type/batch control (`admin/distribution`) and overall reports
  (`admin/reports`). The old scanner dashboard shell (`Scanner/layout.php`)
  and its back-office views/routes (`scanner/manage`, `scanner/reports`,
  `scanner/setting`, `Scanner\ManageController`, `Scanner\ReportsController`)
  are deleted.

The scan flow is simply: open `scanner/scan` → scan. No aid-type picker step;
the active batch's aid type is shown read-only in the kiosk header.

## Rule 5: Performance stats are batch-scoped and role-filtered

`AidStatsModel` methods take a trailing `?int $batchId` (batch-scoped only —
the date-range filter is removed entirely, not just superseded).
`perScanner(int $batchId, ?int $onlyUserId)` returns
`{userID, scanner, handouts, families}` rows — `families` =
`COUNT(DISTINCT control_no)`. The Scanner role only ever sees its own row on
`scanner/performance` (`ScanController` passes `$onlyUserId` server-side,
never hides rows client-side). `admin/reports` shows the full per-kiosk table
(Admin/Developer only), including the PDF export.

The live counter on the scan page updates from the `myBatchCount` field in
the `scanner/log` JSON response
(`AidDistributionModel::familiesForUserInBatch()`).

**Caveat:** the `.env` developer account has no `users` row (`user_id` 0),
so its handouts store `userID NULL` and appear as "Unknown" in perScanner /
keep the live counter at 0. Pre-existing auth design, not a stats bug; test
per-scanner features with real `users`-table accounts.
