# Distribution Schedule Calendar

Date: 2026-08-08
Status: approved design, not yet planned

## Where we are

A distribution batch today is a runtime fact, not a plan. `distribution_batch`
records a name, a subsidy type, a `started_at` that defaults to the moment the
row is inserted, and a `closed_at` that stays null until somebody clicks Close.
An admin opens a batch from a modal on the distribution tab, the scanner refuses
to work until one is open, and the batch stays open until a human remembers to
shut it.

During the pilot that last part did not happen. The department was running an
event: crowds, kiosks, a venue to manage. Pressing Close on a web page ranked
somewhere below all of it, so batch boundaries drifted and the timestamps stopped
describing anything real. The fix is not to remind people harder. It is to make
the batch know its own schedule, so start and end stop depending on anyone's
attention.

There is a second gap. Nothing in the system records where a distribution
happens. Venue lives in whoever's head is organising it.

This design turns a batch into a scheduled event with a venue and a date span,
plots those events on a calendar, and lets the batch open and close itself.

The worked example throughout is August 2026: a three-day AICS payout at Alonte
Sports Arena on August 6 to 8, a two-day senior citizen pension at the City Hall
Quadrangle on August 12 to 13, and a one-day relief distribution at Canlalay
Covered Court on August 20.

## What a batch becomes

A batch gains a venue, a date span, and the hours it runs on each of those days.
It keeps everything it already has.

The split that matters: `scheduled_start` and `scheduled_end` are the plan,
`started_at` and `closed_at` remain the actuals. The calendar edits the plan. The
system writes the actuals. Reports and the pace arithmetic in
`ScanController::kioskSnapshot` keep reading the actuals, so nothing downstream
changes meaning.

Dates and hours are stored separately and carry different authority. A three-day
batch runs 8:00 AM to 5:00 PM on each of its three days, not for 72 unbroken
hours, so a single start datetime and end datetime cannot express it. Two `DATE`
columns hold the span and two `TIME` columns hold the daily hours.

### Schema, V21

```sql
ALTER TABLE `distribution_batch`
  ADD `venue`            varchar(150) NOT NULL DEFAULT '' AFTER `name`,
  ADD `scheduled_start`  date NOT NULL AFTER `subsidy_type_id`,
  ADD `scheduled_end`    date NOT NULL AFTER `scheduled_start`,
  ADD `daily_start_time` time NOT NULL DEFAULT '08:00:00' AFTER `scheduled_end`,
  ADD `daily_end_time`   time NOT NULL DEFAULT '17:00:00' AFTER `daily_start_time`,
  ADD `color`            varchar(16) NOT NULL DEFAULT 'green' AFTER `daily_end_time`,
  MODIFY `started_at`    timestamp NULL DEFAULT NULL,
  ADD KEY `idx_db_sched` (`scheduled_start`, `scheduled_end`);
```

`started_at` becomes nullable because a batch plotted for next week has not
started and must not claim it has. Existing rows get their `scheduled_start` and
`scheduled_end` backfilled from `started_at` and `closed_at` so history keeps
rendering on the calendar.

`color` stores one of six names, never a hex: `green`, `yellow`, `orange`, `red`,
`purple`, `blue`. The hex values live in `theme.css`. Anything outside that list
is rejected on save and falls back to `green` on read.

Delivery follows the V17, V19 and V20 pattern: `sql/patches/v21-batch-schedule.sql`
holds the ALTER and the backfill, `accesscardV20.sql` is regenerated as
`accesscardV21.sql`, and `DumpSchema::dumpPath()` resolves the new dump for tests.
No migrations.

## How a batch opens and closes itself

### The rule

Dates gate, times advise.

The date span decides whether scanning is allowed at all. A scan on a scheduled
date is accepted; a scan on any other date is not. The daily hours drive what the
schedule displays and give the auto-close something to count from, but they never
block a scan.

That one asymmetry disposes of three problems at once. Staff who start at 7:30
instead of 8:00 simply start; the scan lands on a scheduled date and opens the
day. Staff who start at 10:00 change nothing, because nothing happens until a
scan arrives. Staff still working at 5:40 are not cut off, because the close is
driven by scan activity rather than by the clock alone.

### Opening

For the batch whose date span contains today, and there is at most one because
overlaps are refused at save time:

- If `closed_at` is set, clear it. The batch is back inside its window, which
  happens on day two of a multi-day batch and after a grace close that turned out
  to be premature.
- If `started_at` is null, stamp it with the current time and freeze the roster
  by calling `EligibilityBuilder::materialize()` with the batch's saved filters.

The roster freezes at open rather than at save. A batch plotted three weeks out
covers the families who exist on the day it runs, so anyone encoded in between is
included without anyone remembering to refresh anything. The count shown in the
create modal is therefore an estimate, and says so.

### Closing

An anchor starts at today's `daily_end_time`. While a scan exists later than the
anchor, the anchor moves forward in thirty minute steps until it sits past the
last scan. The batch closes once the current time reaches the anchor plus thirty
minutes, and `closed_at` is written as the anchor, not as the current time.

Worked through:

- Scheduled end 5:00 PM, last scan 4:10 PM. The anchor stays at 5:00 PM. At 5:30
  PM the batch closes with `closed_at` at 5:00 PM.
- Scheduled end 5:00 PM, last scan 5:15 PM. The anchor moves to 5:30 PM. If
  nothing arrives by 6:00 PM the batch closes with `closed_at` at 5:30 PM.
- No scans at all. The anchor never moves and `closed_at` lands on the scheduled
  end, which is the right record of a distribution nobody attended.

The recorded end therefore overstates the real one by at most thirty minutes.
Across a nine hour day that is a rounding error in any pace figure, and the rule
survives being said out loud to staff: it closes half an hour after the last
scan.

A scan arriving after a grace close, but still inside the date span, reopens the
batch silently. No prompt, no admin, no phone call. The grace period exists to
record a truthful end time, not to lock people out of a distribution that is
still going.

### Why `closed_at` is never `now()`

Every value the reconciler writes is a function of the schedule columns and the
batch's scan timestamps. The current time decides only whether the transition has
become due, never what gets stored. Running the reconciler at 5:31 PM and running
it at 9:00 AM three days later produce the same `closed_at`. That property is
what makes a request driven trigger safe, and it is worth protecting: a design
that stamped `closed_at` with the current time would write a permanently wrong
number whenever the reconciler ran late.

### What triggers it

A filter on the scanner and distribution route groups. Nothing else.

The alternative, a `spark` command on Windows Task Scheduler beside the existing
`scripts/queue-worker.ps1`, was considered and rejected. The queue worker failing
means an import sits pending and an admin retries it later. A scheduler failing
means staff at a venue meet a refusal at 8:00 AM with nobody present who knows
what Task Scheduler is. Worse, the deployment is a laptop that travels to the
venue, and Windows scheduled tasks are registered per user account and do not
survive a re-image or a different login. Depending on one would replace "a human
forgot to click Close" with "the task was never registered on this laptop", which
is the same class of failure, harder to see and impossible to fix in the room.

A filter ships in the repository. It cannot be forgotten, unregistered, or left
off a new machine.

The cost is that state only advances when a request arrives. That is acceptable
because a batch matters only when someone is using it: the first scanner page
load of the morning happens before the first scan by definition, and a late
reconcile still writes the correct `closed_at`. The only visible effect is a
dashboard that can show a finished batch as open until the next page load.

### Failure modes

- Everyone leaves and the laptop is closed. The batch shows as open until someone
  loads a page, and `closed_at` is still correct when they do.
- The laptop dies mid event and XAMPP restarts. The reconciler recomputes from
  data. Inside the window the batch stays open. There is nothing to recover.
- Several kiosks reconcile at once. Every transition is idempotent and converges
  on the same answer.
- The laptop's clock is wrong. This one cannot be solved in code, because the
  database sits on the same machine and shares the same wrong clock. It is
  handled by making it visible: the scanner banner prints the date and time the
  system believes, so a person in the room notices August 3 on August 4.

### Where the code lives

`app/Libraries/BatchScheduleWindow.php` holds the arithmetic and nothing else. It
takes the schedule columns, the batch's last scan time and the current time, and
returns a verdict: open, close with this `closed_at`, or do nothing. No database,
no framework, no session. It is the piece with all the edge cases and it is
testable in isolation.

`DistributionBatchModel::reconcileSchedule()` is the thin layer that loads the
candidate batch, asks the library, applies the verdict, freezes the roster on
open and writes the audit rows.

`app/Filters/BatchScheduleFilter.php` calls it.

Every transition writes an `audit_trails` row through `AuditTrailsModel`,
attributed to the system for the automatic ones and to the user for manual
overrides. `DistributionController::closeBatch()` survives as a manual escape
hatch.

## The calendar

### Where it sits

A new `Schedule` tab, first in the list, on the existing distribution page:
`distribution?tab=schedule`, then `Batches`, then `Distribution Log`. The tab
strip is the existing `components/page_tabs`. One page, one URL, and the
`roleNav:distribution` filter keeps deciding who reaches it.

`Admin` and `Developer` plot and edit. `Viewer` sees the calendar read only,
matching the `$canManageBatches` check in
`app/Views/Admin/distribution-batches-body.php:10`.

### What draws it

FullCalendar's standard bundle, MIT licensed, vendored to
`public/assets/vendor/fullcalendar/`. No CDN, no build step, consistent with how
Bootstrap is already vendored.

Building the month grid by hand was considered and rejected: multi-day span
layout, drag to create, drag to resize, keyboard access and the mobile view add
up to more work than the rest of this feature combined, for a component with no
domain logic in it.

PHP Event Calendar was also considered and rejected. It is a commercial product
that ships its own PHP backend, its own tables and its own configuration app.
Adopting it would mean running a second data model alongside `distribution_batch`
and synchronising the two. The calendar here is a rendering problem, and the
backend already serves JSON.

Configuration: `dayGridMonth` only, no view switcher, events fed from a JSON
endpoint. Drag to create and drag to resize are enabled for batches that have not
started, and disabled for in progress and finished ones.

### Routes

```
GET  distribution?tab=schedule           the page
GET  distribution/schedule/feed          JSON events for a month
POST distribution/schedule/save          create or update
POST distribution/schedule/(:num)/delete remove a plotted batch
```

The feed returns name, venue, date span, colour, and a status of upcoming,
running or finished. The create and update paths reuse the existing
`distribution/batches/preview` endpoint for the live eligibility estimate.

`app/Views/Admin/batch-create-modal.php` and the `distribution/batches/open`
route are removed. The calendar is the only way a batch comes into existence.
An urgent same day distribution is plotted on the calendar for today, which is
the same two clicks and needs no second code path.

### The form

A modal, matching how every other create form in this repository works, and
collapsing to full screen at 390px without extra work. It opens with the dragged
dates prefilled, or empty from the New schedule button.

Fields: Name, Venue, Subsidy type, Days, Daily hours, Covers, Label.

Venue is free text backed by a datalist of previously used venues. A venue
reference table with its own admin page was considered and rejected as
disproportionate: venues repeat rarely, and a datalist buys most of the
convenience for two lines of markup.

Covers is the existing pair of barangay and sector multi-selects. Empty means
citywide, carried by the `All barangays` and `All sectors` placeholders. Category
and service filtering stay out of scope; `EligibilityBuilder` supports barangay
and sector and extending it is a separate change with its own query and test
work.

Under Covers sits the live estimate: the family count, and a note that it locks
in when the batch opens.

Label is a row of six colour swatches, built from radio inputs, about fifteen
lines of markup. Bootstrap has no swatch component and its palette is six theme
tokens rather than a Tailwind style scale, so the six hues are defined in
`theme.css` as muted values that all clear 4.5:1 against white text.

### Copy

Labels are nouns. Help text appears only where the behaviour cannot be inferred
from the form itself, which in practice means one line under Days explaining that
the batch opens on the first day and closes thirty minutes after the last scan.

Messages name the thing, give the date the way a person would say it aloud, and
end with what to do next. No bare codes, no "invalid input". For example, the
scanner's idle state reads "Nothing scheduled today. Next: Senior Citizen Pension
Q3, City Hall Quadrangle, Wed Aug 12, 8:00 AM." rather than "No open batch."

Exact strings get settled against the real views during implementation. This
section fixes the register, not the wording.

### Colour and status

The bar's fill is the chosen label colour, so status needs its own channel: a
pill inside the bar reading Open, or the start date, or Done, and finished
batches rendered at 40% opacity. A month still separates past from future at a
glance without anyone having to learn a colour convention.

### Overlaps

At most one batch may be open at a time, and the scanner's model depends on it,
so two batches may not occupy the same dates.

Saving into occupied dates is refused by the server. What the user sees next
depends on the batch already there:

- It has no distribution rows. A confirmation names it, its venue and its dates,
  states that only one batch can run at a time, and offers replacing it or
  picking other dates. Replacing deletes the plan.
- It has distribution rows. Replacement is refused outright, with a message
  naming the batch and pointing at other dates. A batch with scans against it is
  history rather than a plan, and deleting it would orphan real records.

## The dashboard widget

A card in the dashboard overview: a month grid with a spanning bar for each batch,
today circled, and beneath it the next two batches as name, venue and date range,
with a status pill on one that is running. Read only, linking through to the
Schedule tab.

The grid is hand written, roughly 120 lines, rather than a second FullCalendar
instance. Shrinking FullCalendar into a 340px card means fighting its sizing and
its toolbar, which is more work than writing a static month grid that never needs
to be interactive.

The reference layout that prompted this carried a Month, Week, Day and Agenda
switcher. It is dropped: nothing switches to Week view inside a 340px card, and
the tabs cost a row of vertical space. That control belongs on the calendar page.

Spanning bars rather than dots, because the batches are multi-day by definition
and a dot cannot say that three marked days are one event.

View data is assembled in `DashboardPageBuilder`, per the controllers decide and
libraries build rule. The view is `app/Views/Admin/dashboard-schedule-card.php`.

## Scanner legibility

The scanner's empty state stops being a refusal and starts being an explanation.
It says what is scheduled, where, which hours, and what today's date and time are
according to the system. During a batch it names the batch and venue, says which
day of how many, and when the day ends.

This is not decoration. The people at the venue are the least technical users in
the system and the furthest from anyone who can help. A screen that states its
own understanding of the world lets them tell the difference between the system
being wrong and themselves being wrong, and it is the only defence against a
laptop with a wrong clock.

## Testing

`BatchScheduleWindow` carries the bulk of it, and needs no database:

- A day that starts early, a day that starts late, a day that runs over.
- The anchor rolling forward across several thirty minute steps.
- A batch with no scans closing at its scheduled end.
- Rollover across the days of a multi-day batch.
- Reopening after a grace close that turned out to be premature.
- Repeated calls producing the same result, and calls at different later times
  producing the same `closed_at`.

At the model level: the roster freezing on open rather than on save, both overlap
refusals, and colour validation rejecting anything outside the six names.

At the controller level: the feed's shape, and the role gate keeping `Viewer` out
of save and delete.

The existing suite runs before and after, per the repository's standing rule, and
the login, role redirect, family create and update, and audit trail flows get
smoke tested. The calendar page and the dashboard card are verified with
Playwright at desktop and 390px against a running dev server.

## Out of scope

- Category and service eligibility filters.
- A venue reference table and its admin page.
- Lifting the one open batch limit.
- Week, day and agenda calendar views.
- A `spark` command for the reconciler. The method is public and takes no
  arguments, so adding one later is a few lines if the dashboard's lag between
  page loads ever becomes a real complaint.
