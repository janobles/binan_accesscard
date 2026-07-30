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
render it into. The row turns red and nothing editable appears. Structural
errors (`HEAD-NONE`, `HEAD-MULTI`, `FP-ADDR`, `QR-11`) carry `field === null`
and so produce no cell at all. The per-family Edit modal that used to serve
them is no longer linked from any page. Confirm import stays disabled with no
way to reach the problem.

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
  fields: [{field, label, cell, value, severity, message, structural}]
}
```

`fields` is what the expanded panel renders. It contains every field carrying
an error, whatever column it belongs to. When the row has a structural problem
it also contains the fields that cause that class of problem, even when those
fields carry no error of their own, flagged `structural: true`:

- `HEAD-NONE` / `HEAD-MULTI` offer `relationship`
- `FP-ADDR` offer `address` and `barangay`
- `QR-11` / QR-grouping problems offer `familyno`

That is what gives every blocking problem an in-app fix. A file missing a Head
is corrected by setting one person's Relationship to Head, not by re-uploading.

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
notice for each structural problem, then a `row g-2` of the row's `fields` as
text inputs or `<select>`s drawn from `fieldOptions`, the same controls the
inline cells use today. Panel footer carries `Apply` (primary) and `Discard`
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
an Apply under 1.5s. Measurement is a numbered step in the plan, run before and
after, with the numbers recorded.

The measured numbers go into this section once the plan's measurement step has
run, so a later reader sees what the file actually costs rather than what it was
predicted to cost.

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
- A structural problem contributes its causing fields, marked `structural`.

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
