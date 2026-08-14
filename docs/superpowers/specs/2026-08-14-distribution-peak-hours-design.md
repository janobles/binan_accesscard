# Distribution Peak Hours and Scanner Performance

Date: 2026-08-14
Status: approved design, not yet planned

## Where we are

The dashboard's Distribution pane answers how far a batch got. It does not answer
when. `SubsidyStatsModel::servedByDay()` gives one number per calendar day and
`servedTimeline()` gives a cumulative curve in 15 minute buckets while a batch is
open. Neither tells the department which hour of which day the queue actually
built up, which is the question that decides how many stations to staff and when
to schedule the next payout.

There are three further problems with what is there today.

The pane is organised as one block with three sub-tabs, Barangay, Stations and
Remaining. They are not variants of one thing. They are three separate subjects
sharing a tab strip, so reading two of them means clicking between them and
losing the first.

The per-scanner figures are thin and one of them is wrong. `perScanner()` returns
handouts and families. The families-per-hour figure shown on the kiosk and in the
station modal is computed in `ScanController::kioskSnapshot()` by dividing
families by the batch's wall-clock span, `started_at` to now for an open batch.
For a three-day batch that denominator includes two nights. A scanner who served
300 families across three days reports about 4 per hour, which describes nothing
that happened.

The station modal presents its four numbers as four KPI cards inside a small
dialog. That is a lot of chrome for four values and it cannot grow to eight
without becoming a wall of boxes.

This design adds hour-level analysis, makes the batch figures answerable per day,
replaces the pace arithmetic with metrics derived from scan timestamps, and
reorganises the pane into independent cards.

## What is being built

Five things, in one change because they share the same fold:

1. A peak-hours heatmap, day by hour within a batch, and weekday by hour across
   all batches.
2. A day filter that makes the headline figures answer for one day.
3. The Distribution pane reorganised from three sub-tabs into five cards.
4. Eight per-scanner metrics computed once and shown in three places.
5. Those figures added to the JSON endpoint and the PDF report.

No schema change. Everything derives from `subsidy_distribution.dt_created`,
`claim_date`, `userID` and `batch_id`, which are all already written server-side
at scan time.

## Data layer

### Two new queries on SubsidyStatsModel

`scanEvents(int $batchId): list<array{userID:int,ts:int,control_no:int}>`

Live rows only (`dt_voided IS NULL`), joined to `batch_eligibility` on the same
terms as `coverage()`, ordered by `userID` then `dt_created`. The join is not
optional. Every existing figure on the pane is defined against the frozen roster,
and a station table built without it would report more served than the Served
card above it.

`hourHistogram(?int $batchId): list<array{day:string,dow:int,hour:int,families:int}>`

Grouped on `claim_date` and the hour of `dt_created`. A null `$batchId` is the
all-time weekday view and groups on day-of-week instead; it gets its own cache
key because it cannot use the per-batch fingerprint that `batchSnapshot()`
relies on.

### One new library

`App\Libraries\Scanner\ScannerMetrics`, no database, pure functions.

`fold(array $events, int $idleGapSeconds = 900): array` walks the event stream
once and returns, per scanner: `families`, `handouts`, `activeSeconds`,
`medianGapSeconds`, `firstTs`, `lastTs`, `longestGapSeconds`, and `byHour[]`. A `TOTAL`
row is folded by the same code path, so the total can never be a differently
computed number that happens to sit at the bottom of the table.

Derived on top of the fold:

- `pace` = families divided by active hours.
- `typicalSeconds` = the median gap between consecutive scans once idle gaps are
  excluded. The median, not the mean: a handful of near-threshold gaps drags a
  mean upward and makes a steady station look slow. Because the fold is in PHP
  the gap list is already in hand, so the sort costs nothing worth avoiding.
- `share` = this scanner's families over the batch total.

Active time is the sum of gaps between consecutive scans by the same scanner,
counting any gap longer than `idleGapSeconds` as idle rather than work. Fifteen
minutes is the default threshold.

The property that matters: active time is derived from scan timestamps alone. It
ends at the last scan. It never calls `time()`, so an open batch with nobody
scanning does not inflate anyone's denominator, and a closed batch's figures are
frozen the moment the last scan lands. This is what the current `perHour` gets
wrong.

A scanner with a single scan has no gap. `activeSeconds` is zero, and pace and
typical time render as a dash rather than as a division by zero or a fabricated
infinity.

All bucketing is Asia/Manila, matching the schedule work, so an hour bucket means
the hour staff actually experienced.

### Why the math is in PHP

Computing gaps in SQL means window functions. `LAG` works on the MariaDB 10.4
that runs locally and in CI, but it pins the application to MariaDB 10.2 or MySQL
8 and above, it makes the gap logic untestable without a database, and the same
arithmetic would still need repeating for the all-time view.

The scanner module already holds a no-database test posture. A pure library
keeps every metric unit-testable, gives one definition of pace for all three
surfaces that show it, and costs only the transfer of a narrow three-column row
set that the existing per-batch cache already bounds.

A materialised summary table was considered and rejected: it is a second source
of truth for numbers that are already derivable, and every correction to the math
would need a backfill.

## Page layout

`batch-overview.php` becomes a KPI row plus five independent cards. The
`$batchBodyTab` state and its `?tab=` parameter are deleted.

```
[ Eligible ][ Served (day) ][ Peak hour ][ Scanners active ]
[==================== coverage bar ====================]

+-- Activity ------------------+  +-- Barangay coverage ---------+
| Hours  Days  Weekdays        |  | Map  Table                   |
+------------------------------+  +------------------------------+

+-- Stations -------------------------------------------------+
| All  Per day                                                |
+-------------------------------------------------------------+

+-- Remaining families ---------------------------------------+
+-------------------------------------------------------------+
```

Tab strips survive only inside a card, and only where they switch between
variants of that card's own data:

- **Activity**: `Hours` is the day by hour heatmap for this batch, `Days` is the
  existing rollout bar chart, `Weekdays` is the all-time weekday by hour heatmap.
  Three grains of one question. `Weekdays` is the one view that ignores the batch
  picker, which the card states in a one-line caption.
- **Barangay coverage**: `Map` and `Table` are the same data in two forms.
  Switching also retires the current `d-none d-lg-block`, which hides the map
  below the large breakpoint. On a phone the reader chooses Table rather than
  being silently given less.
- **Stations**: `All` is the whole batch, `Per day` narrows to the selected day.
  Rows open the station modal.
- **Remaining families**: no strip, full width. It carries a search box and
  server-side pagination and cannot share a row.

The strip is a third variant on `components/page_tabs.php`, alongside
`segmented-tabs--subordinate`, styled in `theme.css`. It must not use
Bootstrap's `.nav-underline`: dashboard pages load Bootstrap 5.2.3 compiled into
the SB Admin theme, and that class is 5.3 only, so it would silently do nothing.

Two consequences of dropping the sub-tabs:

1. Remaining renders on every dashboard load rather than only on its own tab.
   That is one 25 row page query plus one count, both on indexed joins. The
   gating existed because it was a tab, not because of cost. Its `?q=`, `?page=`
   and `?per_page=` parameters are unchanged.
2. The live poll currently skips itself entirely on the Remaining tab, because
   the endpoint carries no rows to repaint that table with. With every card
   visible the rule becomes: the poll repaints every card except Remaining, and
   the updated stamp sits on the cards that move.

## The heatmap

Rendered as an HTML table, not a Chart.js canvas. A matrix chart needs the
`chartjs-chart-matrix` plugin, a new vendored dependency for one view. A table
needs none, reads as real rows and columns to a screen reader, keeps each value
as text, and takes keyboard focus so a cell can select a day. Chart.js keeps the
`Days` rollout and the hourly throughput bars, which it draws well.

Three cell states, which is the busiest, lowest and none split:

- **Outside the batch's daily window** (`daily_start_time` to `daily_end_time`):
  blank and hatched, no number. The station was not meant to be open.
- **Inside the window, zero scans**: the lightest step, printed as `0`. This is
  the operationally interesting one, a staffed hour that served nobody.
- **Scans**: one of five steps, darkest at the batch's own maximum rather than an
  absolute ceiling, so a small batch still shows contrast.

Every cell carries a `title` and an `aria-label` with the full reading, for
example `Aug 12, 9-10 AM, 61 families`, so hover and screen reader agree.

The colour ramp uses the repo's `--chart-color-*` tokens and must be a proper
sequential scale legible in both themes. Load the `dataviz` skill before writing
the scale.

## The day filter

Client-side. The snapshot already carries per-day, per-hour and per-scanner
folds for the heatmap and the Stations `Per day` strip, so a three-day batch with
six scanners is a few hundred small integers. Selecting a day is then instant,
which matters because changing the batch already costs a full page render.

Selection has one source of truth. Clicking a heatmap row header and using the
`Day` select beside the batch picker write the same state, and the other control
follows. The URL is kept honest with `history.replaceState` on
`?day=YYYY-MM-DD`, so a refresh, a bookmark or a link to a colleague keeps the
day. The server reads `?day=` only to choose the initial selection and to render
the correct figures before JavaScript runs. `All days` is the default and is
always the first option, and takes no pill, per the toolbar standard.

What the headline cards do:

| Card | All days | Day selected |
|---|---|---|
| Eligible families | batch roster | unchanged, sub-line `batch roster` |
| Served | batch total, coverage | that day's families, sub-line `N batch total` |
| Peak hour | batch's busiest hour | that day's busiest hour, sub-line families |
| Scanners active | distinct scanners in batch | distinct scanners that day, sub-line `of N` |

Eligible never moves. The roster is frozen per batch, and a per-day denominator
would be an invention.

The Busiest day card is retired: the Activity card's `Days` view carries it, and
the Remaining count moves onto the Remaining card's own header.

The selected day is client state and survives a poll repaint. If a poll returns a
snapshot in which the selected day no longer exists, only possible after a
database reimport, selection falls back to `All days` rather than rendering an
empty card.

## Station modal and the performance page

The modal stops being four KPI cards and becomes a definition list on the
`.family-detail-grid` and `.family-detail-item` pattern in
`public/css/familymodal.css`, the same label-over-value line the family record
view uses.

```
Station maria                                    Batch 3  ·  open

FAMILIES SERVED        HANDOUTS LOGGED
318                    331

PACE                   TYPICAL TIME
47 / hour              1 min 16 s per family
while active

ON STATION             IDLE
7:12 AM - 4:02 PM      2 h 05 m
6 h 45 m               longest gap 41 m

BEST HOUR              SHARE OF BATCH
9-10 AM                18%
61 families            of 727 served
```

Two columns on desktop, one on a phone. Every line is label, value and a
qualifier, so no number appears without the thing that makes it readable. The
hourly throughput chart follows underneath. `aria-live="polite"` stays on the
body and the dash placeholder stays on every value, so the dialog opens with a
stable shape and fills rather than reflowing.

`Scanner/performance.php` renders the same eight lines from the same fold, and
its 15 minute chart becomes hourly. The `.stat-card--records`, `--members`,
`--sectors` and `--services` classes go away with the tiles. They are not styles;
they are lookup hooks left over from a deleted grid. Values are addressed by
`data-metric="pace"` instead, which survives reordering.

Kiosk constraints are unchanged: 44px touch targets, the Refresh button stays,
and the poll runs only while the batch is open.

## Reports and the PDF

`ReportsController::stats()` gains `byHour`, `byScanner` and `days`, and keeps
every existing key so nothing reading it breaks. Role filtering is unchanged and
stays server-side. It matters more now: a Scanner still sees only their own row,
and those rows now carry pace and idle time.

`ReportsController::pdf()` grows to five sections:

1. **Cover figures**: batch name, window, eligible, served, coverage, voided.
2. **Rollout by day**: one row per day with families served, peak hour and
   scanners active. The per-day tally as a printable artifact.
3. **Peak hours**: the heatmap as a printed grid, intensity carried by a shaded
   cell so it survives a monochrome office printer.
4. **Station performance**: the eight metric table with the `TOTAL` row folded by
   the same code as the rows above it.
5. **Unclaimed families**: unchanged, still the complete list from `remaining()`.

The station table columns are scanner, families, handouts, pace per hour, typical
time, on station, idle, best hour and share.

Batch scoping stays absolute and no date-range filter returns. A day filter
inside a batch is a different thing and does not reopen that decision.

Everything printed comes from the model and `ScannerMetrics`, never from
assembled view data, per the existing export rule.

## Testing

`ScannerMetrics` is database-free and gets real unit tests: the idle gap
boundary at exactly the threshold, a single-scan scanner, a scanner with one long
break, all scans inside one minute, an empty batch, and a day with a staffed but
empty hour.

The two model methods run against both CI backends. The SQLite path is where the
hour and day-of-week extraction differs from MariaDB, so that difference is
asserted rather than assumed.

## End-to-end verification

Unit tests prove the fold. They do not prove that the numbers on the page came
from it, so the change is not done until it has been driven in a browser against
a database seeded with a scenario whose answers are known in advance.

The local database is throwaway and is recreated for this: drop, import
`accesscardV22.sql`, then run a seed script. The script is a test fixture, not a
schema change, so it lives in the scratchpad and never under `sql/patches`, which
holds patches only.

The seed is built so every metric has a hand-computed expected value:

- A three-day batch with a daily window of 8:00 to 17:00, at a known venue, with
  a frozen roster of a few hundred heads across several barangays.
- **Scanner A**: a full three days, a clear morning peak on day two, and one 90
  minute lunch break each day. Exercises pace, median gap and idle time on the
  normal case.
- **Scanner B**: arrives late on day one and works only two of the three days.
  This is the case the rejected daily-window denominator would have punished, so
  its pace must come out close to Scanner A's despite a much smaller total.
- **Scanner C**: exactly one scan, all batch. Pace and typical time must render
  as dashes, not zero, not infinity.
- One hour inside the daily window with no scans at all, so the heatmap's
  staffed-but-empty state is exercised rather than assumed.
- A voided distribution and a scan for a family outside the roster, neither of
  which may move any figure.

What is checked, with Playwright against the dev server:

1. The KPI row, the heatmap and the Stations table agree with the hand-computed
   values for the whole batch.
2. Selecting each day changes Served, Peak hour and Scanners active to that day's
   figures and leaves Eligible alone, and the Stations `Per day` strip agrees
   with the heatmap row above it.
3. `?day=` survives a reload, and an unknown day falls back to `All days`.
4. The three Activity strips and the two Barangay strips all render, at both
   viewport widths, with the map hidden behind the Table strip rather than by a
   breakpoint.
5. The station modal and the kiosk performance page show the same eight values as
   that scanner's row in the Stations table. Three surfaces, one fold, no
   disagreement.
6. The PDF downloads, opens, and its five sections carry the same numbers as the
   screen, with the printed heatmap legible in monochrome.

Screenshots at both widths, per the `ui-verification` workflow.

## Documentation

`docs/15-distribution.md` and `docs/17-dashboard-and-reports.md` both describe
the three sub-tabs and the four tiles. Both are updated in the same change rather
than left describing a page that no longer exists.
