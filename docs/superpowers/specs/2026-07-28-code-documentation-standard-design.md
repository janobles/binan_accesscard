# Code Documentation Standard and Lint Tooling

**Date:** 2026-07-28
**Status:** Approved
**Branch:** `chore/doc-standard`

Pure cleanup. No logic is created, changed, or removed. Every behavior that exists
before this branch exists identically after it, and that is proven mechanically
rather than asserted.

## Measured baseline

Every number below was measured on 2026-07-28 and is a gate value: the plan asserts
these exact numbers again at the end.

| Fact | Value |
|---|---|
| PHP files in `app/` | 205 |
| `app/Controllers` | 22 |
| `app/Models` | 25 |
| `app/Libraries` | 22 |
| `app/Support` | 5 |
| `app/Helpers` | 5 |
| `app/Jobs` | 4 |
| `app/Commands` | 4 (991 lines) |
| `app/Validation` | 2 |
| `app/Filters` | 2 |
| `app/Database` | 1 |
| `app/Views` | 65 (32 already have a `/**` header) |
| `app/Config` | 46 (stock CI4, excluded from all layers) |
| `app/Common.php` | 1 (15 lines, 1 non-comment line; stock, excluded) |
| `app/Language/en/Validation.php` | 1 (4-line data array, excluded) |
| Custom CSS in `public/css` | 11 files, ~3,589 lines (7 already have a header) |
| Em dashes in `app/` | 0 |
| `---- ----` dividers | 284 total, 276 in `app/Config`, 8 elsewhere |
| Test suite | 268 tests, 970 assertions |
| **Tests passing** | **267** |
| **Known failure** | **1** - `ScanViewTest::testNoInlineStyles`, pre-existing |
| Skipped | 8 (DB/session tests, no `sqlite3` ext) |
| PHPUnit warnings | 1 |

`ScanViewTest::testNoInlineStyles` fails because `app/Views/Scanner/scan.php` carries
inline `style="` attributes. This predates the branch and is out of scope. The gate is
that this stays the **only** failure. Fixing it is not permitted here; it goes to
`docs/knowledge/violations.md`.

## Problem

No documentation standard exists. Three symptoms:

1. **Inconsistent PHP docblocks.** 68 of 69 files in `Controllers`/`Models`/`Libraries`
   carry some docblock, but with no agreed shape and no tags. Nothing enforces any of it.
2. **Comments that record a requested change rather than explain code.** Concentrated
   in `app/Views` and `public/css`. These read as notes-to-self.
3. **Ad-hoc section dividers.** `// ---- x ----` in PHP, both `/* ==== */` and
   `/* ---- */` in CSS.

No linter. No CI workflow. `vendor/bin` holds only `phpunit` and `php-parse`.

## Goals

- One written standard, enforced by tooling rather than memory.
- Every class, view, and stylesheet carries a docblock that helps a developer.
- A method carries one when it adds something the signature does not. Not otherwise.
- No docblock that merely restates the signature, and none that contradicts the code.
- Zero behavior change, proven by a token-identity gate.
- Docblocks written so a user manual can be harvested from them later.

## Locked decisions

These are settled. They are recorded with their reasoning so they are not reopened.

| # | Decision | Reasoning |
|---|---|---|
| 1 | Format + accurate docblocks. No PHPStan. | PHPStan on 205 unanalyzed files buries the cleanup under unrelated type errors. Separate effort. |
| 1b | **The linter catches documentation that lies. Humans decide what deserves documenting.** Class docblocks are required; per-method presence is not. | Revised mid-execution once the real numbers were visible. A linter can see that a docblock exists but never that it says anything true, so mandating presence buys a green check plus 60 chances to write filler. The missing-docblock list was 8 `__construct` plus terse helpers (`pct`, `norm`, `pick`) where a forced docblock adds nothing. Only 1 class lacked a docblock, so that half costs nothing to enforce. |
| 2 | Prose docblocks; `@param`/`@return` only when the native type is insufficient. | PHP 8.2 signatures already carry types. Restating them is the ritual commenting being removed. |
| 3 | View headers state purpose only. No variable list. | Views receive data via `extract(*_view_data(get_defined_vars()))`. A variable list in the view cannot be verified and would rot. |
| 4 | Data contracts live on the 10 `*_view_data()` functions as `@return array{...}` shapes. | That is the real interface, it is ordinary PHP, phpcs covers it, and it sits beside the code that changes it. |
| 5 | `public/css/` structure is **not** changed. No renames, no moves, no `components/`+`pages/` split. | The cascade layering already works and `asset_helper.php` encodes load order deliberately. Reorganizing touches the manifest for no benefit. |
| 6 | CSS gets headers, one divider format, and pruning. No per-rule commenting requirement. | A selector describes itself. There is no hidden contract to document, unlike a view. |
| 7 | JS is out of scope. | 27 files whose comment edits cannot be mechanically proven safe (comment markers occur inside strings and regex literals). Gets its own branch. |
| 8 | No custom PHPCS sniff. | Stock `Squiz.Commenting.*` sniffs with tag-level message codes excluded give the presence half of the rule exactly. They do not give the whole of it: see the UselessFunctionDocComment limit under Tooling. Reaffirmed after that limit was measured, because writing a sniff would put the only new executable code into a branch whose premise is that it adds none, and would need test coverage no task plans for. The uncovered case goes to review. |
| 9 | Big-bang single branch, one commit per tranche. | User's call. Per-tranche commits are the review mitigation. |
| 10 | Review by `cavecrew-reviewer`, not CodeRabbit. | Deliberate deviation from CLAUDE.md, recorded so it is not later "corrected". The review question is narrow and the token gate has already proven no logic changed. |
| 11 | `docs/superpowers/specs/` kept; `plans/`, `summaries/`, `summary/` deleted. | Specs record why decisions were made. Plans are spent execution checklists. |

## Tooling

Versions verified against Packagist on 2026-07-28. Mutually compatible.

| Package | Version | Role |
|---|---|---|
| `friendsofphp/php-cs-fixer` | ^3.95 | Formatting, auto-fix |
| `codeigniter/coding-standard` | ^1.9 | CodeIgniter's own php-cs-fixer ruleset |
| `squizlabs/php_codesniffer` | ^4.0.1 | Sniff engine |
| `slevomat/coding-standard` | ^8.31 | Docblock sniffs |

All go in `require-dev`. `nexusphp/cs-config` arrives transitively via
`codeigniter/coding-standard` and is not declared directly.

CodeIgniter 4 v4.7.4's own `require-dev` is php-cs-fixer + codeigniter/coding-standard
+ nexusphp/cs-config. Layer 1 therefore matches the toolchain the framework lints
itself with.

`SlevomatCodingStandard.Commenting.UselessFunctionDocComment` errors on a docblock
that consists *only* of tags restating the native signature. It is what gives decision
2 a machine-enforced floor.

**Its limit, measured against slevomat 8.31 rather than assumed.** The sniff bails out
as soon as a docblock carries any summary prose, and it treats a non-empty tag
description as evidence the tag is wanted. So this errors:

```php
/**
 * @param string $id
 */
```

and this does not:

```php
/**
 * Fetches a thing.
 *
 * @param string $id The id.
 */
```

No stock sniff in phpcs or slevomat compares tag text against the signature, so the
second case cannot be caught mechanically without writing one. Decision 8 declined to
write a custom sniff, and that stands, so the split is:

- **Machine-enforced:** a docblock is required everywhere, and a docblock that is
  nothing but signature-restating tags is an error.
- **Review-enforced (Gate G):** a redundant tag sitting inside an otherwise useful
  docblock.

This is a real reduction from what an earlier draft of this spec claimed. It is
recorded rather than papered over because the codebase carries essentially no tags
today, which makes the gap a question of future drift, covered by review and by CI on
every PR.

## Enforcement: three layers

Split by what is mechanically checkable. Linters are deterministic about structure and
blind to prose quality; the written standard covers the rest, checked by review.

### Layer 1: Formatter (php-cs-fixer) - INSTALLED BUT NOT APPLIED

**Outcome, recorded after attempting it.** The repo-wide reformat was attempted and
then abandoned on evidence. It is not part of this branch.

Running the fixer under the token-identity gate forced **18 rules off**, because each
moved executable tokens: `ordered_imports`, `no_unused_imports`,
`single_import_per_statement`, `global_namespace_import`, `fully_qualified_strict_types`,
`ordered_types`, `ordered_class_elements`, `nullable_type_declaration`, `single_quote`,
`modernize_strpos`, `random_api_migration`, `fopen_flags`, `trailing_comma_in_multiline`,
`return_assignment`, `use_arrow_functions`, `static_lambda`, `control_structure_braces`,
`assign_null_coalescing_to_coalesce_equal`.

What survived produced 2,616 added and 1,662 deleted lines across 128 files, consisting
of docblock expansion and alignment padding:

```php
-    /** @return array<string, mixed> */
+    /**
+     * @return array<string, mixed>
+     */
```

Three reasons it was dropped:

1. **The gate proved the goals are contradictory.** Meaningful PHP formatting moves
   tokens. A branch that forbids moving tokens cannot meaningfully reformat. Asking for
   both, as the first draft of this spec did, was incoherent.
2. **What remained works against this branch.** Expanding a compact, readable
   `/** @return array<string, mixed> */` into three lines makes the codebase more
   verbose, which is the opposite of the standard being introduced.
3. **It broke two tests for no gain.** `FamilyDataTableTest` asserts against literal
   source text (`"'qr' => \$this->qrCell(\$controlNo)"`), so alignment padding fails it.
   Those assertions are brittle by design, but repairing them is not this branch's job.

php-cs-fixer and `.php-cs-fixer.dist.php` stay installed and configured for a future
formatting branch, where changing behavior is permitted and those two tests can be
fixed alongside.

**Consequence for CI:** `composer lint` must NOT include `lint:format`, since the tree
is deliberately unformatted. `composer lint` is `lint:sniff` plus `lint:comments`.
`lint:fix` and `lint:format` remain available to run by hand.

The original scope, kept for the record:

Config `.php-cs-fixer.dist.php`, based on `codeigniter/coding-standard`.

- **Includes:** `app/` except `app/Views` and `app/Config`; plus `tests/`, `tools/`.
- **`app/Views` excluded:** php-cs-fixer behaves unpredictably on majority-inline-HTML files.
- **`app/Config` excluded:** stock CI4 files.
- **Ruleset constraint:** only rules affecting whitespace, comments, and import order.
  Any rule that fails the token gate is removed from the ruleset. The gate is never
  relaxed to accommodate a rule.

### Layer 2: Sniffer (phpcs + slevomat)

Config `phpcs.xml.dist`.

- **Scope:** `app/Controllers`, `app/Models`, `app/Libraries`, `app/Support`,
  `app/Jobs`, `app/Commands`, `app/Helpers`, `app/Validation`, `app/Filters`,
  `app/Database`.
- **Excluded:** `app/Views` (Layer 3 instead), `app/Config` (stock CI4),
  `app/Common.php` (stock, one non-comment line), `app/Language` (data arrays, no
  classes or functions to document).
- **Sniffs:**
  - `Squiz.Commenting.ClassComment` - requires a class docblock. Exclude message codes
    `TagNotAllowed`, and any `@author`/`@copyright` requirements.
  - `Squiz.Commenting.FunctionComment` - kept only for the codes that catch a docblock
    contradicting the code: `IncorrectTypeHint` (the documented type disagrees with the
    signature), `IncorrectParamVarName` (the tag names a parameter that is gone),
    `InvalidReturn`, and `WrongStyle` (a plain comment where a docblock belongs).
    Excluded: every tag-requiring code, every tag punctuation and alignment code, and
    `Missing` itself.

**Why `Missing` is excluded.** Three exclusion groups came out of running the sniffer
against the real codebase rather than reasoning about it:

| Group | Count | Verdict |
|---|---|---|
| `Missing` on methods | 60 | Excluded. 8 are `__construct`; the rest are terse helpers (`pct`, `norm`, `pick`, `width`). A forced docblock here is filler. |
| Tag punctuation and alignment (`ParamCommentFullStop`, `ParamCommentNotCapital`, `SpacingAfterParamName`, `SpacingAfterParamType`, `ThrowsNotCapital`) | 89 | Excluded. Whether a tag description ends in a period is not a documentation question, and none were auto-fixable. |
| Contradiction codes (`IncorrectTypeHint`, `WrongStyle`, `IncorrectParamVarName`, `InvalidReturn`) | 61 | Kept. Each flags a docblock that is actively wrong, which is what a tool can genuinely judge. |

This is the layer's governing principle, and it is narrower than the first draft of this
spec claimed: **a linter can see that a docblock exists, but never that it is true.**
Enforcing presence therefore produces coverage metrics, not documentation. Enforcing
contradiction produces something real.
  - `SlevomatCodingStandard.Commenting.UselessFunctionDocComment` - bans tags that
    restate the signature.
  - `SlevomatCodingStandard.Commenting.DocCommentSpacing` - uniform layout.
  - `SlevomatCodingStandard.Commenting.ForbiddenAnnotations` - bans `@author`,
    `@created`, `@version`.

The exact exclusion list is finalized in commit 2 against a fixture file, not guessed
here. See the commit-2 acceptance test.

### File accounting

Every PHP file in `app/` is assigned to exactly one layer or to the exclusion list:

```
Layer 2 (docblocks required)   92   Controllers 22 + Models 25 + Libraries 22
                                    + Support 5 + Helpers 5 + Jobs 4
                                    + Commands 4 + Validation 2 + Filters 2
                                    + Database 1
Layer 3 (view headers)         65   Views
Excluded                       48   Config 46 + Common.php 1 + Language 1
                              ----
                              205
```

Tranche sizes in the rollout below sum to 92 + 65 = 157 touched files.

### Layer 3: Repo check (`scripts/check-comment-style.sh`)

Grep-based; covers what neither tool reaches. Exit non-zero on any hit.

- Every file in `app/Views` opens with a `/**` docblock.
- Every file in `public/css` opens with a `/*` header.
- No em dashes anywhere in `app/` or `public/css`.
- No `----` or `====` divider comments outside `app/Config` and `public/assets`.

## The standard

Written in full to `docs/knowledge/php-practices/comments.md`, summarized in `CLAUDE.md`.

### Class and file docblocks

Two to four lines of prose: what it is, who calls it, why it exists.

```php
/**
 * Assembles view data for every dashboard page. Controllers pick WHICH page;
 * this class gathers the model data and renders the shell view. First place to
 * look when a dashboard page shows the wrong thing.
 */
class DashboardPageBuilder
```

### Method docblocks

Not required. Write one when it carries something the signature cannot, and skip it
otherwise. A docblock on `__construct(private IncomingRequest $request)` or on a
self-describing `encodeToPng()` is noise, and no tool will ask you for it.

Write one when there is a non-obvious side effect (writes an audit row, returns a
redirect instead of HTML), an invariant (rows are archived, never deleted), an array
shape, a unit, or a meaning a bare type cannot carry (what a null signifies).

When you do write one: what it does, plus anything surprising. `@param`/`@return`
**only** when the native type is insufficient.

```php
/**
 * Guards Developer/Admin access, then renders the admin shell on the given tab.
 * Returns a redirect instead of HTML when access is denied.
 *
 * @param string $activePage Tab slug, must match a key in TAB_VIEWS.
 */
public function renderAdminPage(string $activePage): string|RedirectResponse
```

`@param string $id The id.` is a lint error.

### Inline comments

Explain *why*, never *what*. A comment restating the line below it is deleted.

### View headers

Purpose, data source, and any non-obvious rendering constraint. No variable list.

```php
<?php
/**
 * Sector list page (Admin > Reference Data > Sectors).
 *
 * Data comes from sector_management_view_data(); this view never touches a
 * model. Counts are whole-table, not the current page.
 */
```

### View data contracts

The 10 `*_view_data()` functions in `app/Helpers/dashboard_view_helper.php` each get an
array-shape return docblock:

```php
/**
 * Builds the Sectors page bundle: the paged list plus the Add-Sector modal data.
 *
 * @return array{
 *     sectors: list<array<string, mixed>>,
 *     existingShortcodes: list<string>,
 *     activeCount: int,
 *     archivedCount: int,
 *     canManage: bool
 * }
 */
```

Shapes are read off the producing code, never guessed. Where a shape cannot be
determined with certainty from the source, prose describes the keys instead of an
invented `array{}`.

### CSS headers

`public/css/theme.css` already carries the model header. Every stylesheet matches its
form: what it styles, which manifest context loads it, and the scope boundary.

```css
/* Biñan green skin + house component rules layered over the vendored
   SB Admin 1 theme (assets/sb-admin/css/styles.css). Loaded via the `head`
   manifest context (asset_helper.php) so every dashboard shell gets it.
   Scope: theme tokens, shell colors, card tables, pagination, stat cards,
   topbar account menu. Page-specific rules stay in their page CSS files. */
```

Bootstrap 5.3 CSS-variable overrides stay exactly as they are, at both levels:
`:root` tokens in `theme.css`, and component-scoped `--bs-btn-*` blocks in page CSS.
That is Bootstrap's documented no-build customization path and the repo already uses
it correctly.

### Banned everywhere

- Em dashes.
- `// ---- section ----` and `/* ==== section ==== */` dividers. Replace with a blank
  line and a short prose sentence, or split the unit.
- `@author`, `@created`, `@version`.
- Comments describing a change someone wanted rather than what the code does.
- Historical residue (`the old records-multiselect widget was retired...`).
- AI-slop register. Plain language, matching `docs/knowledge/` house style.

### Written for the manual

- View headers name the page by **UI path** (`Admin > Reference Data > Sectors`), not
  file path.
- Controller docblocks state the **user-facing action**, not HTTP mechanics.

## Verification gates

Each gate is a command with a pass criterion. A tranche is not complete until its
gates pass.

### Gate A: token identity

`scripts/assert-tokens-unchanged.php <ref> <path>...` runs `token_get_all()` on each
file at both revisions, strips `T_WHITESPACE`, `T_COMMENT`, `T_DOC_COMMENT`, and
compares the remaining sequences.

**Pass:** zero differing files. Applies to commits 3, 4, 5, 6, 7.

`T_INLINE_HTML` is **not** stripped, so markup outside `<?php` is compared verbatim.
A view header inserted inside a PHP block cannot alter it.

### Gate B: CSS comment identity

`scripts/assert-css-unchanged.sh <ref>` strips `/* ... */` and blank lines from each
stylesheet at both revisions and compares. **Pass:** zero differing files. Applies to
commit 8.

### Gate C: test suite

`vendor/bin/phpunit`. **Pass:** exactly `Tests: 268 ... Failures: 1`, and the one
failure is `ScanViewTest::testNoInlineStyles`. Any other count fails the tranche. Run
after every commit.

### Gate D: routes

`php spark routes`. **Pass:** every route resolves, no errors. Run after commit 5.

### Gate E: Playwright

Per CLAUDE.md, against `php spark serve`, logged in as developer/developer123, at
desktop and 390px.

- **Baseline captured before commit 1**, saved to the session scratchpad:
  `browser_snapshot` of every dashboard tab, plus login and the scanner scan page.
- **Re-captured after commit 7 and after commit 8**, diffed against baseline.
- **Pass:** accessibility-tree diff is empty.
- Flows smoke-tested at both points: login, role redirect, family create, family
  update, audit trail row written, scanner scan, each reference-data tab.

Commits 7 and 8 are the only ones that can move rendered output.

### Gate F: lint clean

`composer lint`. **Pass:** zero errors across all three layers. Required from commit 8
onward.

### Gate G: review

`cavecrew-reviewer` on each tranche's diff. **Pass:** no finding at Critical or Major
that survives triage.

## Rollout

One branch, `chore/doc-standard`, one commit per tranche.

**Commit 0 (pre-work, not a commit):** capture the Gate E Playwright baseline.

1. **`chore: remove spent superpowers plans and summaries`**
   Delete `docs/superpowers/plans/` (22), `summaries/` (1), `summary/` (3). Keep
   `specs/` (20). Add those three paths to `.gitignore` beside the existing
   `.superpowers/` entry at line 161.
   Gates: C.

2. **`chore: add lint tooling and comment standard`**
   composer dev dependencies; `.php-cs-fixer.dist.php`; `phpcs.xml.dist`;
   `scripts/check-comment-style.sh`; `scripts/assert-tokens-unchanged.php`;
   `scripts/assert-css-unchanged.sh`; `docs/knowledge/php-practices/comments.md`;
   CLAUDE.md summary; `composer lint` and `composer lint:fix` scripts.

   **Acceptance test for the sniff config.** Create a throwaway fixture in the
   scratchpad containing: a class with no docblock, a method with no docblock, a
   method whose docblock is only `@param string $id The id.`, and a correctly
   documented method. Run phpcs against it. The config is done when it reports
   exactly three errors and passes the fourth case. Adjust the exclusion list until
   that holds. This resolves the Layer 2 exclusion list deterministically rather than
   by guesswork.

   Gates: C, and the fixture acceptance test.

3. ~~**`style: apply formatter repo-wide`**~~ **DROPPED.** Attempted, then abandoned on
   evidence: the token gate forced 18 rules off, and what remained was docblock
   expansion and alignment padding across 128 files that broke two tests and made
   comments more verbose. See the Layer 1 section. The branch ships no formatting
   commit.

4. **`docs: bring Libraries to standard`** - 22 files.
   Gates: A, C, G.

5. **`docs: bring Controllers to standard`** - 22 files.
   Gates: A, C, D, G.

6. **`docs: bring remaining app code to standard`** - 48 files: `Models` (25),
   `Support` (5), `Helpers` (5), `Jobs` (4), `Commands` (4), `Validation` (2),
   `Filters` (2), `Database` (1). Includes the 10 `*_view_data()` array-shape
   contracts in `app/Helpers/dashboard_view_helper.php`. This closes the whole Layer 2
   scope, so phpcs runs clean from here.
   Gates: A, C, G.

7. **`docs: add purpose headers to views`** - 65 files, 33 of which have no header today.
   Gates: A, C, E, G.

8. **`docs: add headers and prune comments in custom css`** - 11 files, 4 of which have
   no header today. Standardize dividers, delete historical residue.
   Gates: B, C, E, G.

9. **`ci: run lint and tests on pull requests`**
   `.github/workflows/ci.yml` (new; no workflows exist today) running `composer lint`
   and `vendor/bin/phpunit` on PRs to `main`. Lint becomes build-failing only here,
   once the repo is already clean.
   Gates: C, F.

## Out of scope

Recorded so they are not attempted, and so their absence is not mistaken for an
oversight.

- PHPStan, Psalm, Rector.
- Any logic change, refactor, or simplification.
- `app/Config` (stock CI4), `vendor/`, `public/assets/` (vendored Bootstrap, SB Admin,
  DataTables).
- `public/css/` file structure: no renames, no moves, no new directories.
- The 27 JS files in `public/js`.
- Fixing `ScanViewTest::testNoInlineStyles` or the inline styles in `scan.php`.
- The two conflicting brand greens (`--binan-green: #145c3b` in `theme.css` vs
  `--login-green: #176b4d` in `login.css`).
- Any pre-existing dead code found along the way.

The last three go to `docs/knowledge/violations.md` as appended entries, per CLAUDE.md.
