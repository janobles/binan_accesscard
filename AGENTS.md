# Agent Instructions

Read `CLAUDE.md` for rules and non-negotiables. Do not duplicate or override
them — this file only routes retrieval.

Before editing code under `app/` (controllers, models, views, libraries,
routes), retrieve:

| Question | Source |
|----------|--------|
| Framework API (CodeIgniter 4 / Bootstrap 5) | Context7 MCP (`.mcp.json`); CI4 library serves LATEST docs — cross-check pins in `docs/knowledge/sources.md` |
| Repo conventions ("how does THIS repo do X") | `docs/knowledge/binan-conventions/` |
| UI markup / SBAdmin styling | `docs/knowledge/sbadmin/` |
| PHP idioms | `docs/knowledge/php-practices/idioms.md` |
| Comment / docblock style and the lint gates | `docs/knowledge/php-practices/comments.md` |
| Known mess at this path | `docs/knowledge/violations.md` |

Keyword → file grep index: `.claude/skills/binan-conventions/SKILL.md`.

Run `composer lint` before opening a PR. CI runs it too, so a red lint blocks the
merge. Do not run `composer lint:fix` across the repo; see `CLAUDE.md`.

Fix a listed violation → tick it in `violations.md` (`[x]` +
`*(Fixed: ...)*`). Spot new mess → verify, then append.
