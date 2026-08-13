---
name: conventions
description: Use BEFORE editing controllers, models, views, or libraries; adding or changing routes; styling or building pages; writing queries; or touching audit-trail logic in this repo. Routes the question to the right source (Context7 for framework API, the docs/ handbook for repo conventions) so edits follow this repo's intended patterns instead of stale model knowledge.
---

# Conventions retrieval router

Before editing code under `app/Controllers|Models|Views|Libraries` or
`app/Config/Routes.php`, classify your question and retrieve. One Grep, not a
scan.

Every chapter in `docs/` ends with a `## Rules` section holding the terse,
enforceable version of its conventions. **Read the `#rules` anchor, not the whole
chapter**, unless you need the reasoning above it.

## Decision table

| Question type | Source |
|---|---|
| CI4 / Bootstrap framework API | Context7 MCP (see caveat below) |
| "How does THIS repo do X" | the chapter's `## Rules` section |
| Domain terms (barangay, head, sector, subsidy type) | `docs/00-introduction.md` glossary |
| Version pins / canonical URLs | `docs/reference/version-pins.md` |
| Comment / docblock style, lint gates | `docs/reference/comment-standard.md` |
| "Is there existing mess here" | `docs/reference/violations.md` |

## Grep index (keyword to chapter anchor)

| Keywords | Destination |
|---|---|
| controller, view data, PageBuilder, boundary, library, subnamespace, request lifecycle, filter | `docs/01-architecture.md#rules` |
| model, query, table, allowedFields, schema, SQL dump, enum, role, headID, family, ERD | `docs/02-database.md#rules` |
| route, namespace, Routes.php, new controller, navigation manifest, Navigation.php, roleNav, page key, sidebar link, url prefix, 404 by role | `docs/10-navigation-and-access.md#rules` |
| records list, datatable, entry form, stepper spine, family write, archive, restore | `docs/11-records.md` |
| import, excel, xlsx, validation code, QR-TAKEN, DUP-DB, review screen, staging | `docs/12-import.md` |
| barangay, sector, service, category, subsidy type, lookup, reference data | `docs/13-reference-data.md` |
| control number, QR, card, card_generated_at, qr_control | `docs/14-access-cards.md` |
| batch, distribution_batch, kiosk, scanner shell, perScanner, myBatchCount, scan flow, eligibility, schedule window | `docs/15-distribution.md#rules` |
| audit, audit_trails, AuditTrailsModel, family mutation, SessionAuditLogger, logAction | `docs/16-audit-trails.md#rules` |
| dashboard, KPI, stats cache, reports, export, DashboardPageBuilder | `docs/17-dashboard-and-reports.md` |
| view, layout, partial, component, bootstrap, css, page, inline style, empty state, stepper, button color, btn(), toolbar, filter panel, pills, dual search, design system, esc(), SB Admin | `docs/20-frontend.md#rules` |
| strict_types, constructor, typed, match, php idiom, docblock | `docs/21-php-style.md#rules` |
| test, phpunit, forge, CI, MariaDB, sqlite, DumpSchema | `docs/22-testing.md` |
| index, EXPLAIN, slow, performance, cache, TTL, patch script, benchmark, dump version | `docs/23-performance.md#rules` |
| deploy, backup, restore, handover, production, account admin | `docs/06-operations-and-handover.md` |

`docs/README.md` is the full index if nothing above matches.

## Context7 caveat

The CI4 library (`/codeigniter4/userguide`) serves LATEST docs, not this repo's
pinned versions. Cross-check version-sensitive answers against
`docs/reference/version-pins.md` (CI4 v4.7.3, PHP floor 8.2).

**Bootstrap needs more care than a single pin.** The Context7 Bootstrap library
is pinned to 5.3, which matches this repo's JavaScript bundle and its login page,
but **not** its dashboard CSS. Dashboard pages load Bootstrap compiled into SB
Admin v7.0.7, which is 5.2.3 and contains no 5.3 features. A 5.3-only class on a
dashboard page silently does nothing. For any dashboard styling question, confirm
the class or variable existed in 5.2 before relying on a Context7 answer.

## Protocol

1. Classify the task's question(s) with the decision table.
2. Read the mapped `#rules` anchor. Query Context7 for framework API, then
   cross-check `docs/reference/version-pins.md`.
3. Apply the edit grounded in the retrieved rule; cite `path:line` where relevant.
4. Spot new mess mid-task: verify it, then append to
   `docs/reference/violations.md` immediately.
5. Fix a listed violation: tick it `[x]` and add `*(Fixed: <PR/commit>)*`.
6. After editing anything under `docs/`, run `bash scripts/check-doc-cites.sh`.
   Every `path:line` cite must resolve.

## Writing in these files

A chapter has two registers and they do not mix. Prose above the `## Rules`
heading explains and can be edited freely. The rules section is terse and
enforceable; keep it that way rather than softening it into prose. If you are
writing new prose, the `writing-voice` skill carries the register.
