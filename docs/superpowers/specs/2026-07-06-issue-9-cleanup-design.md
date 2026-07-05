# Issue #9 Cleanup — Design Spec

**Date:** 2026-07-06
**Issue:** [#9](https://github.com/daki-crypto/binan_accesscard/issues/9) — Post-PR #8 cleanup (dead code + CodeRabbit re-review backlog)
**Branch:** `chore/issue-9-cleanup` off `main`
**Deliverable:** Code PR to `main` + summary file. Closes issue #9.

## Purpose

Issue #9 collects two backlogs from the `feat/qr-module` → `main` merge (PR #8):
dead code left by the merge, and CodeRabbit re-review findings not yet actioned.
This spec resolves all 13 items in one branch: mechanical/security fixes plus the
five items that needed a product/architecture decision (now decided).

The current working branch (`feat/preset-aid-fast-scan`) is unrelated; this work
starts fresh from `main`.

## Scope

All 13 issue #9 items. No unrelated refactoring. Every change stays within the
project non-negotiables (no migrations, SQL-dump-is-truth, audit trail on family
mutations, controllers-decide/libraries-build, PHP 8.2+).

## Decisions (locked during brainstorming)

- **Topbar:** Adopt the orphaned `topbar-account-menu.php` partial across
  Admin/Employee/Viewer layouts (drop the inline dropdowns). Chosen for the
  richer identity header (avatar + full name + account-level label) and DRY.
- **JobQueueModel:** Move the `job_queue` DDL into the SQL dump (source of truth);
  drop the runtime `CREATE TABLE`. Resolves the no-migrations conflict.
- **ServiceModel::nextServiceId():** Wrap id allocation in a transaction with a
  `FOR UPDATE` lock. No schema change (AUTO_INCREMENT is off-limits).
- **scrollTop UX:** Keep the auto-scroll (won't-fix). Already
  `prefers-reduced-motion`-guarded, only fires on suggestion-set change, and the
  snap pairs intentionally with the `is-updated` flash. Documented, no code change.
- **CSS constraint:** Prefer vendored Bootstrap + SB-Admin components; add custom
  CSS only where no component exists, and document any such CSS in the summary file.

## Work items

### Part 1 — Dead code / topbar

**P1-1 · `app/Helpers/asset_helper.php` — dead `family-form` manifest entry**
Delete the `'family-form' => ['css/familyform.css']` key. Verified dead:
`public/css/familyform.css` was removed in the reusable-modal refactor and no code
calls `asset_styles('family-form')`. `asset_helper.php` is the app's single asset
utility (vendored bootstrap/sb-admin/chartjs/datatables + custom page CSS, grouped
by context, cache-busted via `asset_url()`); removing the stale key preserves that
pattern.

**P1-2/3 · Adopt `topbar-account-menu.php` across layouts**
- Replace the inline account dropdown block in `app/Views/Admin/layout.php`,
  `app/Views/Employee/layout.php`, `app/Views/Viewer/layout.php` with
  `<?= view('Partials/topbar-account-menu') ?>` (or `include`), passing the data
  the partial expects.
- The partial reads `$user`, `$username`, `$accountLevelLabel`,
  `$accountSettingsUrl` (default `site_url('account/profile')`),
  `$accountSettingsMode` (default `modal`). `$user` is already supplied to all
  three layouts by `DashboardPageBuilder` (`currentSessionUser()`).
- Add `accountLevelLabel` to the three view-data arrays in
  `app/Libraries/DashboardPageBuilder.php` (admin ~172, employee ~542, viewer
  ~631). Derive the label from the session role using existing helpers
  (`RoleAccess::normalizeRole()` / the role-label logic in `SessionAuditLogger`).
  Controllers decide, library builds — the label belongs in the builder.
- Keep the `.topbar-account-*` CSS in `css/sb-admin-adapter.css` — the summary
  header (avatar + name block) has no SB-Admin/Bootstrap equivalent, so the custom
  CSS is required. Document this in the summary file.
- The avatar asset `public/assets/image/default-profile.svg` exists.
- Verify each role's menu renders and the "My Account" modal + logout still work.

### Part 2 — CodeRabbit findings

**P2-1 · `FamilyController::importFile()` (~:277) — spoofable extension check**
Replace `strtolower($file->getClientExtension())` with server-side
`$file->guessExtension()` when gating the `xlsx` upload. Client extension is
attacker-controlled.

**P2-2 · `FamilyController::store()` (~:138-201) — swallowed Throwable**
A non-`FamilyRecordWriteException` `Throwable` is currently caught with no
log/audit, unlike `import()`/`changeFamilyState()`. Add the same failure logging
and audit path so silent failures are observable.

**P2-3 · `ServiceModel::nextServiceId()` (~:199) — id race**
Wrap the `SELECT MAX(id)+1` and the dependent insert in a single transaction and
take a `FOR UPDATE` lock so concurrent creates can't allocate the same id. No
schema change.

**P2-4 · `app/Views/Lookups/services.php` (~:89) — empty-state colspan**
The empty-row `colspan` is hardcoded and miscounts when the Actions column is
hidden. Compute it from `$canManage` (mirror the header column count).

**P2-5 · `JobQueueModel::ensureTable()` — runtime CREATE TABLE**
Remove `ensureTable()` and its `CREATE TABLE`. Add the `job_queue` table
definition to the SQL dump (`accesscardV14.sql`) so schema stays dump-sourced.
Remove callers of `ensureTable()`. Keep `sql/job_queue.sql` as reference or fold
it into the dump.

**P2-6 · `ModelQueryHelpers` trait — duplicated logic**
Investigate during implementation. Compare `app/Models/Concerns/ModelQueryHelpers.php`
against the smaller traits (`LookupModelTrait`, `NormalizesIds`, `RecordStatus`,
`Resolves*`, `MemberQueryFilters`). If it genuinely duplicates them, remove the
duplication and repoint callers to the smaller traits; if it is a thin aggregator
with unique logic, keep it and note why. Do not change behavior.

**P2-7 · `scripts/README.md` — SYSTEM queue worker**
Change the documented Windows scheduled-task guidance to run the queue worker
under a least-privilege service account, not `SYSTEM`.

**P2-8 · `ProfileController::updateDeveloper()` — stale docblock**
Update the docblock: the real flow is `credentials.json` + an editable username,
not a `password` field.

**P2-9 · Demo `card h-100` classes (×4)**
Remove SB-Admin-Pro demo classes from stat cards/panels in
`app/Views/Admin/layout.php`, `Employee/layout.php`, `Viewer/layout.php`,
`Family/list.php`. Replace with the intended layout classes only where needed.

**P2-10 · Unescaped `asset_url()` (×3)**
Wrap `asset_url()` output in `esc(..., 'attr')` inside `img`/`link` tags in
`app/Views/Auth/login.php`, `Employee/layout.php`, `Viewer/layout.php`.

**P2-11 · `FamilyFormOptionsModel` — relationship label**
Rename the relationship label "Children" → "Child".

**P2-12 · `manage-family-modal.js:805` scrollTop — won't-fix**
No code change. Document the rationale (see Decisions) in the summary file and
close the item.

## Verification

- `vendor/bin/phpunit` (or `composer test`) before and after — must stay green.
- Smoke test: login → each role redirect → new topbar account menu (render +
  My Account modal + logout) → family create (audit row written) → family import
  of an `.xlsx` (accepted) and a renamed non-xlsx (rejected) → service create.
- `coderabbit review --base main --agent`; triage per
  `superpowers:receiving-code-review` (no blind-apply); re-run phpunit.
- Park any out-of-scope/pre-existing CodeRabbit findings back into issue #9 (or a
  new issue) with the PR # + branch as a receipt.

## Out of scope

- AUTO_INCREMENT or any schema change beyond adding `job_queue` to the SQL dump.
- Refactoring the asset helper beyond removing the dead key.
- Any change to the `feat/preset-aid-fast-scan` work.

## Summary file

On completion, write a short summary documenting: the topbar custom-CSS keep
(`.topbar-account-*`, no SB-Admin equivalent), the scrollTop won't-fix rationale,
and the JobQueue DDL relocation. This is the receipt referenced when closing #9.
