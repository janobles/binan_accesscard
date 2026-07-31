# Import Review Refinement

Design for rebuilding the family-import review screen (step 2 of the import
wizard) as a paginated table with explicit per-row Apply, a path to resolve
every violation the file contains, and a cost per fix that does not scale with
file size.

Branch base: `feat/nav-taxonomy-url-space`.

## Why

Three problems with the screen as it stands.

**Violations with no resolution path.** The review table renders four value
columns (`import-review.js`: lastname, firstname, birthday, sex). An error on
any of the other fourteen importer fields (barangay, contact number, monthly
income, services, address, relationship, QR number, and the rest) still builds
a `cells` entry in `ImportReviewPresenter::people()`, but there is no column to
render it into. The row turns red and nothing editable appears, so Confirm
import stays disabled with no way to reach the problem.

This covers the structural problems too. `HEAD-NONE` and `HEAD-MULTI` are
recorded against `relationship` and `FP-ADDR` against `address`
(`FamilyExcelImporter::addError()` at lines 742, 744, 914), so they already
produce an editable cell; they are invisible for exactly the same reason
barangay is. Widening the fix surface to every errored field is therefore the
whole fix, with no special-casing.

The codes that really carry `field === null` are `DUP-DB`, `ADD-MEMBER`,
`DUP-EXISTS`, `DUP-DIFF`, `DUP-PERSON` and `QR-CONTIG`. All are informational:
they report what the import will do with a row (skip it, append it) rather than
something to correct, so having no editor is right. They surface as text in the
row's Issues list. `QR-11` names `familyno` but carries no sheet row, so it
stays a file-level notice.

**Edits apply themselves.** `saveCell()` posts on every `change` event. There
is no chance to correct a mistyped fix before it is staged.

**It is slow on a real file.** Each of those automatic saves revalidates the
whole batch, re-encodes ~7MB of staging JSON, returns the entire report, and
repaints every row. The page itself ships all people as a JSON island in the
HTML, and there is no pager, so a 10k-row file renders 10k rows.

Reference file for all measurement: `excel/family-import-D-10k-800-errors.xlsx`
(10,000 records, ~800 errors and warnings).

## What this is not

Out of scope: bulk remove, the ready-to-import list, any change to the
importer's validation rules, any schema change.

## Architecture

Three units with clean edges.

### `ImportReviewQuery` (new)

A value object holding the review table's query: `page`, `per` (25/50/100),
`severity` (`all` | `problems` | `blocking` | `warning`), `code` (a single
issue code, or empty), and `q` (free text matched against family label, person
name, and QR). Built from the request in the controller so the presenter stays
pure, with no request or session access.

### `ImportReviewPresenter` (reshaped)

`build()` keeps producing only the summary the page needs on load: `file`,
`counts`, `fileNotices`, and the list of issue codes actually present in the
file (to populate the filter dropdown). It no longer builds `people`,
`families`, `ready`, or `unassigned`.

`page(array $result, ImportReviewQuery $query): array` is new and returns one
slice: `{rows, total, filtered, page, per}`. Each row carries the identity
columns, its severity, the distinct issue labels for its Issues column, and the
fields the expanded panel will offer.

`familiesToFix()`, `unassignedRows()`, and `readyFamilies()` are deleted. They
serve the retired grouped-card report and nothing else.

Row shape:

```
{
  sheetRow: int,
  qr: string,
  family: string,          // head's last name, or "QR <n>"
  role: string,
  values: {lastname, firstname, birthday, sex},
  severity: ''|'warning'|'blocking',
  issues: [{code, label, severity, message}],   // every distinct problem
  fields: [{field, label, cell, value, severity, message}]
}
```

`fields` is what the expanded panel renders: every field on this row carrying
an error, whatever column it belongs to, one entry per field with a blocking
error beating a warning on the same field. That single rule is the whole fix.
Because `HEAD-NONE` is recorded against `relationship` and `FP-ADDR` against
`address`, a file missing a Head is corrected by setting one person's
Relationship to Head rather than by re-uploading, and no structural
special-casing is needed.

`issues` lists every distinct problem on the row including the informational,
field-less ones, so a row that will be skipped or appended says so even though
it offers nothing to edit.

### `FamilyImportController`

Two new endpoints, both JSON:

- `GET records/import/review/(:num)/rows` - parses an `ImportReviewQuery` from
  the query string, loads the staged bundle, returns
  `ImportReviewPresenter::page()`.
- `POST records/import/review/(:num)/apply` - applies one person's edits.

Deleted, together with their routes: `reviewCellSave`, `reviewFamilyModal`,
`reviewFamilySave`, `reviewFamilyRemove`, the `ImportFamilyModalBuilder`
library, and the `.js-import-fix-edit` registration in
`manage-family-modal.js`. This closes the open cleanup item in
`docs/knowledge/violations.md`, whose stated blocker was exactly the decision
made here: structural problems get a surface on the review screen.

`reviewCommit` and `reviewCancel` are unchanged.

## The screen

Bootstrap 5, following Manage Records as the design source of truth and the
repo's toolbar standard.

Toolbar above the card: page search on the left with a leading icon
(placeholder `Search this import...`), entries-per-page select on the right.

Filter row below it, `nav-pills .segmented-tabs`: `All` (default, no pill) ·
`Problems` · `Must fix` · `Warnings`, each showing its count. Beside the
search, a dropdown narrows to one issue code, listing only codes present in the
file.

Table columns: status icon · Family · Role · Last Name · First Name · Issues ·
chevron. Issues shows one badge per distinct problem label, red for blocking
and amber for warning. Rows keep the existing `table-danger` /
`table-warning` tint.

Clicking a flagged row expands a detail `<tr>` beneath it. Inside: a short
list of the row's issues (including the informational ones that offer no
editor), then a `row g-2` of the row's `fields` as text inputs or `<select>`s
drawn from `fieldOptions`, the same controls the inline cells use today. Panel footer carries `Apply` (primary) and `Discard`
(link). Nothing posts until Apply. Discard collapses the panel and drops the
typing.

Standard Bootstrap pager under the table with `Showing X to Y of Z people`.

At 390px the table scrolls inside its own `overflow-x: auto` container and the
expanded panel stacks one field per line.

The page HTML carries the summary only. `import-review.js` fetches page 1 on
load. There is no JSON island of people.

## Apply

`POST .../apply` takes `{import_row, fields[<field>] => <value>}` for one
person. Every field name is checked against `ImportReviewPresenter::FIELD_LABELS`
before anything is written; unknown names are a 422, not a silent skip. The
handler patches that staged row, revalidates, re-stages, appends to
`ImportReviewChangeLog`, and returns `{row, counts, codes, refresh, csrf}` -
the reshaped edited row and fresh totals, never the whole report.

The client splices the returned row into the table in place and repaints the
counts and the filter pills.

One exception. If the applied fields include `familyno`, `relationship`,
`address`, or `barangay`, other rows' severity can change (those four drive the
cross-row rules), so the response sets `refresh: true` and the client refetches
the current page instead of splicing. Ordinary fixes stay cheap; the four
fields that can move other rows pay for a refetch.

Confirm import is unchanged: it revalidates, refuses on any blocking count, and
stays disabled while `counts.blocking > 0`.

## Performance

The cost of one Apply today, in order of weight:

1. `existingHeadsForRows()` - collects every valid QR in the file, then
   `existingControlNos()`, a chunked `whereIn` over `qr_control`, and
   `identitiesForHeads()`.
2. `existingPeopleForRows()` - collects every distinct lastname in the file and
   runs `activePeopleByLastname()` across all of them, then `controlsForHeads()`.
   On a 10k file against a populated member table this is the heaviest query
   the app issues.
3. `validateAndBuild()` over all rows - pure in-memory array work, no I/O.
4. `json_encode` of the ~7MB bundle and the write, plus the `json_decode` on
   the next load.

Steps 1, 2 and 4 dominate. Step 3, the rule engine itself, is the cheap part.
Fixing 800 problems means 800 repetitions of all four.

Paging and the row-scoped Apply response remove the largest cost outright: the
whole report no longer crosses the wire and the whole table no longer rebuilds
per fix. Two changes address the rest.

**Memoize the existing-record lookups.** `existingHeads` and `existingPeople`
are derived purely from the set of QRs and lastnames present in the staged
rows. They can only go stale when an edit changes a `familyno` or a `lastname`.
Compute them once when the file is staged, keep them in a sidecar staging file
alongside the bundle, and reuse them on Apply unless the applied fields include
those two, in which case rebuild and rewrite the sidecar. Invalidation is a
two-field check, and a test pins it: editing a lastname must produce the same
report as a cold revalidate.

**Split the staging file.** Store rows and errors as separate files under
`writable/import-staging/` so an Apply rewrites only what changed rather than
re-encoding the whole bundle. `ImportStagingStore` grows `saveRows()` /
`saveErrors()` / `loadRows()` / `loadErrors()`; `sweep()` and the TTL cover both
files by the same `job-<id>` prefix, and both still live under `writable/`
because they hold PII.

After both, an Apply is one in-memory validation pass plus one rows-file write.

**Budget, measured against the 10k reference file:** a rows page under 500ms,
an Apply under 1.5s.

Measured on the developer machine (`Theas-Air-2`, Intel Core i5-5350U @
1.80GHz, macOS 21.6.0, `php spark serve` + XAMPP MySQL on localhost), against
job 5, the 10,000-row reference file (479 must-fix, 319 warnings), via the
browser's own Resource Timing (`performance.getEntriesByType('resource')`)
for the rows page load and via timed `fetch()` calls from the page (matching
what DevTools' Network panel reports) for the rest:

- Rows, page 1: 189-202ms across repeated requests (one first-load sample hit
  485.5ms, load ordered before every other asset on the page; steady-state
  requests are the representative number).
- Rows, `severity=blocking`: 197-247ms.
- Rows, `page=200` (the worst case, filter walks every row before slicing):
  208-336ms.
- Apply, `sex` (non-invalidating, cache reused): 758.9ms, 1114.8ms, 1144.8ms
  across three rows (avg ~1006ms).
- Apply, `lastname` (invalidating, cache rebuilt): 858.5ms, 868.8ms, 884.5ms,
  912.6ms across four rows (avg ~881ms). The 10k reference file has no row
  flagged with a lastname error, so this was not driven through a "Fix this
  person" click; it was sent as the same `multipart/form-data` POST
  (`import_row` + `fields[lastname]`) the UI's own Apply button sends,
  confirmed against a real UI-driven Apply's request body, changing an
  unflagged row's lastname. That still exercises the code path this number
  is meant to evidence - `existingPeopleForRows()` rebuilding the memoized
  cache on a lastname edit - so the number stands as evidence for that path,
  just not as an operator-driven repro.

Both budgets hold: every rows sample is under 500ms and every Apply sample is
under 1.5s, including the invalidating case. The cache-rebuild path is not
measurably slower than the cache-reused path in this sample; on this machine,
both are dominated by the same per-request overhead (PHP process start,
MySQL round trips through XAMPP) rather than by the memoized-versus-rebuilt
lookup itself. Since the budget was not missed, the spec's trigger for
revisiting scoped revalidation was not reached and that option stays
rejected.

## Rejected: scoped revalidation

The obvious next optimization is to stop revalidating the whole batch on Apply
and rerun only the affected QR group plus the global uniqueness checks. It is
rejected, not deferred.

Revalidating everything is correct by construction. Scoping it is a
cache-invalidation problem, and these rules genuinely reach across the file:
duplicate-person detection compares every row against every other, QR
contiguity is positional, head uniqueness is per group. A wrong scope does not
make the review slow, it makes the review pass a file the write step then
mangles - a data-integrity bug in the one feature whose purpose is data
integrity.

The payoff does not justify that. Once the lookups are memoized and the staging
file is split, what remains per Apply is a single in-memory pass with no I/O,
against a human typing between applies in a batch admin flow. There is little
left to win.

If an operator reports real slowness, that is a fresh issue with the measured
numbers attached, which is a better trigger than a guess filed in advance. It
does not belong in `docs/knowledge/violations.md`: that list tracks verified
code mess, and every line of it earns its place by being a confirmed defect.

## Error handling

Both JSON endpoints return an HTTP status with the message:

- 403 - no import permission.
- 404 - the staging file is gone (swept after its 24h TTL, or the job already
  committed). The client shows the message and stops offering Apply, since
  nothing can be saved.
- 422 - unknown field, unknown sheet row, or a malformed query.
- 500 - restage failure, audited through `auditSystemError()`.

The CSRF hash rotates on every JSON response, as the current endpoints already
do.

## Testing

`ImportReviewPresenterTest`:

- `page()` honours page, per-page, severity filter, code filter, and search.
- A row's `issues` lists every distinct problem, including ones on fields the
  table does not display.
- A row's `fields` includes every errored field whatever its column.
- `HEAD-NONE` yields an editable `relationship` field and `FP-ADDR` an
  editable `address` field, so both are fixable in place.
- A row whose only problems are field-less (`DUP-EXISTS`, `ADD-MEMBER`) lists
  them under `issues` and offers no editable field.

`FamilyImportControllerTest`:

- Apply patches only allowlisted fields; an unknown field is a 422 and writes
  nothing.
- Apply clears the fixed flag and updates counts.
- Applying `familyno` / `relationship` / `address` / `barangay` sets
  `refresh: true`; applying any other field does not.
- Missing staging returns 404 on both `rows` and `apply`.
- A memoized lookup reused after a non-invalidating edit produces the same
  report as a cold revalidate.

Manual, with `excel/family-import-D-10k-800-errors.xlsx`: upload, page through,
filter to Must fix, fix a sex, a barangay, and a missing Head, then Confirm.
Playwright snapshots at desktop and 390px against Manage Records.

## Violations closed

Both items are in `docs/knowledge/violations.md` under the
`feat/nav-taxonomy-url-space` heading:

- The UX/needs-decision item on the review screen losing paging, per-list
  search, and severity/code filters. Restored here. Bulk remove and the
  ready-to-import list stay out of scope and stay noted.
- The cleanup item on the unlinked `reviewFamilyModal` / `reviewFamilySave` /
  `reviewFamilyRemove` endpoints, their routes, `ImportFamilyModalBuilder`, and
  the `manage-family-modal.js` hook. The decision that item waited on is made
  above, so they are deleted.
