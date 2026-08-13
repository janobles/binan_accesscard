# Scanner Batches & Kiosk Shell

**Scope:** distribution batches, the one-action scan flow, per-scanner
performance stats, and the kiosk-vs-dashboard shell split in the scanner
module.

## Rule 1: Batch = one giving event; at most one open; subsidy type bound at plot time

`distribution_batch` (dump V21) holds one row per giving event, including the
subsidy type distributed in it (`subsidy_type_id` -> the `subsidy` reference
table) plus its schedule: `venue`, `scheduled_start`, `scheduled_end`,
`daily_start_time`, `daily_end_time`, `color`. The single open batch is
`closed_at IS NULL` **and** `started_at IS NOT NULL`
(`DistributionBatchModel::activeBatch()`): a batch plotted for a later day
also has no `closed_at`, and it is not open until its first day arrives. The
invariant is enforced by refusing overlapping date spans at save time
(`DistributionBatchModel::overlapping()`), not by a DB constraint. A batch is plotted on the Schedule tab's calendar with a name,
venue, subsidy type and date/time span; it opens and closes itself against
that plan rather than by an admin clicking a button - see Rule 1a. The kiosk
never picks a subsidy type - every scan during a batch logs against the
batch's `subsidy_type_id`.

Subsidy types are their own concept, unrelated to the `services`/`category`
reference data (which describe member program enrollment, not handouts).

`subsidy_distribution.batch_id` stamps every handout with the batch that was
open when it was logged. `batch_id NULL` = pre-batch history; batch-scoped
views never include it.

## Rule 1a: Batches open and close on schedule, not on a click

Plotting a schedule only writes the plan (`scheduled_start`/`scheduled_end`,
daily times); it never starts the batch. `App\Libraries\BatchScheduleWindow`
is the pure open/close arithmetic (dates gate scanning, `daily_end_time`
advises a closing time that a late scan pushes forward in 30-minute grace
steps); `DistributionBatchModel::reconcileSchedule()` is the only thing that
reads that verdict and writes `started_at`/`closed_at`. `App\Filters\
BatchScheduleFilter` (alias `batchSchedule`) calls it on every scanner and
distribution request, so state advances without a scheduled task on the
laptop that travels to the venue. Each transition writes its own
`audit_trails` row (user id 0, reads as "system"). `closeBatch()` still exists
as a manual override (`POST distribution/batches/close/{id}`) for ending a
batch early.

## Rule 2: Schedule and subsidy-type lifecycle is Admin/Developer only, and audited

- Schedule CRUD: `Admin\DistributionController::scheduleFeed()/saveSchedule()/
  deleteSchedule()` (`GET distribution/schedule/feed`, `POST
  distribution/schedule/save`, `POST distribution/schedule/(:num)/delete`).
  The Schedule tab lives on the `distribution` page (a FullCalendar month view;
  the create/edit modal is name + venue + subsidy-type select + date/time span
  + label colour), alongside the all-handouts log. Saving over a date span
  already taken by another batch is refused with the clashing batch's name;
  deleting is refused once the batch has distributions against it.
- Subsidy-type CRUD: `Admin\SubsidyTypesController` on the `reference-data` page's
  subsidy-types tab, routes `reference-data/subsidy-types/create|archive|
  restore|delete`. Delete is blocked while any distribution references the
  subsidy type (`SubsidyTypeModel::deleteIfUnused()`); archive is the safe retire.

Both controllers sit behind the route's `roleNav` filter (the manifest grants
`distribution` and `reference-data` to Admin/Developer) and write `audit_trails`
rows for every mutation, rendered through the one shell
(`DashboardPageBuilder::renderPage()` + `app/Views/layout.php`).
The kiosk has no back-office pages.

## Rule 3: One-action scan; duplicates refused per batch

The scan IS the log - there is no confirm step and no claimant/date form:

- `POST scanner/log` takes only `control_no`. Resolves the family via
  `ScanController::resolveFamily()` (head with suffix/barangay/badges,
  members excluding the head, also with badges), then inserts a distribution
  for the **family head** dated **today**, with the open batch's
  `subsidy_type_id` and `batch_id`. Insert + audit row share one transaction.
- **One handout per family per batch**, regardless of date. The server checks
  `SubsidyDistributionModel::inBatch(control_no, batch_id)` before inserting; a
  repeat scan returns `logged: false` with the original entry and writes
  nothing. The kiosk shows a red "Duplicate Entry" banner (`alert-danger`); a
  fresh log shows a green "Logged" banner (`alert-success`). Scanning the
  same family again requires a new batch.
- The response always carries the family panel data so the kiosk renders in
  one round trip. There is no separate lookup endpoint.
- The scan panel shows the family name, then fetches the History/Family
  Information tabs from `scanner/history/{controlNo}` as an AJAX fragment
  (`X-Requested-With: XMLHttpRequest`) and injects it inline
  (`loadHistoryFragment()` in `scan.php`) - same markup the standalone "View
  all" page renders (`Scanner/history-fragment.php`, shared by both), just
  without a page navigation. Injected `<script>` tags are re-created
  manually (innerHTML doesn't execute them) and `lookup-search.js`/
  `records-filter-panel.js`/`table-paginate.js` are re-bound against the new
  container, since their DOMContentLoaded init already ran before the
  fragment existed.
- `scanner/scan` renders an empty state (no redirect loop) when no batch is
  open; `logAid()` returns **409** when no batch is open (covers a batch
  closed mid-session).

## Rule 4: Kiosk shell vs admin shell

- **Kiosk shell** - `app/Views/Scanner/kiosk-layout.php`: full-viewport,
  green-themed, no sidebar/topbar; slim header (batch name · subsidy-type badge ·
  live `#myBatchCount` counter · logout). Used by `scan.php` and
  `performance.php` - the kiosk's only two pages. Time-and-motion rules apply:
  no per-scan keypresses added, page fits viewport without scrolling.
- **Family panel details** - the head card always shows the head's badges
  (sector shortcodes, service category names, service shortcodes, from
  `MemberModel::referenceBadges()`); the members list sits in a Bootstrap
  collapse, collapsed by default, so the kiosk stays uncluttered. Expanding
  is optional and never part of the scan flow.
- **Simple shell** - `app/Views/Scanner/simple-layout.php`: same topnav brand
  and account menu as the kiosk shell, but loads the dashboard table CSS/JS
  (`asset_styles('admin')`, `lookup-search.js`, `records-filter-panel.js`)
  directly instead of going through the dashboard page builder - that builder
  is wired to one big `activePage` switch, so a Scanner-reachable page can't
  render through it without forking that switch. Used by
  `scanner/history/{controlNo}` (`ScanController::history()`), the "View all"
  page - the one Scanner page that needs a real search/filter/pagination table.
  A plain GET renders it full-page (this shell); an AJAX GET
  (`$this->request->isAJAX()`) renders just `Scanner/history-fragment.php` -
  the scan panel injects that fragment inline instead of navigating here.
- **"View all" page anatomy** (`Scanner/history-fragment.php`) - two Bootstrap
  tabs, both server-rendered on the one page load (switching tabs is
  client-side only, no reload):
  - **History** tab: every past scan of this control number. Server search +
    Batch/Subsidy Type filters (`components/toolbar`, `SubsidyDistributionModel::
    batchCountsFor()`/`subsidyTypeCountsFor()`) + pagination
    (`historyForPaged()`/`countHistoryFor()`), same anatomy as Manage Records.
  - **Family Information** tab: the head + rest of the family, one row each.
    Client-side only (families are small, no pagination) - search box paired
    with its own button (same pattern as the scan input), one Filters
    dropdown with five checkbox groups (Relationship, Sex, Date of Birth,
    Sectors, Services and Programs - Sectors and Services/Programs are
    filtered **separately**, via `MemberModel::referenceBadgesSplit()` rather
    than the merged `referenceBadges()` the scan panel/logAid() response
    uses), then Clear. Relationship/Sex/DOB options are data-derived (no
    fixed reference list); Sectors/Services options are the full system-wide
    set (every sector, every service category/shortcode) so the dropdown
    always lists every possible value, not just what this family has.
- **Dashboard shell** - `app/Views/layout.php`: the SB-Admin frame every staff
  role shares. Owns subsidy types (the `reference-data` page's subsidy-types tab),
  batches and the distributions log (the `distribution` page), and its reports
  endpoints (`distribution/reports/*`).

## Rule 5: Performance stats are batch-scoped and role-filtered

`SubsidyStatsModel` methods take a trailing `?int $batchId` (batch-scoped only -
the date-range filter is removed entirely, not just superseded).
`perScanner(int $batchId, ?int $onlyUserId)` returns
`{userID, scanner, handouts, families}` rows - `families` =
`COUNT(DISTINCT control_no)`. `bySubsidyType(?int $batchId)` drives the
handouts-per-subsidy-type chart/PDF table. The Scanner role only ever sees its
own row on `scanner/performance` (`ScanController` passes `$onlyUserId`
server-side, never hides rows client-side). The `distribution` page's reports show the full
per-kiosk table (Admin/Developer only), including the PDF export.

The live counter on the scan page updates from the `myBatchCount` field in
the `scanner/log` JSON response
(`SubsidyDistributionModel::familiesForUserInBatch()`).

**Caveat:** the `.env` developer account has no `users` row (`user_id` 0),
so its handouts store `userID NULL` and appear as "Unknown" in perScanner /
keep the live counter at 0. Pre-existing auth design, not a stats bug; test
per-scanner features with real `users`-table accounts. (The V18 dump ships a
DB-backed `developer` account, which does have a `users` row.)
