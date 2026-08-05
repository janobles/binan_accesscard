# Dashboard Eligibility UX Overhaul

Date: 2026-08-05
Status: approved design, not yet planned

## Problem

The 2026-08-04 dashboard-and-distribution-eligibility work fixed the data:
coverage has a real denominator, barangay is a reference column, void is soft.
The layout built on top of that data is what's wrong now.

The page currently stacks two unrelated scopes with no seam between them. A
headingless two-item strip reports the registry; directly under it, without
announcement, everything switches to whichever batch the selector holds. The
barangay view then draws one bar chart and one table saying the same thing
twice, sorted on a premise (staff get dispatched to the worst barangay) that
doesn't match how the program runs: distribution happens at one central site,
families travel in, nobody gets sent anywhere. The Stations tab is a flat table
for data that is really a fleet of independently-operating scanner kiosks.

Underneath all of that, the page never answers the end-to-end question. An
officer can see one batch at a time and the registry total, and nothing that
connects them: how many distributions the city has hosted, how many families
the program has ever reached, how many it never has.

This pass restructures the dashboard into two tabs and restyles both, using the
existing data layer. No schema change, no new SQL patch. Reference: a three-day
rice subsidy pilot that served 6 barangays a day, 18 of 24 total, serving
9,133 / 6,078 / 2,828 families across the three days for 18,039 total.

## Structure

Two page-level tabs, because the page carries two scopes and mixing them is the
core layout failure:

- **Overview**, default: the program end to end, profiling through
  distribution. Never moves with the batch selector.
- **Distribution**: one batch, the event and its breakdowns.

The tab strip is `components/page_tabs`, which today hardcodes `?tab=` in its
hrefs. It gains an optional `param` argument defaulting to `'tab'`, so the
outer strip can use `?view=overview|distribution` while the Distribution tab's
inner strip keeps `?tab=barangay|stations|remaining`. Existing callers pass
nothing and are unaffected.

Two tab levels on one page is a real risk of tab soup, so they must not look
alike: the outer strip is the page's primary navigation and reads as such; the
inner strip sits inside the Distribution pane and is visually lighter. If they
render identically, the inner one gets toned down, not the outer one promoted.

Role gating is unchanged. The tabs render only for roles that already see
distribution data (`$seesDistribution`, Developer and Admin per
`DashboardPageBuilder::buildViewData()`). An Encoder's dashboard is unchanged:
no tabs, just their activity panel.

## Overview tab

`<h2>Program to date</h2>`. Four stock-Bootstrap `.card` tiles, `row
row-cols-2 row-cols-md-4 g-3`, no `card-header`, no icon. This deliberately
breaks from `docs/knowledge/sbadmin/target-theme.md`'s documented card
convention (`card-header` with an `<i>` icon), scoped to KPI tiles only;
content cards elsewhere keep the icon+title header.

| Card | Meaning | Source |
|------|---------|--------|
| Families profiled | registered in the system | `countFamilies()`, exists |
| Distributions hosted | batches run, open and closed | `COUNT(*)` on `distribution_batch` |
| Families ever served | distinct families reached, any batch | `COUNT(DISTINCT memberID)` on unvoided `subsidy_distribution` |
| Families never served | profiled, never reached | profiled minus ever served |

All four are all-time counts with no trend arrow: "to date" is already
cumulative, so there is no prior period to compare against.

Together they read as the program's funnel and its output: how many households
are known, how many events have been staged, how many households those events
actually reached, and how many the program has never reached at all.

### Never served drops its QR requirement

`DashboardModel::programStats()` currently defines never-served as a family
that has a `qr_control` row and no unvoided distribution. That gate makes sense
for its original purpose ("the pool the next batch draws from" needs a
scannable card) but it is wrong for an end-to-end overview, where a family
without a printed card has still never been served. It also silently breaks the
arithmetic: with the gate, ever-served plus never-served equals *carded*
families, not profiled ones, and the card row would not reconcile.

Overview's never-served is therefore profiled minus ever-served, and the three
family cards add up exactly. This changes no number visible today (every
profiled family in the current data has a QR issued), but it will diverge once
profiling outruns card printing, which is the normal state during a rollout.

The gap it exposes, profiled families with no card, is real: control numbers
are issued deliberately, not automatically (`QrCardController`: "Heads without
a `qr_control` mapping are excluded from generation"). If that gap ever needs
its own tile, it is a fifth card and its own decision, not a silent redefinition
of this one.

### Distributions table

Under the cards, one row per distribution ever run: name, subsidy type, dates,
eligible, served, coverage percent. Most recent first. Each row links into the
Distribution tab with that batch selected.

This is the cross-batch history, and it is what makes "Distributions hosted" a
number you can act on rather than trivia: the card says five, the table says
which five and how each one did.

It does not duplicate the Distribution *page*'s batches tab
(`Admin/distribution-batches-body.php`). That one is a management surface: the
active-batch banner, the close control, the New Batch modal. This one is a
read-only outcomes list with no lifecycle controls. Different job, different
columns.

## Distribution tab

`<h2>This batch</h2>`, with the batch picker and Download Report in the section
header, as today. No batch-over-batch comparison line: considered and dropped,
out of scope for this pass.

### The four figures

The scenario this tab exists for: one distribution site, families holding
access cards arrive to claim a subsidy, tracked across n days. The card row
describes that event, the way the Umami reference's cards all describe the view
currently selected. Same tile treatment as the Overview tab's row.

| Card | Source | Pilot reading |
|------|--------|---------------|
| Eligible families | `distribution_batch.eligible_count` | 20,000 |
| Served | `coverage()['served']`, coverage percent as a small sub-line | 18,039 / 90% |
| Remaining | `coverage()['remaining']` | 1,961 |
| Busiest day | max of the by-day series, labelled with its day | Day 1, 9,133 |

Card 4 carries the n-days dimension. It states the shape of the arrival curve
(the pilot ran 9,133 / 6,078 / 2,828, heavily front-loaded) without making
anyone read the chart, and it is the figure that informs staffing for the next
event. It reads identically on an open or a closed batch, so no label or layout
flips underneath an operator mid-event.

A slim progress bar sits under the row, spanning its full width: the cards give
the numbers, the bar gives fraction-of-whole at a glance. The voided count and
the "batch open, updated hh:mm" stamp keep their existing muted line below it,
voided still hidden at zero.

This replaces the current `.batch-progress` block, which packed the same four
facts into one sentence.

### Rollout by day

Bar chart, one bar per calendar day the batch was open. Renders only when a
batch spans more than one day; a single-day batch gets no chart, one bar proves
nothing. The underlying series is still computed for a single-day batch,
because the Busiest day card reads from it either way.

Shown for open and closed batches alike: this is retrospective reporting ("how
did the three days break down"), a different job from the existing cumulative
line chart, which stays open-batch-only because its job is live monitoring (a
flat tail means scanning stopped).

Backed by a new `SubsidyStatsModel` query grouping `subsidy_distribution` by
`claim_date` within the batch, distinct `memberID` per day. `claim_date` is
already a `date` column and is set server-side to `date('Y-m-d')` at scan time
(`ScanController::logAid()`), never from user input, so it needs no `DATE()`
wrapper and is a reliable day key.

The day bars sum to the Served card's total because a family can be scanned at
most once per batch: `ScanController::logAid()` refuses a repeat scan in the
same batch. If that rule ever changes, per-day distinct counts would
double-count a family across days and the bars would stop summing to the
headline.

The new series has to reach the client the same way the existing ones do: the
`#reportsData` JSON block in `Admin/batch-overview.php` and the
`distribution/reports/stats` endpoint both gain a `byDay` key, and
`ReportsCharts.update()` repaints it. Without that the chart would go stale
under the live poll while everything beside it ticks.

### Barangay sub-tab, rebuilt

**The existing barangay bar chart is removed.** `#chartBarangay`
(`Admin/batch-overview.php`, drawn by `scanner-reports.js`) plotted the same
per-barangay coverage the table underneath it already listed, twice on one
screen. The map takes over the visual read of that data and the bar chart form
moves to the day rollout above, where it plots something the table cannot show.
Removing it also retires `$emptyChart` and its "Nothing was handed out in this
batch, so there is no coverage to plot" branch, which existed only to keep that
chart from rendering as empty gridlines.

**Table**: leaderboard, best-first by coverage percent, always. No open/closed
sort flip. The 2026-08-04 spec's "worst-first, where staff get sent" rationale
is corrected here: distribution is a single central site, families travel to
it, no one is dispatched to a barangay. The table's job is a straight readout,
so it reads best-first like a leaderboard, same column, same data, both batch
states.

**Map**: compact panel beside the table, not a hero element, built from
`public/assets/image/binan_brgy.svg` (24 unlabeled `<path>` elements, one per
barangay, no id or class to key off). A commit predating this branch
(`8264289`) deleted a `binan_brgy.json` GeoJSON with the same 24 features in
the same PSGC order and each feature's `adm4_en` name. Same export source, so
path order should line up with feature order; this gets verified by a
centroid/bbox comparison script during implementation, not assumed. One
spelling to reconcile: the GeoJSON says "Mampalasan," the seeded `barangay`
table says "Mamplasan." The DB spelling wins.

Colored by served/eligible intensity, flat 3-4 step scale, no legend clutter.
Linked to the day chart: clicking a day bar recolors the map to that day's
activity; the default "All" selection shows the cumulative state, matching the
table. Hovering or clicking a barangay opens a Bootstrap popover (the stock
component, not a custom tooltip or a modal) showing the exact received/total
for that barangay at the selected day scope. The table itself never changes
with day selection, it stays cumulative for the whole batch: the
map-plus-day-chart pairing shows how the rollout unfolded, the table shows
where it landed.

If the path-to-barangay mapping doesn't verify cleanly, or the map proves
unusable at 390px (slivers like Casile are a poor touch target on a 24-region
SVG), the map is cut and the table carries the sub-tab alone. Nothing
decision-critical rides on the map alone; it earns a place only as a secondary,
verified-correct visual.

### Stations sub-tab, rebuilt

Grid of squares, one per distinct scanner account with at least one successful
scan in this batch. Not a fixed set of "kiosk slots": a scanner that never logs
a scan never gets a square, and the grid grows as scanners start scanning.
`row-cols` plus `ratio ratio-1x1` for the square shape.

Each square: the scanner's username (their real login identity, which is
already the operational unit; no "Kiosk 1/2/3" relabeling) and one headline
number, families served. Click navigates to the existing `Scanner/performance`
page for that scanner, which already has the fuller breakdown (handouts,
timeline, pace, busiest window) built and unused.

That page currently reads the viewer from session only
(`ScanController::performance()`, `$userId = session('user_id')`), so an admin
clicking a square today would land on their own, usually empty, numbers. Fix:
an optional `?scanner=<userID>` override, honored only for Admin/Developer
callers and only when the target user's `account_level` is `scanner`; a
Scanner-role viewer keeps seeing their own session, unaffected.

Backed by the existing `SubsidyStatsModel::perScanner()`, which already returns
`userID`, `scanner` (username), `handouts`, `families`. No new query.

### Remaining sub-tab

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
- Headings are `<h2>` per tab and `<h3>` for panels inside a tab, matching the
  existing `.batch-pane-title` pattern, so the page has one readable outline
  rather than styled text pretending to be structure.

`docs/knowledge/php-practices/comments.md` already bans this register in code
comments. The same ban applies to strings the user reads.

## Out of scope

- Gender or sector demographic breakdown. Raised during design as an analogy
  for "served vs not served," not a real requirement; dropped once clarified.
- Batch-over-batch trend comparison (percent up or down against the previous
  batch of the same subsidy type). The Overview tab's distributions table shows
  every batch's outcome, which covers the comparison without arrow chrome.
- A separate cross-batch history page. The Overview tab is that view.
- A period selector (Today / Last 7 days / Last 30 days) of the kind the Umami
  reference carries. Batches are irregular events of one to n days, not calendar
  periods, so a rolling window cuts across whole and partial rollouts and
  produces a number nobody can act on. The batch selector is the real time
  control.
- Any modal. The one piece of "rich content on interaction" is the map's
  Bootstrap popover; everything else the grid/table/chart already shows inline
  or is a page navigation (Stations square to `Scanner/performance`, Overview
  table row to the Distribution tab).
- A new SQL patch. Every number above is derivable from the V20 schema as
  already merged: `barangayID`, `batch_eligibility`, `dt_voided`, and
  `subsidy_distribution.claim_date` for day grouping. `member.sex` exists but
  goes unused, since demographics are out of scope.
- The distribution calendar and masterlist import, both already out of scope
  per the 2026-08-04 spec and untouched by this one.

## Verification

- `vendor/bin/phpunit` and `composer lint` green.
- `?view=` and `?tab=` survive each other: switching the outer tab keeps the
  selected batch, switching an inner sub-tab keeps both the batch and the outer
  tab. `page_tabs`'s new `param` argument defaults to `'tab'` and every existing
  caller renders byte-identical hrefs.
- Overview's three family cards reconcile: profiled minus ever-served equals
  never-served, against a fixture containing a profiled family with no
  `qr_control` row (the case the old QR gate excluded).
- Distributions hosted equals the row count of the table beneath it, and each
  row links to the Distribution tab with that batch selected.
- An Encoder's dashboard renders no tabs and is otherwise unchanged.
- `binan_brgy.svg` path order verified against the recovered GeoJSON's feature
  order (centroid or bounding-box comparison script), not assumed correct by
  inspection.
- A fixture seeds a three-day batch with 9,133 / 6,078 / 2,828 distinct families
  served per day (the pilot's shape). The chart renders three bars carrying
  exactly those values and they sum to the 18,039 the Served card reports. A
  single-day batch renders no chart at all.
- The Busiest day card names the day carrying the largest per-day count and
  agrees with the tallest bar in the chart beside it. On a single-day batch it
  names that day and no chart renders.
- Eligible, Served and Remaining on the cards match what `coverage()` returns,
  and Served plus Remaining equal Eligible.
- Barangay leaderboard returns the same rows in both batch states, sorted
  best-first, no flip.
- Stations grid shows exactly the scanners with at least one scan in the batch,
  no more, no fewer; clicking a square as Admin/Developer shows that scanner's
  real numbers, not the admin's own.
- Playwright at desktop and 390px against `app.baseURL`, compared with Manage
  Records as the design source of truth, per this repo's UI/UX verification
  standard.
