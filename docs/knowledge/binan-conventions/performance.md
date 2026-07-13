# Performance conventions

## Schema changes ship as dumps plus patch scripts

No migrations, ever. A schema change means a new `accesscardVN.sql` dump at the
repo root and an idempotent script in `sql/patches/` (use `ADD INDEX IF NOT
EXISTS` so re-runs are safe). V17 added `idx_member_deleted_name`,
`idx_member_created`, and `idx_audit_created`; read `sql/patches/v17-indexes.sql`
as the template.

## EXPLAIN before shipping a query

Any new list, count, or report query gets an `EXPLAIN` against a realistic
dataset before merge. What to look for: the intended index in `key`, and no
`Using filesort` on paginated lists. Keyword search is substring LIKE by
decision (2026-07-12): indexes cannot help it and that is accepted, so do not
"fix" it with FULLTEXT. Benchmarks at 300k member rows live in
`docs/superpowers/plans/2026-07-12-performance-benchmarks.md`.

## Dashboard stat caching

`DashboardModel::stats()` caches its counts for 60 seconds under
`DashboardModel::STATS_CACHE_KEY`. `AuditTrailsModel::logAction()` deletes that
key first thing, so any audited mutation refreshes the tiles on the next visit.
The TTL is only a fallback for direct DB edits. If you add a new cached
aggregate, follow the same pattern: a public constant for the key, a delete in
the mutation funnel, and a short TTL as backstop.

## Dev server

Run `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090`. One worker
serializes all asset requests and makes every page feel seconds slower.
Static assets get week-long cache headers from `public/.htaccess` on Apache.

## Comment style

Comments and these docs are read by the devs on this team, not by tooling.
Explain how the code works and why it is built that way, in plain sentences.
No em dashes, no boilerplate.
