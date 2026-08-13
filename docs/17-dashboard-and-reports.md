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
invalidated as well as expired. `logAction()` deletes the stats cache keys after
every logged mutation, and since every family mutation writes an audit row
(chapter 16), every family mutation clears the cache. The TTL is the backstop, not
the mechanism.

Two keys are cached this way: the overview stats and the program stats behind the
Overview tab's four counts. Both use the same 60-second TTL.

This coupling is worth remembering if you ever add a mutation path that skips the
audit. You would break the dashboard's freshness as well as the accountability
trail.

## Reports

`app/Controllers/Admin/ReportsController.php` serves the distribution reports,
and everything it returns is batch-scoped. There is no date-range filter; it was
removed rather than superseded when batches arrived, because a date range spanning
two batches answers no question anyone asks.

`stats()` returns the combined totals and the per-kiosk drilldown as JSON, which
is what the dashboard's Distribution pane polls. `pdf()` renders the export.

The underlying queries live in `SubsidyStatsModel`, covered in chapter 15
alongside the role filtering: the Scanner role only ever sees its own row, and
that filtering happens server-side.

The per-kiosk table and its PDF export are Admin and Developer only.

## Exports

The PDF export is generated server-side from the same model methods that feed the
on-screen tables, so the printed report and the screen cannot disagree.

If you are adding an export, take the data from the model rather than from the
assembled view data. The view data is shaped for display, and reproducing that
shaping in an export is how the two drift apart.
