# Temporary aid patching

Written 2026-08-14. Nothing here is built yet.

Distribution started before the household records existed. The scanner has been
running in a temporary logging mode that records the control number printed on a
card and nothing else, because there is no family in the database to attach it
to. When the consolidated household spreadsheet is finally imported, every one of
those logged scans has to become a real handout attached to a real family.

That conversion is what this document calls **patching**.

## Why the system is in this state

The city handed out pre-printed access cards during a barangay survey. Each card
carries a control number. The survey collected the household data on paper and in
separate barangay spreadsheets, which are still being consolidated into the one
workbook the import expects (`docs/12-import.md`).

So the cards are already in people's hands, and distributions are already
happening, while the `member` table is still empty of the families those cards
belong to. The scanner cannot write a normal handout row, because
`subsidy_distribution` requires a `memberID` and a `control_no` that resolves
through `qr_control` to a family head. Neither exists yet.

The `temporary-aid` branch is the interim answer: log the control number against
the open batch and resolve it later. It is deliberately a stopgap, and it is the
input to this work.

## What temporary mode records today

The branch adds one table, `temp_aid_distribution`, and one model,
`app/Models/Scanner/TempAidDistributionModel.php`:

| Column | Meaning |
|---|---|
| `temp_aidID` | Row id |
| `control_no` | The number scanned off the paper card |
| `aid_type_id` | The subsidy type bound to the open batch |
| `claim_date` | The day of the scan |
| `batch_id` | The batch that was open |
| `dt_created` | Insert timestamp |

A unique index on `(batch_id, control_no)` is what stops a family claiming twice
in the same batch. That index is the only integrity the temporary log has.

Four properties of this table matter for the patch, and each one is a decision
that has to be made explicitly later:

**No operator.** There is no `userID` column. A temporary scan does not record who
scanned it, so per-scanner performance figures during this period come from the
batch, not from the row.

**A void is a delete.** `voidInBatch()` removes the row. There is no `dt_voided`
and no history, so a mistaken scan that was voided leaves no trace at all. The
real `subsidy_distribution` table voids by stamping `dt_voided` and keeps the row.

**No validation against anything.** A control number is accepted because it parses
as a positive integer. Nothing checks that the number was ever printed, that it
belongs to a household in this barangay, or that it is inside the range the city
issued. A card from another barangay, a typo, and a genuine scan are
indistinguishable in the log.

**Coverage is a fiction.** `TempAidDistributionModel::summary()` reports every
logged scan as received and nothing as waiting, because there is no roster to be
waiting against. Reports during this period show 100 percent coverage by
construction. Anyone reading those numbers later needs to know that.

## What a real handout looks like

After the import, a scan writes one `subsidy_distribution` row:

```
distribution_id, control_no, memberID, subsidy_type_id,
claim_date, userID, batch_id, dt_created, dt_voided
```

with foreign keys to `qr_control`, `member`, `subsidy`, `distribution_batch`, and
`users`. The link from a card to a family runs `control_no` to `qr_control.headID`
to `member.memberID`. The import creates those `qr_control` rows: each family row
in the spreadsheet carries its printed QR number, and `QrControlModel::assign()`
writes the mapping when the family is written.

That is the whole basis of the patch. **The import is what makes a scanned control
number resolvable.** Before it, the number means nothing to the database. After
it, it means one family.

## The gap, field by field

| `subsidy_distribution` column | Where it comes from | Risk |
|---|---|---|
| `control_no` | `temp_aid_distribution.control_no` | May not exist in `qr_control` after the import |
| `memberID` | `qr_control.headID` for that control number | Same |
| `subsidy_type_id` | `temp_aid_distribution.aid_type_id` | Column was renamed in v19; the branch predates it |
| `claim_date` | Straight copy | None |
| `batch_id` | Straight copy | Batch must still exist and must not have been reused |
| `userID` | Not recorded | Has to be NULL or a stand-in |
| `dt_created` | Straight copy | Keeps the real scan time, not the patch time |
| `dt_voided` | Not recorded | Voided scans were deleted; nothing to carry |

Two of those rows are the actual work. Everything else is a copy.

## Where the temporary log comes from

The scans are not sitting in the database the patch will run against. The laptop
that travels to the venue holds them, and the log gets to the office as a copy:
either a full database dump or an export of `temp_aid_distribution` on its own.
That copy is the source of truth for the reconciliation, and it arrives before or
after the family import with no fixed order between them.

This changes the shape of the problem in four ways, and each is a requirement in
its own right rather than a detail of how the file gets moved.

**The export is a snapshot, and there will be more than one.** Distributions keep
running while the spreadsheet is being consolidated. A second export overlaps the
first. Loading it must add only the scans the office does not already hold, which
means a scan needs an identity that survives the copy. `temp_aidID` is an
auto-increment on the venue laptop and means nothing once the row is somewhere
else, so the identity has to be `(batch_id, control_no)`, or `(control_no,
claim_date)` if batch ids turn out not to survive the copy either.

**Ids are only as portable as the tables behind them.** `batch_id` and
`aid_type_id` are local to the machine that generated them, and nothing in the
schema catches a scan that lands on the wrong batch after loading. In practice
this is small: the server has run exactly one batch, so there is one id to place
and it can be confirmed by name, venue, and dates rather than trusted. Treat the
mapping as something an operator states, not something the load infers, and it
stays small even if a second batch appears.

**A void performed after an export is invisible.** A scan voided at the venue is
deleted there. If it was already exported, the office holds a row the venue no
longer has, and a later export cannot express the deletion. Either exports are
never partial, or the reconciliation has to handle a row that should not be
patched and has no marker saying so.

**Copying the whole database is not the same as copying the table.** A full dump
from the venue overwrites the family records the office just imported, which is
the opposite of what this work needs. If the transfer is a full dump, only
`temp_aid_distribution` is restored from it, into an office database that stays
authoritative for everything else. That direction has to be written down and
followed, because getting it backwards destroys the import.

## What the live deployment already showed

The temporary branch has been running on the server through a real distribution
at the Alonte Sports Arena, scheduled 8am to 4pm. Two things came out of it that
were not visible from the code.

### A batch is not one continuous span

The distribution ran 14 to 16 July, stopped, and resumed on 14 August. One
event, one subsidy type, one venue, one roster of families, with a month of
nothing in the middle.

`distribution_batch` cannot express that. It holds `scheduled_start` and
`scheduled_end` and treats everything between them as the batch. Recording this
event as 14 July to 14 August makes every day in that month a scanning day, makes
the batch appear open in the calendar for a month, and blocks any other batch
from being plotted across a span that is mostly empty, because
`DistributionBatchModel::overlapping()` refuses overlapping spans. Recording it as
two batches splits one event's handouts into two rosters and two completion
percentages, and a family who claimed in July would count as not yet served in
August.

So the schedule needs to become a set of dates a batch runs on, not a single
range: several ranges, or explicit occurrence rows, with the daily times still
applying to each. `BatchScheduleWindow` already separates the two ideas the right
way, dates gate and times advise (`docs/15-distribution.md`), which is what makes
this a change to what a date span is rather than a rewrite of the verdict logic.

This is a schema and scheduling change, not a patching change, and it may well
deserve a design of its own. It sits here because the patch cannot finish without
it: the scans it converts belong to a batch whose real shape the current schema
cannot hold, and a completion percentage computed against the wrong span is wrong
in a way nobody will notice.

### Staff start before the posted time

The logs show scans before 8am. The schedule was posted as 8 to 4, and the queue
formed earlier. This is the behaviour the dates-gate rule was designed for and it
worked, but it is worth stating plainly for anyone reading the patched data
later: a claim timestamped 7:40 is not an error, and `daily_start_time` describes
the plan rather than the day.

## Goals

1. Every temporary scan that resolves to an imported family becomes a real
   `subsidy_distribution` row, with its original claim date and batch intact.
2. Every temporary scan that does not resolve is reported, individually, with
   enough context for a person to chase it. It is never silently dropped and
   never guessed at.
3. The patch can be run, inspected, and run again without duplicating anything.
4. After a successful patch, distribution reporting for the temporary period
   reads the same as any other period, and the temporary table stops being a
   source anything reads.

## Non-goals

- Reconstructing voided scans. They were deleted and the information is gone.
- Reconstructing who scanned. It was never recorded.
- Retroactively enforcing eligibility. A family that received something did
  receive it, whether or not the roster built later would have listed them.
- Editing the spreadsheet on the system's behalf. Corrections happen in the
  workbook and come back through the import, the same rule the import review
  screen already holds to.

## Requirements

### R1. A dry run before anything is written

The patch reports before it acts: how many temporary scans exist, how many
resolve to a family, how many do not, and why each unresolved one failed. Nothing
is written until that report is accepted. The import review screen is the model
to follow, including naming the specific unresolved control number rather than
counting it.

### R2. Matching is exact, and exact only

A temporary scan matches a family when its `control_no` has a row in
`qr_control`. There is no fuzzy matching, no nearest-number, no name matching.
A single mistyped digit is another family's card, which is exactly the reasoning
that makes `QR-TAKEN` a blocking import error rather than a warning.

### R3. Idempotent, and safe to interrupt

Running the patch twice produces the same result as running it once. A patch that
fails halfway leaves a state the next run can continue from. The natural key is
`(batch_id, control_no)`, the same pair the temporary table already holds unique.
Note that `subsidy_distribution` has no such unique constraint, so this cannot be
left to the database as it stands.

### R4. Every patched row writes an audit trail

Hard rule 3 has no exception for bulk or internal paths. What is still open is the
granularity: one audit row per patched handout, or one per patch run recording its
counts. A run of several thousand rows makes that a real choice, not a formality,
and it should be decided on what a person auditing this period would need to see.

### R5. Unresolved scans survive the patch

An unresolved scan is not deleted. It stays queryable, with its reason, so that a
family who turns up later in a corrected spreadsheet can be patched in a second
run. The likely reasons, each of which needs its own handling decision:

| Case | What it means |
|---|---|
| Control number absent from `qr_control` | The household was never in the spreadsheet, or was blocked at import |
| Control number present, head inactive | The family exists but is marked inactive |
| Batch missing or altered | The batch row was deleted or reused since the scan |
| Subsidy type missing | The type bound to the batch was deleted from reference data |
| Duplicate in a batch that already has a real row | The same family has both a temporary and a real handout in one batch |

### R6. The reporting seam is explicit

Before the patch, coverage during the temporary period is 100 percent by
construction. After the patch, it is real: patched handouts over the batch's
eligibility roster. Those two numbers will differ, sometimes a lot, and the
difference is not a bug. Whether the eligibility roster is even built for a batch
that ran before any family existed is an open question below.

### R7. Loading an export is its own step, separate from patching

Getting the venue's scans into the office database and turning them into handouts
are two operations, and they run at different times against different inputs. The
load reports what it added, what it already had, and anything it could not place,
before the patch is considered. Keeping them separate is what makes a second
export safe: it can be loaded and inspected without touching a single
`subsidy_distribution` row.

## Prerequisites

**The consolidated spreadsheet is imported.** Nothing in this work can start
before it, and the quality of the match depends entirely on the QR numbers in that
file being the numbers actually printed on the cards.

**The venue's temporary log is in the office database.** The export has been
copied across and loaded, and someone has confirmed that its batch ids and
subsidy type ids mean what they say in the database receiving them.

**The `temporary-aid` branch is reconciled with main.** This is the largest piece
of work in the whole exercise, and it is easy to underestimate because the branch
itself is small. It was cut on 13 July 2026 against `accesscardV18.sql`. Main has
moved 419 commits since, through the v19 rename of aid to subsidy, the v20
eligibility roster, the v21 batch schedule, and the v22 normalisation that split
addresses, moved sectors into a junction table, and switched name storage to
uppercase. The branch's `aid_type_id` column, its `AidStatsModel` references, and
its scan controller are all pre-rename.

Two consequences follow, and the second is the one that bites:

The branch is a rebase and a rename, not a redesign. Its three commits touch a
scan controller, a model, a view, and a patch file, and every one of those has a
current equivalent on main to rebase onto.

**The server is running the old code against old data.** Whatever is in the
production database was written by a July build, and the office database it has to
reconcile with is four schema versions ahead. The temporary table itself is
simple enough to survive that, being six columns of integers and dates, but
anything it references is not. Confirm the shape of what actually came off the
server before writing any loader against a remembered schema.

**Schema changes are patch files.** A new column, if the design needs one, is
`sql/patches/vNN-*.sql` folded into a new dump. Never a migration
(`docs/02-database.md`).

## Open questions

These are the ones a brainstorming session has to settle. They are listed in the
order they block each other.

1. **How does a batch express dates it does not run on?** Several date ranges on
   one batch, or a row per day, or a range plus exclusions. This decides the
   schema change, what the calendar draws, what `overlapping()` compares, and
   what the completion percentage divides by. It is the first thing to settle
   because the Alonte batch cannot be recorded truthfully until it is.

2. **Which batch does each scan belong to, and who says so?** There is one batch
   on the server, so this is a single confirmed mapping rather than a matching
   problem. The question is only whether the operator states it during the load
   or whether the load claims to work it out.

3. **What is the transfer, exactly?** A `mysqldump` of the single table, a CSV, or
   a full database dump that the office restores selectively. This decides what
   the load step reads and how it is verified on arrival.

4. **Is patching a one-time operation or an ongoing tool?** The spreadsheet will
   arrive in pieces and be corrected after the first import. If corrections keep
   coming, the patch is a page someone opens repeatedly, not a script run once.
   Everything below depends on this answer.

5. **Who runs it, and from where?** A `spark` command run by a developer, or an
   admin page. A page means access control, a review screen, and progress on a
   run that may take minutes. A command means a developer is present for every
   correction, forever.

6. **Does it go through the background worker?** The import already does, for the
   reason that a large workbook does not fit in a web request
   (`docs/05-background-worker.md`). A patch over thousands of rows has the same
   shape.

7. **What is `userID` on a patched row?** NULL is honest and matches the existing
   handling of the developer-account scan path, which already stores NULL. A
   dedicated system account is more explicit but adds a real row to `users` that
   can log in unless it is prevented from doing so.

8. **What happens to `temp_aid_distribution` after a clean run?** Kept as history,
   kept with a patched marker per row, or dropped in the dump version that
   completes the work. It cannot simply be dropped if unresolved rows are still
   sitting in it.

9. **Are eligibility rosters built retroactively for temporary-period batches?**
   Without a roster there is no denominator and the completion percentage for
   those batches stays meaningless. With one, it is built from a roster that
   nobody approved at the time, against a population that did not exist when the
   batch ran. `EligibilityBuilder` freezes the roster once per batch on purpose
   (`docs/15-distribution.md`), so this is a deliberate exception either way.

10. **How is a control number that was never issued told apart from one whose
   household is simply missing?** If the city has the list of printed number
   ranges, an out-of-range scan is a different problem from a household that has
   not been encoded, and it should read differently in the report. If there is no
   such list, they are indistinguishable and the report should not pretend
   otherwise.

11. **What is the answer when a batch has both a temporary and a real handout for
   the same family?** This happens if any batch straddled the import. Skip and
   report is the conservative answer; it needs confirming that it is the right
   one.

## Success criteria

The work is done when all of these are true:

- A dry run over the real temporary log produces a report the CSWD accepts.
- Running the patch converts every resolvable scan, and running it again converts
  nothing and reports no change.
- Total handouts for a temporary-period batch, read from the distribution pages,
  equal the temporary log's count for that batch minus the reported unresolved
  scans.
- No `subsidy_distribution` row exists twice for the same `(batch_id,
  control_no)`.
- The audit trail shows the patch happened, in whatever granularity R4 settles
  on.
- The unresolved list is visible to someone who can act on it, and shrinks as
  corrected households are imported.
