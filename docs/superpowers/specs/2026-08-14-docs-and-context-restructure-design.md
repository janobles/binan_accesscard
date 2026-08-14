# Documentation and Agent-Context Restructure - Design

**Date:** 2026-08-14
**Status:** Approved (design)
**Author:** JP + Claude

## Problem

Three separate problems share one root cause: nobody ever decided what the
repository's documentation is *for*, so every document was written for whoever
needed it at the time.

**The docs tree has no shape.** `docs/` holds three loose files
(`networking.md`, `running-the-system.md`, `UI_MODERNIZATION_PRD.md`), a
fourteen-file `knowledge/` tree written exclusively for agents, and a dated
archive of specs and plans. Nothing tells a reader where to start, and there is
no order to read things in. Two more documents sit at the repository root as
plain text (`IMPORT-VALIDATIONS.txt`, `PROJECT_STRUCTURE.md`), where they are
invisible to anyone browsing `docs/`.

**The README is still the CodeIgniter appstarter README.** Roughly two thirds of
it explains what CodeIgniter is, how to install it from Composer, and where the
framework's forums are. A developer joining the project, or an IT department
being handed the system, learns almost nothing about the system itself.

**The agent context file has drifted into prose.** `CLAUDE.md` is 174 lines and
mixes three different kinds of content: hard rules that must never be violated,
long procedural workflows that matter only at one moment (the CodeRabbit review
loop, the Playwright verification loop), and soft behavioural framing ("bias
toward caution over speed", "think before coding"). The workflows are paid for
on every single turn even though they are needed once per branch. The soft
framing is the weakest part of the file: it asks for a disposition rather than
stating a testable rule, and a disposition is exactly what an agent rationalises
its way around.

There is also a factual error to correct in passing: `knowledge/sources.md` pins
Bootstrap at 5.3.3, but the SB Admin bundle actually loaded by the application is
5.2.3. Classes that only exist in 5.3 silently do nothing, and the pin is what
makes that failure confusing rather than obvious.

## Goals

- One `docs/` tree with a deliberate reading order, written in the register of a
  technology documentation site: prose, human, informative, technical without
  being robotic.
- Documentation good enough that a future CSWD-facing user manual can be written
  by drawing on it, rather than by rediscovering the system.
- A README aimed at the three people who will actually open it: a developer
  joining, a developer receiving a turnover, and the IT department that will
  eventually absorb the system.
- An agent context file that carries only always-true rules plus a routing table,
  with every long procedure moved into a skill that loads when its situation
  arrives.
- `AGENTS.md` as the real file so any vendor's agent reads the same instructions.

## Non-goals

- No application code changes. Nothing under `app/`, `public/`, or `tests/`
  changes behaviour. The only executable files touched are the two lint scripts
  whose paths move.
- No new documentation tooling. No static site generator, no docs build step.
  GitHub's markdown and mermaid rendering is the target renderer.
- The CSWD end-user manual is not written here. This work makes it writable
  later.
- `docs/superpowers/` is not touched. It is a dated archive of design decisions
  and stays exactly as it is.

## Decisions taken during design

**`docs/knowledge/` is not a retrieval system and does not need to stay
separate.** The RAG pipeline spec (2026-07-06) states the non-goal plainly: no
vector database, no embeddings, retrieval is Grep and Read over curated
markdown. What exists is 1,366 lines of markdown, a keyword index inside a skill,
and the Context7 MCP. Nothing about that requires a parallel tree.

**The two audiences are served by two registers inside one file, not by two
trees.** Each chapter has a prose body for a human reader and a terse `## Rules`
section at the end carrying the old `knowledge/` content near-verbatim. The
`conventions` skill routes a keyword to `docs/NN-chapter.md#rules`, so an agent
reads an anchor rather than a chapter, and per-retrieval token cost does not grow
just because humans got prose.

**Three files stay registers rather than becoming chapters.**
`violations.md` is a mutable punch-list ticked per pull request, `sources.md` is
a version-pin table refreshed on a dependency bump, and `comments.md` is the
standard `composer lint` mechanically enforces. Prose would damage all three.
They move to `docs/reference/` unchanged in kind.

**`AGENTS.md` is canonical; `CLAUDE.md` becomes a symlink to it.** `AGENTS.md`
is the cross-vendor convention and Claude Code follows symlinks. Git stores the
link as a mode-120000 blob, which resolves on macOS and Linux; Windows clones
need `core.symlinks=true`, the default in Git for Windows when developer mode is
on. Claude-specific content stays in the file because it is harmless to other
tools and it is the instruction set that matters most today.

**Hard rules replace behavioural framing, each carrying one clause of why.** A
rule an agent can test itself against survives a long session; a disposition does
not. The reason clause is kept to a single line so the rule stays scannable, and
the full rationale lives in the chapter the rule links to.

**Recommendations are never ranked by how long they take to build.** This is a
standing instruction from the project owner and it becomes a rule in `AGENTS.md`.
Options are judged on correctness, maintainability, runtime cost, and fit with
the repository's constraints. Effort is stated separately when it genuinely
matters, never as a reason for a ranking.

## The documentation tree

Chapters are numbered in decades so a chapter can be inserted later without
renumbering its neighbours.

```
docs/
  README.md                     index and reading order
  00-introduction.md            what the system is, who uses it, domain glossary
  01-architecture.md            CI4 layout, feature subnamespaces, request
                                lifecycle, filters, the controller/library boundary
  02-database.md                mermaid ERD over 17 tables, dump-is-truth,
                                sql/patches, dump versioning
  03-setup.md                   prerequisites, importing the dump, .env,
                                the three ways to run the app
  04-networking.md              the baseURL rule, Cloudflare tunnel, LAN access
  05-background-worker.md       job_queue, the worker scripts, cron install,
                                why large imports need it
  06-operations-and-handover.md production deployment, backup and restore,
                                the worker as a service, account administration,
                                what breaks and where to look

  10-navigation-and-access.md   roles, the Navigation manifest, roleNav,
                                one page one URL
  11-records.md                 families and members, the entry stepper,
                                the datatable, search
  12-import.md                  Excel import, the validation reference, the
                                review screen, the tools/*.php scripts
  13-reference-data.md          barangay, category, sector, services, subsidy types
  14-access-cards.md            QR control numbers, card generation
  15-distribution.md            batches, schedule windows, eligibility,
                                the scanner kiosk, the scan flow
  16-audit-trails.md            what is logged and the rule against bypassing it
  17-dashboard-and-reports.md   DashboardPageBuilder, KPI assembly, exports

  20-frontend.md                layout, components, the SB Admin adapter,
                                the design system, btn() and the toolbar standard
  21-php-style.md               PHP 8.2 idioms, typed signatures, no strict_types
  22-testing.md                 phpunit, dual-backend CI, no forge() in tests
  23-performance.md             indexes, cache TTL, the EXPLAIN workflow

  reference/
    version-pins.md             was knowledge/sources.md, Bootstrap pin corrected
    violations.md               unchanged live punch-list
    comment-standard.md         was knowledge/php-practices/comments.md

  superpowers/                  untouched dated archive
```

### Where the existing content goes

| Source | Destination |
|---|---|
| `docs/running-the-system.md` | `03-setup.md`, worker section to `05-background-worker.md` |
| `docs/networking.md` | `04-networking.md` |
| `PROJECT_STRUCTURE.md` | `01-architecture.md` plus the module chapters, then deleted |
| `IMPORT-VALIDATIONS.txt` | `12-import.md`, rewritten into the handbook register |
| `knowledge/binan-conventions/mvc-boundaries.md` | `01-architecture.md#rules` |
| `knowledge/binan-conventions/models.md` | `02-database.md#rules` |
| `knowledge/binan-conventions/routing-subnamespaces.md` | `10-navigation-and-access.md#rules` |
| `knowledge/binan-conventions/audit-trail.md` | `16-audit-trails.md#rules` |
| `knowledge/binan-conventions/scanner-batches.md` | `15-distribution.md#rules` |
| `knowledge/binan-conventions/ui-design-system.md` | `20-frontend.md#rules` |
| `knowledge/binan-conventions/views-bootstrap.md` | `20-frontend.md#rules` |
| `knowledge/sbadmin/adapter.md`, `target-theme.md` | `20-frontend.md` |
| `knowledge/binan-conventions/performance.md` | `23-performance.md#rules` |
| `knowledge/php-practices/idioms.md` | `21-php-style.md#rules` |
| `knowledge/php-practices/comments.md` | `reference/comment-standard.md` |
| `knowledge/sources.md` | `reference/version-pins.md` |
| `knowledge/violations.md` | `reference/violations.md` |

### Deletions

- `docs/UI_MODERNIZATION_PRD.md`. It is superseded and now actively wrong: it
  makes green the primary action colour, while the shipped standard is a blue
  search button and `#198754` for add, with green never used on buttons. Its
  surviving content moves into `20-frontend.md`: the 44px touch-target floor, the
  LGU demographic rationale for compact data views, tokens-only with no ad-hoc
  hex, and the rejection of glassmorphism.
- `PROJECT_STRUCTURE.md`, after its content lands in the chapters.
- `IMPORT-VALIDATIONS.txt`, after it lands in `12-import.md`.
- `todos.txt` and `inbox.txt.tuxedo-lock`, both untracked, from a TUI tool no
  longer in use. `*.tuxedo-lock` is added to `.gitignore`.

### Voice

The handbook reads like the documentation of a technology a person chose to use:
it explains the thing, then how to work with it, in plain sentences with a
human behind them. It assumes a technical reader without assuming familiarity
with this system or with Philippine local-government social services. Concepts
are introduced before they are used. The domain glossary in `00-introduction.md`
is what lets later chapters say "barangay" and "subsidy batch" without stopping
to explain.

The repository's existing comment rules apply to the docs as well: no em dashes,
no `---- ----` dividers, no AI-slop register. The `writing-voice` skill records
this register so it survives contact with future sessions.

## The context file

`AGENTS.md` becomes the real file. `CLAUDE.md` becomes a symlink to it. Target
length is roughly 80 lines, down from 174, with nothing lost - every removed line
lands in a skill or a chapter.

Structure:

1. **What this is.** Two sentences.
2. **Hard rules.** Numbered and imperative, each with one clause of why and a
   link to the chapter that explains it in full. The set: no migrations, schema
   changes are patch files; names and enum values match the dump exactly; every
   family mutation writes an audit row; controllers decide and libraries build;
   one page, one URL, declared in the manifest; PHP 8.2 typed signatures with no
   `declare(strict_types=1)`; escape every dynamic value in a view; never run
   `lint:fix` across the repository; never rank a technical option by how long it
   takes to build.
3. **Skill routing table.** Situation to skill. This is the deliberately verbose
   part, because it is what makes everything else loadable on demand.
4. **Commands.** The six composer and spark commands.
5. **Where the docs live.** One line pointing at `docs/README.md`.

## Project skills

Seven skills under `.claude/skills/`, built with the `skill-creator` skill so
each gets a description tuned for reliable triggering.

| Skill | Fires when | Absorbs |
|---|---|---|
| `conventions` | before editing anything under `app/` or the routes | the current `binan-conventions` skill, retargeted at chapter anchors |
| `code-review` | a branch is ready for review or merge | the CodeRabbit CLI workflow, the triage posture, the GitHub issue format, and branch hygiene: sync local main before branching, `composer lint` before a PR, never `lint:fix` repo-wide |
| `ui-verification` | any UI or UX change needs verifying | the Playwright MCP loop: dev server, login, snapshot versus screenshot, desktop and 390px, comparison against Manage Records, the missing-Chromium fix |
| `writing-voice` | writing docs, or writing copy that ships in the UI | two registers: the handbook register for `docs/`, and the plain register for CSWD-facing interface copy, plus the banned patterns |
| `run-app` | the app needs to be started or driven | XAMPP versus `spark serve` versus tunnel, `PHP_CLI_SERVER_WORKERS=8`, using the intl-enabled `php` rather than XAMPP's, port 8090, starting the queue worker. The built-in `run` skill looks for a project skill first, and currently finds none |
| `database-dump` | cutting a dump version or changing schema | patch-file naming and ordering, backfill sequence, `DumpSchema::dumpPath()` in tests, the drop-import-demo reset path |
| `testing-ci` | writing or debugging tests, or a red CI run | the dual-backend job, the rule against `forge()` schema in tests, reproducing the MariaDB job locally against `accesscard_ci` with dotted environment variables |

`binan-conventions` is renamed rather than deleted. The `bootstrap` skill is a
generic Bootstrap-expert prompt with no repository knowledge and does not replace
it; both stay.

The import tooling under `tools/` gets no skill. It is documented as a section of
`12-import.md`.

## Scripts

- `scripts/check-knowledge-cites.sh` becomes `scripts/check-doc-cites.sh` and
  scans `docs/` rather than `docs/knowledge/`. Every backtick-wrapped
  `path:line` cite must still resolve, and the cites move with their content.
- `composer lint:comments` calls `scripts/check-comment-style.sh`, which
  references the comment standard by path. That reference is updated to
  `docs/reference/comment-standard.md`.

## Verification

This branch changes no application behaviour, so verification is about broken
references rather than broken code.

1. `vendor/bin/phpunit` passes, unchanged from `main`.
2. `composer lint` passes, including the comment-style script after its path
   update.
3. `bash scripts/check-doc-cites.sh` reports every cite resolving.
4. No markdown link in `docs/`, `README.md`, or `AGENTS.md` points at a path that
   no longer exists. A link sweep over the tree confirms this.
5. `grep -rn "docs/knowledge\|PROJECT_STRUCTURE\|IMPORT-VALIDATIONS"` over the
   repository, excluding `vendor/`, `node_modules/`, and `docs/superpowers/`,
   returns nothing.
6. `CLAUDE.md` resolves through the symlink and reads identically to `AGENTS.md`.
7. The `02-database.md` ERD renders on GitHub. Mermaid syntax is verified against
   the actual foreign keys in `accesscardV22.sql`, not from memory.
8. Each of the seven skills is checked to have a description that triggers on the
   situations listed above and not on unrelated ones.

## Risks

**Reference rot.** Roughly forty paths move at once. Mitigated by items 3 to 5 of
the verification list, all mechanical.

**Retrieval regression.** If the `## Rules` sections are softened into prose,
agents lose the terse rulebook that currently works. The rule is that rules
sections are copied near-verbatim; prose goes above them, never inside them.

**Symlink on Windows.** A Windows clone without `core.symlinks=true` gets a text
file containing the string `AGENTS.md` instead of a link. Noted in `03-setup.md`
so the failure is recognisable rather than mysterious.

**Scope.** One branch covering docs, README, context file and seven skills is
large, but the parts are coupled: the skills are defined by what leaves the
context file, and the context file routes to chapters that must exist first.
Splitting it would mean landing a context file that points at paths not yet
created.

## Sequence

1. Build the `docs/` tree: move and rewrite content, chapter by chapter, and
   write `docs/README.md`.
2. Correct the Bootstrap pin and fold the surviving PRD content into
   `20-frontend.md`.
3. Retarget and rename the two scripts.
4. Write the seven skills.
5. Rewrite `AGENTS.md`, replace `CLAUDE.md` with the symlink.
6. Rewrite `README.md`.
7. Delete the superseded files, update `.gitignore`.
8. Run the verification list.
