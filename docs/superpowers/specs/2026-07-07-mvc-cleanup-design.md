# MVC Cleanup & FamilyController Split — Design Spec

**Date:** 2026-07-07
**Branch:** `refactor/mvc-cleanup` (off freshly synced `main`)
**Goal:** Tick all five items in `docs/knowledge/violations.md`. No behavior, URL,
or schema changes — pure extraction, relocation, and deletion.

## Baseline

- Test suite green before work starts: 87 tests, 281 assertions, 5 skipped
  (sqlite3 ext absent), 1 PHPUnit warning (no coverage driver). This is the
  reference state; every commit must match or improve it.
- `php spark routes` resolves all routes.

## Scope

All five violations.md items in one branch, one concern per commit:

1. 🟠 Split 1750-line `app/Controllers/Families/FamilyController.php`.
2. 🟡 Move raw `$db->table()` queries out of `ServiceController` (lines
   181–216) and `SectorController` (line 234) into `ServiceModel` /
   `SectorModel` methods.
3. ⚪ Delete dead `shapeExistingMembers()` (FamilyController:824).
4. 🟡 Move inline styles: `app/Views/Family/list.php:47,69` →
   `public/css/managerecord.css`; `app/Views/Accounts/account-form-modal.php:44`
   → `public/css/accounts.css`.
5. 🔵 strict_types decision: **reword** the CLAUDE.md convention to "typed
   signatures, no `declare(strict_types=1)`" instead of adding the declare
   repo-wide. Rationale: CI4's own appstarter ships without it; adding it
   turns silent request-input/DB-row coercions into runtime TypeErrors in
   untested paths, for no feature gain. `php-practices/idioms.md` already
   documents current reality.

## FamilyController Split

Target layout (URLs unchanged; `Routes.php` retargets only):

| File | ~Lines | Responsibility |
|---|---|---|
| `Controllers/Families/FamilyController.php` | 450 | store, update, viewFamily, editFamily, archive, restore, createFamily. Decisions only: guards, redirects, flash, audit calls. |
| `Controllers/Families/FamilyImportController.php` | 200 | importForm, import, importStatus, downloadTemplate. Delegates to existing `FamilyExcelImporter` / `FamilyExcelTemplate` / `FamilyImportJob`. |
| `Controllers/Families/FamilyDataTableController.php` | 120 | dataTable endpoint. |
| `Libraries/FamilyDataTablePresenter.php` | 250 | Row shaping, QR cell, actions HTML, payload envelope (absorbs the `dataTable*` private helpers). |
| `Libraries/FamilyModalDataBuilder.php` | 200 | Modal view-data assembly (absorbs `renderFamilyModal` data prep, `familyModalUpdateData`, `shapeModalMembers`, service-name and income-label maps). |

- Stateless request-shaping helpers (`memberPayloadFromArray`, `cleanName`,
  `cleanAddress`, `moneyOrNull`, `nullableText`, address split/combine,
  `rulesForEntryType`) move to a new `Libraries/FamilyRequestShaper`
  (decided at planning: FamilyRecordWriter stays write-only). Request-bound
  helpers (`memberPayload`, `entryType`, `submissionWasTruncated`,
  `splitHeadAndMembers`) stay in the controller and delegate.
- `renderFamilyModal()` stays in FamilyController (it decides guards and
  calls `view()`); only its data assembly moves to `FamilyModalDataBuilder`.
- Shared guards/context helpers (`requireFamilyEntryAccess`,
  `requireFamilyViewAccess`, `isEmployeeContext`, `currentRouteBase`,
  `partialGuard`, `recordMissing`, JSON error helpers) → new trait
  `Controllers/Families/FamilyRequestContext`, since all three
  controllers need them. Behavior must be byte-identical — employee vs
  admin route context (`currentRouteBase`) is the sensitive path.

### Invariants

- **Audit trail:** every mutation path (store, update, archive, restore,
  import) keeps its `Audit/AuditTrailsModel` write. Verified per commit.
- **JSON contracts:** `dataTable` response shape and `importStatus` polling
  payload are consumed by front-end JS — signatures and shapes relocated,
  never changed.
- **URLs:** all routes keep their current paths and names; only controller
  targets in `Routes.php` change. `php spark routes` confirms resolution
  after every routing commit.

## Commit Sequence

1. Lookup query moves (ServiceModel / SectorModel).
2. Inline styles → page CSS.
3. strict_types convention reword (docs only).
4. `FamilyRequestContext` trait extraction.
5. Import extraction (`FamilyImportController`).
6. dataTable extraction (`FamilyDataTableController` + presenter library).
7. Modal extraction (`FamilyModalDataBuilder`).
8. `FamilyRequestShaper` extraction + delete `shapeExistingMembers()`.
9. Documentation sweep (see RAG section) + violations.md ticks with commit refs.

Each commit: `vendor/bin/phpunit` green (≥ baseline) + `php spark routes` clean.

## Testing

- Existing phpunit suite before/after every commit; suite must be green
  (87+ tests, 0 failures) at branch end — merge blocker.
- New unit tests for the extracted libraries: `FamilyDataTablePresenter`
  and `FamilyModalDataBuilder` (they become testable units).
- Manual smoke before PR: login, role redirect, family create/update,
  Excel import, archive/restore, manage-records dataTable page, audit rows
  written for each mutation.

## Review Gate (CodeRabbit)

- Run `coderabbit review --base main --agent` on the finished branch
  (background; wait for completion — large diffs take minutes; retry on
  transient `TRPCClientError`).
- Triage every finding per `superpowers:receiving-code-review` — verify
  against code and CLAUDE.md non-negotiables; **no blind-apply**.
- Fix in-scope genuine bugs, re-run phpunit; park pre-existing /
  out-of-scope findings in a GitHub issue citing PR # and branch.
- Won't-fix findings noted with reasons.

## RAG / Knowledge-Base Updates

The refactor must leave `docs/knowledge/` truthful (commit 8):

- `violations.md`: tick items 1–5 `[x]` with `*(Fixed: <commit/PR>)*`.
- `binan-conventions/` (esp. `mvc-boundaries.md`): update any references to
  FamilyController's old shape; record the new controllers/libraries as the
  worked example of the controllers-decide/libraries-build boundary.
- CLAUDE.md: strict_types reword (item 5).
- `PROJECT_STRUCTURE.md`: add the new files.

## Risks

- Modal rendering depends on employee vs admin route context — trait
  extraction must preserve exact `currentRouteBase()` behavior.
- Diff size: mitigated by one-concern-per-commit; CodeRabbit (not Copilot)
  reviews it, per CLAUDE.md.
- 5 skipped DB/session tests (no sqlite3) reduce automated coverage of
  session-dependent paths — covered by the manual smoke list instead.

## Out of Scope

- Any schema/migration work, route URL changes, UI redesign, behavior
  changes, pre-existing dead code not created by this change (other than
  the explicitly listed `shapeExistingMembers()`).
