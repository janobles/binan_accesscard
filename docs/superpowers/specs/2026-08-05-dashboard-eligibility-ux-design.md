# Dashboard Eligibility UX Overhaul

Date: 2026-08-05
Status: approved design, not yet planned

## Problem

The 2026-08-04 dashboard-and-distribution-eligibility work fixed the data: coverage
has a real denominator, barangay is a reference column, void is soft. The layout
built on top of that data is what's wrong now. Zone 1 is a headingless two-item
strip. Zone 2's barangay view is one bar chart plus one table saying the same
thing twice, sorted on a premise (staff get dispatched to the worst barangay)
that doesn't match how the program actually runs: distribution happens at one
central site, families travel in, nobody gets sent anywhere. The Stations tab
is a flat table for data that is really a fleet of independently-operating
scanner kiosks. And the page never answers the question a CSWD officer
actually has walking up to it: not "what is the coverage percentage" but
"who showed up, from where, and when, across a rollout that can span days."

This pass restyles the dashboard end to end using the existing data layer.
No schema change, no new SQL patch. Reference: a three-day rice subsidy pilot
that served 6 barangays a day, 18 of 24 total, 9,133 / 6,078 / 2,828 across the
three days.

## Zone 1, program to date

Gets a visible `<h2>Program to date</h2>` (today it has none, just an
`aria-label`). Four stock-Bootstrap `.card` tiles, `row row-cols-2
row-cols-md-4 g-3`, no `card-header`, no icon. This deliberately breaks from
`docs/knowledge/sbadmin/target-theme.md`'s documented card convention
(`card-header` with an `<i>` icon), scoped to KPI tiles only; content cards
elsewhere keep the icon+title header.

1. Families profiled
2. Families ever served (distinct family heads, any batch, ever)
3. Families never served
4. Barangays with zero coverage to date

Card 4 counts only barangays that have at least one profiled family and no
family ever served. A barangay with nothing profiled in it is excluded: that
is a profiling gap, not a coverage gap, and counting it here would blame
distribution for work that never reached the encoding stage. The `Unassigned`
bucket (`member.barangayID IS NULL`, which `byBarangay()` already emits as its
own row) is excluded too, since it is a data-quality artifact and not a
barangay.

All four are all-time counts with no trend arrow: "to date" is already
cumulative, so there's no prior period to compare against. (2) and (3) sum to
(1), the same layering Umami's visitors/visits/views has.

These four are also how this design satisfies the cross-batch view: cards 2,
3 and 4 all aggregate over every batch ever run, so the officer's
"city-wide, across batches" question is answered by Zone 1, and Zone 2 stays
strictly about the batch in the selector. No separate cross-batch history
page is built.

Rejected: a period selector (Today / Last 7 days / Last 30 days) of the kind
the Umami reference carries. Batches are irregular events of one to n days,
not calendar periods, so a rolling 7 or 30 day window cuts across whole and
partial rollouts and produces a number nobody can act on. The batch selector
in Zone 2 is the real time control on this page.

`DashboardModel::programStats()` gains the two new counts, same 60 second
cache (`PROGRAM_STATS_CACHE_KEY`). Its return shape is consumed in exactly
three places (`DashboardPageBuilder`, `Pages/dashboard.php`, the model
itself), all touched by this work, so the shape is free to change.

## Zone 2, this batch

Header (`<h2>This batch</h2>`, batch picker, Download Report) and the
served/eligible/percent progress block are unchanged. No batch-over-batch
comparison line: considered and dropped, out of scope for this pass.

### Rollout by day

New bar chart, one bar per calendar day the batch was open. Renders only when
a batch spans more than one day; a single-day batch gets no chart, one bar
proves nothing. Shown for open and closed batches alike: this is retrospective
reporting ("how did the three days break down"), a different job from the
existing cumulative line chart, which stays open-batch-only because its job is
live monitoring (a flat tail means scanning stopped).

Backed by a new `SubsidyStatsModel` query grouping `subsidy_distribution` by
`claim_date` within the batch, distinct `memberID` per day. `claim_date` is
already a `date` column and is set server-side to `date('Y-m-d')` at scan time
(`ScanController::logAid()`), never from user input, so it needs no `DATE()`
wrapper and is a reliable day key.

The day bars sum to the progress block's served total because a family can be
scanned at most once per batch: `ScanController::logAid()` refuses a repeat
scan in the same batch. If that rule ever changes, per-day distinct counts
would double-count a family across days and the bars would stop summing to the
headline.

The new series has to reach the client the same way the existing ones do: the
`#reportsData` JSON block in `Admin/batch-overview.php` and the
`distribution/reports/stats` endpoint both gain a `byDay` key, and
`ReportsCharts.update()` repaints it. Without that the chart would go stale
under the live poll while everything beside it ticks.

### Barangay tab, rebuilt

**The existing barangay bar chart is removed.** `#chartBarangay`
(`Admin/batch-overview.php`, drawn by `scanner-reports.js`) plotted the same
per-barangay coverage the table underneath it already listed, twice on one
screen. The map takes over the visual read of that data and the bar chart form
moves to the day rollout above, where it plots something the table cannot show.
Removing it also retires `$emptyChart` and its "Nothing was handed out in this
batch, so there is no coverage to plot" branch, which existed only to keep that
chart from rendering as empty gridlines.

**Table**: leaderboard, best-first by coverage percent, always. No
open/closed sort flip. The 2026-08-04 spec's "worst-first, where staff get
sent" rationale is corrected here: distribution is a single central site,
families travel to it, no one is dispatched to a barangay. The table's job is
a straight readout, so it reads best-first like a leaderboard, same column,
same data, both batch states.

**Map**: compact panel beside the table, not a hero element, built from
`public/assets/image/binan_brgy.svg` (24 unlabeled `<path>` elements, one per
barangay, no id or class to key off). A commit predating this branch
(`8264289`) deleted a `binan_brgy.json` GeoJSON with the same 24 features in
the same PSGC order and each feature's `adm4_en` name. Same export source, so
path order should line up with feature order; this gets verified by a
centroid/bbox comparison script during implementation, not assumed. One
spelling to reconcile: the GeoJSON says "Mampalasan," the seeded `barangay`
table says "Mamplasan." The DB spelling wins.

Colored by served/eligible intensity, flat 3-4 step scale, no legend
clutter. Linked to the day chart: clicking a day bar recolors the map to that
day's activity; the default "All" selection shows the cumulative state,
matching the table. Hovering or clicking a barangay opens a Bootstrap
popover (the stock component, not a custom tooltip or a modal) showing the
exact received/total for that barangay at the selected day scope. The table
itself never changes with day selection, it stays cumulative for the whole
batch: the map-plus-day-chart pairing shows how the rollout unfolded, the
table shows where it landed.

If the path-to-barangay mapping doesn't verify cleanly, or the map proves
unusable at 390px (slivers like Casile are a poor touch target on a 24-region
SVG), the map is cut and the table carries the tab alone. Nothing
decision-critical rides on the map alone; it earns a place only as a
secondary, verified-correct visual.

### Stations tab, rebuilt

Grid of squares, one per distinct scanner account with at least one
successful scan in this batch. Not a fixed set of "kiosk slots": a scanner
that never logs a scan never gets a square, and the grid grows as scanners
start scanning. `row-cols` plus `ratio ratio-1x1` for the square shape.

Each square: the scanner's username (their real login identity, which is
already the operational unit; no "Kiosk 1/2/3" relabeling) and one headline
number, families served. Click navigates to the existing
`Scanner/performance` page for that scanner, which already has the fuller
breakdown (handouts, timeline, pace, busiest window) built and unused.

That page currently reads the viewer from session only
(`ScanController::performance()`, `$userId = session('user_id')`), so an
admin clicking a square today would land on their own, usually empty,
numbers. Fix: an optional `?scanner=<userID>` override, honored only for
Admin/Developer callers and only when the target user's `account_level` is
`scanner`; a Scanner-role viewer keeps seeing their own session, unaffected.

Backed by the existing `SubsidyStatsModel::perScanner()`, already returns
`userID`, `scanner` (username), `handouts`, `families`. No new query.

### Remaining tab

Unchanged.

## Labels and copy

Every string on this page is either a number's label or an empty state. Both
get held to the same rule: say what is true and stop. No sentence exists to
fill space, explain the obvious, or narrate what the reader can see.

- A tile label names its number and nothing else: "Families never served," not
  "Families never served yet, representing the pool available for future
  distributions."
- No helper text under a tile. If a number needs a sentence to be understood,
  the wrong number is on the card.
- Empty states state the fact and the next action if one exists, in one line.
  "No distribution batch exists yet. Open one from the Distribution page to see
  its coverage here." is the standard to match: fact, then where to go. A batch
  with a roster but no scans keeps showing 0 of 1,240 rather than an empty-state
  message, per the 2026-08-04 spec, because zero of a known total is data.
- Section headings are nouns, not sentences: "Rollout by day," "Program to
  date." No "Here you can see..." framing.
- Section headings are `<h2>` for the two zones and `<h3>` for panels inside
  them, matching the existing `.batch-pane-title` pattern, so the page has one
  readable outline rather than styled text pretending to be structure.

`docs/knowledge/php-practices/comments.md` already bans this register in code
comments. The same ban applies to strings the user reads.

## Out of scope

- Gender or sector demographic breakdown. Raised during design as an analogy
  for "served vs not served," not a real requirement; dropped once
  clarified.
- Batch-over-batch trend comparison on Zone 2. Discussed, explicitly deferred.
- Any modal. The one piece of "rich content on interaction" is the map's
  Bootstrap popover; everything else the grid/table/chart already shows
  inline or is a page navigation (Stations square to `Scanner/performance`).
- A new SQL patch. Every number above is derivable from the V20 schema as
  already merged: `barangayID`, `batch_eligibility`, `dt_voided`, and
  `subsidy_distribution.claim_date` for day grouping. `member.sex` exists but
  goes unused, since demographics are out of scope (see above).
- The distribution calendar and masterlist import, both already out of scope
  per the 2026-08-04 spec and untouched by this one.

## Verification

- `vendor/bin/phpunit` and `composer lint` green.
- `binan_brgy.svg` path order verified against the recovered GeoJSON's
  feature order (centroid or bounding-box comparison script), not assumed
  correct by inspection.
- A fixture seeds a three-day batch with 9,133 / 6,078 / 2,828 distinct
  families served per day (the pilot's shape). The chart renders three bars
  carrying exactly those values and they sum to the 18,039 the progress block
  reports. A single-day batch renders no chart at all.
- Zone 1 card 4 excludes both a barangay with zero profiled families and the
  Unassigned bucket, verified against a fixture that contains one of each.
- Barangay leaderboard returns the same rows in both batch states, sorted
  best-first, no flip.
- Stations grid shows exactly the scanners with at least one scan in the
  batch, no more, no fewer; clicking a square as Admin/Developer shows that
  scanner's real numbers, not the admin's own.
- Playwright at desktop and 390px against `app.baseURL`, compared with Manage
  Records as the design source of truth, per this repo's UI/UX verification
  standard.
