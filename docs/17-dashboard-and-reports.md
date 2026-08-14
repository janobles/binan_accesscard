# Dashboard and reports

## Start here when a page shows the wrong thing

`app/Libraries/DashboardPageBuilder.php` assembles the view data for every
dashboard page and renders the shell through one `renderPage()`. When a dashboard
page shows a number you did not expect, or a panel that should not be there, this
is the first file to open. It is almost never the controller, because the
controller is one line.

```php
public function renderPage(string $activePage): string|RedirectResponse
```

The argument is the navigation manifest key from chapter 10, which is what ties
the URL, the sidebar entry, the page title, and the data assembly together.

Behind it sit `buildViewData()` and `buildRecordListViewData()`. There is one
render path for every role. Where a role genuinely differs, an Encoder's dashboard
showing only their own activity, a Viewer's record list emitting no edit actions,
that is a conditional inside the builder rather than a parallel method. Chapter 01
explains why the parallel methods were removed.

Adding a dashboard page means adding a branch here, not a data-assembly block in
the controller.

## Where the numbers come from

`app/Models/DashboardModel.php` owns the counts. The builder composes their
results; it never touches the query builder itself.

The dashboard also carries a Distribution pane, which polls a pair of read-only
endpoints rather than being assembled with the page. Those endpoints have their
own manifest key, `dashboard-reports`, and the reason is instructive: the pane
renders for every staff role, while the Distribution page it used to be grouped
with does not. Sharing a key left an Encoder looking at a pane whose data 404'd
silently. If you add a fragment that loads for a wider audience than the page it
came from, it needs its own key.

## The stats cache

The overview counts scan the `member` table, which is large enough that doing it
on every dashboard load is wasteful. They are cached for **60 seconds**.

A 60-second cache on a page people watch while entering records would normally be
a bug: create a family, refresh, and the count is stale. So the cache is
invalidated as well as expired. `logAction()` deletes `DashboardModel::STATS_CACHE_KEY`
after every logged mutation, and since every family mutation writes an audit row
(chapter 16), every family mutation clears the overview counts. For that key the
TTL is the backstop, not the mechanism.

A second key, `PROGRAM_STATS_CACHE_KEY`, holds the program stats behind the
Overview tab's four counts. It uses the same 60-second TTL but nothing deletes it,
so those four counts refresh on expiry alone and can lag a mutation by up to a
minute.

This coupling is worth remembering if you ever add a mutation path that skips the
audit. You would break the dashboard's freshness as well as the accountability
trail.

## Reports

`app/Controllers/Admin/ReportsController.php` serves the distribution reports,
and everything it returns is batch-scoped. There is no date-range filter; it was
removed rather than superseded when batches arrived, because a date range spanning
two batches answers no question anyone asks. That is a different question from
the day filter inside a single open batch (below): the day filter narrows one
batch's own figures to one of its days, it never spans batches, and it does not
bring the date-range filter back.

`stats()` returns the combined totals and the per-kiosk drilldown as JSON, which
is what the dashboard's Distribution pane polls for its live updates. `pdf()`
renders the export. Both read `SubsidyStatsModel::batchSnapshot()`
(`app/Models/Scanner/SubsidyStatsModel.php:588`), so the two cannot disagree
about a figure.

Three of `stats()`'s payload keys carry the peak-hours work: `heatmap` is the
day-by-hour grid behind the Activity card's Hours view, `byScanner` is the
per-scanner fold (the table's rows, TOTAL last; this replaced the old
`perScanner` key), `byScannerByDay` is the same fold partitioned by calendar
day, keyed by date, for the Stations card's Per day view, and `days` lists the
batch's own days for the day picker. See chapter 15's `ScannerMetrics` section
for what a `byScanner` row means, especially `pace`, which is a cadence figure
(non-idle gaps per active hour), never families divided by elapsed time.

The underlying queries live in `SubsidyStatsModel`, covered in chapter 15
alongside the role filtering: the Scanner role only ever sees its own row, and
that filtering happens server-side.

The per-kiosk table and its PDF export are Admin and Developer only.

### The Distribution pane's cards

The pane used to be one block with a page-level tab strip switching between
Barangay, Stations, and Remaining. They are three separate subjects, not three
views of one, so reading two of them meant losing the first; each is now a card
of its own, rendered together, and there is no `?tab=` for this pane any more
(the reference-data page and the `distribution` page's own Schedule/Log tabs
still use `?tab=`, unrelated to this pane). A tab strip survives only inside a
card, switching client-side between views of that card's own data:
`components/card_tabs.php` renders it, and it writes no query parameter, unlike
`components/page_tabs.php`'s page-level strip.

Below the always-visible headline KPI row and coverage bar sit five cards:
Activity (Hours, the peak-hours heatmap; Days, the rollout bar chart; and
Weekdays, the all-time histogram that ignores the batch picker), the
families-served timeline chart, shown only while the batch is open, Barangay
coverage (Table and Map), Stations (All and Per day), and Remaining.

`?day=` picks a day inside the current batch, read server-side by
`DashboardPageBuilder` and echoed by the heatmap's day rows and the `#dayPick`
select (`app/Views/Admin/batch-overview.php`). It narrows the headline
figures that have a day dimension, served and peak hour, to that one day;
eligible and scanners active stay batch-wide, because a family is eligible for
the batch, not for one of its days. Choosing a day is client-side
(`public/assets/js/dashboard/batch-heatmap.js` writes `?day=` with
`replaceState`, no reload), which is what keeps it from being the date-range
filter above: it never leaves the batch it started in.

## Exports

The PDF export (`app/Views/Scanner/pdf/report-hours.php`,
`app/Libraries/Scanner/ReportsPdfGenerator.php`) is generated server-side from
the same batch snapshot that feeds the on-screen tables, so the printed report
and the screen cannot disagree. It carries five sections: the KPI row,
coverage by barangay, rollout by day, the peak-hours grid, and per-scanner
performance, each read off the same `byScanner`/`heatmap`/`byDay` data the
dashboard cards render.

**The PDF does not print the unclaimed-families roster.** Remaining families
are a KPI count in the report, not a list of names. An earlier version printed
the full roster and ran the file to a hundred-odd pages behind one page of
report; the names live on the dashboard's Remaining card instead, where they
are paginated and searchable.

If you are adding an export, take the data from the model rather than from the
assembled view data. The view data is shaped for display, and reproducing that
shaping in an export is how the two drift apart.
