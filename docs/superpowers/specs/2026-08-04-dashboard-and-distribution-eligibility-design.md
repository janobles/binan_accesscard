# Dashboard and Distribution Eligibility

Date: 2026-08-04
Status: approved design, not yet planned

## Problem

The dashboard cannot report subsidy distribution correctly. This is a data
problem, not a layout problem. An audit of the distribution path found the two
headline numbers are uncomputable from the current schema, and the live poll
does not survive production data volume.

Target scale: roughly 100,000 families citywide. One scan per family per batch.
Station count varies per event, typically 10 to 20.

### Audit findings

Numbers are stable audit IDs, grouped by theme rather than listed in order, and
are referenced by number later in this document.

Correctness:

1. Coverage has no valid denominator. `qr_control` carries no batch link, so
   `SubsidyStatsModel::receivedVsNot()` uses every family ever issued a QR as the
   total for every batch. A batch serving one barangay reads about 2 percent
   forever.
2. Barangay is parsed from free text. There is no `barangay` column on `member`,
   only `address text`. `byBarangay()` slices
   `SUBSTRING_INDEX(member.address, ',', -1)`. Seeded rows already yield dirty
   keys such as `" CANLALAY"` (leading space, uppercase). Excel-imported data at
   scale will produce hundreds of junk buckets.
3. Duplicate scans are refused but never recorded (`ScanController::logAid`), so
   repeat-claim attempts have no data behind them.
12. `SubsidyDistributionModel::void()` calls `delete()`. Voiding a mistaken scan
    destroys the row while the audit trail still references its
    `distribution_id`. Wrong default for a record of goods received.

Performance, severe at target scale:

4. `app/Views/Admin/reports-body.php` polls `distribution/reports/stats` every 5
   seconds per open dashboard. That endpoint runs four aggregates with no cache.
   `DashboardModel::stats()` caches for 60 seconds; `SubsidyStatsModel` does not.
5. `receivedVsNot()` counts in PHP: `count($b->get()->getResultArray())`
   hydrates one array per received family to produce an integer.
6. `byBarangay()` groups on a computed string, so it cannot use an index. Full
   scan plus filesort over `member` joined twice, every 5 seconds.

Structure:

7. Batch resolution is duplicated three times with the same defaulting rules:
   `ReportsController::resolveBatch()`, `DashboardPageBuilder::buildReportsData()`,
   `ScanController::performance()`.
8. `bySubsidyType` is dead weight. A batch binds one subsidy type, so within a
   batch it is always one row. It is not rendered, but is still computed on every
   poll and printed in the PDF.
10. `scanner/performance` is orphaned. Routed at `Routes.php:140`, linked from no
    view. The Scanner role does not appear in `Config\Navigation` at all
    (`ALL_STAFF` is Developer, Admin, Encoder, Viewer), so scanners have no
    sidebar. The per-station dashboard is built and unreachable.
11. `openBatch()` accepts name and subsidy type only. Eligibility is new UI, not
    a modification.

## Decisions

### Eligibility is snapshotted, not computed live

A batch stores its criteria and materialises the resulting family list into a
roster table when it opens. That roster is the denominator, permanently.

Rejected alternative: evaluate criteria as a WHERE clause on read. It breaks
three ways. A family profiled after the event silently joins the denominator of
a closed batch, so a printed 92 percent becomes 89 percent. An address
correction moves a family between barangays retroactively. Coverage for a closed
event gets recomputed forever, when it is a fact that stopped changing the
moment the batch closed. A distribution batch is a financial and legal record,
so its denominator freezes at open.

Three consequences, all wanted:

- Closed-batch statistics are immutable, so they cache permanently.
- The denominator is a stored integer and the numerator an indexed count.
- The `member.sectorID` JSON column is read once per batch during the roster
  build, never on the hot path. No migration to that column is needed.

The roster table also makes externally supplied masterlists (DSWD Listahanan,
4Ps, barangay-submitted lists) an additive feature later: importing a list is a
second way to populate `batch_eligibility`, with no schema change and no rework.

### Barangay becomes a real reference table

`member.barangayID` replaces free-text parsing. The family form gets a dropdown,
the Excel importer maps to it, and the barangay chart groups on an indexed
integer. Existing addresses need a one-time backfill, which is imperfect by
nature: unmatched rows land in an "Unassigned" bucket that encoders can correct.

### Void becomes soft

`subsidy_distribution.dt_voided` replaces the hard delete. Voided rows are
excluded from every statistic, so the family correctly returns to "not served",
and the event survives for audit.

## Schema (V19 to V20)

No migrations. The SQL dump is the source of truth, so this is a new
`accesscardV20.sql` plus a patch under `sql/patches/`.

```
barangay              barangayID, name, dt_deleted
                      (reference table, seeded with the city's barangays)
member                + barangayID int NULL, KEY idx_member_brgy
distribution_batch    + eligible_count int NOT NULL DEFAULT 0
batch_barangay        batch_id, barangayID
batch_sector          batch_id, sectorID
batch_eligibility     batch_id, headID   PK (batch_id, headID)
subsidy_distribution  + dt_voided timestamp NULL
```

Empty `batch_barangay` means citywide. Empty `batch_sector` means all sectors.

## Opening a batch

One modal, no wizard. Fields:

1. Name and subsidy type (exists today)
2. Barangays, multi-select, empty means citywide
3. Sectors, multi-select, empty means all sectors
4. A live count beneath the form: "This batch will cover 1,240 families."

On submit, inside the existing transaction: insert the batch, insert the filter
rows, build the roster with one `INSERT INTO batch_eligibility SELECT ...`, store
`eligible_count`, write the audit row. One indexed insert-select, once per batch.

The preview count and the roster build must run the same query or the number the
admin approved will not match the denominator they get. One class,
`Libraries/EligibilityBuilder`, exposing `count(filters)` and
`materialize(batchId, filters)` over a shared query builder.

The roster is frozen at open. An admin may explicitly rebuild it, which writes an
audit row and updates `eligible_count`. Explicit and logged, never silent.

## Dashboard

Every element earns its place by answering "what decision changes because of
this number?". Elements that failed that test are cut, including several that
exist today.

Cut: Registered Members, Active Sectors, Services and Programs (reference-table
row counts belong on Reference Data), Recent Records (a shortcut to Manage
Records wearing a table), and the current Subsidy Coverage tile (wrong
denominator).

### Zone 1, program to date

A quiet strip, not cards. Never moves with the batch selector. Cached 60 seconds,
as `DashboardModel::stats()` already is.

- Families profiled
- Families never served (the pool the next batch draws from)

### Zone 2, this batch

The batch selector belongs to this zone, not the page header.

- Progress block: "486 of 1,240 served, 39%" with a bar. One unit, not four
  tiles: remaining and percent are both derived from the same two facts.
- Remaining: stands alone because it is the number staff act on.
- Voided: rendered only when above zero.

Labels change with batch state. Open shows "Remaining"; closed shows "Not
claimed". The tiles and layout do not change shape, so the page does not move
under an operator mid-event.

### Charts

Two, both batch-scoped.

- Coverage by barangay, horizontal bars, sorted worst first. Sorting by worst is
  what makes it operational: the top row is where staff get sent.
- Cumulative served over time, open batch only. A flat line means scanning
  stopped, which is the one thing a live view must surface. Hidden on closed
  batches.

Rejected: any subsidy-type chart (one type per batch, always one bar) and any
donut (says less than the progress block).

### One tabbed table

Replaces today's stacked tables.

- Barangay: eligible, served, remaining, coverage per barangay.
- Stations: families served per station in this batch, each row linking to
  `scanner/performance` for that station. This gives the orphaned page an entry
  point (finding 10).
- Remaining: the unclaimed families, searchable and exportable. This is the
  artifact the page exists for and the app cannot produce today.

### Empty and closed states

A batch with no scans shows the roster and 0 percent, not "No scans were
logged." Zero of 1,240 is information; the current message reads like a broken
page.

### Download Report

Stays, batch-scoped, and gains the remaining list. A report without the
unclaimed names cannot support liquidation.

## Performance

- Closed-batch statistics are immutable and cache indefinitely, keyed by batch.
- Only an open batch polls. The poll runs two indexed counts plus the barangay
  rollup, not three full scans.
- Scan insert invalidates the open batch's cache key, matching the existing
  `AuditTrailsModel` invalidation pattern for `dashboard_stats`.
- `receivedVsNot()` counts in SQL. No `getResultArray()` for a count.
- Barangay rollup groups on `barangayID`, which is indexed.

## Structural cleanup, in scope because this work touches all three call sites

- Batch resolution moves to one place (finding 7). The three current copies call
  it.
- `bySubsidyType` is removed from the stats endpoint and the PDF (finding 8).

## Scanner stations

The per-station page exists and works: own families, own handouts, bucketed
timeline, per-hour pace, busiest window. It needs an entry point, not a redesign.

- Link from the kiosk shell for scanners, who have no sidebar by design.
- Link from the dashboard Stations tab for admins.

Pace and timeline stay on the station page. They are cut from the main dashboard
because with one scan per family the citywide question is "who is left", not
"how fast".

## Out of scope

- The distribution calendar. The Distribution page owns planning; the dashboard
  owns results.
- Importing external masterlists. The roster table makes this additive later.
- Recording duplicate scan attempts (finding 3). No data exists for it today, and
  it needs its own decision about what to store.

## Verification

- `vendor/bin/phpunit` and `composer lint` green.
- Coverage against a seeded batch whose roster is known: served, remaining, and
  percent reconcile by hand.
- A family profiled after a batch closes does not change that batch's numbers.
- A voided distribution returns its family to "not served" and the row survives.
- Barangay rollup returns one row per seeded barangay, no free-text buckets.
- Query counts on the stats endpoint: closed batch served from cache, open batch
  bounded and indexed.
- Playwright at desktop and 390px against `app.baseURL`, compared with Manage
  Records as the design source of truth.
