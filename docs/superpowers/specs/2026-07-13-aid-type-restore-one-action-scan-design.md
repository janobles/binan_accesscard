# Aid Type Restore + One-Action Scan + Kiosk Member Details (V18 corrected)

**Date:** 2026-07-13
**Status:** Approved design, pending implementation plan
**Supersedes:** `2026-07-13-batches-service-refactor-design.md` (its core premise
was wrong; its page-split and UI decisions survive)

## Problem

The previous spec assumed `aid_type` (Financial/Rice/Grocery) was superseded by
the `services`/`category` reference data, so PR 1 dropped the table and rebound
batches and scans to `service_id`. That premise is wrong: aid types are their
own concept with their own table, unrelated to services and programs. The
service binding must be reverted while keeping the parts that are still wanted:
the page split (`admin/batches` / `admin/distributions`), the create-batch
modal, and the planned PR 2 UI/UX pass.

Two scope additions ride along:

1. **One-action scan.** The kiosk currently requires scan, then a confirm
   (Enter) on a claimant/date form. Replace with a single action: scan logs the
   distribution immediately. A repeat scan reports a duplicate and writes
   nothing.
2. **Kiosk member details.** The scan family panel should show which sectors,
   services & programs, and categories each member belongs to.

## Decisions (user-approved)

1. **Full revert of the service binding.** Batches and scans bind to
   `aid_type_id` again. Services are not linked to batches at all.
2. **Forward-fix on this unmerged branch** (no git revert surgery). The V18
   dump and patch are rewritten in place.
3. **Aid Types gets its own page under the Reference Data sidebar group**
   (`admin/aidtypes`), CRUD styled like Manage Categories / Services and
   Programs. Aid-type CRUD no longer lives on a distribution page.
4. **One-action scan: claimant is always the family head, date is always
   today.** No form, no confirm, no override.
5. **Duplicate scope is the batch.** One logged distribution per family per
   batch, regardless of date. A family scanned in a previous batch can be
   logged again only when a new batch is open.
6. **Banners, Bootstrap-faithful:** big full-width `alert-success` banner on
   log ("Logged"), big `alert-danger` banner on repeat ("Duplicate Entry").
7. **Kiosk member details are per-member only, kiosk only** (Manage Records
   untouched). Family head details always visible; other members inside a
   collapsed Bootstrap collapse/accordion, expanded on demand so the kiosk
   stays uncluttered.
8. **PR structure unchanged:** this branch is PR 1 (schema + backend + pages,
   corrected); PR 2 is the UI/UX pass with aid-type columns instead of service
   codes.

## Schema (V18 dump rewritten in place)

`accesscardV18.sql` = V17 base with:

- `aid_type` table **kept** exactly as V17 (`aid_type_id`, `name`,
  `dt_created`, `dt_deleted`; seed Financial/Rice/Grocery).
- `distribution_batch.aid_type_id` and `aid_distribution.aid_type_id` **kept**
  with their V17 indexes (`idx_db_aidtype`, `idx_ad_type`). No `service_id`
  columns anywhere.
- Test seed rows removed: sector 11 (`TS`), category 8 (`TSC`), services 47–48
  (`TS1`, `TSC1`).
- Developer account row (`developer` / `developer123`, argon2id,
  `account_level 'developer'`).
- No family/QR data (V14 demo-workflow rule).

`sql/patches/v18-batch-service.sql` is replaced by a patch that only removes
the test reference rows and upserts the developer account (no column changes —
V17 schema already matches). Rename the patch to reflect its real content.

Delete `accesscardV17.sql` once V18 import + smoke test pass (rule carried
over from the previous spec).

## Backend changes (mostly reverts of PR 1 commits)

- Restore `AidTypeModel`; remove the service joins from
  `DistributionBatchModel` and distribution queries.
  `DistributionBatchModel::open()` takes `aid_type_id` again;
  single-open-batch invariant unchanged.
- `ScanController::logAid()` stamps `aid_type_id` from the open batch;
  409-when-no-batch unchanged.
- `AidStatsModel`, `admin/reports`, `ReportsPdfGenerator`, `pdf/report.php`:
  back to aid-type columns/counts.
- Kiosk header badge shows the batch's aid type name.
- Batch open/close remain audited (`audit_trails`), Admin/Developer only.

## Aid Types reference page

- Route `admin/aidtypes` (+ CRUD POST routes), Admin/Developer only, sidebar
  link in the Reference Data group next to Manage Categories and Services and
  Programs.
- Same page pattern as the existing reference pages (toolbar standard, table,
  add/edit modal, soft delete via `dt_deleted`).
- Controller: dedicated `Admin\AidTypesController` (or equivalent per repo
  conventions), view data assembled via `DashboardPageBuilder`.

## Batches and Distributions pages (kept from PR 1, rebound)

- `admin/batches`: create modal = batch name + single aid-type select (the
  category → service cascade is removed). Tables show aid type name.
- `admin/distributions`: paged, searchable log; aid type column replaces
  service/shortcode columns.

## One-action scan flow

Client (`app/Views/Scanner/scan.php`):

- Scan/enter control number → single request to the server which looks up the
  family **and logs the distribution in the same call** (claimant = family
  head, `claim_date` = today, `aid_type_id` + `batch_id` from the open batch).
- Response renders the family panel plus one of two banners:
  - **Logged:** full-width `alert-success`, large text — aid type, head name,
    family control number.
  - **Duplicate Entry:** full-width `alert-danger`, large text — this family
    already has a row in the current batch; show when it was logged and by
    whom. Nothing is written.
- The Log Distribution form (date field, claimant select, Confirm button) and
  the bare-Enter-confirms handler are removed. Auto-clear of the input and the
  focus guard stay.
- Lookup failures (unknown control number, network) keep the existing
  empty-state error surface.

Server:

- Duplicate check is authoritative server-side:
  `aid_distribution WHERE batch_id = <open batch> AND control_no = <scanned>`
  exists → duplicate response, no insert. The client never decides alone.
- Insert path unchanged otherwise (userID from session, kiosk batch count
  returned for the header counter).
- Existing scan-history payload stays so the Aid History panel keeps working.

## Kiosk member details

- Lookup payload gains per-member reference data: sectors (from
  `member.sectorID` JSON, resolved to sector shortcode/name), services (from
  `member_services` join `services`), and categories (derived from each
  service's category).
- Family Head card: always visible, now includes the head's sector, category,
  and service badges (shortcodes, e.g. `SC`, `FA`, `EDA8`).
- Members list: wrapped in a Bootstrap collapse (collapsed by default) so the
  panel stays compact; expanding reveals member rows, each with their own
  badges. Expansion is optional and never required for the scan flow — no
  added per-scan keypresses.

## Out of scope

- Any schema change beyond restoring the V17 batch/aid-type shape and the
  test-row cleanup.
- Migrations (repo rule: SQL dump is schema source of truth).
- Sector/service/category badges in Manage Records family view.
- Editing or overriding a logged distribution from the kiosk.
- Retrofitting non-QR pages to the design system.

## Verification

- `php spark routes` resolves; `vendor/bin/phpunit` green before/after.
- Fresh import of rewritten V18 + smoke test: login, create aid type on the
  new page, open batch via modal, kiosk scan auto-logs (success banner),
  re-scan same card shows Duplicate Entry with no new row, close batch, open
  new batch, same card logs again, reports render by aid type → then delete
  V17.
- Playwright snapshots/screenshots for every touched page at desktop and
  390px (PR 2), Manage Records as design source of truth.
- CodeRabbit CLI review per repo workflow before merging each PR; fall back
  to /code-review if the CLI refuses.
