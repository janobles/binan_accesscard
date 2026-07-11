# Issue #9 Cleanup — Fix Summary

**Branch:** `chore/issue-9-cleanup` (off `main`)
**Spec:** `docs/superpowers/specs/2026-07-06-issue-9-cleanup-design.md`
**Plan:** `docs/superpowers/plans/2026-07-06-issue-9-cleanup.md`
**Closes:** [#9](https://github.com/daki-crypto/binan_accesscard/issues/9)

Resolves all 13 items from issue #9 (post-PR#8 dead code + CodeRabbit re-review
backlog). Each fix was implemented, task-reviewed (spec + quality), and verified
against the full PHPUnit suite (baseline 72 tests, 0 fail, 4 skip — held green
throughout).

## Fixes applied

| Item | Commit | Change |
|------|--------|--------|
| P1-1 | `a24a193` | Removed dead `family-form` key from `asset_helper.php` manifest (CSS file was deleted, no callers). |
| P2-1 | `5ab0c4d` | `FamilyController::import()` now gates uploads on server-side `guessExtension()` (allows `xlsx`/`zip`) instead of the spoofable `getClientExtension()`. |
| P2-2 | `cad9bef` | `FamilyController::store()` now calls `auditSystemError()` for unexpected (non-`FamilyRecordWriteException`) failures, matching `import()`/`changeFamilyState()`. |
| P2-3 | `71c236a` | `ServiceModel::nextServiceId()` takes a `FOR UPDATE` lock inside the caller's transaction; `ServiceController` create branch now wraps allocation + insert in one transaction. `services` is InnoDB, so the lock is effective. No schema change. |
| P2-4 | `c94e9dd` | `services.php` empty-state colspan is now `$canManage ? 6 : 5` (was hardcoded `6`). |
| P2-5 | `fc9cebc` | Removed runtime `CREATE TABLE` from `JobQueueModel::ensureTable()`; the `job_queue` table already ships in `accesscardV14.sql`. Both callers (`FamilyController::import`, `QueueWork`) now guard on `hasTable()` and fail gracefully. |
| P2-6 | `af2fe38` | `ModelQueryHelpers` deduped: its 8 methods were byte-identical copies of `NormalizesIds`/`ResolvesSectorNames`/`ResolvesUserNames`/`ResolvesMemberNames`. It now composes those four traits (183→15 lines). Consumers (`DashboardModel`, `AuditTrailsModel`) unchanged; no trait-method collision. |
| P2-7 | `2557899` | `scripts/README.md` now documents running the queue worker under a dedicated least-privilege service account (not `SYSTEM`), with required permissions and rationale. |
| P2-8 | `7356f7d` | Corrected `ProfileController::updateDeveloper()` + class docblock: credentials persist to `writable/developer/credentials.json` via `DeveloperProfile`, not `.env`; username is editable here. |
| P2-10 | `071a93f` | Wrapped 4 `asset_url()` outputs in `esc(..., 'attr')` (login.php ×2, Employee/Viewer layouts ×1 each). |
| P2-11 | `f9b6c84` | Relationship label `Children` → `Child` in `FamilyFormOptionsModel`; matching example row in `FamilyExcelTemplate` updated for consistency. |
| P1-2/3 | `1d0e1cc` | Adopted the `topbar-account-menu` partial across Admin/Employee/Viewer layouts, replacing the copy-pasted inline dropdowns. Added `accountLevelLabel` (from `RoleAccess::normalizeRole`) to the three `DashboardPageBuilder` view-data arrays. |

## Custom CSS kept (intentional)

**`.topbar-account-*` in `css/sb-admin-adapter.css`** — retained, not deleted. The
adopted account-menu partial renders a summary header (avatar + full uppercase
name + account-level label) that has **no SB-Admin/Bootstrap component
equivalent**, so the custom CSS is required. The rest of the partial uses standard
Bootstrap dropdown classes. This is a deliberate, minimal exception to the
"prefer vendored components" rule.

## Won't-fix (with rationale)

**P2-12 — `manage-family-modal.js:805` `box.scrollTop = 0`.** Kept as-is.
Deliberate "jump to updated suggestion" behavior: it is `prefers-reduced-motion`-
guarded, fires only when the suggestion set actually changes, and pairs with the
`is-updated` flash to surface the top match. Preserving a manual scroll position
would add state-tracking for a low-value edge case, and a smooth-scroll would feel
janky mid-typing. Product decision: keep.

**P2-9 — `card h-100` on stat cards.** Kept as-is (not a demo leftover). `.stat-card.card`
is a compound CSS selector that only fires when the `card` class is present (it
drives `overflow:hidden`), and `Family/list.php:26`'s `card` class is its sole
source of border/background/radius. `h-100` provides equal-height cards in a
Bootstrap row. Stripping either risks silent overflow/layout regressions for no
visual gain — so the classes are functional, not SB-Admin-Pro demo residue.

## Verification

- Full PHPUnit suite green at every task (72 tests, 0 fail, 4 skip).
- **Deferred to reviewer/user:** browser visual smoke of the new topbar account
  menu across all three roles (Admin/Employee/Viewer) — the partial adoption is a
  UI change; tests don't render it.
