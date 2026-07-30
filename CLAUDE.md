# CLAUDE.md

Biñan Access Card - CodeIgniter 4 app for family/member access-card records,
assistance services, and audit trails (City of Biñan CSWD).

## Core Behavioral Guidelines

Bias toward caution over speed. For trivial tasks, use judgment.

**1. Think before coding.** State assumptions. If multiple interpretations exist,
present them - don't pick silently. If a simpler approach exists, say so. If
unclear, stop and ask.

**2. Simplicity first.** Minimum code that solves the problem. No speculative
features, no abstractions for single-use code, no error handling for impossible
scenarios. If 200 lines could be 50, rewrite.

**3. Surgical changes.** Touch only what you must. Don't refactor working code or
"improve" adjacent formatting. Match existing style. Remove orphans YOUR change
created; leave pre-existing dead code (mention it, don't delete).

**4. Goal-driven execution.** Turn tasks into verifiable goals ("fix bug" → "write
a failing test, then make it pass"). State a brief plan for multi-step work and
verify each step.

## Non-Negotiables

- **No migrations.** DB schema source of truth is the SQL dump (`accesscardV19.sql`).
  Never add migrations or alter schema in code. Seeds (`app/Database/Seeds/`) add
  test login accounts ONLY - never tables/columns.
- **Match the SQL dump.** Column names, allowed enum values, and role names must
  match the dump exactly. The encoding role stores as `encoder` and is labeled
  `Encoder` everywhere: schema, code, and UI.
- **One page, one URL.** Routes carry no role prefix. A page declares its manifest
  key (`['filter' => 'roleNav:<key>']`) and `app/Config/Navigation.php` decides
  which roles reach it. Adding a page means adding a manifest entry, not editing a
  layout.
- **Every family mutation writes an audit trail** (`audit_trails` via
  `Audit/AuditTrailsModel`). Don't bypass it.
- **Controllers decide, libraries build.** Dashboard controllers route pages;
  view-data assembly lives in `Libraries/DashboardPageBuilder.php`. Keep it that way.
- **PHP 8.2+.** Typed signatures everywhere; no `declare(strict_types=1)`
  (matches CI4 appstarter - see `docs/knowledge/php-practices/idioms.md`).
  Respect existing namespace conventions.

## Commands

```bash
php spark routes        # confirm every route resolves to a controller
php spark serve         # dev server (or use XAMPP at app.baseURL)
vendor/bin/phpunit      # full test suite
composer test           # alias for phpunit
composer lint           # docblock sniff + comment-style check (required before a PR)
composer lint:sniff     # phpcs only (phpcs.xml.dist)
composer lint:comments  # view/CSS headers, banned patterns (scripts/check-comment-style.sh)
```

`composer lint` and `composer test` also run on every PR to `main`
(`.github/workflows/ci.yml`), so a red lint blocks the merge, not just review.

`composer lint:format` / `lint:fix` (php-cs-fixer) exist but are **not** part of
`composer lint` and are not in CI. The repo is deliberately unformatted: a
whole-repo reformat produces a diff nobody can review and moves executable
tokens. Do not run `lint:fix` across the repo. See
`docs/knowledge/php-practices/comments.md`.

DB: MySQL `accesscard` @ localhost:3306, user `root` (see `.env`).

### CodeRabbit review (`--agent`)

CodeRabbit is the primary code reviewer for this repo (Copilot silently fails on
large diffs - see below). Config lives in `.coderabbit.yaml` (committed).

```bash
coderabbit auth status                              # confirm signed in first
coderabbit review --base <base-branch> --agent      # structured findings for agents
coderabbit review --base <base-branch> --plain      # human-readable
coderabbit review findings                          # re-print the last review
```

Review workflow (before merging a feature branch):

1. Run `coderabbit review --base <target> --agent` and **wait** for it to finish
   (large diffs take a few minutes; run it in the background and wait on it).
2. Triage every finding - **do not blind-apply.** Follow the
   `superpowers:receiving-code-review` posture: verify each against the code and
   this file's non-negotiables. Skip findings that contradict a documented design
   decision (note them as won't-fix with the reason).
3. Fix the in-scope, genuine bugs; re-run `vendor/bin/phpunit`.
4. Park the rest (pre-existing / out-of-scope) in a GitHub issue for later, citing
   the PR # and branch as a receipt.

Notes:
- CodeRabbit GitHub App is NOT installed on this repo. Reviews run via the local
  CLI (`coderabbit review --agent`); triage off its output, not the PR page.
  `.coderabbit.yaml` still applies (CLI reads it). Retry on `TRPCClientError` - transient.
- `coderabbit auth login` needs an interactive terminal (OAuth). If a shell reports
  "Non-interactive environment", ask the user to run it in a real terminal, or pass
  `--api-key`.
- **Copilot** rejects PRs over ~20,000 changed lines with no inline comments - don't
  wait on it for big branches; rely on CodeRabbit.

### GitHub issue format

- Title states the actual current scope.
- One scope line near the top: `**Scope:** PR # · branch · base · tool`.
- One checkbox style throughout: `- [ ] 🔴 Critical: \`path:line\` - description.`
  (🔴 Critical, 🟠 Major, 🟡 Minor, ⚪ Cleanup, 🔵 UX/needs-decision - emoji + word,
  colon, then `path:line`, then em dash before the description). Fixed items `[x]`
  + `*(Fixed: ...)*`.
- Reference-only material (already-fixed, won't-fix) in a collapsed
  `<details><summary>...</summary>` block.
- Check for an existing issue on the topic before opening a new one; fold in via a
  body edit instead of duplicating. Prefer editing the body over adding comments.
- Close with `gh issue close`, not a comment saying "closing".

## Tests (`tests/`)

Run `vendor/bin/phpunit` before and after changes (DB/session tests skip
without `sqlite3` ext). Smoke-test key flows: login, role redirect, family
create/update, audit log creation.

## UI/UX verification (Playwright)

For any UI/UX change, verify visually with the Playwright MCP against the dev
server (`app.baseURL`, e.g. `http://localhost:8090`; start with `php spark
serve` if down). Navigate, log in (developer/developer123), then
`browser_snapshot` (accessibility tree, best for asserting structure) or
`browser_take_screenshot` each affected tab at desktop and 390px mobile widths;
compare against Manage Records, the design source of truth. Needs a browser -
if the MCP reports Chromium missing, run `npx playwright install chrome`.

## Retrieval (before editing app code)

Before editing `app/Controllers|Models|Views|Libraries` or routes, use the
`binan-conventions` skill (`.claude/skills/binan-conventions/SKILL.md`) -
decision table + grep index over `docs/knowledge/`:

- Framework API (CI4 / Bootstrap 5) → Context7 MCP (`.mcp.json`); CI4 library
  serves LATEST docs - cross-check pins in `docs/knowledge/sources.md`.
- Repo conventions (MVC boundaries, audit trail, routing, models, views) →
  `docs/knowledge/binan-conventions/`
- UI / SBAdmin styling → `docs/knowledge/sbadmin/` (target theme: SB Admin 1)
- PHP idioms → `docs/knowledge/php-practices/`
- Known mess → `docs/knowledge/violations.md` (canonical punch-list; tick
  items you fix, append verified new ones)

`DashboardPageBuilder` assembles all dashboard view data - start debugging
there. Full file map: `PROJECT_STRUCTURE.md`.

## Comments and docblocks

Every class, view, and stylesheet carries a docblock. A method carries one
only when it says something the signature cannot. Prose
first: what it is, who calls it, why it exists. `@param`/`@return` only when
the native type is not enough (array shapes, what a null means, units).
`@param string $id The id.` is a lint error, not a style preference.

Views state purpose and data source, no variable list. The data contracts
live on the `*_view_data()` functions in `app/Helpers/dashboard_view_helper.php`.

Banned: em dashes, `---- ----` dividers, `@author`/`@created`/`@version`,
comments recording a change someone wanted, AI-slop register.

Run `composer lint` before any PR. Full standard:
`docs/knowledge/php-practices/comments.md`.
