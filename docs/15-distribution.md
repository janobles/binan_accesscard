# Distribution

A distribution is the city handing something out: rice, cash aid, relief goods.
The system's job is to know who should get it, to record who did, and to make
both of those true while several hundred people stand in a queue.

Everything here is organised around the **batch**.

## The batch

One row in `distribution_batch` is one giving event. It carries a name, a venue,
the subsidy type being handed out, a date span with daily start and end times, and
a colour for the calendar.

The subsidy type is bound to the batch when it is plotted, and the kiosk never
picks one. Every scan during a batch logs against that batch's `subsidy_type_id`.
This is deliberate: choosing a subsidy type per scan would be one more decision at
the front of a queue, and the answer is the same every time.

**At most one batch is open at a time.** A batch is open when:

```sql
closed_at IS NULL AND started_at IS NOT NULL
```

Both halves matter. A batch plotted for next Tuesday also has no `closed_at`, and
it is not open until its first day arrives. `DistributionBatchModel::activeBatch()`
is the canonical implementation of that condition; if you find yourself writing
it out by hand somewhere new, use the model method instead.

The single-open invariant is enforced by refusing overlapping date spans at save
time, through `DistributionBatchModel::overlapping()`, not by a database
constraint. Saving a schedule over a span another batch already holds is refused
and names the clashing batch.

`subsidy_distribution.batch_id` stamps every handout with the batch that was open
when it was logged. A NULL `batch_id` is pre-batch history from before this
feature existed, and batch-scoped views never include it.

## Batches open and close on schedule, not on a click

This is the part that most repays understanding, because it looks like
overengineering until you know what it replaced.

The distribution happens at a barangay hall. The laptop travels there. Nobody is
going to remember to press "open batch" at 8am while unloading sacks of rice, and
nobody is going to press "close" at 5pm while packing up. So the batch does it
itself.

Plotting a schedule writes the plan only: the date span and the daily times. It
never starts the batch.

`app/Libraries/BatchScheduleWindow.php` holds the arithmetic, and it deliberately
touches no database and no framework state, so the rules can be tested directly.
Two ideas carry it:

**Dates gate.** Scanning is allowed on any day inside the batch's span and on no
other day. Staff who start early or run a day late need no override.

**Times advise.** `daily_end_time` is where the closing countdown starts, and
every scan past it pushes that anchor forward one grace step. A distribution
running late is never cut off with people still in the queue.

The verdict is a function of the schedule and the actual scan times. "Now" decides
only whether a transition has come due, never what gets stored, which is why a
reconcile running hours late still writes the correct `closed_at`.

`DistributionBatchModel::reconcileSchedule()` is the only thing that reads that
verdict and writes `started_at` or `closed_at`. `app/Filters/BatchScheduleFilter.php`
(alias `batchSchedule`) calls it on every scanner and distribution request, so
state advances on the traffic that is already happening. No cron job on the
laptop, nothing to install at the venue.

Each transition writes its own `audit_trails` row under user id 0, which reads as
"system". `closeBatch()` still exists as a manual override at
`POST distribution/batches/close/{id}`, for ending a batch early.

Times are Asia/Manila.

## Eligibility

A batch targets barangays and sectors. `batch_barangay` and `batch_sector` hold
the targeting; `batch_eligibility` holds the resolved answer, one row per eligible
family head.

`app/Libraries/EligibilityBuilder.php` builds it. Eligible means an active family
head, holding a QR control number, whose barangay and sector fall inside the
batch's filters. An empty filter array means no restriction on that dimension, so
an empty barangay list is citywide rather than nobody.

The roster is built once per batch and frozen. One query serves both the count
shown in the batch-open modal and the roster itself, so the number an admin
approves is exactly the denominator the batch gets. It is never rebuilt from the
dashboard, because a roster that drifts after approval makes the completion
percentage meaningless.

`distribution_batch.eligible_count` caches the size so the dashboard does not
count rows on every load.

## The scan

**The scan is the log.** There is no confirm step, no claimant form, no date
picker.

`POST scanner/log` takes one field, `control_no`. It resolves the family, then
inserts a distribution for the **family head**, dated **today**, with the open
batch's `subsidy_type_id` and `batch_id`. The insert and its audit row share one
transaction.

**One handout per family per batch**, regardless of date. Before inserting, the
server checks `SubsidyDistributionModel::inBatch(control_no, batch_id)`. A repeat
scan returns `logged: false` with the original entry and writes nothing: the kiosk
shows a red "Duplicate Entry" banner where a fresh log shows a green "Logged" one.
Scanning the same family again requires a new batch.

The response always carries the family panel data, so the kiosk renders in one
round trip. There is no separate lookup endpoint, because a second request is a
second chance to be slow.

If no batch is open, `scanner/scan` renders an empty state rather than a redirect
loop, and the log endpoint returns 409, which covers a batch closing mid-session.

### Why it is shaped this way

The scanner is a keyboard-wedge gun. It types the control number and presses
Enter. Every design decision in the kiosk follows from time and motion: no
keypress is added to the per-scan path, the page fits the viewport without
scrolling, and the members list sits collapsed by default so the panel stays
uncluttered. Expanding it is optional and never part of the scan flow.

If you are changing the kiosk, the question to ask is whether your change costs
anything per scan. Multiply it by four hundred.

## The three shells

The scanner does not use the dashboard layout, and the reasons are worth knowing
before you try to make it.

**Kiosk shell** (`app/Views/Scanner/kiosk-layout.php`) is full-viewport and green,
with no sidebar or topbar. Its slim header carries the batch name, the subsidy
type badge, a live `#myBatchCount` counter, and logout. It is used by `scan.php`
and `performance.php`, the kiosk's only two pages.

**Simple shell** (`app/Views/Scanner/simple-layout.php`) has the same brand and
account menu but loads the dashboard table CSS and JavaScript directly rather than
going through `DashboardPageBuilder`. The builder is wired to one large
`activePage` switch, so a Scanner-reachable page cannot render through it without
forking that switch. This shell serves `scanner/history/{controlNo}`, the "View
all" page, which is the one Scanner page needing a real search, filter, and
pagination table.

**Dashboard shell** (`app/Views/layout.php`) owns the back office: subsidy types
on the reference-data page, batches and the handouts log on the distribution page,
and the reports endpoints. The kiosk has no back-office pages at all.

### The history fragment

`scanner/history/{controlNo}` serves double duty. A plain GET renders it
full-page in the simple shell; an AJAX GET renders only
`Scanner/history-fragment.php`, which the scan panel injects inline instead of
navigating away.

Two details of that injection bite if you touch it. Injected `<script>` tags have
to be re-created manually, because `innerHTML` does not execute them. And
`lookup-search.js`, `records-filter-panel.js`, and `table-paginate.js` have to be
re-bound against the new container, because their `DOMContentLoaded` initialisation
already ran before the fragment existed.

## The back office

The Schedule tab on the `distribution` page is a FullCalendar month view. Plotting
or editing opens a modal: name, venue, subsidy type, date and time span, and a
label colour. Saving over a taken span is refused with the clashing batch's name.
Deleting is refused once the batch has distributions against it.

Subsidy types are managed on the reference-data page. Deleting one is blocked
while any distribution references it; archiving is the safe retirement.

Both sit behind the route's `roleNav` filter, which grants `distribution` and
`reference-data` to Admin and Developer, and both write audit rows for every
mutation.

## Performance stats

`SubsidyStatsModel` is batch-scoped throughout. The date-range filter was removed
entirely rather than merely superseded, so every method takes a trailing
`?int $batchId`.

`scanEvents(int $batchId)` (`app/Models/Scanner/SubsidyStatsModel.php:492`) is the
one query behind every per-scanner figure: every scan in the batch, as
`{userID, ts, control_no}`, sorted by scanner then time. Nothing downstream
queries the database again for a scanner's numbers; it folds this stream.

### `ScannerMetrics`: the fold, and what active time is not

`App\Libraries\Scanner\ScannerMetrics` (`app/Libraries/Scanner/ScannerMetrics.php`)
turns that event stream into per-scanner figures and the peak-hours grid. It
touches no database and no framework state, matching the scanner module's
no-DB test posture, so every figure is unit-testable without a fixture. One
fold behind the Stations table, the station drill-in modal, and the kiosk
performance page is the point: the three read the same numbers and cannot
disagree about a scanner's pace.

**What the old figure got wrong.** `ScanController::kioskSnapshot()` used to
compute a scanner's pace by dividing their families by the batch's wall clock,
`started_at` to now. A batch scheduled across three days closes each evening
and reopens the next morning (see the schedule section above), so that clock
kept running through both nights the venue was empty. A scanner who worked two
of the three days had their families divided by roughly three days of elapsed
time, and every station in a multi-day batch read at a fraction, often near a
quarter, of the rate it was actually working at.

**What it does now.** Active time is derived from the scan timestamps
themselves, not the clock. `ScannerMetrics::fold()` walks one scanner's events
in order and measures the gap between each consecutive pair. A gap of
`ScannerMetrics::IDLE_GAP_SECONDS` (900 seconds, `app/Libraries/Scanner/ScannerMetrics.php:29`)
or less is counted as active time; anything longer is idle, someone standing at
an empty queue, not work, and is excluded from both the active-time total and
the pace calculation. A scanner's own idle nights, lunch break, or any other
gap this wide simply never enters the denominator, whether the batch runs one
day or five.

**`pace` is a cadence figure, not a throughput one.** It is non-idle gaps per
active hour: `3600 / mean(non-idle gap)`. Read it as "how close together this
scanner's scans landed while they were working", never as "families per hour".
The first scan in any run has no gap before it, so it is excluded from the gap
count entirely; counting it would understate the pace by folding in an arrival
that consumed no active time.

`ScannerMetrics::derive()` turns one folded row into eight figures, shown
identically in the Stations table, the station drill-in modal
(`App\Libraries\Scanner\BatchScope`, `app/Views/Admin/batch-station-modal.php`),
and the kiosk performance page, all from `app/Views/Scanner/_metrics-grid.php`:
families served, handouts logged, pace, typical time between families (the
median non-idle gap), time on station (first scan to last), idle time, best
hour, and share of the batch's families. `perScanner()` is gone; every reader
that used it now reads `byScanner`, the same fold's rows.

### The peak-hours heatmap

`ScannerMetrics::heatmap()` folds the same event stream into a day-by-hour
grid: one row per day the batch ran, one column per hour, a distinct-family
count per cell. A family scanned twice at two stations in the same hour counts
once, because the grid is built from the same control-number folding the
`families` figure uses, not a second `GROUP BY`.

Rendered as an HTML table (`app/Views/Admin/batch-heatmap.php`), not a chart,
because a screen reader can take keyboard focus on a table cell and read its
value as text, which a canvas cannot offer. The same partial also renders an
all-time weekday-by-hour view, fed by `weekdayHistogram()`
(`app/Models/Scanner/SubsidyStatsModel.php:530`), which spans every batch the
city has ever run and ignores the batch picker entirely.

Three cell states carry the reading, and the distinction between the first two
is the one worth remembering: **closed** is an hour outside the batch's daily
window, no evidence the station was open. **Staffed but empty** is an hour
inside that window with zero families, a blank the batch actually paid for and
the one state worth acting on. **Served** is any hour with at least one
family, shaded across five steps scaled to the grid's own busiest cell rather
than an absolute ceiling, so a small batch still shows contrast.

The live counter on the scan page updates from the `myBatchCount` field in the
`scanner/log` response.

**One caveat that looks like a bug and is not.** A session whose user id is 0,
which is what an account with no `users` row leaves behind, stores `userID` as
NULL on its handouts (`app/Models/Scanner/SubsidyDistributionModel.php`). Those
scans appear as "Unknown" in `perScanner` and leave the live counter at zero.
That is the foreign key being kept intact, not a stats fault. Test per-scanner
features with real accounts from the `users` table; the dump ships a
database-backed `developer` account that has one.

## Rules

Copied from the conventions this codebase is held to. Terse on purpose.

**Scope:** distribution batches, the one-action scan flow, per-scanner
performance stats, and the kiosk-vs-dashboard shell split in the scanner
module.

### Rule 1: Batch = one giving event; at most one open; subsidy type bound at plot time

`distribution_batch` holds one row per giving event, including the
subsidy type distributed in it (`subsidy_type_id` -> the `subsidy` reference
table) plus its schedule: `venue`, `scheduled_start`, `scheduled_end`,
`daily_start_time`, `daily_end_time`, `color`. The single open batch is
`closed_at IS NULL` **and** `started_at IS NOT NULL`
(`DistributionBatchModel::activeBatch()`): a batch plotted for a later day
also has no `closed_at`, and it is not open until its first day arrives. The
invariant is enforced by refusing overlapping date spans at save time
(`DistributionBatchModel::overlapping()`), not by a DB constraint. A batch is
plotted on the Schedule tab's calendar with a name, venue, subsidy type and
date/time span; it opens and closes itself against that plan rather than by an
admin clicking a button - see Rule 1a. The kiosk never picks a subsidy type -
every scan during a batch logs against the batch's `subsidy_type_id`.

Subsidy types are their own concept, unrelated to the `services`/`category`
reference data (which describe member program enrollment, not handouts).

`subsidy_distribution.batch_id` stamps every handout with the batch that was
open when it was logged. `batch_id NULL` = pre-batch history; batch-scoped
views never include it.

### Rule 1a: Batches open and close on schedule, not on a click

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

### Rule 2: Schedule and subsidy-type lifecycle is Admin/Developer only, and audited

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

### Rule 3: One-action scan; duplicates refused per batch

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

### Rule 4: Kiosk shell vs admin shell

- **Kiosk shell** - `app/Views/Scanner/kiosk-layout.php`: full-viewport,
  green-themed, no sidebar/topbar; slim header (batch name, subsidy-type badge,
  live `#myBatchCount` counter, logout). Used by `scan.php` and
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
  page. A plain GET renders it full-page through `Scanner/history.php` (this
  shell); an AJAX GET (`$this->request->isAJAX()`) renders just
  `Scanner/history-fragment.php` - the scan panel injects that fragment inline
  instead of navigating here.
- **"View all" page anatomy** (`Scanner/history-fragment.php`) - two Bootstrap
  tabs, both server-rendered on the one page load (switching tabs is
  client-side only, no reload):
  - **Family Information** tab, active by default
    (`Scanner/family-tab-body.php`): the head plus the rest of the family, one
    row each, shown in full. No search, filters, per-page control, or
    pagination; a family is small enough to render whole. Sectors and
    services/programs are kept as two separate lists, via
    `MemberModel::referenceBadgesSplit()` rather than the merged
    `referenceBadges()` the scan panel and the `logAid()` response use.
  - **Audit Logs** tab (`Scanner/history-tab-body.php`): every past scan of this
    control number, from `SubsidyDistributionModel::historyAllFor()`. The
    controller passes no keyword and no batch or subsidy-type filter, and the
    tab is not paginated, because one control number's scans are a small
    bounded set.
- **Dashboard shell** - `app/Views/layout.php`: the SB-Admin frame every staff
  role shares. Owns subsidy types (the `reference-data` page's subsidy-types tab),
  batches and the distributions log (the `distribution` page), and its reports
  endpoints (`distribution/reports/*`).

### Rule 5: Performance stats are folded from scan events, batch-scoped, role-filtered

`SubsidyStatsModel` methods take a trailing `?int $batchId` (batch-scoped only -
the date-range filter is removed entirely, not just superseded).
`scanEvents(int $batchId)` (`app/Models/Scanner/SubsidyStatsModel.php:492`) is
the one query behind every per-scanner figure and the peak-hours grid;
`App\Libraries\Scanner\ScannerMetrics::fold()` turns it into
`{userID, scanner, handouts, families, pace, typicalSeconds, share,
onStationSeconds, idleSeconds, bestHour, bestHourFamilies}` rows per scanner -
`families` = `COUNT(DISTINCT control_no)`, `pace` = non-idle gaps per active
hour, not families per elapsed hour. `perScanner()` is gone; every reader
(Stations table, station drill-in modal, kiosk performance page, PDF) now
reads `byScanner`, the same fold's rows, so the four cannot disagree about one
scanner's pace. See "`ScannerMetrics`: the fold, and what active time is not"
above for why active time is derived from scan timestamps rather than the
batch's wall clock.

The Scanner role only ever sees its own row on `scanner/performance`
(`ScanController` passes `$onlyUserId` server-side, never hides rows
client-side). The dashboard's Distribution pane and the `distribution` page's
reports show the full per-kiosk table (Admin/Developer only), including the
PDF export.

The live counter on the scan page updates from the `myBatchCount` field in
the `scanner/log` JSON response
(`SubsidyDistributionModel::familiesForUserInBatch()`).

**Caveat:** the `.env` developer account has no `users` row (`user_id` 0),
so its handouts store `userID NULL` and appear as "Unknown" in perScanner /
keep the live counter at 0. Pre-existing auth design, not a stats bug; test
per-scanner features with real `users`-table accounts. (The dump ships a
DB-backed `developer` account, which does have a `users` row.)
