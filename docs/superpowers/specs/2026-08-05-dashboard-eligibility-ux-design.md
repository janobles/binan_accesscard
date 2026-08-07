# Dashboard Eligibility UX Overhaul

Date: 2026-08-05
Status: approved design, not yet planned

## Where we are

The 2026-08-04 eligibility work sorted out the data. Coverage has a denominator
that means something, barangay is a real reference column instead of a string
we parse out of an address, and voiding a scan no longer deletes the record.
That part is solid. What we built on top of it is not.

The page runs two different scopes together with nothing separating them. Up
top there's a small unlabelled strip reporting the registry, and immediately
below it, with no announcement, everything switches to whichever batch happens
to be in the selector. Nobody tells you the scope changed. You just have to
know.

The barangay view has a second problem. It draws a bar chart and a table that
carry identical information, one above the other, and sorts them worst-first on
a premise that turns out to be wrong. That sort was designed around sending
staff to the barangays falling behind. But the program does not work that way:
distribution happens at a single central site and families travel in to claim.
Nobody gets dispatched anywhere, so sorting for dispatch optimises for a
decision nobody makes.

The Stations tab is a plain three-column table describing what is really a
fleet of scanner kiosks working independently across the room.

And underneath all of it, the page cannot answer the question an officer walks
up with. You can see one batch, and you can see the registry total, but nothing
connects them. How many distributions has the city actually hosted? How many
families has the program ever reached? How many has it never reached at all?
None of that is on the page.

This pass splits the dashboard into two tabs and rebuilds both on top of the
data layer we already have. No schema changes, no new SQL patch.

Throughout this document the worked example is the three-day rice subsidy
pilot: six barangays served per day, eighteen of the city's twenty-four covered
in total, with 9,133 families on day one, 6,078 on day two and 2,828 on day
three, for 18,039 altogether.

## How the page is organised

Two tabs at the page level, because the page really is covering two different
things and running them together is the layout failure we keep tripping over.

**Overview** is the default. It covers the program end to end, from profiling
through to distribution, and it never moves when you change the batch selector.

**Distribution** covers one batch: the event itself and the breakdowns within
it.

The tab strip is the existing `components/page_tabs`. Right now it hardcodes
`?tab=` into every href, so we give it an optional `param` argument that
defaults to `'tab'`. The outer strip can then use
`?view=overview|distribution` while the Distribution tab's inner strip goes on
using `?tab=barangay|stations|remaining`. Every existing caller passes nothing
and keeps rendering exactly the hrefs it renders today.

Two levels of tabs on one page can easily turn into tab soup, so they need to
look clearly different. The outer strip is the page's main navigation and
should read that way. The inner strip lives inside the Distribution pane and
should sit visually lighter. If the two end up looking alike when we build it,
tone the inner one down rather than making the outer one louder.

Role gating does not change. Tabs only render for the roles that already see
distribution data, which is Developer and Admin via `$seesDistribution` in
`DashboardPageBuilder::buildViewData()`. An Encoder's dashboard stays exactly as
it is today: no tabs, just their activity panel.

## Overview tab

Heading is `<h2>Program to date</h2>`, followed by four stat cards laid out as
`row row-cols-2 row-cols-md-4 g-3` using plain Bootstrap `.card`, with no
`card-header` and no icon.

That last bit is a deliberate exception to
`docs/knowledge/sbadmin/target-theme.md`, which says cards carry a
`card-header` with an icon and title. It applies to KPI tiles only. An icon
beside a number is decoration, and four of them in a row is four pieces of
decoration competing with the four numbers you are trying to read. Content
cards elsewhere in the app keep their icon and title header as documented.

| Card | What it means | Where it comes from |
|------|---------------|---------------------|
| Families profiled | households registered in the system | `countFamilies()`, already exists |
| Distributions hosted | batches run, open and closed | `COUNT(*)` on `distribution_batch` |
| Families ever served | distinct households reached in any batch | `COUNT(DISTINCT memberID)` on unvoided `subsidy_distribution` |
| Families never served | profiled but never reached | profiled minus ever served |

All four are all-time figures and none of them carries a trend arrow. "To date"
is already cumulative, so there is no previous period to compare against and an
arrow would be inventing one.

Read together they tell the whole story: how many households we know about, how
many events we have staged, how many households those events actually reached,
and how many we have never reached at all.

### One behaviour change: never served drops its QR requirement

Worth flagging clearly, because this changes an existing number's definition
rather than just moving it.

`DashboardModel::programStats()` currently counts a family as never served only
if it has a `qr_control` row and no unvoided distribution. That made sense for
what the figure originally meant. It was described as "the pool the next batch
draws from," and to draw from that pool a family needs a card someone can
actually scan.

On an end-to-end overview it is the wrong rule. A family with no printed card
has still never been served, and leaving them out quietly breaks the
arithmetic: with the QR gate in place, ever-served plus never-served adds up to
the number of *carded* families, not profiled ones, so the card row would not
reconcile and anyone checking the maths would find a hole.

So on this tab, never served is simply profiled minus ever served, and the
three family cards add up. In today's data this changes nothing on screen,
because every profiled family currently has a QR issued. It starts to matter
the moment profiling runs ahead of card printing, which is the normal state
during a rollout.

The gap this exposes, families profiled but not yet carded, is genuinely real.
Control numbers get issued deliberately rather than automatically, which
`QrCardController` spells out: "Heads without a `qr_control` mapping are
excluded from generation." If that gap ever deserves its own tile it should be
a fifth card and a decision we make on purpose, not something we smuggle back
into this one.

### Distributions table

Underneath the cards, one row per distribution we have ever run: name, subsidy
type, dates, eligible, served, and coverage percent, most recent first. Each
row links straight into the Distribution tab with that batch already selected.

This is the cross-batch history, and it is also what earns "Distributions
hosted" its place on the card row. On its own, a number like five is trivia.
Paired with a table telling you which five and how each one went, it becomes
the thing you actually came to look at.

It is not a duplicate of the batches tab over on the Distribution page
(`Admin/distribution-batches-body.php`). That one is a management surface: the
active-batch banner, the close control, the New Batch modal. This one is a
read-only outcomes list with no lifecycle controls on it at all. Different job,
different columns.

## Distribution tab

Heading is `<h2>This batch</h2>`, with the batch picker and Download Report
sitting in the section header the way they do today.

### The four figures

Here is the scenario this tab exists to serve: one distribution site, families
turning up with their access cards to claim a subsidy, tracked across however
many days the event runs. The card row describes that event, in the same spirit
as the Umami reference where every card describes whatever view you currently
have selected. Same tile treatment as the Overview tab.

| Card | Where it comes from | Pilot reading |
|------|---------------------|---------------|
| Eligible families | `distribution_batch.eligible_count` | 20,000 |
| Served | `coverage()['served']`, with coverage percent as a small sub-line | 18,039 and 90% |
| Remaining | `coverage()['remaining']` | 1,961 |
| Busiest day | the largest value in the by-day series, labelled with its day | Day 1, 9,133 |

Busiest day is the card carrying the multi-day dimension. It tells you the
shape of the arrival curve without making anyone stop and read a chart: the
pilot ran 9,133 then 6,078 then 2,828, which is heavily front-loaded, and that
is exactly the fact you want when staffing the next event. It reads the same
way whether the batch is open or closed, so nothing relabels or reflows
underneath an operator while an event is running.

A slim progress bar sits below the row, spanning its full width. The cards give
you the numbers and the bar gives you fraction-of-whole in one glance. The
voided count and the "batch open, updated hh:mm" stamp keep their existing
muted line underneath, with voided still hidden when it is zero.

This replaces the current `.batch-progress` block, which packed all four of
these facts into a single running sentence.

### Rollout by day

A bar chart with one bar per calendar day the batch was open. It only renders
when a batch actually spans more than one day, because a single bar tells you
nothing you cannot read off the Served card. The series behind it still gets
computed for single-day batches, since the Busiest day card reads from it
either way.

This one shows for open and closed batches alike. It is retrospective
reporting, answering "how did the three days break down," which is a different
job from the cumulative line chart already on the page. That one stays
open-batch-only because its job is live monitoring, where a flat tail means
scanning has stopped and somebody needs to know right now.

The data comes from a new `SubsidyStatsModel` query grouping
`subsidy_distribution` by `claim_date` within the batch, counting distinct
`memberID` per day. `claim_date` is already a `date` column and gets set
server-side to `date('Y-m-d')` at scan time in `ScanController::logAid()`,
never from user input, so it needs no `DATE()` wrapper and is a dependable day
key.

The day bars sum exactly to the Served card because a family can only be
scanned once per batch: `ScanController::logAid()` refuses a repeat scan within
the same batch. That is worth writing down, because if the rule ever loosens
the per-day distinct counts would start double-counting families across days
and the bars would quietly stop matching the headline.

One thing not to forget when building this: the new series has to reach the
client the same way the existing ones do. Both the `#reportsData` JSON block in
`Admin/batch-overview.php` and the `distribution/reports/stats` endpoint need a
`byDay` key, and `ReportsCharts.update()` has to repaint it. Miss that and the
chart sits frozen while everything around it ticks over on the live poll, which
is a particularly confusing bug to look at.

### Barangay sub-tab

**The existing barangay bar chart comes out.** `#chartBarangay` lives in
`Admin/batch-overview.php` and is drawn by `scanner-reports.js`, and it plots
precisely the per-barangay coverage that the table directly beneath it already
lists. Two renderings of one dataset stacked on one screen. The map takes over
the visual read, and the bar chart form moves up to the day rollout where it
plots something a table genuinely cannot. Taking it out also retires
`$emptyChart` and its "Nothing was handed out in this batch, so there is no
coverage to plot" branch, which only ever existed to stop that chart rendering
as a screen of empty gridlines.

**The table** becomes a leaderboard, sorted best-first by coverage percent, in
both batch states. No flipping the sort depending on whether the batch is open.
This corrects the 2026-08-04 spec's worst-first rationale, which was built
around sending staff to the worst-performing barangay. Since distribution
happens at one central site and families travel to it, nobody is being
dispatched and that sort is optimising for a decision that does not exist. The
table's job is a straight readout, so it reads best-first like a leaderboard.

**The map** is a compact panel beside the table, not a hero element. It is
built from `public/assets/image/binan_brgy.svg`, which holds 24 unlabelled
`<path>` elements, one per barangay, with no id or class to key off. Fortunately
a commit predating this branch (`8264289`) deleted a `binan_brgy.json` GeoJSON
carrying the same 24 features in the same PSGC order, each with its `adm4_en`
name. Same export source, so path order should line up with feature order.
"Should" is doing real work in that sentence, so it gets verified with a
centroid or bounding-box comparison during implementation rather than assumed.
One spelling to reconcile: the GeoJSON says "Mampalasan" and the seeded
`barangay` table says "Mamplasan". The database spelling wins.

Colour it by served-over-eligible intensity on a flat three or four step scale,
with no legend furniture. It links to the day chart: click a day's bar and the
map recolours to that day's activity, with the default "All" selection showing
the cumulative picture that matches the table. Hover or click a barangay and a
Bootstrap popover (the stock component, not a hand-rolled tooltip and not a
modal) gives you the exact received-over-total for that barangay at whatever day
scope is selected. The table itself never responds to the day selection and
stays cumulative for the whole batch. The map and day chart together show you
how the rollout unfolded; the table shows you where it ended up.

If the path-to-barangay mapping does not verify cleanly, or the map turns out
unusable at 390px, the map gets cut and the table carries the sub-tab on its
own. That second risk is real: slivers like Casile make poor touch targets on a
24-region SVG. Nothing decision-critical rides on the map alone, and it only
earns its place as a secondary visual we have confirmed is correct.

### Stations sub-tab

A grid of squares, one per scanner account with at least one successful scan in
this batch. Not a fixed set of kiosk slots: a scanner who never logs a scan
never gets a square, and the grid fills in as scanners start working. Use
`row-cols` with `ratio ratio-1x1` for the square shape.

Each square carries the scanner's username and one headline number, families
served. The username is the right label because it is already the real
operational identity: the accounts are literally named `Scanner1` through
`Scanner20`, so relabelling them "Kiosk 1/2/3" would invent a naming scheme on
top of one that already works.

Clicking a square takes you to the existing `Scanner/performance` page for that
scanner, which already has the deeper breakdown built and sitting unused:
handouts, timeline, pace, busiest window.

There is a catch to fix first. That page reads the viewer from the session and
nothing else (`ScanController::performance()`, `$userId = session('user_id')`),
so an admin clicking a square today would land on their own numbers, which are
usually zero, displayed under a scanner's name. That is a silent wrong answer,
which is worse than an error. The fix is an optional `?scanner=<userID>`
override, honoured only for Admin and Developer callers and only when the
target account's `account_level` is `scanner`. A Scanner-role viewer keeps
seeing their own session exactly as now.

The data is already there in `SubsidyStatsModel::perScanner()`, which returns
`userID`, `scanner`, `handouts` and `families`. No new query needed.

### Remaining sub-tab

No changes.

## Labels and copy

Every string on this page is either a label on a number or an empty state, and
both get held to the same standard: be useful, then stop. Nothing exists to
fill space or narrate what the reader can already see.

- A tile label names its number and leaves it there. "Families never served",
  not "Families never served yet, representing the pool available for future
  distributions."
- No helper text under a tile. If a number needs a sentence of explanation to
  make sense, the problem is the number, not the missing sentence.
- Empty states give the fact and, if there is one, the next step, in a single
  line. The existing "No distribution batch exists yet. Open one from the
  Distribution page to see its coverage here." is the standard: what is true,
  then where to go. Note that a batch with a roster but no scans yet keeps
  showing 0 of 1,240 rather than an empty state, per the 2026-08-04 spec,
  because zero out of a known total is real information.
- Section headings are nouns, not sentences. "Rollout by day", "Program to
  date". No "Here you can see..." framing.
- Headings run `<h2>` per tab and `<h3>` for panels inside a tab, matching the
  existing `.batch-pane-title` pattern, so the page has one coherent outline
  instead of styled text pretending to be structure.

`docs/knowledge/php-practices/comments.md` already bans this register in code
comments. It applies just as much to strings a user reads.

## Out of scope

- **Gender or sector demographic breakdowns.** These came up during design as an
  analogy for served-versus-not-served rather than an actual requirement, and
  were dropped once that was clear.
- **Batch-over-batch trend comparison**, meaning a percentage up or down against
  the previous batch of the same subsidy type. The Overview tab's distributions
  table already shows every batch's outcome, which supports the comparison
  without any arrow chrome.
- **A separate cross-batch history page.** The Overview tab is that view.
- **A period selector** along the lines of Today / Last 7 days / Last 30 days
  from the Umami reference. Batches are irregular events lasting one to n days
  rather than calendar periods, so a rolling window slices across whole and
  partial rollouts and produces a number nobody can act on. The batch selector
  is the real time control here.
- **Modals.** The only piece of rich-on-interaction content is the map's
  Bootstrap popover. Everything else either shows inline or is a plain page
  navigation: a Stations square goes to `Scanner/performance`, an Overview table
  row goes to the Distribution tab.
- **A new SQL patch.** Every figure above comes out of the V20 schema as
  already merged, using `barangayID`, `batch_eligibility`, `dt_voided` and
  `subsidy_distribution.claim_date`. `member.sex` exists but goes untouched,
  since demographics are out of scope.
- **The distribution calendar and masterlist import**, both already out of scope
  per the 2026-08-04 spec and unaffected by this one.

## How we know it works

- `vendor/bin/phpunit` and `composer lint` both green.
- `?view=` and `?tab=` survive each other. Switching the outer tab keeps the
  selected batch; switching an inner sub-tab keeps both the batch and the outer
  tab. `page_tabs`'s new `param` argument defaults to `'tab'` and every existing
  caller renders byte-identical hrefs.
- Overview's three family cards reconcile: profiled minus ever served equals
  never served. Test this against a fixture containing a profiled family with no
  `qr_control` row, since that is exactly the case the old QR gate excluded.
- Distributions hosted matches the row count of the table beneath it, and every
  row links through to the Distribution tab with that batch selected.
- An Encoder's dashboard renders no tabs and is otherwise untouched.
- `binan_brgy.svg` path order is verified against the recovered GeoJSON's
  feature order with a centroid or bounding-box comparison, not eyeballed.
- A fixture seeds a three-day batch with 9,133 / 6,078 / 2,828 distinct families
  served per day. The chart renders three bars carrying exactly those values,
  and they sum to the 18,039 on the Served card. A single-day batch renders no
  chart at all.
- The Busiest day card names the day with the largest per-day count and agrees
  with the tallest bar beside it. On a single-day batch it names that day and no
  chart renders.
- Eligible, Served and Remaining on the cards match what `coverage()` returns,
  and Served plus Remaining equals Eligible.
- The barangay leaderboard returns the same rows in both batch states, sorted
  best-first, with no flip.
- The Stations grid shows exactly the scanners with at least one scan in the
  batch, no more and no fewer. Clicking a square as an Admin or Developer shows
  that scanner's real numbers rather than the viewer's own.
- Playwright at desktop and 390px against `app.baseURL`, compared against Manage
  Records as the design source of truth, per this repo's UI/UX verification
  standard.
