# Code Documentation Standard and Lint Tooling

**Date:** 2026-07-28
**Status:** Approved, ready for planning

## Problem

The codebase has no documentation standard. Three symptoms:

1. **Inconsistent PHP docblocks.** 68 of 69 files in `Controllers`/`Models`/`Libraries`
   carry some docblock, but there is no agreed shape. Some are three sentences of
   prose, some are one line, none use tags. Nothing enforces any of it.
2. **Comments written to request a change rather than explain code**, concentrated in
   `app/Views`. These read as notes-to-self, not documentation.
3. **`// ---- section ----` dividers** used as ad-hoc structure. 284 occurrences, but
   276 of them are in stock CI4 `app/Config` files that must not be touched. Only 8
   non-Config files actually carry them.

No linter exists. No CI workflow exists. `vendor/bin` holds only `phpunit` and
`php-parse`.

Measured baseline as of 2026-07-28:

| Location | PHP files |
|---|---|
| `app/Controllers` | 22 |
| `app/Models` | 25 |
| `app/Libraries` | 22 |
| `app/Support` | 5 |
| `app/Jobs` | 4 |
| `app/Views` | 65 |
| `app/Config` (excluded, stock CI4) | 46 |
| `app/Helpers`, `Validation`, `Database` | 8 |

Em dashes in `app/`: **0**. Already clean, so the standard only has to keep them out.

## Goals

- One documented comment standard, enforced by tooling rather than by memory.
- Every class, public method, and view carries a docblock that helps a developer.
- No docblock that merely restates the signature.
- **Zero behavior change.** This is a cleanup. Executable tokens must be identical
  before and after, and that must be proven, not asserted.
- Docblocks written so a user manual can later be harvested from them.

## Non-Goals

- PHPStan or any static type analysis. Deferred; would surface hundreds of
  pre-existing errors and needs its own triage effort.
- Rewriting, refactoring, or simplifying any logic.
- Touching `app/Config` (stock CI4) or `vendor`.
- Deleting pre-existing dead code discovered along the way. Log it in
  `docs/knowledge/violations.md` per CLAUDE.md.

## Tooling

Versions verified against Packagist on 2026-07-28. All require PHP ^8.2 or looser and
are mutually compatible.

| Package | Version | Role |
|---|---|---|
| `friendsofphp/php-cs-fixer` | v3.95.17 | Formatting, auto-fix |
| `codeigniter/coding-standard` | v1.9.2 | CodeIgniter's own php-cs-fixer ruleset |
| `squizlabs/php_codesniffer` | 4.0.1 | Sniff engine |
| `slevomat/coding-standard` | 8.31.0 | Docblock sniffs (requires phpcs ^4.0.1) |

All four go in `require-dev`.

### Why slevomat

`SlevomatCodingStandard.Commenting.UselessFunctionDocComment` errors on any
`@param`/`@return` that only restates what the native signature already says. That is
the "no commenting for the sake of commenting" rule, machine-enforced. Without it,
the useful-tags-only standard would be unenforceable and would decay.

## Architecture: three enforcement layers

Split by what is actually checkable. A linter is deterministic about structure and
blind to prose quality, so the layers cover structure and a written standard covers
the rest.

### Layer 1: Formatter (php-cs-fixer)

Config: `.php-cs-fixer.dist.php`, based on `codeigniter/coding-standard`.

- **Scope:** `app/` (excluding `app/Views` and `app/Config`), `tests/`, `tools/`.
- **`app/Views` excluded** because php-cs-fixer behaves unpredictably on files that
  are majority inline HTML.
- **`app/Config` excluded** because those are stock CI4 files.
- **Ruleset constraint:** only rules affecting whitespace, comments, and import
  order. Any rule that reorders or rewrites executable tokens is dropped, because it
  would fail the token-identity gate below.

### Layer 2: Sniffer (phpcs + slevomat)

Config: `phpcs.xml.dist`.

- **Scope:** `app/Controllers`, `app/Models`, `app/Libraries`, `app/Support`,
  `app/Jobs`, `app/Helpers`, `app/Validation`. Not Views, not Config.
- **Sniffs:**
  - `SlevomatCodingStandard.Commenting.UselessFunctionDocComment` — bans tags that
    restate the signature.
  - `SlevomatCodingStandard.Commenting.DocCommentSpacing` — uniform docblock layout.
  - `SlevomatCodingStandard.Commenting.ForbiddenAnnotations` — bans `@author`,
    `@created`, `@version`.
  - `Generic.Commenting.DocComment` — docblock formatting.
  - Custom `BinanStandard.Commenting.RequiredDocComment` — see below.

### Layer 3: Repo check (`scripts/check-comment-style.sh`)

Grep-based, covers what the other two cannot reach.

- View files in `app/Views` must open with a docblock.
- No em dashes anywhere in `app/`.
- No `// ---- ... ----` dividers outside `app/Config`.

Shell rather than PHP because the checks are pattern matches over text, and Views
cannot be sniffed usefully.

### The one piece of new code

PHPCS's stock `Squiz.Commenting.FunctionComment` requires `@param` for *every*
argument, which directly contradicts the standard. Presence-without-required-tags
therefore needs a custom sniff:

`tools/phpcs/BinanStandard/Sniffs/Commenting/RequiredDocCommentSniff.php`

Roughly 60 lines. Responsibility: every `class`, `interface`, `trait`, and every
`public` method must be preceded by a doc comment. It checks presence only; slevomat
handles content quality. It must not inspect or require tags.

## The standard

Written in full to `docs/knowledge/php-practices/comments.md`, summarized in
`CLAUDE.md`.

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

What it does, plus anything surprising. `@param` and `@return` **only** when the
native type is insufficient: array shapes, what a null means, units, enum-ish
strings, side effects.

```php
/**
 * Guards Developer/Admin access, then renders the admin shell on the given tab.
 * Returns a redirect instead of HTML when access is denied.
 *
 * @param string $activePage Tab slug, must match a key in TAB_VIEWS.
 */
public function renderAdminPage(string $activePage): string|RedirectResponse
```

`@param string $id The id.` is a lint error, not a style preference.

### Inline comments

Explain *why*, never *what*. A comment that restates the line below it gets deleted.

### View headers

Every file in `app/Views` opens with a block naming the page, its data source, and
the variables it expects.

```php
<?php
/**
 * Sector list page (Admin > Reference Data > Sectors).
 *
 * Data comes from sector_management_view_data(); this view never touches a
 * model. Counts are whole-table, not the current page.
 *
 * Expects: $sectors, $existingShortcodes, $pager, $canManage
 */
?>
```

### Banned

- Em dashes.
- `// ---- section ----` dividers. Replace with a blank line and a short prose
  sentence, or split the method.
- `@author`, `@created`, `@version`.
- Comments describing a change someone wanted rather than what the code does.
- AI-slop register. Plain language for human readers, matching
  `docs/knowledge/` house style.

### Written for the manual

Docblocks are the future source material for a user manual, so:

- View headers name the page by its **UI path** (`Admin > Reference Data > Sectors`),
  not its file path.
- Controller docblocks state the **user-facing action**, not the HTTP mechanics.

Costs nothing now; means a manual outline can be harvested rather than rewritten.

## Verification

### Token-identity gate

The primary control. `scripts/assert-tokens-unchanged.php` runs `token_get_all()` on
every touched file at both revisions, strips `T_WHITESPACE`, `T_COMMENT`, and
`T_DOC_COMMENT`, and compares the remaining token sequences. Any difference fails.

- Commits 4 through 7 (all docblock and comment work): must pass exactly. This is the
  deterministic proof of zero behavior change.
- Commit 3 (formatter): the gate applies too. If a php-cs-fixer rule trips it, that
  rule is rewriting executable tokens and gets dropped from the ruleset. The gate is
  not relaxed to accommodate a rule.

### Test suite

`vendor/bin/phpunit` after every commit. `php spark routes` after commit 5.

### Playwright

Per CLAUDE.md, against `php spark serve`, logged in as developer/developer123, at
desktop and 390px widths.

- **Baseline captured before any commit**, saved to the session scratchpad.
- **Re-captured after commit 7** and diffed against the baseline. Views are the only
  commits that can change rendered output; a diff means a header block broke markup.
- Flows smoke-tested: login, role redirect, family create/update, audit trail write,
  scanner scan, reference-data tabs.

### Review

`cavecrew-reviewer` subagent per tranche.

This is a **deliberate deviation** from CLAUDE.md's CodeRabbit-first rule, recorded
here so it is not later "corrected". Reasoning: the review question in this branch is
narrow (does this docblock match the code, is this comment noise), the diff provably
contains no logic changes, and one-line-per-finding output suits a 65-file comment
sweep. CodeRabbit would spend minutes hunting logic bugs in a diff that the token
gate has already proven has none.

## Rollout

One branch, `chore/doc-standard`, one commit per tranche so review can proceed
commit-by-commit rather than against a single multi-thousand-line diff.

1. **`chore: remove spent superpowers plans and summaries`**
   Delete `docs/superpowers/plans/` (22 files), `summaries/` (1), `summary/` (3).
   Keep `specs/` (20) — specs record why decisions were made, which git history does
   not capture well. `.superpowers/` is already gitignored (`.gitignore:161`); add
   `docs/superpowers/plans/` and the summary directories alongside it.

2. **`chore: add lint tooling and comment standard`**
   composer dev dependencies, `.php-cs-fixer.dist.php`, `phpcs.xml.dist`, the custom
   sniff, `scripts/check-comment-style.sh`,
   `scripts/assert-tokens-unchanged.php`, `docs/knowledge/php-practices/comments.md`,
   CLAUDE.md summary, and `composer lint` / `composer lint:fix` scripts.

3. **`style: apply formatter repo-wide`**
   Purely mechanical. No prose changes. Token gate plus phpunit.

4. **`docs: bring Libraries to standard`** — 22 files.

5. **`docs: bring Controllers to standard`** — 22 files.

6. **`docs: bring Models, Support, and remaining app code to standard`** — 42 files:
   `app/Models` (25), `app/Support` (5), `app/Jobs` (4), `app/Helpers` (5),
   `app/Validation` (2), `app/Database` (1). Everything left in the Layer 2 scope, so
   that the sniffer runs clean before commit 8 turns it into a build failure.

7. **`docs: add data-contract headers to Views`** — 65 files. Largest and riskiest
   tranche; the only one touching live markup.

8. **`ci: run lint and tests on pull requests`**
   `.github/workflows/ci.yml` (new; no workflows exist today) running `composer lint`
   and `vendor/bin/phpunit` on PRs to `main`. Lint is set to fail the build only at
   this point, once the repo is already clean.

## Risks

- **Commit 7 is 65 files of view edits.** Mitigated by the token gate (proves no PHP
  behavior change) and the Playwright baseline diff (catches broken markup, which the
  token gate cannot see since HTML outside `<?php` is `T_INLINE_HTML` and is compared
  as-is).
- **The custom sniff is new code** and the only place a bug can originate. It is
  read-only over the token stream and cannot alter source.
- **Big-bang rollout was chosen over phased.** Accepted by the user with the review
  burden understood; per-tranche commits are the mitigation.
