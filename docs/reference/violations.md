# Violations Punch-List

Canonical punch-list for code-mess items (dead code, non-conforming views,
redundant helpers, boundary leaks). GitHub issues track QA/feature work, not
code mess - this file is the single home to avoid drifting lists.

Maintenance: cleanup PRs tick items `[x]` + `*(Fixed: <PR/commit>)*`. New
violations spotted mid-task get appended immediately, verified first.

Seeded 2026-07-06 from an audit pass. Closed issue #7 was mined; its only
unchecked item was already moved to issue #9 (UX decision, not code mess).

## Findings

- [x] 🟠 Major: `app/Controllers/Families/FamilyController.php:1` - 1723-line
  controller mixes family CRUD, Excel import, QR control-number handling, and
  modal partial rendering. Candidates for extraction into libraries per the
  controllers-decide/libraries-build boundary (see
  `docs/01-architecture.md`).
  *(Fixed: split into FamilyController (~1000 lines, CRUD) +
  FamilyImportController + FamilyDataTableController + FamilyRequestContext
  trait, with FamilyDataTablePresenter and FamilyModalDataBuilder libraries -
  a8edb59, b11cbe7, 6f8562c, f9d7df7, refactor/mvc-cleanup)*
- [x] 🟡 Minor: `app/Views/Family/list.php` (filter dropdowns; markup since moved
  to `app/Views/Family/list-body.php`) - inline `style="max-height: 14rem;"`
  on dropdown menu (also line 69). Move to a page-CSS rule in
  `public/css/managerecord.css` or a utility class.
  *(Fixed: `.family-filter-field .dropdown-menu` rule in managerecord.css - 05556ae)*
- [x] 🟡 Minor: `app/Views/Accounts/account-form-modal.php:44` - inline
  `style="border:0;background:transparent;padding:0 0 0.5rem;"` on header;
  belongs in `public/css/accounts.css` next to the other
  `.account-card-header` rules.
  *(Fixed: `.edit-account-modal > .account-card-header` rule in accounts.css - 05556ae)*
- [x] ⚪ Cleanup: `app/Controllers/Families/FamilyController.php:824` -
  `shapeExistingMembers()` is defined but never called anywhere in the repo
  (verified by grep). Dead code; remove.
  *(Fixed: removed; splitAddressBarangay also moved to MemberFieldNormalizer - 65173fd)*
- [x] 🔵 Needs-decision: `app/Libraries/DashboardPageBuilder.php:1` - CLAUDE.md
  says "respect existing strict-type conventions" but **zero** files under
  `app/` declare `declare(strict_types=1)` (typed signatures are used, the
  declare is not). Decide: adopt the declare repo-wide (one mechanical PR) or
  reword the convention to "typed signatures, no strict_types declare".
  `php-practices/idioms.md` documents current reality.
  *(Fixed: reworded CLAUDE.md convention to typed-signatures-only, refactor/mvc-cleanup)*
- [x] 🟠 Major: `app/Views/components/dashboard_sidebar.php:1` - sidebar exists
  three times: this component (used only by `Admin/layout.php:83`, all links
  hardcoded to `admin/*` despite the "role-aware" doc comment) plus hand-rolled
  inline copies in `Employee/layout.php:41` and `Viewer/layout.php:48`. Also
  accepts a dead `$sidebarUserUrl` prop. Fix: one genuinely role-aware sidebar
  partial (nav items + route base per role) consumed by all three layouts, then
  relocate to `Partials/` per the views-bootstrap.md taxonomy (it is a page
  fragment, not a props-only component). Cross-role regression surface; own
  branch.
  *(Fixed: one `app/Views/layout.php`, sidebar rendered from
  `Config\Navigation::linksFor($role)`; the three role layouts and the two
  role-parallel dashboard controllers are deleted - feat/nav-taxonomy-url-space.
  It stayed under `components/` because the manifest makes it props-only again.)*
- [x] 🟠 Major: `app/Controllers/Families/FamilyController.php:862` - when a
  member posted no sector checkbox, `memberPayloadFromArray()` silently copied
  the head's sectors, contradicting the per-person sector controls and allowing
  an age-ineligible classification to be stored on the member.
  *(Fixed: members without a sector selection now persist an empty selection;
  age eligibility validation change.)*

- [x] 🟡 Minor: `app/Views/Scanner/scan.php:52,55`: inline `style="..."` on the
  QR image and the headline, which is what fails `ScanViewTest::testNoInlineStyles`
  (red on `main` since before feat/family-member-rows, verified by stashing the
  branch). Move both to `public/css/scanner-scan.css`, which the scanner manifest
  already loads.
  *(Fixed: the view carries no `style=` attribute any more and the test passes;
  the `--filter` that excluded it from CI is deleted - chore/backlog-cleanup.)*

## Appended from feat/nav-taxonomy-url-space (2026-07-30)

- [x] ⚪ Cleanup: `app/Controllers/Families/FamilyImportController.php` -
  `reviewFamilyModal`, `reviewFamilySave`, and `reviewFamilyRemove` (with their
  `records/import/review/{id}/family*` routes, `ImportFamilyModalBuilder`, and the
  `.js-import-fix-edit` registration in `manage-family-modal.js:2207`) are no
  longer linked from any page: the per-family Edit/Remove cards they served were
  replaced by the per-person review table, which fixes a bad value in its own
  cell. The routes are still registered and still answer a direct request behind
  their `roleNav:records-import` filter. Left in place
  because the design spec calls the import backend unchanged, and because a
  structural problem (no head, two addresses under one QR) has no single cell to
  edit, so a decision is owed on whether that case gets a surface here or is
  fixed in the spreadsheet only. Retire the endpoints and the builder together
  with that decision.
  *(Fixed: feat/nav-taxonomy-url-space retired the endpoints, routes,
  `ImportFamilyModalBuilder`, and the `manage-family-modal.js` hook along with
  the per-family cards they served.)*
- [x] ⚪ Cleanup: `app/Helpers/asset_helper.php:119,134` - the `employee` and
  `viewer` script groups (and the matching style groups) are dead now that
  `layout.php` is the only dashboard shell and loads `admin`. Pre-existing shape,
  not touched by the collapse; delete when nothing else reads the manifest by
  role name.
  *(Fixed: all four groups deleted; the only callers pass head/admin/login/scanner
  - chore/backlog-cleanup.)*
- [x] 🔵 UX/needs-decision: the review screen lost the paging, per-list search,
  severity/code filters, bulk remove, "needs a QR" list, and "ready to import"
  list that the grouped card report had. The per-person table with the
  problems-only switch is the design spec's deliberate replacement, but a
  350-person file now renders as 350 rows with no pager. Revisit if an operator
  reports it; the toggle keeps the default view small.
  *(Fixed: feat/nav-taxonomy-url-space restored paging, search, and
  severity/code filters on the per-person table. Bulk remove and the
  "needs a QR" / "ready to import" lists stayed out of scope and are not
  restored.)*

Exempt (checked, not violations): `app/Views/errors/html/*` (framework error
pages, standalone by design), `app/Views/Scanner/pdf/report.php` (PDF
rendering needs inline styles), layout shells + `Auth/login.php` (standalone
`<html>` is their job).

## Deferred from chore/doc-standard (2026-07-28)

- [x] ⚪ Cleanup: `public/assets/js/` (23 files) - has the same comment problems
  the PHP had: divider banners, notes recording a requested change.
  *(Won't fix - chore/issue-sweep. Roughly ninety percent of the work is
  building a JavaScript comment extractor that can prove an edit changed
  nothing, since comment markers occur inside strings and regex literals, and
  ten percent is editing comments. The gate costs more than tidier comments are
  worth. `scripts/list-comments.php` and its quote-aware CSS reader stay
  available if the trade ever changes.)*
- [x] 🔵 UX/needs-decision: two brand greens are in use, `--binan-green: #145c3b`
  (`public/css/theme.css:8`) and `--login-green: #176b4d`
  (`public/css/login.css:8`). A design decision, not a cleanup one.
  *(Fixed: UI Modernization pilot on login page adopted `#176b4d` into the strict `design-tokens.css` system, effectively deciding the green for the future)*
- [x] ⚪ Cleanup: `public/css/managerecord.css:263` - `.records-table-controls`
  is emitted by no view; `app/Views/components/table_controls.php` builds the
  same row from Bootstrap utilities. Left in place because
  `docs/20-frontend.md` still names the
  class as the controls-row hook. Retire the rule and the doc reference together.
  *(Fixed: both the base rule and its media-query branch are gone, and Rule 6
  now names the component instead of the class - chore/backlog-cleanup.)*
- [x] ⚪ Cleanup: `app/Views/Lookups/picker.php` - rendered by nothing. The family
  form uses the picker built into `family-modal.php`. Superseded, not wired up.
  *(Fixed: deleted - chore/backlog-cleanup.)*
- [x] 🟡 Minor: `app/Views/components/table_controls.php:8` - the header carries a
  full variable list, which the comment standard bans for views. Kept because a
  props-only component has no `*_view_data()` function to hold the contract, so
  deleting the list would lose it with nowhere to put it. Resolve by deciding
  where a component's props contract lives.
  *(Fixed: the contract lives in the header. `comments.md` now carves out
  `components/*` and `Cards/pdf/*`: the ban is on a list that duplicates a
  contract held elsewhere, not on the only copy of one - chore/backlog-cleanup.)*
- [x] 🟡 Minor: `scripts/check-comment-style.sh` - the em-dash and divider scans
  match on raw text, so an em dash or a `----` run inside a PHP string, a regex,
  or inline HTML would be reported as a comment violation. A false positive fails
  the build rather than passing it, so this is loud, not silent. Fixing it means
  parsing comments with `token_get_all()` for PHP and a quote-aware scan for CSS,
  which is its own branch. Raised by CodeRabbit on PR #42.
  *(Fixed: both scans read `scripts/list-comments.php`, which tokenizes the PHP
  and reads CSS quote-aware - chore/backlog-cleanup.)*
- [x] 🟡 Minor: `scripts/assert-css-unchanged.sh:19` - `strip()` removes anything
  between `/*` and `*/` with a regex, so a comment-like sequence inside a quoted
  string or a `url()` is stripped from both sides and an edit there would not be
  seen. This one fails open, unlike the check above. No stylesheet in `public/css`
  contains such a string today. A real fix needs a CSS-aware lexer. Raised by
  CodeRabbit on PR #42.
  *(Fixed: `strip()` calls `scripts/strip-css-comments.php`, a quote-aware reader
  - chore/backlog-cleanup.)*
- [x] 🟡 Minor: `scripts/assert-tokens-unchanged.php` - `--allow-added` waives the
  token comparison for an added file entirely, so a new executable PHP file passes
  the gate unread. Intended as an escape hatch for the rare docs-only branch that
  adds a file; not used by the branch that added the script. Tighten it to allow
  an added file only when `significantTokens()` comes back empty. Raised by
  CodeRabbit on PR #42.
  *(Fixed: exactly that - an added file passes only when it tokenizes to nothing
  executable - chore/backlog-cleanup.)*
- [x] 🟡 Minor: `app/Views/Cards/pdf/batch_page.php:10` - the header carries `@var`
  tags for `$cells` and `$isFirstPage`, which the comment standard bans for views.
  Same shape as the `table_controls.php` item above: a partial rendered by the PDF
  builder has no `*_view_data()` function to hold the contract, so deleting the
  tags loses it with nowhere to put it. Resolve with that item, not separately.
  *(Fixed with that item: the `@var` tags are now a prose props list under the
  same carve-out - chore/backlog-cleanup.)*
- [x] 🟡 Minor: `scripts/check-comment-style.sh` - the em-dash scan still covers
  inline `<script>` blocks, which the divider scan now skips. No file hits it
  today, so nothing was changed; an em dash written into a view's inline JS would
  fail a check that cannot be satisfied without failing the token gate.
  *(Fixed: a view's inline script is `T_INLINE_HTML`, so it never reaches either
  scan now that both read comment text only - chore/backlog-cleanup.)*
- [x] ⚪ Cleanup: `docs/15-distribution.md` (Rules 3
  and 5) - still names `aid_type_id` and `AidDistributionModel`, both renamed
  to `subsidy_type_id`/`SubsidyDistributionModel` by the V19 subsidy rename.
  Spotted while updating the doc for the schedule calendar (feat/
  distribution-schedule-calendar); out of scope there, left for a pass over
  the whole file.
  *(Fixed: every schema object and class name in the file now matches V22 -
  chore/issue-sweep.)*
- [x] 🟠 Major: `app/Views/components/data_table.php` - passes table rows as
  `list<list<raw HTML>>`, so the caller concatenates markup and owns the
  escaping (the docblock says as much: "Cell values are RAW HTML"). Every other
  component either takes scalars and escapes them itself (`toolbar`,
  `table_controls`, `table_footer`) or takes a body view and
  composes (`card`, `modal`). Rows are content, so they belong to the second
  shape. Fifteen views already write their own `<table>` inside `card` +
  `table_controls` + `table_footer`, which is the correct pattern; this
  component is the deviation and is down to one caller (`Pages/dashboard.php`).
  The card/data_table composition is also what forced the 25-line `$tempData`
  recursion comment in `components/card.php`. Resolve by converting that one
  caller to `card` + an inline table and deleting the component, not by
  migrating the fifteen onto it.
  *(Fixed: `Pages/dashboard.php` now renders `card` + the new
  `Pages/dashboard-activity-body.php`, and the component is deleted.)*
- [x] 🔴 Critical: `member.sectorID varchar(255) DEFAULT '[]'` - a many-to-many
  stored as a JSON list in a varchar. No foreign key, no usable index, and every
  sector filter matched ids inside the string (`MemberModel::applyRecordFilters`,
  `EligibilityBuilder::scoped`), so a deleted sector left its id behind in every
  member row that carried it. `member_services` beside it always had the right
  shape.
  *(Fixed in V22: `member_sectors(memberID, sectorID)` with both foreign keys,
  `MemberSectorModel`, and `php spark members:split-sectors` to copy the JSON
  across before `sql/patches/v22-normalize-drop.sql` drops the column.)*
- [x] 🔴 Critical: `services.category text` - the grouping NAME copied onto every
  service row, while `category` (categoryID, code UNIQUE, name) already existed.
  A rename desynced every service filed under it, and grouping depended on two
  strings staying character-identical. The column was text because it pointed at
  two tables: a service is grouped by a standalone category OR by a sector.
  *(Fixed in V22: nullable `categoryID` + `sectorID` with foreign keys and a
  CHECK that exactly one is set; `php spark services:link-categories` resolves
  the old labels.)*
- [x] 🔴 Critical: `member.address` stored "address, barangay" next to the
  `member.barangayID` V20 added, so the same fact lived twice and only the
  free-text copy was displayed. Filtering answered by spelling: "Sto. Tomas" and
  "SANTO TOMAS" were different barangays, and a second hardcoded barangay list in
  `FamilyProfilingFormV2` had drifted from the `barangay` table.
  *(Fixed in V22: address holds the address alone, the list comes from
  `BarangayModel::activeNames()`, and `php spark members:split-address` strips the
  barangay after `members:backfill-barangay` has filled every id.)*
- [x] 🟠 Major: foreign keys existed only on `audit_trails` and `member_services`.
  `member.barangayID`, `qr_control.headID`, every `subsidy_distribution` id and
  all three `batch_*` tables could point at rows that no longer existed, with no
  way to detect it.
  *(Fixed in V22: constraints added in `sql/patches/v22-normalize.sql`.)*
- [x] 🟠 Major: `member.Salary float` - the one capitalized column in the schema
  (the family form carried a documented workaround for the casing), and a float
  for a peso amount.
  *(Fixed in V22: `salary decimal(12,2)`. The stored value now reads back
  "25000.00", so the income select matches it through
  `MemberFieldNormalizer::salaryOptionValue()`.)*
- [x] 🟠 Major: `suffix`/`sex` were Title Case enums, so uppercase could not be
  stored at all - MySQL resolved an inserted 'JR' back to the member 'Jr'.
  *(Fixed in V22: `enum('JR','SR',...)` and `enum('MALE','FEMALE')`, with the
  option lists, the Excel code maps and the "Other" free text uppercased to
  match.)*
- [x] 🟡 Minor: `services.serviceID` was the only lookup primary key without
  AUTO_INCREMENT, so `ServiceModel::nextServiceId()` allocated ids by hand under a
  FOR UPDATE lock. *(Fixed in V22; the method is gone.)*
- [x] 🟡 Minor: `sector.shortcode` and `services.shortcode` had no UNIQUE key
  though `category.code` did, so two rows could share a code and shortcode lookup
  picked whichever came first. *(Fixed in V22.)*

## Appended from docs/restructure-docs-and-agent-context (2026-08-14)

Found while fact-checking the handbook against the code. Not fixed in that
branch, which changed documentation only.

- [ ] ⚪ Cleanup: `app/Models/Scanner/SubsidyDistributionModel.php:163` -
  `batchCountsFor()` and `subsidyTypeCountsFor()` are defined but called from
  nowhere in the repo (verified by grep). They were the filter counts for a
  search-and-filter Audit Logs tab on the scanner "View all" page; that tab now
  renders unfiltered and unpaginated. Dead code; remove or wire up.
- [x] ⚪ Cleanup: `install-cron-worker.ps1` at the repository root was a nine-line
  shim forwarding to `scripts/install-cron-worker.ps1`. Nothing referenced it, the
  bash installer had no equivalent, and the real script resolves its paths from its
  own location, so running it from `scripts/` out of the repository root already
  worked. *(Fixed: root shim deleted, this branch.)*
- [ ] 🟡 Minor: `app/Models/Audit/AuditTrailsModel.php:65` invalidates
  `DashboardModel::STATS_CACHE_KEY` on every logged mutation but not
  `PROGRAM_STATS_CACHE_KEY`, so the Overview tab's four counts lag a mutation by
  up to their 60 second TTL while the tiles beside them update immediately.
  Decide whether that is intended and either delete both keys or say so in the
  model docblock.
- [ ] 🟡 Minor: `app/Views/Cards/pdf/batch_page.php:5` says "Nine cards to a
  page, laid out three per row", but the grid is 3x4 and the page count comes
  from `QrCardSettings::$cellsPerPage`, which is 12. The header contradicts both
  the config docblock and `QrCardPdfGenerator::` padding to a full 3x4 grid.
