# Performance

The dataset is not small. A real deployment holds tens of thousands of members,
and the dummy seeder will give you 50,000 to work against. Most of what follows
exists because something was slow at that size.

## Indexes

Indexes ship as patch files like any other schema change (chapter 02). Write them
idempotently with `ADD INDEX IF NOT EXISTS` so a re-run is safe;
`sql/patches/v17-indexes.sql` is the template, and its comments explain what each
index serves.

The three from v17 and why they exist:

**`idx_member_deleted_name`** on `member (dt_deleted, lastname, firstname)`. The
records list always filters on `dt_deleted` and sorts by name. This composite lets
the database filter and return rows already in sort order, so there is no filesort
over the whole table. It also covers the dashboard's family and member counts,
which filter on `dt_deleted` alone.

**`idx_member_created`** on `member (dt_created)`, for the records list's
date-range filter.

**`idx_audit_created`** on `audit_trails`, which orders and filters by timestamp
and is the fastest-growing table in the schema at one row per mutation.

## EXPLAIN before shipping a query

Any new list, count, or report query gets an `EXPLAIN` against a realistic dataset
before it merges. Two things to look for: the index you intended appearing in
`key`, and no `Using filesort` on a paginated list.

**Keyword search is a substring `LIKE` by decision.** Indexes cannot help it, and
that is accepted rather than unnoticed. Do not "fix" it with FULLTEXT.

## The stats cache

`DashboardModel::stats()` caches its counts for 60 seconds under
`STATS_CACHE_KEY`, and the Overview tab's four counts cache the same way under
their own key.

The TTL is not the mechanism, it is the backstop.
`AuditTrailsModel::logAction()` deletes the key first thing, so any audited
mutation refreshes the tiles on the next visit. Since every family mutation writes
an audit row (chapter 16), every family mutation clears the cache. The 60 seconds
only covers direct database edits that bypass the application entirely.

If you add a cached aggregate, follow the same three-part pattern: a public
constant for the key, a delete in the mutation funnel, and a short TTL as backstop.
A cache with a TTL and no invalidation will show stale numbers to the person who
just created the record, which is the one person guaranteed to notice.

## The dev server

```bash
PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090
```

One worker serialises every asset request behind the page itself and makes the
whole application feel seconds slower for reasons that have nothing to do with
queries. If you are about to investigate a slow page on the dev server, check the
worker count first.

On Apache, static assets get week-long cache headers from `public/.htaccess`.

## Rules

Copied from the conventions this codebase is held to. Terse on purpose.

### Schema changes ship as dumps plus patch scripts

No migrations, ever. A schema change means a new `accesscardVN.sql` dump at the
repo root and an idempotent script in `sql/patches/` (use `ADD INDEX IF NOT
EXISTS` so re-runs are safe). V17 added `idx_member_deleted_name`,
`idx_member_created`, and `idx_audit_created`; read `sql/patches/v17-indexes.sql`
as the template.

### EXPLAIN before shipping a query

Any new list, count, or report query gets an `EXPLAIN` against a realistic
dataset before merge. What to look for: the intended index in `key`, and no
`Using filesort` on paginated lists. Keyword search is substring LIKE by
decision (2026-07-12): indexes cannot help it and that is accepted, so do not
"fix" it with FULLTEXT. The benchmarks behind that decision were taken at 300k
member rows.

### Dashboard stat caching

`DashboardModel::stats()` caches its counts for 60 seconds under
`DashboardModel::STATS_CACHE_KEY`. `AuditTrailsModel::logAction()` deletes that
key first thing, so any audited mutation refreshes the tiles on the next visit.
The TTL is only a fallback for direct DB edits. If you add a new cached
aggregate, follow the same pattern: a public constant for the key, a delete in
the mutation funnel, and a short TTL as backstop.

### Dev server

Run `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090`. One worker
serializes all asset requests and makes every page feel seconds slower.
Static assets get week-long cache headers from `public/.htaccess` on Apache.
