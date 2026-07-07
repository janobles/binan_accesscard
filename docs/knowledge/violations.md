# Violations Punch-List

Canonical punch-list for code-mess items (dead code, non-conforming views,
redundant helpers, boundary leaks). GitHub issues track QA/feature work, not
code mess — this file is the single home to avoid drifting lists.

Maintenance: cleanup PRs tick items `[x]` + `*(Fixed: <PR/commit>)*`. New
violations spotted mid-task get appended immediately, verified first.

Seeded 2026-07-06 from an audit pass. Closed issue #7 was mined; its only
unchecked item was already moved to issue #9 (UX decision, not code mess).

## Findings

- [x] 🟠 Major: `app/Controllers/Families/FamilyController.php:1` — 1723-line
  controller mixes family CRUD, Excel import, QR control-number handling, and
  modal partial rendering. Candidates for extraction into libraries per the
  controllers-decide/libraries-build boundary (see
  `binan-conventions/mvc-boundaries.md`).
  *(Fixed: split into FamilyController (~1000 lines, CRUD) +
  FamilyImportController + FamilyDataTableController + FamilyRequestContext
  trait, with FamilyDataTablePresenter and FamilyModalDataBuilder libraries —
  a8edb59, b11cbe7, 6f8562c, f9d7df7, refactor/mvc-cleanup)*
- [x] 🟡 Minor: `app/Views/Family/list.php` (filter dropdowns; markup since moved
  to `app/Views/Family/list-body.php`) — inline `style="max-height: 14rem;"`
  on dropdown menu (also line 69). Move to a page-CSS rule in
  `public/css/managerecord.css` or a utility class.
  *(Fixed: `.family-filter-field .dropdown-menu` rule in managerecord.css — 05556ae)*
- [x] 🟡 Minor: `app/Views/Accounts/account-form-modal.php:44` — inline
  `style="border:0;background:transparent;padding:0 0 0.5rem;"` on header;
  belongs in `public/css/accounts.css` next to the other
  `.account-card-header` rules.
  *(Fixed: `.edit-account-modal > .account-card-header` rule in accounts.css — 05556ae)*
- [x] ⚪ Cleanup: `app/Controllers/Families/FamilyController.php:824` —
  `shapeExistingMembers()` is defined but never called anywhere in the repo
  (verified by grep). Dead code; remove.
  *(Fixed: removed; splitAddressBarangay also moved to MemberFieldNormalizer — 65173fd)*
- [x] 🔵 Needs-decision: `app/Libraries/DashboardPageBuilder.php:1` — CLAUDE.md
  says "respect existing strict-type conventions" but **zero** files under
  `app/` declare `declare(strict_types=1)` (typed signatures are used, the
  declare is not). Decide: adopt the declare repo-wide (one mechanical PR) or
  reword the convention to "typed signatures, no strict_types declare".
  `php-practices/idioms.md` documents current reality.
  *(Fixed: reworded CLAUDE.md convention to typed-signatures-only, refactor/mvc-cleanup)*

Exempt (checked, not violations): `app/Views/errors/html/*` (framework error
pages, standalone by design), `app/Views/Scanner/pdf/report.php` (PDF
rendering needs inline styles), layout shells + `Auth/login.php` (standalone
`<html>` is their job).
