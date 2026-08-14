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

## Prerequisites

**The consolidated spreadsheet is imported.** Nothing in this work can start
before it, and the quality of the match depends entirely on the QR numbers in that
file being the numbers actually printed on the cards.

**The `temporary-aid` branch is brought up to the current schema.** It was cut
against `accesscardV18.sql`, before the v19 rename of aid to subsidy, before the
v20 eligibility roster, the v21 batch schedule, and the v22 normalisation. Its
`aid_type_id` column and its `AidStatsModel` references are pre-rename names. This
is a rebase and a rename, not a redesign, but it has to happen before the branch
can merge at all.

**Schema changes are patch files.** A new column, if the design needs one, is
`sql/patches/vNN-*.sql` folded into a new dump. Never a migration
(`docs/02-database.md`).

## Open questions

These are the ones a brainstorming session has to settle. They are listed in the
order they block each other.

1. **Is patching a one-time operation or an ongoing tool?** The spreadsheet will
   arrive in pieces and be corrected after the first import. If corrections keep
   coming, the patch is a page someone opens repeatedly, not a script run once.
   Everything below depends on this answer.

2. **Who runs it, and from where?** A `spark` command run by a developer, or an
   admin page. A page means access control, a review screen, and progress on a
   run that may take minutes. A command means a developer is present for every
   correction, forever.

3. **Does it go through the background worker?** The import already does, for the
   reason that a large workbook does not fit in a web request
   (`docs/05-background-worker.md`). A patch over thousands of rows has the same
   shape.

4. **What is `userID` on a patched row?** NULL is honest and matches the existing
   handling of the developer-account scan path, which already stores NULL. A
   dedicated system account is more explicit but adds a real row to `users` that
   can log in unless it is prevented from doing so.

5. **What happens to `temp_aid_distribution` after a clean run?** Kept as history,
   kept with a patched marker per row, or dropped in the dump version that
   completes the work. It cannot simply be dropped if unresolved rows are still
   sitting in it.

6. **Are eligibility rosters built retroactively for temporary-period batches?**
   Without a roster there is no denominator and the completion percentage for
   those batches stays meaningless. With one, it is built from a roster that
   nobody approved at the time, against a population that did not exist when the
   batch ran. `EligibilityBuilder` freezes the roster once per batch on purpose
   (`docs/15-distribution.md`), so this is a deliberate exception either way.

7. **How is a control number that was never issued told apart from one whose
   household is simply missing?** If the city has the list of printed number
   ranges, an out-of-range scan is a different problem from a household that has
   not been encoded, and it should read differently in the report. If there is no
   such list, they are indistinguishable and the report should not pretend
   otherwise.

8. **What is the answer when a batch has both a temporary and a real handout for
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
