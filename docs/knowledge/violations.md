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
  `binan-conventions/mvc-boundaries.md`).
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

- [ ] 🟡 Minor: `app/Views/Scanner/scan.php:52,55`: inline `style="..."` on the
  QR image and the headline, which is what fails `ScanViewTest::testNoInlineStyles`
  (red on `main` since before feat/family-member-rows, verified by stashing the
  branch). Move both to `public/css/scanner-scan.css`, which the scanner manifest
  already loads.

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
- [ ] ⚪ Cleanup: `app/Helpers/asset_helper.php:119,134` - the `employee` and
  `viewer` script groups (and the matching style groups) are dead now that
  `layout.php` is the only dashboard shell and loads `admin`. Pre-existing shape,
  not touched by the collapse; delete when nothing else reads the manifest by
  role name.
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

- [ ] ⚪ Cleanup: `public/assets/js/` (23 files) - has the same comment problems
  the PHP had: divider banners, notes recording a requested change. Skipped
  because comment markers occur inside strings and regex literals, so there is
  no mechanical way to prove an edit changed nothing, and because a view's
  inline `<script>` is compared verbatim by the token gate. Needs its own branch
  with its own gate.
- [ ] 🔵 UX/needs-decision: two brand greens are in use, `--binan-green: #145c3b`
  (`public/css/theme.css:8`) and `--login-green: #176b4d`
  (`public/css/login.css:8`). A design decision, not a cleanup one.
- [ ] ⚪ Cleanup: `public/css/managerecord.css:263` - `.records-table-controls`
  is emitted by no view; `app/Views/components/table_controls.php` builds the
  same row from Bootstrap utilities. Left in place because
  `docs/knowledge/binan-conventions/ui-design-system.md:72` still names the
  class as the controls-row hook. Retire the rule and the doc reference together.
- [ ] ⚪ Cleanup: `app/Views/Lookups/picker.php` - rendered by nothing. The family
  form uses the picker built into `family-modal.php`. Superseded, not wired up.
- [ ] 🟡 Minor: `app/Views/components/table_controls.php:8` - the header carries a
  full variable list, which the comment standard bans for views. Kept because a
  props-only component has no `*_view_data()` function to hold the contract, so
  deleting the list would lose it with nowhere to put it. Resolve by deciding
  where a component's props contract lives.
- [ ] 🟡 Minor: `scripts/check-comment-style.sh` - the em-dash and divider scans
  match on raw text, so an em dash or a `----` run inside a PHP string, a regex,
  or inline HTML would be reported as a comment violation. A false positive fails
  the build rather than passing it, so this is loud, not silent. Fixing it means
  parsing comments with `token_get_all()` for PHP and a quote-aware scan for CSS,
  which is its own branch. Raised by CodeRabbit on PR #42.
- [ ] 🟡 Minor: `scripts/assert-css-unchanged.sh:19` - `strip()` removes anything
  between `/*` and `*/` with a regex, so a comment-like sequence inside a quoted
  string or a `url()` is stripped from both sides and an edit there would not be
  seen. This one fails open, unlike the check above. No stylesheet in `public/css`
  contains such a string today. A real fix needs a CSS-aware lexer. Raised by
  CodeRabbit on PR #42.
- [ ] 🟡 Minor: `scripts/assert-tokens-unchanged.php` - `--allow-added` waives the
  token comparison for an added file entirely, so a new executable PHP file passes
  the gate unread. Intended as an escape hatch for the rare docs-only branch that
  adds a file; not used by the branch that added the script. Tighten it to allow
  an added file only when `significantTokens()` comes back empty. Raised by
  CodeRabbit on PR #42.
- [ ] 🟡 Minor: `app/Views/Cards/pdf/batch_page.php:10` - the header carries `@var`
  tags for `$cells` and `$isFirstPage`, which the comment standard bans for views.
  Same shape as the `table_controls.php` item above: a partial rendered by the PDF
  builder has no `*_view_data()` function to hold the contract, so deleting the
  tags loses it with nowhere to put it. Resolve with that item, not separately.
- [ ] 🟡 Minor: `scripts/check-comment-style.sh` - the em-dash scan still covers
  inline `<script>` blocks, which the divider scan now skips. No file hits it
  today, so nothing was changed; an em dash written into a view's inline JS would
  fail a check that cannot be satisfied without failing the token gate.
