# Codebase RAG Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the grep-based retrieval layer (Context7 MCP + `docs/knowledge/` corpus + router skill + slimmed CLAUDE.md + AGENTS.md) specified in `docs/superpowers/specs/2026-07-06-codebase-rag-pipeline-design.md`.

**Architecture:** Five planes loaded cheapest-first: AGENTS.md → CLAUDE.md (non-negotiables + router) → Context7 MCP (live framework docs) → `docs/knowledge/` (repo-specific conventions, retrieved on demand) → `.claude/skills/binan-conventions/SKILL.md` (decision table + grep index). No vector store, no embeddings, no runtime code.

**Tech Stack:** Markdown docs, bash cite-check script, Context7 MCP (already connected), git.

## Global Constraints

- **No app runtime code changes.** This is tooling + docs only. `vendor/bin/phpunit` must stay green untouched.
- **No migrations, no schema changes** (repo non-negotiable; not applicable here but binding).
- Pinned versions (source of truth, verified 2026-07-06): **CodeIgniter v4.7.3** (`composer.lock`), **PHP 8.2.30**, **Bootstrap v5.3.3** (vendored at `public/assets/bootstrap/css/bootstrap.min.css`, version string in file header).
- Every rule in a convention doc **must cite a real `path:line`** that resolves (checked by `scripts/check-knowledge-cites.sh`).
- Convention docs: **one concern per file, ≤ ~150 lines**, structure = Rule → canonical snippet (path:line) → anti-pattern seen in repo → why.
- `violations.md` format matches CLAUDE.md issue convention: `- [ ] 🟠 Major: \`path:line\` — description.` (🔴 Critical, 🟠 Major, 🟡 Minor, ⚪ Cleanup, 🔵 UX/needs-decision).
- CLAUDE.md slimming: **no rule lost, only relocated** — non-negotiables stay verbatim.
- UI target theme is **SB Admin 1** (startbootstrap-sb-admin v7+, Bootstrap 5-based). SB Admin 2 rejected (Bootstrap 4.6). Do not reopen this decision.
- Context7 serves **latest** docs, not pinned versions. Every doc plane that mentions Context7 must carry the cross-check-against-`sources.md` caveat.
- Commit message convention: conventional commits (`docs:`, `chore:`, `feat:`), body only when "why" isn't obvious.

---

### Task 1: Branch setup + Context7 Bootstrap verification

**Files:**
- No file changes; branch + verification only.

**Interfaces:**
- Produces: feature branch `feat/rag-pipeline` off up-to-date `main`; confirmed Context7 answers for both CI4 and Bootstrap 5.

- [ ] **Step 1: Sync local main, create branch**

Local main is known to lag merged PRs (past incident). Sync first:

```bash
git checkout main
git fetch origin
git reset --hard origin/main
git checkout -b feat/rag-pipeline
```

- [ ] **Step 2: Verify Context7 connection**

Run: `claude mcp list`
Expected: `context7 ✔ Connected` (or equivalent connected status).

If not connected: check `CONTEXT7_API_KEY` is exported in the shell (`echo ${CONTEXT7_API_KEY:+set}`); keyless mode also works (rate-limited) — a missing key is not a blocker.

- [ ] **Step 3: Verify Context7 answers for Bootstrap 5**

Using the MCP tools in-session:
1. `resolve-library-id` with libraryName `Bootstrap`, query "Bootstrap 5.3 card and table component classes".
2. `query-docs` against the returned ID (expect something like `/twbs/bootstrap`) with query "card component markup and utility classes".

Expected: relevant Bootstrap 5.3 doc snippets returned. (CI4 already verified 2026-07-06 via `/codeigniter4/userguide` — do not re-verify.)

Record the resolved Bootstrap library ID — Task 2 writes it into `sources.md`.

- [ ] **Step 4: Baseline test run**

Run: `vendor/bin/phpunit`
Expected: green (some DB/session tests skip without `sqlite3` ext — skips are fine). Record the pass/skip counts; the final task compares against them.

No commit (no file changes).

---

### Task 2: Scaffold `docs/knowledge/`, write `sources.md`, add cite-check script

**Files:**
- Create: `docs/knowledge/binan-conventions/` (dir), `docs/knowledge/sbadmin/` (dir), `docs/knowledge/php-practices/` (dir)
- Create: `docs/knowledge/sources.md`
- Create: `scripts/check-knowledge-cites.sh`

**Interfaces:**
- Produces: `docs/knowledge/` tree all later tasks write into; `sources.md` (the version-pin anchor every doc references); `scripts/check-knowledge-cites.sh` (exit 0 = all `path:line` cites in `docs/knowledge/**/*.md` resolve) used as the "test" in every doc-authoring task.

- [ ] **Step 1: Write the cite-check script (the failing test for this plane)**

Create `scripts/check-knowledge-cites.sh`:

```bash
#!/usr/bin/env bash
# Verify every `path:line` cite in docs/knowledge resolves to a real file and line.
# Cite format in docs: `app/Path/File.php:123` (backtick-wrapped, repo-relative).
set -u
cd "$(dirname "$0")/.."
fail=0
count=0
while IFS=: read -r file line; do
  [ -z "$file" ] && continue
  count=$((count + 1))
  if [ ! -f "$file" ]; then
    echo "MISSING FILE: $file (cited as $file:$line)"
    fail=1
  elif [ "$(wc -l < "$file" | tr -d ' ')" -lt "$line" ]; then
    echo "LINE OUT OF RANGE: $file:$line"
    fail=1
  fi
done < <(grep -rhoE '`(app|public|tests|scripts)/[^`]+:[0-9]+`' docs/knowledge --include='*.md' 2>/dev/null | tr -d '`' | sort -u)
if [ "$fail" -eq 0 ]; then
  echo "OK: $count unique cites resolve."
fi
exit $fail
```

Then: `chmod +x scripts/check-knowledge-cites.sh`

- [ ] **Step 2: Run it — expect trivially green (no docs yet)**

Run: `bash scripts/check-knowledge-cites.sh`
Expected: `OK: 0 unique cites resolve.` exit 0.

- [ ] **Step 3: Write `docs/knowledge/sources.md`**

```markdown
# Version Pins & Canonical Sources

Freshness anchor for `docs/knowledge/`. On a dependency bump, refresh the
affected cheatsheets and update this file.

## Pins (source of truth in parentheses)

| Dependency    | Pinned version | Source of truth                                          |
|---------------|----------------|----------------------------------------------------------|
| CodeIgniter 4 | v4.7.3         | `composer.lock` (`codeigniter4/framework`)               |
| PHP           | 8.2.30         | local runtime; repo floor is PHP 8.2+ (CLAUDE.md)        |
| Bootstrap     | v5.3.3         | `public/assets/bootstrap/css/bootstrap.min.css` (header) |
| UI theme      | SB Admin 1 (startbootstrap-sb-admin v7+) | design decision, spec 2026-07-06 |

## Canonical doc URLs

- CodeIgniter 4 user guide: https://codeigniter.com/user_guide/
- Bootstrap 5.3: https://getbootstrap.com/docs/5.3/
- SB Admin 1: https://startbootstrap.com/template/sb-admin
- PHP manual: https://www.php.net/manual/en/

## Context7 (live framework docs)

- CodeIgniter 4: `/codeigniter4/userguide`
- Bootstrap: `<ID recorded in Task 1>`

**Caveat: Context7 serves the LATEST docs, not the pinned version above.**
Fine while latest matches the pins (true as of 2026-07-06). Before trusting a
Context7 answer for anything version-sensitive, cross-check against the pins
in this file. If the repo lags a future major, prefer the pinned canonical
URL over Context7.
```

Replace `<ID recorded in Task 1>` with the actual Bootstrap library ID from Task 1 Step 3.

- [ ] **Step 4: Create the directory skeleton**

```bash
mkdir -p docs/knowledge/binan-conventions docs/knowledge/sbadmin docs/knowledge/php-practices
```

(Dirs stay empty until their tasks; git tracks them once files land — no `.gitkeep` needed since each dir gets files in Tasks 3–7.)

- [ ] **Step 5: Verify and commit**

Run: `bash scripts/check-knowledge-cites.sh` — Expected: exit 0.

```bash
git add scripts/check-knowledge-cites.sh docs/knowledge/sources.md
git commit -m "docs(rag): scaffold docs/knowledge with sources.md and cite-check script"
```

---

### Task 3: Audit pass → seed `violations.md`

**Files:**
- Create: `docs/knowledge/violations.md`

**Interfaces:**
- Consumes: severity/checkbox format from Global Constraints.
- Produces: `docs/knowledge/violations.md` — the diagnostic punch-list later convention docs cite as "anti-pattern seen in repo" evidence.

- [ ] **Step 1: Mine closed issue #7 for still-unfixed items**

```bash
gh issue view 7 --json body -q .body > /tmp/issue7-body.md
grep -E '^\s*-\s*\[ \]' /tmp/issue7-body.md
```

For each still-unchecked item, verify against current code (Read the cited `path:line`) — the fix may have landed without the box being ticked. Keep only items still true.

- [ ] **Step 2: Audit sweep — dead code, non-Bootstrap views, redundant helpers**

Run each probe; each hit is a *candidate*, verify by reading before listing:

```bash
# Views not using the Bootstrap/adapter layer (raw inline styles, non-BS classes)
grep -rln 'style="' app/Views --include='*.php' | head -40
# Views bypassing layout shells (self-contained <html>)
grep -rln '<html' app/Views --include='*.php'
# Redundant helpers: custom helpers duplicating CI4 built-ins
ls app/Helpers/ 2>/dev/null && grep -rn 'function ' app/Helpers/ | head -20
# Dead code: private methods never called within their class
# (manual pass over app/Libraries/ and app/Controllers/ — check each private
# method name has a second hit in its own file)
grep -rn 'private function' app/Libraries app/Controllers --include='*.php'
# View-data assembly leaking outside DashboardPageBuilder (controllers building arrays for views)
grep -rn 'return view(' app/Controllers --include='*.php' | head -30
```

- [ ] **Step 3: Write `docs/knowledge/violations.md`**

Header + verified findings, grouped by severity, exactly this shape:

```markdown
# Violations Punch-List

Canonical punch-list for code-mess items (dead code, non-conforming views,
redundant helpers, boundary leaks). GitHub issues track QA/feature work, not
code mess — this file is the single home to avoid drifting lists.

Maintenance: cleanup PRs tick items `[x]` + `*(Fixed: <PR/commit>)*`. New
violations spotted mid-task get appended immediately, verified first.

Seeded 2026-07-06 from an audit pass + still-unfixed items mined from closed
issue #7.

## Findings

- [ ] 🟠 Major: `app/...:NN` — description of verified violation.
- [ ] ⚪ Cleanup: `app/...:NN` — description.
```

Every entry must be a violation you verified by reading the code, with the real `path:line`. Empty severity groups are omitted.

- [ ] **Step 4: Verify cites and commit**

Run: `bash scripts/check-knowledge-cites.sh` — Expected: exit 0, count > 0.

```bash
git add docs/knowledge/violations.md
git commit -m "docs(rag): seed violations punch-list from audit pass and issue #7"
```

---

### Task 4: Convention docs — `mvc-boundaries.md` + `audit-trail.md`

**Files:**
- Create: `docs/knowledge/binan-conventions/mvc-boundaries.md`
- Create: `docs/knowledge/binan-conventions/audit-trail.md`

**Interfaces:**
- Consumes: `violations.md` entries (anti-pattern evidence), cite-check script.
- Produces: two ≤150-line convention docs; SKILL.md grep index (Task 8) keys on their filenames.

Extraction method for BOTH docs (and Tasks 5–7): **extract from the best existing code, don't invent.** For each rule: (1) grep the real code, (2) pick the canonical example matching CLAUDE.md intent, cite `path:line`, (3) write Rule → canonical snippet → anti-pattern seen in repo (cite, or reference the `violations.md` entry) → why.

- [ ] **Step 1: Gather canonical examples for MVC boundaries**

```bash
# Canonical: controllers dispatch, DashboardPageBuilder assembles view data
grep -n 'public function' app/Libraries/DashboardPageBuilder.php | head -20
grep -n 'DashboardPageBuilder' app/Controllers/Admin/DashboardController.php
# Anti-pattern candidates: controllers assembling view-data arrays inline
grep -rn 'return view(' app/Controllers --include='*.php'
```

- [ ] **Step 2: Write `mvc-boundaries.md`**

Required sections (fill snippets from Step 1 greps, real code only):

```markdown
# MVC Boundaries

**Scope:** who decides vs who builds. Controllers route and decide;
`DashboardPageBuilder` owns dashboard view-data assembly; models own queries.

## Rule 1: Controllers decide, libraries build
Canonical: `app/Controllers/Admin/DashboardController.php:NN` — dispatches to
`DashboardPageBuilder`:
```php
<real snippet>
```
Anti-pattern seen: <cite or violations.md ref>.
Why: single place to debug view data (CLAUDE.md: "start debugging here").

## Rule 2: View-data assembly lives in DashboardPageBuilder
<same structure>

## Rule 3: Queries live in models, not controllers/libraries
<same structure>
```

- [ ] **Step 3: Gather canonical examples for audit trail**

```bash
grep -n 'AuditTrailsModel\|audit_trails' app/Controllers/Families/FamilyController.php | head
grep -n 'public function' app/Models/Audit/AuditTrailsModel.php
grep -rn 'SessionAuditLogger' app/Libraries app/Controllers --include='*.php' | head
```

- [ ] **Step 4: Write `audit-trail.md`**

Same Rule/canonical/anti-pattern/why structure. Must cover: (a) every family mutation (create/update/member/service changes) writes `audit_trails` via `Audit/AuditTrailsModel` — non-negotiable, never bypass; (b) the canonical call shape from `FamilyController` with real snippet + `path:line`; (c) session-level audit via `SessionAuditLogger` and when each applies.

- [ ] **Step 5: Verify and commit**

Run: `bash scripts/check-knowledge-cites.sh` — Expected: exit 0.
Check both files ≤ ~150 lines: `wc -l docs/knowledge/binan-conventions/*.md`

```bash
git add docs/knowledge/binan-conventions/
git commit -m "docs(rag): add mvc-boundaries and audit-trail convention docs"
```

---

### Task 5: Convention docs — `routing-subnamespaces.md` + `models.md`

**Files:**
- Create: `docs/knowledge/binan-conventions/routing-subnamespaces.md`
- Create: `docs/knowledge/binan-conventions/models.md`

**Interfaces:**
- Consumes: cite-check script; same extraction method as Task 4 (grep → canonical cite → anti-pattern → why; Rule-per-section structure shown in Task 4 Step 2).
- Produces: two ≤150-line convention docs keyed by SKILL.md grep index.

- [ ] **Step 1: Gather routing evidence**

```bash
grep -n 'namespace' app/Config/Routes.php | head -30
ls app/Controllers/
php spark routes | head -30   # confirm every route resolves
head -40 tests/unit/DashboardControllerRoutingTest.php   # the guard test to cite
```

- [ ] **Step 2: Write `routing-subnamespaces.md`**

Must cover, each as Rule → canonical `path:line` snippet → anti-pattern → why:
(a) feature subnamespaces (Auth, Accounts, Families, Lookups, Audit, Admin, Employee, Viewer) and routes targeting namespaces directly in `app/Config/Routes.php`; (b) where a new controller goes and how its route is declared; (c) the guard test `tests/unit/DashboardControllerRoutingTest.php` — cite it as the enforcement mechanism; (d) verification command `php spark routes`.

- [ ] **Step 3: Gather model-layer evidence**

```bash
ls app/Models app/Models/*/
grep -n 'class\|protected \$table\|allowedFields' app/Models/Families/MemberModel.php | head
grep -n 'public function' app/Models/DashboardModel.php | head
```

- [ ] **Step 4: Write `models.md`**

Must cover: (a) model responsibilities and feature grouping (`Auth/`, `Families/`, `Audit/`, `Lookups/` + shared `DashboardModel`/`SearchModel`/`ViewLayoutModel`); (b) query placement — queries in models, not controllers/libraries (cross-reference rule 3 of `mvc-boundaries.md` by filename, don't duplicate its snippets); (c) schema truth: **no migrations — schema source of truth is the SQL dump; column names/enums/roles match the dump exactly; employee accounts store as `User` role**; (d) canonical model shape with real `path:line` snippet.

- [ ] **Step 5: Verify and commit**

Run: `bash scripts/check-knowledge-cites.sh` — Expected: exit 0.

```bash
git add docs/knowledge/binan-conventions/
git commit -m "docs(rag): add routing-subnamespaces and models convention docs"
```

---

### Task 6: `views-bootstrap.md` + `sbadmin/` docs

**Files:**
- Create: `docs/knowledge/binan-conventions/views-bootstrap.md`
- Create: `docs/knowledge/sbadmin/adapter.md`
- Create: `docs/knowledge/sbadmin/target-theme.md`

**Interfaces:**
- Consumes: cite-check script; extraction method + Rule-section structure from Task 4 Step 2; `violations.md` non-conforming-view entries.
- Produces: three ≤150-line docs; SKILL.md routes "UI markup / SBAdmin styling" questions here.

- [ ] **Step 1: Gather view-layer evidence**

```bash
grep -n 'renderSection\|include\|extend' app/Views/Admin/layout.php | head -20
ls app/Views/components/
ls public/css/          # page-specific css + sb-admin-adapter.css
grep -n '' public/css/sb-admin-adapter.css | head -30   # what the adapter maps
grep -rln 'class="card\|class="table' app/Views --include='*.php' | head
```

- [ ] **Step 2: Write `views-bootstrap.md`**

Must cover: (a) layout shells — `app/Views/Admin/layout.php` and `Employee/layout.php` swap per-page views; new pages plug into a shell, never standalone `<html>`; (b) shared partials in `app/Views/components/`; (c) Bootstrap 5.3.3 vendored at `public/assets/bootstrap/` — use its classes, not inline styles (cite a conforming view and a `violations.md` offender); (d) page CSS pattern (`public/css/<page>.css` + `sb-admin-adapter.css`).

- [ ] **Step 3: Write `sbadmin/adapter.md`**

Documents current reality: Bootstrap 5 + homegrown `public/css/sb-admin-adapter.css` (not a stock vendored theme). What the adapter provides (real selectors, cited `path:line`), how layouts consume it, what NOT to hand-roll because the adapter already covers it.

- [ ] **Step 4: Write `sbadmin/target-theme.md`**

Documents the decided migration target: **SB Admin 1 (startbootstrap-sb-admin v7+, Bootstrap 5-based); SB Admin 2 rejected (pinned to Bootstrap 4.6, fights the BS5 base).** Cheatsheet of SB Admin 1 conventions (card/table/sidebar/topbar markup shapes, from the canonical URL in `sources.md`) + which existing views are migration targets (reference `violations.md` entries by their cites). This doc is forward-looking — its SB Admin 1 markup examples come from the upstream template, so they carry URLs, not repo `path:line` cites; repo cites appear only for current-state references.

- [ ] **Step 5: Verify and commit**

Run: `bash scripts/check-knowledge-cites.sh` — Expected: exit 0.

```bash
git add docs/knowledge/binan-conventions/views-bootstrap.md docs/knowledge/sbadmin/
git commit -m "docs(rag): add views-bootstrap and sbadmin adapter/target-theme docs"
```

---

### Task 7: `php-practices/idioms.md`

**Files:**
- Create: `docs/knowledge/php-practices/idioms.md`

**Interfaces:**
- Consumes: cite-check script; extraction method from Task 4.
- Produces: one ≤150-line doc; SKILL.md routes "PHP idiom" questions here.

- [ ] **Step 1: Gather idiom evidence**

```bash
grep -rln 'declare(strict_types=1)' app --include='*.php' | wc -l
grep -rL 'declare(strict_types=1)' app/Controllers app/Models app/Libraries --include='*.php'   # non-conformers
grep -rn 'public function __construct' app/Libraries --include='*.php' | head
grep -rn 'readonly\|enum \|match (' app --include='*.php' | head -10
```

- [ ] **Step 2: Write `idioms.md`**

Must cover, Rule/canonical/anti-pattern/why structure: (a) `declare(strict_types=1)` + namespace header on every file (cite a canonical header; list non-conformers via `violations.md`); (b) constructor patterns as used here (promoted properties if present — only document what the greps actually show); (c) typed signatures (param + return types); (d) PHP 8.2 floor. Close with: deep language questions → PHP manual / Context7, cross-check `sources.md` pins.

- [ ] **Step 3: Verify and commit**

Run: `bash scripts/check-knowledge-cites.sh` — Expected: exit 0.

```bash
git add docs/knowledge/php-practices/
git commit -m "docs(rag): add php-practices idioms doc"
```

---

### Task 8: Router skill — `.claude/skills/binan-conventions/SKILL.md`

**Files:**
- Create: `.claude/skills/binan-conventions/SKILL.md`

**Interfaces:**
- Consumes: all `docs/knowledge/` filenames from Tasks 2–7 (grep index must hit real files).
- Produces: the retrieval router that CLAUDE.md (Task 9) and AGENTS.md (Task 10) point at.

- [ ] **Step 1: Write SKILL.md**

Trigger description names concrete actions (skills fire on description matching, not path watching):

```markdown
---
name: binan-conventions
description: Use BEFORE editing controllers, models, views, or libraries; adding or changing routes; styling or building pages; writing queries; or touching audit-trail logic in this repo. Routes the question to the right knowledge source (Context7 for framework API, docs/knowledge/ for repo conventions) so edits follow this repo's intended patterns instead of stale model knowledge.
---

# Biñan Conventions Retrieval Router

Before editing code under `app/Controllers|Models|Views|Libraries` or
`app/Config/Routes.php`, classify your question and retrieve. One Grep, not
a scan.

## Decision table

| Question type                        | Source                                       |
|--------------------------------------|----------------------------------------------|
| CI4 / Bootstrap 5 framework API      | Context7 MCP (see caveat below)              |
| "How does THIS repo do X"            | `docs/knowledge/binan-conventions/`          |
| UI markup / SBAdmin styling          | `docs/knowledge/sbadmin/`                    |
| PHP idiom / language practice        | `docs/knowledge/php-practices/`              |
| "Is there existing mess here"        | `docs/knowledge/violations.md`               |
| Version pins / canonical URLs        | `docs/knowledge/sources.md`                  |

**Context7 caveat:** Context7 serves LATEST docs, not this repo's pinned
versions. Cross-check version-sensitive answers against the pins in
`docs/knowledge/sources.md` (CI4 v4.7.3, Bootstrap v5.3.3, PHP 8.2).

## Grep index (keyword → file)

| Keywords | File |
|----------|------|
| controller, view data, PageBuilder, boundary, library | `binan-conventions/mvc-boundaries.md` |
| audit, audit_trails, AuditTrailsModel, family mutation, SessionAuditLogger | `binan-conventions/audit-trail.md` |
| route, namespace, subnamespace, Routes.php, new controller | `binan-conventions/routing-subnamespaces.md` |
| model, query, table, allowedFields, schema, SQL dump, enum, role | `binan-conventions/models.md` |
| view, layout, partial, component, bootstrap, css, page | `binan-conventions/views-bootstrap.md` |
| adapter, sb-admin-adapter | `sbadmin/adapter.md` |
| sb admin, theme, sidebar, topbar, card markup, migration target | `sbadmin/target-theme.md` |
| strict_types, constructor, typed, php idiom | `php-practices/idioms.md` |
| dead code, cleanup, violation, mess, punch-list | `violations.md` |
| version, pin, url, context7 | `sources.md` |

## Protocol

1. Classify the task's question(s) with the decision table.
2. Grep/Read the mapped file(s) under `docs/knowledge/`; query Context7 for
   framework API (then cross-check `sources.md`).
3. Apply the edit grounded in the retrieved rule; cite `path:line` where
   relevant.
4. Spot new mess mid-task → verify it, append to
   `docs/knowledge/violations.md` immediately.
5. Fix a listed violation → tick it `[x]` + `*(Fixed: <PR/commit>)*`.
```

- [ ] **Step 2: Verify every grep-index file exists**

```bash
cd docs/knowledge && for f in binan-conventions/mvc-boundaries.md binan-conventions/audit-trail.md binan-conventions/routing-subnamespaces.md binan-conventions/models.md binan-conventions/views-bootstrap.md sbadmin/adapter.md sbadmin/target-theme.md php-practices/idioms.md violations.md sources.md; do [ -f "$f" ] || echo "MISSING: $f"; done; cd ../..
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add .claude/skills/binan-conventions/SKILL.md
git commit -m "docs(rag): add binan-conventions retrieval router skill"
```

---

### Task 9: Slim CLAUDE.md to non-negotiables + retrieval router

**Files:**
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: SKILL.md path from Task 8; `docs/knowledge/` corpus.
- Produces: slimmed CLAUDE.md. **No rule lost, only relocated** — verify by diff.

- [ ] **Step 1: Relocation map (what moves where)**

| CLAUDE.md section | Fate |
|---|---|
| Header, Core Behavioral Guidelines | **Stays** (always-true working posture) |
| Non-Negotiables | **Stays verbatim** |
| Commands, CodeRabbit workflow, GitHub issue format | **Stays** (operational, needed most sessions) |
| Tests section | **Stays**, trimmed to the run commands + "smoke-test key flows" line; per-test descriptions already live in the test files |
| Architecture section (controllers/models/libraries/views inventory) | **Moves** — covered by `docs/knowledge/binan-conventions/` + existing `PROJECT_STRUCTURE.md`; replaced by the router |

- [ ] **Step 2: Apply the edit**

Replace the `## Tests` per-test bullet list and the whole `## Architecture` section with:

```markdown
## Tests (`tests/`)

Run `vendor/bin/phpunit` before and after changes (DB/session tests skip
without `sqlite3` ext). Smoke-test key flows: login, role redirect, family
create/update, audit log creation.

## Retrieval (before editing app code)

Before editing `app/Controllers|Models|Views|Libraries` or routes, use the
`binan-conventions` skill (`.claude/skills/binan-conventions/SKILL.md`) —
decision table + grep index over `docs/knowledge/`:

- Framework API (CI4 / Bootstrap 5) → Context7 MCP (`.mcp.json`); serves
  LATEST docs — cross-check pins in `docs/knowledge/sources.md`.
- Repo conventions → `docs/knowledge/binan-conventions/`
- UI / SBAdmin → `docs/knowledge/sbadmin/` (target theme: SB Admin 1)
- PHP idioms → `docs/knowledge/php-practices/`
- Known mess → `docs/knowledge/violations.md` (canonical punch-list; tick
  items you fix, append verified new ones)

Full file map: `PROJECT_STRUCTURE.md`.
```

- [ ] **Step 3: Verify no rule lost**

```bash
git diff CLAUDE.md
```

Read the diff: every deleted line must be either (a) present in a `docs/knowledge/` doc or `PROJECT_STRUCTURE.md`, or (b) pure prose duplication. Non-negotiables section must show zero deletions. If any rule has no new home, restore it or add it to the right doc before committing.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md
git commit -m "docs(rag): slim CLAUDE.md to non-negotiables + retrieval router"
```

---

### Task 10: `AGENTS.md` stub

**Files:**
- Create: `AGENTS.md` (repo root)

**Interfaces:**
- Consumes: decision-table routing from Task 8 (mirrored inline — external agents don't execute Claude skills).
- Produces: entry point for Cursor/Codex/Copilot.

- [ ] **Step 1: Write `AGENTS.md`**

```markdown
# Agent Instructions

Read `CLAUDE.md` for rules and non-negotiables. Do not duplicate or override
them — this file only routes retrieval.

Before editing code under `app/` (controllers, models, views, libraries,
routes), retrieve:

| Question | Source |
|----------|--------|
| Framework API (CodeIgniter 4 / Bootstrap 5) | Context7 MCP (`.mcp.json`); serves LATEST docs — cross-check pins in `docs/knowledge/sources.md` |
| Repo conventions ("how does THIS repo do X") | `docs/knowledge/binan-conventions/` |
| UI markup / SBAdmin styling | `docs/knowledge/sbadmin/` |
| PHP idioms | `docs/knowledge/php-practices/` |
| Known mess at this path | `docs/knowledge/violations.md` |

Keyword → file grep index: `.claude/skills/binan-conventions/SKILL.md`.

Fix a listed violation → tick it in `violations.md`. Spot new mess → verify,
then append.
```

- [ ] **Step 2: Commit**

```bash
git add AGENTS.md
git commit -m "docs(rag): add AGENTS.md retrieval stub for external agents"
```

---

### Task 11: Final verification + PR

**Files:**
- No new files; verification + branch handoff.

- [ ] **Step 1: Full verification battery**

```bash
bash scripts/check-knowledge-cites.sh        # exit 0, all cites resolve
wc -l docs/knowledge/*/*.md                  # each convention doc ≤ ~150 lines
vendor/bin/phpunit                           # same pass/skip counts as Task 1 baseline
php spark routes > /dev/null && echo routes-ok
```

Expected: all green; phpunit counts match Task 1 baseline (no app code touched).

- [ ] **Step 2: Grep-index spot check**

Pick 3 keywords from SKILL.md's index (e.g. `audit_trails`, `sb-admin-adapter`, `strict_types`), confirm each mapped file exists and actually contains guidance on that keyword:

```bash
grep -l 'audit_trails' docs/knowledge/binan-conventions/audit-trail.md
grep -l 'sb-admin-adapter' docs/knowledge/sbadmin/adapter.md
grep -l 'strict_types' docs/knowledge/php-practices/idioms.md
```

Expected: each prints the filename.

- [ ] **Step 3: CLAUDE.md diff review (second pass)**

`git diff main -- CLAUDE.md` — confirm again: non-negotiables intact, every relocated rule findable in `docs/knowledge/`.

- [ ] **Step 4: Push and open PR**

```bash
git push -u origin feat/rag-pipeline
gh pr create --title "docs(rag): codebase RAG pipeline (knowledge corpus + router skill)" --body "$(cat <<'EOF'
Implements docs/superpowers/specs/2026-07-06-codebase-rag-pipeline-design.md.

- docs/knowledge/ corpus: conventions, sbadmin, php-practices, violations punch-list, sources pins
- .claude/skills/binan-conventions/SKILL.md retrieval router (decision table + grep index)
- CLAUDE.md slimmed to non-negotiables + router (no rule lost, only relocated)
- AGENTS.md stub for external agents (inlined decision table)
- scripts/check-knowledge-cites.sh keeps path:line cites honest

Tooling + docs only; no app runtime code. phpunit green (matches pre-branch baseline).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Then run the repo's CodeRabbit review workflow per CLAUDE.md (`coderabbit review --base main --agent`, triage — don't blind-apply).
