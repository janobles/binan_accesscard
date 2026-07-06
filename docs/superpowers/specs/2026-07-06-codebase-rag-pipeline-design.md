# Codebase RAG Pipeline — Design

**Date:** 2026-07-06
**Status:** Approved (design)
**Author:** JP + Claude

## Problem

The codebase sets standards for itself (MVC boundaries, CI4 4.7 practices, Bootstrap 5,
SB-Admin-flavored UI, PHP 8.2 idioms) but does not follow them consistently: dead code,
redundant helpers, and views that don't use the Bootstrap/SBAdmin layer exist. When an agent
(Claude Code, and external agents like Cursor/Codex) edits the code, it leans on model
knowledge that (a) may be stale versus the pinned framework versions and (b) has no way to
know *this repo's* intended patterns. Result: new edits perpetuate the inconsistency.

Goal: a retrieval layer that grounds any agent's edits in **current framework knowledge** and
**this repo's intended conventions**, plus a **diagnostic index** that drives cleanup of the
existing mess — without a runtime service, vector store, or embedding cost.

## Non-goals

- No vector database / embeddings pipeline. Retrieval is Grep/Read over curated markdown + a
  live docs MCP.
- No knowledge-graph build (graphify) in this iteration.
- Does **not** decide the UI direction (SB Admin 1 vs 2). It *documents* the chosen target.
- No schema/migration changes, no app runtime code. This is tooling + docs only.

## Architecture

Five planes, cheapest-to-load first:

```
AGENTS.md            -> thin pointer to CLAUDE.md (for external agents: Cursor, Codex, Copilot)
CLAUDE.md            -> non-negotiables + retrieval router (slimmed)
.mcp.json            -> Context7 MCP config (shared, env-var key)
Context7 MCP (live)  -> CI4 4.7 API, Bootstrap 5 API   (no local curation)
docs/knowledge/      -> what only this repo has (retrieved on demand)
  binan-conventions/*.md   -> intended MVC / audit / PageBuilder / routing / model patterns
  sbadmin/*.md             -> Bootstrap 5 adapter + target-theme cheatsheet
  php-practices/*.md       -> PHP 8.2 + repo idioms
  violations.md            -> diagnostic punch-list (path:line, checkbox)
  sources.md               -> version pins (from composer.lock) + canonical URLs
.claude/skills/binan-conventions/SKILL.md  -> retrieval protocol + grep index
```

**Governing rule:** anything needed only *sometimes* lives in `docs/knowledge/` and is
retrieved; only always-true, must-never-violate rules stay in CLAUDE.md.

### Retrieval flow

Before editing `app/Controllers|Models|Views|Libraries`, the agent consults the skill's
decision table:

| Question type                          | Source                                   |
|----------------------------------------|------------------------------------------|
| CI4 / Bootstrap 5 framework API        | Context7 MCP                             |
| "How does THIS repo do X"              | `docs/knowledge/binan-conventions/`     |
| UI markup / SBAdmin styling            | `docs/knowledge/sbadmin/`               |
| PHP idiom / language practice          | `docs/knowledge/php-practices/`         |
| "Is there existing mess here"          | `docs/knowledge/violations.md`          |

A **grep index** in SKILL.md maps keywords → file so retrieval is one Grep, not a scan.

## Components

### 1. `.mcp.json` (Context7)

Project-scoped, committed. Uses `${CONTEXT7_API_KEY}` env expansion — no secret in git. Key
lives in the developer's shell env (`~/.zshrc`). Context7 supplies live docs for CodeIgniter4
and Bootstrap 5, removing the need to hand-curate framework cheatsheets. Keyless mode works
(rate-limited) as a fallback.

**Version caveat:** Context7 serves *latest* docs, not the repo's pinned version
(`/codeigniter4/userguide` has no version-pinned ID). Fine while latest = 4.7.x (repo pins
v4.7.3), but if the repo lags a future major, live docs mislead. `sources.md` records this
caveat; SKILL.md instructs agents to cross-check Context7 answers against the pins in
`sources.md`.

### 2. `docs/knowledge/binan-conventions/*.md`

**Extracted from the best existing code, not invented** — the repo is inconsistent, so each
doc names the *canonical* example and contrasts the anti-pattern actually present. Per file:

1. Grep the real code for the pattern.
2. Pick the canonical example (matches CLAUDE.md intent), cite `path:line`.
3. Write: **Rule → canonical snippet (path:line) → anti-pattern seen in repo → why.**

Planned files (each ≤ ~150 lines, one concern):
- `mvc-boundaries.md` — controllers decide / libraries build / `DashboardPageBuilder` owns
  view-data assembly.
- `audit-trail.md` — every family mutation writes `audit_trails` via `AuditTrailsModel`.
- `routing-subnamespaces.md` — feature-subnamespace routing convention.
- `models.md` — model responsibilities, query placement.
- `views-bootstrap.md` — layout shells, component partials, Bootstrap 5 usage.

Every rule cites a real `path:line` so it is verifiable and self-policing.

### 3. `docs/knowledge/sbadmin/*.md`

Documents the reality found: **Bootstrap 5 + a homegrown adapter** (`public/css/sb-admin-adapter.css`),
not a stock vendored theme. Captures the intended UI target as an open decision:

**Target theme decided: SB Admin 1** (startbootstrap-sb-admin v7+, Bootstrap 5-based) — chosen
for customizability and alignment with the repo's Bootstrap 5 base. SB Admin 2 rejected (pinned
to Bootstrap 4.6, fights the BS5 base). The cheatsheet documents SB Admin 1 conventions + the
migration target for existing non-conforming views.

### 4. `docs/knowledge/php-practices/*.md`

PHP 8.2+ idioms as used here (strict types, namespaces, constructor patterns). Distilled, with
`path:line` exemplars; deep language questions defer to web/Context7.

### 5. `docs/knowledge/violations.md`

Diagnostic punch-list seeded by one audit pass: dead code, non-Bootstrap/SBAdmin views,
redundant helpers. Format matches CLAUDE.md's issue convention:

```
- [ ] 🟠 Major: `path:line` — description.
- [ ] ⚪ Cleanup: `path:line` — description.
```

Maintenance: every cleanup PR checks items off (`[x]` + `*(Fixed: ...)*`); any new violation
spotted mid-task is appended immediately.

**Single punch-list rule:** `violations.md` is the canonical punch-list for code-mess items —
GitHub issues stay for QA/feature work, not code-mess tracking (avoids two drifting lists).
Issue #7 (deferred CodeRabbit findings) is already closed; the audit pass mines its body for
any still-unfixed items and seeds them into `violations.md`.

### 6. `docs/knowledge/sources.md`

Version pins pulled from `composer.lock` (CI4, Bootstrap, PHP) + canonical doc URLs. The
freshness anchor: on a dependency bump, the affected cheatsheets are refreshed.

### 7. `.claude/skills/binan-conventions/SKILL.md`

The router. Contains the decision table, the grep index (keyword → file), and the retrieval
protocol (including the Context7 version cross-check). Instructs the agent to retrieve before
editing.

**Trigger wording:** skills fire on description matching, not path watching. The description
must name concrete actions — "editing controllers, models, views, or libraries; adding routes;
styling pages; writing queries" — not just "edits under `app/`".

### 8. `CLAUDE.md` (slimmed)

Heavy convention/architecture prose moves into `docs/knowledge/`; CLAUDE.md keeps only
non-negotiables + a short "how to retrieve" router pointing at the skill and Context7. Reduces
always-loaded token cost every session while keeping a single source of truth for patterns.

### 9. `AGENTS.md` (new, repo root)

Thin stub so non-Claude agents inherit the pipeline. External agents (Cursor/Codex) read
AGENTS.md but do not execute Claude skills — so the stub **inlines the decision table**
(~5 lines) rather than only pointing at SKILL.md, otherwise retrieval never happens:

```
Read CLAUDE.md for rules and non-negotiables.
Before editing app/ code, retrieve:
  framework API (CI4 / Bootstrap 5)  -> Context7 MCP (.mcp.json); cross-check docs/knowledge/sources.md pins
  repo conventions ("how does THIS repo do X") -> docs/knowledge/binan-conventions/
  UI markup / SBAdmin styling        -> docs/knowledge/sbadmin/
  PHP idioms                         -> docs/knowledge/php-practices/
  known mess at this path            -> docs/knowledge/violations.md
Grep index: .claude/skills/binan-conventions/SKILL.md
```

No *rule* duplication — rules stay in CLAUDE.md; only the routing table is mirrored.

## Data flow

1. Agent receives an edit task under a watched path.
2. Reads CLAUDE.md router → SKILL.md decision table + grep index.
3. Classifies the question → retrieves from the right plane (Grep local corpus, or query
   Context7 for framework API).
4. Applies the edit grounded in retrieved rules; cites `path:line` where relevant.
5. If mess is encountered, appends/updates `violations.md`.

## Freshness & maintenance

- **Framework knowledge:** live via Context7, always version-current. No manual refresh.
- **Convention docs:** cite `path:line`; a stale cite = a wrong doc = fixed on sight.
- **Version pins:** `sources.md` mirrors `composer.lock`; refresh cheatsheets on dependency bump.
- **Violations:** burned down by cleanup PRs; grown by new sightings.

## Testing / verification

Tooling-and-docs change, no runtime surface. Verification is behavioral:
- `.mcp.json` resolves → `claude mcp list` shows `context7 ✔ Connected`. (Verified 2026-07-06:
  CI4 resolve+query works via `/codeigniter4/userguide`.)
- Bootstrap 5 resolves and answers on Context7 (one resolve + query, same as CI4 check).
- Every convention doc's `path:line` cite resolves to real code (grep check).
- SKILL.md grep index keywords each hit an existing file.
- CLAUDE.md slimming preserves all non-negotiables (diff review; no rule lost, only relocated).
- `vendor/bin/phpunit` still green (no app code touched — sanity only).

## Build order (for the plan)

1. `.mcp.json` + Context7 connect (done: config written; key + verify pending restart).
2. Scaffold `docs/knowledge/` skeleton + `sources.md` from `composer.lock`.
3. Audit pass → seed `violations.md`; mine closed issue #7 for still-unfixed items.
4. Author `binan-conventions/*.md` from canonical code (grep + cite).
5. Author `sbadmin/*.md` + `php-practices/*.md`.
6. Write `.claude/skills/binan-conventions/SKILL.md` (decision table + grep index).
7. Slim CLAUDE.md → router; relocate detail to corpus.
8. Add `AGENTS.md` stub.
9. Verify (cites resolve, index hits, phpunit green).

## Open items

- ~~UI target theme (SB Admin 1 vs 2)~~ — **Decided: SB Admin 1.**
- Context7 API key rotation (prior key exposed in session transcript).
