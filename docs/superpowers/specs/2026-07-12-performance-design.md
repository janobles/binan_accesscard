# Performance Fixes — Design

**Date:** 2026-07-12
**Status:** Approved
**Goal:** Tab navigation currently takes 2–5s even on small data; supervisor's target with 100,000+ head-of-family records is 5–10s for a full page load (network tab, all requests), hard cap 30s.

## Problem analysis

Two distinct problems were identified:

1. **Baseline per-request overhead** — even tiny lookup pages (/sectors, /services, /categories) take seconds. Login TTFB measured at ~0.1s, so the framework itself is fast. The delay comes from `php spark serve` running a single PHP worker (the page plus every CSS/JS/image request queue serially) and development-mode overhead (debug toolbar).
2. **Data scale** — searched/filtered columns have no indexes (only FK indexes exist in the dump). Keyword search uses `LIKE '%x%'` table scans; `countAllMembers` runs a filtered `COUNT(*)` per page view; dashboard stat cards recompute counts on every hit.

Pagination already exists (50/page via `SearchModel::allMembers`), so no batch-loading work is needed.

## Decisions (user-approved)

- **LIKE-only search.** No FULLTEXT. Search semantics stay substring-exact (`LIKE '%x%'`). Indexes accelerate filters, sorts, and counts; the keyword scan itself remains a table scan, which at 100k rows is an acceptable ~100–300ms.
- **Stat-card caching: 60s TTL + delete-on-mutation.** Dashboard tile counts cached via CI4's cache service with a 60s TTL, and the cache key deleted inside the family-mutation path (which already funnels through audited writes), so tiles are always fresh after a mutation and the TTL is only a fallback.
- **No migrations.** Schema changes ship as a new dump version (`accesscardV17.sql`) plus an idempotent `ALTER TABLE` patch script (`sql/patches/v17-indexes.sql`) for existing databases, per the repo's no-migrations rule.

## Track A — dev/server overhead

1. Run the dev server with `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090` so asset requests are served in parallel. Document in README (and memory); no code change.
2. Static asset cache headers: `Cache-Control` for `public/` assets via `.htaccess` (Apache deployments). `spark serve` already answers 304s for browser-cached assets.
3. Verify with browser network-tab timings on the records list and a lookup page, before/after.

## Track B — data scale

1. **Indexes** (V17 dump + patch script):
   - `member`: name columns, `status`, and date columns used by list filters; composite indexes matching the WHERE shapes of `SearchModel::allMembers` / `countAllMembers`, chosen by running `EXPLAIN` on the real queries.
   - `audit_trails`: timestamp/action indexes for the audit list pages.
   - Every added index justified by an `EXPLAIN` before/after.
2. **Query fixes:** confirm batched lookups stay batched (`withServiceNames` etc. — no per-row queries); no search-semantics changes.
3. **Caching:** stat-card counts as decided above. Per-page `COUNT(*)` left as-is if indexed counts are fast enough — measure first, cache only if needed.

## Verification

- Seed a synthetic dataset of 100k+ heads (scratch script; not committed to the dump).
- Before/after network-tab timings: records list, keyword search, dashboard, lookup pages. Targets: 5–10s full page load, never over 30s (expected well under).
- `vendor/bin/phpunit` green; smoke-test login, role redirect, family create/update, audit log creation.

## Code comments

Comments in this work are written for the developers on this team, not for tooling. They should explain how the code works and why it is built that way, in plain language a teammate can follow. Concretely:

- Explain intent and mechanism: why an index has these columns in this order, why the cache key is deleted in the mutation path, what the TTL is a fallback for.
- Plain sentences. No em dashes, no filler phrases, no generated-sounding boilerplate.
- Patch scripts and non-obvious queries get a short header comment saying what they do and when to run them.

## RAG / docs update (final step)

- New `docs/knowledge/binan-conventions/performance.md`: index conventions, dump-version + patch-script pattern, caching + invalidation rule, "EXPLAIN before shipping queries". Written in the same plain style described above, since the team reads these docs directly.
- Add the comment-style rule above to the conventions docs so future work follows it.
- Update the `binan-conventions` skill grep index and `docs/knowledge/violations.md` (tick fixed items).
- Memory note for the V17 dump.

## Risks

- Cached tiles can be up to 60s stale only when a count changes without going through the family-mutation path (e.g. direct DB edits) — acceptable.
- `PHP_CLI_SERVER_WORKERS` is dev-only; production Apache deployments are unaffected but should get the `.htaccess` cache headers.
