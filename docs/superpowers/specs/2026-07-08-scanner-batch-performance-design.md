# Scanner Batch & Performance Tracking — Design

**Date:** 2026-07-08
**Status:** Approved
**Scope:** QR scanner module — batch-scoped distribution tracking, per-scanner
performance stats, scan-flow split (setting page → scan page), minimal
kiosk shell for the scan flow.

## Goals

1. Track aid distribution per **batch** (one distribution event, e.g. one day
   of giving). A manual close ("reset") ends the batch; the next batch starts
   its statistics at zero.
2. **Individual performance:** each scanner-role user can answer "how many
   families have I verified / given aid to this batch?" — live during
   scanning and in reports.
3. **Admin overview:** Admin/Developer see per-scanner performance and batch
   totals.
4. Split the scan flow: a **distribution-setting page** (pick aid type)
   precedes the **scan page**.
5. The scan flow gets its own **minimal shell** (no sidebar/topbar); reports
   and manage stay in the dashboard shell.

## Schema — new dump `accesscardV15.sql`

No migrations (repo non-negotiable). Bump the SQL dump; re-import replaces
V14 in the demo workflow (drop DB → import V15 → import Excel via UI).

```sql
DROP TABLE IF EXISTS `distribution_batch`;
CREATE TABLE `distribution_batch` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

`aid_distribution` gains:

```sql
`batch_id` int(11) DEFAULT NULL,
KEY `idx_ad_batch` (`batch_id`)
```

Invariants:
- At most **one open batch** system-wide (`closed_at IS NULL`). Enforced in
  the model (open() refuses when an open batch exists), not by a DB
  constraint.
- Historical rows keep `batch_id = NULL` ("pre-batch history"); stats treat
  them as unbatched and they never appear in batch-scoped views.

## Batch lifecycle

- **Open/close:** Admin/Developer only, from the existing `scanner/manage`
  page — new "Distribution Batches" card: open form (name), close button on
  the active batch, list of past batches.
- Both actions write **audit trail** rows (`AuditTrailsModel`), consistent
  with the family-mutation rule.
- **Close = manual reset.** Stats for the next batch start at 0.
- **No open batch ⇒ scanning blocked:**
  - Setting page renders a "No active distribution — ask an administrator"
    state instead of the aid-type picker.
  - `POST scanner/log` returns **409** with a clear error if no batch is open
    (covers batch closed mid-session).

## Scan flow split + kiosk shell

Routes (all inside the existing `scanner` group, same role guard
Scanner/Admin/Developer):

| Route | Action |
|---|---|
| `GET scanner/setting` | Distribution-setting page: active batch info (name, date, running total) + aid-type picker → "Start scanning" |
| `GET scanner/scan?aid_type=N` | Scan page. Missing/invalid `aid_type` or no open batch → redirect to `scanner/setting` |
| `POST scanner/log` | As today, plus: server resolves the open batch and stamps `batch_id`; response includes `myBatchCount` |

Shell:
- New `app/Views/Scanner/kiosk-layout.php` — minimal full-viewport shell, no
  sidebar/topbar. Slim header bar: batch name · aid-type badge ·
  **"You: n families this batch"** live counter · "Change type" link (back to
  setting) · logout.
- `setting.php` (new) and `scan.php` (reworked) use the kiosk shell.
  `reports` and `manage` keep `Scanner/layout.php` (dashboard shell).
- The aid-type dropdown and its session-JS logic are **removed** from
  `scan.php`; the aid type comes from the setting page (server-validated
  param). Time-and-motion rules hold: scan → glance → one Enter; the split
  adds zero per-scan keypresses.
- Counter updates from the `myBatchCount` field in the `logAid` JSON
  response — no reload.
- Valid Bootstrap 5 components/utilities only; page fits viewport without
  scrolling (established scanner UX rules).

## Performance stats — reports page

`AidStatsModel` becomes batch-aware; new method `perScanner(int $batchId)`
returning per-user rows: scanner name (join `users`), handouts logged,
distinct families (control numbers) reached.

`scanner/reports` page:
- **Batch selector** (dropdown: active batch default, past batches listed).
  The existing from/to date filter remains for all-time views; batch scope
  and date scope are alternative filters (selecting a batch scopes all
  numbers to that batch).
- **Admin/Developer view:** batch summary cards (families served, total
  handouts, coverage) + per-scanner table + existing barangay/aid-type
  charts scoped to the selection.
- **Scanner view:** same page, but the performance section shows only the
  logged-in user's numbers; no other scanners' rows are rendered (filtered
  server-side, not hidden client-side).
- **PDF export** (`ReportsPdfGenerator`): gains batch scope; per-scanner
  table included for Admin/Developer only.

## Components

| Unit | Responsibility |
|---|---|
| `Models/Scanner/DistributionBatchModel` (new) | `activeBatch()`, `open(name, userId)`, `close(batchId)`, `all()` — single-open-batch invariant lives here |
| `Models/Scanner/AidStatsModel` | Existing methods gain optional batch scope; new `perScanner()` |
| `Models/Scanner/AidDistributionModel` | `logAid()` stores `batch_id`; new `countForUserInBatch()` |
| `Controllers/Scanner/ScanController` | New `setting()` action; `scan()` guards aid-type + open batch; `logAid()` stamps batch, returns `myBatchCount` |
| `Controllers/Scanner/ManageController` | Batch open/close actions (Admin/Dev guard + audit) |
| `Controllers/Scanner/ReportsController` | Batch selector param, role-aware data assembly |
| `Views/Scanner/kiosk-layout.php` (new) | Minimal shell |
| `Views/Scanner/setting.php` (new) | Setting page |

## Error handling

- Stats methods keep the existing no-DB posture: safe empty shapes on any DB
  error.
- `logAid` validation errors stay 422; missing batch is 409; role failure
  403 — matching existing controller conventions.
- Batch open when one is already open → flash error, no write.

## Testing

Extend the existing scanner test posture (unit tests skip without sqlite3):
- `DistributionBatchModel`: open/close, single-open invariant.
- `ScanController`: scan redirects to setting without aid type/open batch;
  `logAid` 409 when no batch; `batch_id` stamped; `myBatchCount` returned.
- `ManageController`: batch actions guarded to Admin/Developer, audited.
- `AidStatsModel::perScanner` shape; reports role visibility (scanner sees
  only self).
- Full `vendor/bin/phpunit` before and after.

## RAG / knowledge updates (post-implementation)

- `docs/knowledge/binan-conventions/`: document the kiosk-vs-dashboard shell
  convention and the batch concept.
- Update `violations.md` if the work fixes or reveals items.
- Refresh the binan-conventions grep index so the new docs are retrievable.

## Out of scope

- Multiple concurrent open batches (venues) — explicitly deferred.
- Reports page visual redesign (parked previously; this change adds sections
  in the current style).
- Backfilling `batch_id` for historical rows.
