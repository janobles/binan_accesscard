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

**Context7 caveat:** the CI4 library (`/codeigniter4/userguide`) serves
LATEST docs, not this repo's pinned versions. Cross-check version-sensitive
answers against the pins in `docs/knowledge/sources.md` (CI4 v4.7.3,
Bootstrap v5.3.3 — Bootstrap's `/websites/getbootstrap_5_3` is
version-pinned, so it's safe). PHP floor: 8.2.

## Grep index (keyword → file)

| Keywords | File |
|----------|------|
| controller, view data, PageBuilder, boundary, library | `binan-conventions/mvc-boundaries.md` |
| audit, audit_trails, AuditTrailsModel, family mutation, SessionAuditLogger | `binan-conventions/audit-trail.md` |
| route, namespace, subnamespace, Routes.php, new controller | `binan-conventions/routing-subnamespaces.md` |
| model, query, table, allowedFields, schema, SQL dump, enum, role | `binan-conventions/models.md` |
| view, layout, partial, component, bootstrap, css, page, inline style | `binan-conventions/views-bootstrap.md` |
| adapter, sb-admin-adapter, sidebar css, theme token | `sbadmin/adapter.md` |
| sb admin, target theme, sidenav, topnav, migration | `sbadmin/target-theme.md` |
| strict_types, constructor, typed, match, docblock, php idiom | `php-practices/idioms.md` |
| dead code, cleanup, violation, mess, punch-list | `violations.md` |
| version, pin, url, context7 | `sources.md` |

All paths relative to `docs/knowledge/`.

## Protocol

1. Classify the task's question(s) with the decision table.
2. Grep/Read the mapped file(s) under `docs/knowledge/`; query Context7 for
   framework API (then cross-check `sources.md`).
3. Apply the edit grounded in the retrieved rule; cite `path:line` where
   relevant.
4. Spot new mess mid-task → verify it, append to
   `docs/knowledge/violations.md` immediately.
5. Fix a listed violation → tick it `[x]` + `*(Fixed: <PR/commit>)*`.
6. After editing docs under `docs/knowledge/`, run
   `bash scripts/check-knowledge-cites.sh` — every `path:line` cite must
   resolve.
