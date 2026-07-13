# Performance Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring page loads to the supervisor's target (5-10s full page load at 100k+ head-of-family records, never over 30s) by fixing dev-server overhead, adding DB indexes, and caching dashboard stat counts.

**Architecture:** Two tracks. Track A removes per-request overhead in the dev/deploy setup (parallel PHP workers, static asset cache headers). Track B makes queries scale: indexes shipped as a new dump version plus an idempotent patch script (repo rule: no migrations), and dashboard stat counts cached for 60s with cache deletion on every audited mutation so tiles stay fresh.

**Tech Stack:** CodeIgniter 4 (PHP 8.2+), MySQL/MariaDB, CI4 file cache handler, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-12-performance-design.md`

## Global Constraints

- No migrations. Schema changes go into `accesscardV17.sql` (new dump) and `sql/patches/v17-indexes.sql` (idempotent ALTER for existing DBs). Never `app/Database/Migrations`.
- LIKE-only search. Do not change search semantics; no FULLTEXT indexes, no `MATCH AGAINST`.
- Every family mutation keeps writing an audit trail. Cache invalidation hooks into that path; it must never block or replace the audit write.
- Typed PHP signatures, no `declare(strict_types=1)`.
- Comments are for the human devs on this team: plain sentences explaining how the code works and why. No em dashes, no filler.
- Run `vendor/bin/phpunit` before and after each task's change.
- Before editing files under `app/`, consult the `binan-conventions` skill decision table if unsure about a pattern.

---

### Task 1: Static asset cache headers + dev-server workers doc

**Files:**
- Modify: `public/.htaccess` (append a caching block at the end)
- Modify: `README.md` (dev server section; create the section if missing)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing code-visible; later benchmark tasks assume the dev server runs with `PHP_CLI_SERVER_WORKERS=8`.

- [ ] **Step 1: Append cache-header block to `public/.htaccess`**

Add at the end of the file (do not touch the existing rewrite rules):

```apache
# Let browsers cache static assets for a week. Fingerprintless filenames mean
# a hard refresh is needed after asset changes, which is acceptable here
# because assets change only on deploys.
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 7 days"
    ExpiresByType application/javascript "access plus 7 days"
    ExpiresByType image/png "access plus 7 days"
    ExpiresByType image/jpeg "access plus 7 days"
    ExpiresByType image/svg+xml "access plus 7 days"
    ExpiresByType font/woff2 "access plus 7 days"
</IfModule>
```

- [ ] **Step 2: Document parallel dev-server workers in README.md**

Add to the local development section:

```markdown
### Dev server

Run the dev server with multiple PHP workers so the page and its assets load
in parallel instead of queueing behind a single worker:

    PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090

With one worker (the default) every CSS/JS/image request is served one at a
time, which alone adds seconds to each page load.
```

- [ ] **Step 3: Verify headers and workers**

Run: `curl -s -D - -o /dev/null http://localhost:8090/assets/css/styles.css | head -20` against an Apache-served instance if available; on spark serve just confirm the site still loads. Then start the server with `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090` and confirm a logged-in page load in the browser network tab shows assets loading in parallel.

Expected: page renders, no 500s, assets fetched concurrently.

- [ ] **Step 4: Run full test suite**

Run: `vendor/bin/phpunit`
Expected: all green (same pass/skip counts as before the change).

- [ ] **Step 5: Commit**

```bash
git add public/.htaccess README.md
git commit -m "perf: cache static assets, document parallel dev-server workers"
```

---

### Task 2: Index patch script + V17 dump

**Files:**
- Create: `sql/patches/v17-indexes.sql`
- Create: `accesscardV17.sql` (copy of `accesscardV16.sql` with the index lines added)

**Interfaces:**
- Consumes: `accesscardV16.sql` table definitions (`member`, `audit_trails`).
- Produces: indexes `idx_member_deleted_name`, `idx_member_created`, `idx_audit_created` that Task 5 benchmarks rely on.

Index choices, justified against the actual queries:

- `member (dt_deleted, lastname, firstname)`: `SearchModel::allMembersBuilder` always filters on `dt_deleted` (IS NULL or IS NOT NULL) and the default sort is `lastname, firstname`. This composite lets MySQL filter and return rows already in sort order, avoiding a filesort over the whole table. Also serves `DashboardModel::countFamilies/countMembers` (both filter `dt_deleted IS NULL`).
- `member (dt_created)`: the Manage Records date-range filter (`applyDateRange` on `m.dt_created`) and nothing else needs it.
- `audit_trails (dt_created)`: audit list pages order and filter by timestamp; the table grows fastest of all (one row per mutation).

Not indexed on purpose: `sectorID` is a varchar holding a JSON array (`'[]'`), filtered with LIKE, so a B-tree index cannot help it; keyword search is substring LIKE across many columns (user decision: keep LIKE-only), so it stays a scan.

- [ ] **Step 1: Write the patch script**

Create `sql/patches/v17-indexes.sql`:

```sql
-- V16 -> V17: adds the indexes behind the Manage Records list, dashboard
-- counts, and audit list. Run once against an existing accesscard DB:
--   mysql -u root accesscard < sql/patches/v17-indexes.sql
-- Each block first drops the index if it already exists so the script can be
-- re-run safely (MariaDB supports IF EXISTS / IF NOT EXISTS on indexes).

ALTER TABLE `member`
  ADD INDEX IF NOT EXISTS `idx_member_deleted_name` (`dt_deleted`, `lastname`, `firstname`);

ALTER TABLE `member`
  ADD INDEX IF NOT EXISTS `idx_member_created` (`dt_created`);

ALTER TABLE `audit_trails`
  ADD INDEX IF NOT EXISTS `idx_audit_created` (`dt_created`);
```

- [ ] **Step 2: Apply to the local DB and verify with EXPLAIN**

Run:

```bash
mysql -h 127.0.0.1 -u root accesscard < sql/patches/v17-indexes.sql
mysql -h 127.0.0.1 -u root accesscard -e "SHOW INDEX FROM member; SHOW INDEX FROM audit_trails;"
mysql -h 127.0.0.1 -u root accesscard -e "EXPLAIN SELECT m.memberID FROM member m LEFT JOIN member h ON h.memberID = m.headID WHERE m.dt_deleted IS NULL ORDER BY m.lastname, m.firstname LIMIT 50;"
```

Expected: the three indexes listed; the EXPLAIN row for `m` shows `key: idx_member_deleted_name` and no `Using filesort` in Extra.

- [ ] **Step 3: Create the V17 dump**

Copy `accesscardV16.sql` to `accesscardV17.sql`. In the copy, find the `ALTER TABLE \`member\`` block that adds `PRIMARY KEY (\`memberID\`)` and `KEY \`fk_head\`` and extend it:

```sql
  ADD KEY `idx_member_deleted_name` (`dt_deleted`,`lastname`,`firstname`),
  ADD KEY `idx_member_created` (`dt_created`);
```

Do the same for the `audit_trails` key block:

```sql
  ADD KEY `idx_audit_created` (`dt_created`);
```

Mind the comma/semicolon at the end of the existing last line of each block.

- [ ] **Step 4: Prove the V17 dump imports cleanly**

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS accesscard_v17check; CREATE DATABASE accesscard_v17check"
mysql -h 127.0.0.1 -u root accesscard_v17check < accesscardV17.sql
mysql -h 127.0.0.1 -u root accesscard_v17check -e "SHOW INDEX FROM member" 
mysql -h 127.0.0.1 -u root -e "DROP DATABASE accesscard_v17check"
```

Expected: import with no errors; `idx_member_deleted_name` and `idx_member_created` present.

- [ ] **Step 5: Run full test suite**

Run: `vendor/bin/phpunit`
Expected: green.

- [ ] **Step 6: Commit**

```bash
git add sql/patches/v17-indexes.sql accesscardV17.sql
git commit -m "perf(db): V17 indexes for member list, dashboard counts, audit list"
```

---

### Task 3: Cache dashboard stat counts (60s TTL)

**Files:**
- Modify: `app/Models/DashboardModel.php` (the `stats()` method, around line 29)
- Create: `tests/unit/DashboardStatsCacheTest.php`

**Interfaces:**
- Consumes: existing private counters `countFamilies()`, `countMembers()`, `countActiveLookup()` in `DashboardModel`.
- Produces: public constant `DashboardModel::STATS_CACHE_KEY = 'dashboard_stats'` (string). Task 4's invalidation deletes this exact key.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/DashboardStatsCacheTest.php` (mirrors the no-DB unit style of `tests/unit/AidTypeModelTest.php`):

```php
<?php

namespace Tests\Unit;

use App\Models\DashboardModel;
use CodeIgniter\Test\CIUnitTestCase;

final class DashboardStatsCacheTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete(DashboardModel::STATS_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        cache()->delete(DashboardModel::STATS_CACHE_KEY);
        parent::tearDown();
    }

    public function testStatsReturnsCachedValueWhenPresent(): void
    {
        // Prime the cache with a sentinel. If stats() reads the cache it
        // returns this untouched instead of recounting from the DB.
        $sentinel = ['families' => 7, 'members' => 21, 'sectors' => 3, 'assistance' => 5];
        cache()->save(DashboardModel::STATS_CACHE_KEY, $sentinel, 60);

        $this->assertSame($sentinel, (new DashboardModel())->stats());
    }

    public function testStatsPopulatesCacheOnMiss(): void
    {
        (new DashboardModel())->stats();

        $cached = cache()->get(DashboardModel::STATS_CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('families', $cached);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter DashboardStatsCacheTest`
Expected: FAIL with `Undefined constant App\Models\DashboardModel::STATS_CACHE_KEY`.

- [ ] **Step 3: Implement the cached stats()**

In `app/Models/DashboardModel.php`, add the constant and wrap `stats()`:

```php
    /**
     * Cache key for the dashboard headline counts. The audit-trail writer
     * deletes this key after every logged mutation, so the 60 second TTL is
     * only a fallback for changes that bypass the app (direct DB edits).
     */
    public const STATS_CACHE_KEY = 'dashboard_stats';

    /**
     * Returns the four headline counts (families, members, active sectors, active
     * services) for the dashboard summary cards. Frontend: dashboard overview.
     *
     * Counts are cached for 60 seconds because they scan the member table and
     * every dashboard visit was recomputing them. See STATS_CACHE_KEY for how
     * the cache stays fresh after mutations.
     */
    public function stats(): array
    {
        $cached = cache(self::STATS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $stats = [
            'families' => $this->countFamilies(),
            'members' => $this->countMembers(),
            // "Active Sectors" / "Services and Programs" cards: count only live
            // rows so archiving lowers the figure and restoring raises it again.
            'sectors' => $this->countActiveLookup('sector'),
            'assistance' => $this->countActiveLookup('services'),
        ];

        cache()->save(self::STATS_CACHE_KEY, $stats, 60);

        return $stats;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter DashboardStatsCacheTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Run full test suite**

Run: `vendor/bin/phpunit`
Expected: green.

- [ ] **Step 6: Commit**

```bash
git add app/Models/DashboardModel.php tests/unit/DashboardStatsCacheTest.php
git commit -m "perf(dashboard): cache headline stat counts for 60s"
```

---

### Task 4: Invalidate the stats cache on audited mutations

**Files:**
- Modify: `app/Models/Audit/AuditTrailsModel.php` (`logAction()`, around line 53)
- Test: `tests/unit/DashboardStatsCacheTest.php` (add one test)

**Interfaces:**
- Consumes: `DashboardModel::STATS_CACHE_KEY` from Task 3; existing `logAction(int $userId, ?int $memberId, string $action, ...): bool`.
- Produces: nothing new; behavior change only.

Rationale: every family mutation already funnels through `AuditTrailsModel::logAction()` (repo non-negotiable), so deleting the cache key there guarantees fresh tiles after any create/update/archive. Non-family audit actions (account changes etc.) also trigger a delete; that over-invalidation just causes one extra recount, which is harmless.

- [ ] **Step 1: Write the failing test**

Add to `tests/unit/DashboardStatsCacheTest.php`:

```php
    public function testLogActionDeletesStatsCache(): void
    {
        cache()->save(DashboardModel::STATS_CACHE_KEY, ['families' => 1], 60);

        // Without the audit table this insert fails and returns false, but the
        // cache delete must run regardless so tiles never go stale.
        (new \App\Models\Audit\AuditTrailsModel())
            ->logAction(1, null, 'TEST_ACTION', 'cache invalidation test');

        $this->assertNull(cache()->get(DashboardModel::STATS_CACHE_KEY));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter testLogActionDeletesStatsCache`
Expected: FAIL, cached array still present.

- [ ] **Step 3: Add the delete at the top of logAction()**

At the start of `logAction()` in `app/Models/Audit/AuditTrailsModel.php`, before any table guard or insert:

```php
        // Every mutation in this app logs an audit row, so this is the one
        // choke point where the dashboard counts can change. Drop the cached
        // stats here so the tiles recount on the next dashboard visit.
        cache()->delete(\App\Models\DashboardModel::STATS_CACHE_KEY);
```

Keep the rest of the method untouched. The audit write itself must not depend on the cache call succeeding; `cache()->delete()` does not throw for a missing key.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter DashboardStatsCacheTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Run full test suite**

Run: `vendor/bin/phpunit`
Expected: green.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Audit/AuditTrailsModel.php tests/unit/DashboardStatsCacheTest.php
git commit -m "perf(dashboard): drop cached stats on every audited mutation"
```

---

### Task 5: 100k-row benchmark, before/after receipts

**Files:**
- Create: `/private/tmp/claude-502/.../scratchpad/seed100k.php` (scratch; NOT committed)
- Create: `docs/superpowers/plans/2026-07-12-performance-benchmarks.md` (results; committed)

**Interfaces:**
- Consumes: indexes from Task 2, caching from Tasks 3-4.
- Produces: the timing numbers for the supervisor (network-tab targets: 5-10s full page, hard cap 30s).

- [ ] **Step 1: Snapshot the current DB so it can be restored**

```bash
mysqldump -h 127.0.0.1 -u root accesscard > "$SCRATCHPAD/accesscard_pre_bench.sql"
```

- [ ] **Step 2: Write the seed script (scratchpad, not the repo)**

```php
<?php
// Seeds ~100k family heads plus 2 relatives each (300k member rows) for the
// performance benchmark. Run with the intl-enabled php, not XAMPP's:
//   php seed100k.php
// Talks straight to MySQL; the app is not involved.
$pdo = new PDO('mysql:host=127.0.0.1;dbname=accesscard;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET autocommit=0');

$last = ['Garcia','Reyes','Santos','Cruz','Bautista','Ocampo','DelaCruz','Torres','Ramos','Aquino'];
$first = ['Jose','Maria','Juan','Ana','Pedro','Luz','Carlos','Rosa','Miguel','Elena'];

$insert = $pdo->prepare(
    'INSERT INTO member (lastname, firstname, middlename, headID, sectorID, address, dt_created)
     VALUES (?, ?, ?, ?, ?, ?, NOW())'
);

for ($i = 0; $i < 100000; $i++) {
    $ln = $last[$i % 10] . $i;
    // Head row first; headID must point at itself, so insert with 0 then fix.
    $insert->execute([$ln, $first[$i % 10], 'B', 0, '[]', 'Blk ' . $i . ' Binan']);
    $headId = (int) $pdo->lastInsertId();
    $pdo->exec("UPDATE member SET headID = $headId WHERE memberID = $headId");
    // Two relatives per head.
    $insert->execute([$ln, 'Child1', 'B', $headId, '[]', 'Blk ' . $i . ' Binan']);
    $insert->execute([$ln, 'Child2', 'B', $headId, '[]', 'Blk ' . $i . ' Binan']);
    if ($i % 1000 === 0) { $pdo->exec('COMMIT'); echo "$i\n"; }
}
$pdo->exec('COMMIT');
echo "done\n";
```

Adjust column list if the insert errors on NOT NULL columns; check `accesscardV17.sql` for defaults.

- [ ] **Step 3: Measure WITHOUT indexes (before numbers)**

Drop the Task 2 indexes first so the "before" is honest:

```bash
mysql -h 127.0.0.1 -u root accesscard -e "ALTER TABLE member DROP INDEX IF EXISTS idx_member_deleted_name, DROP INDEX IF EXISTS idx_member_created; ALTER TABLE audit_trails DROP INDEX IF EXISTS idx_audit_created;"
```

Then, logged in as developer in the browser, record network-tab total load time for: dashboard, Manage Records list, a keyword search, /sectors. Also time the raw queries:

```bash
mysql -h 127.0.0.1 -u root accesscard -e "SELECT COUNT(*) FROM member WHERE dt_deleted IS NULL; SELECT memberID FROM member WHERE dt_deleted IS NULL ORDER BY lastname, firstname LIMIT 50;" -vv 2>&1 | grep -i "sec\|rows"
```

- [ ] **Step 4: Re-apply indexes, measure again (after numbers)**

```bash
mysql -h 127.0.0.1 -u root accesscard < sql/patches/v17-indexes.sql
```

Repeat every measurement from Step 3. Confirm dashboard second-visit is near-instant (cache hit) and first-visit acceptable.

- [ ] **Step 5: Write up results and restore the DB**

Record before/after numbers per page in `docs/superpowers/plans/2026-07-12-performance-benchmarks.md` (simple table: page, before, after, target met yes/no). Then restore:

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE accesscard; CREATE DATABASE accesscard"
mysql -h 127.0.0.1 -u root accesscard < "$SCRATCHPAD/accesscard_pre_bench.sql"
```

- [ ] **Step 6: Commit**

```bash
git add docs/superpowers/plans/2026-07-12-performance-benchmarks.md
git commit -m "docs: before/after benchmark numbers for the V17 performance work"
```

---

### Task 6: RAG / knowledge docs update

**Files:**
- Create: `docs/knowledge/binan-conventions/performance.md`
- Modify: `.claude/skills/binan-conventions/SKILL.md` (add performance.md to the grep index / decision table)
- Modify: `docs/knowledge/violations.md` (tick anything this work fixed; append none unless verified)

**Interfaces:**
- Consumes: everything shipped in Tasks 1-5.
- Produces: retrieval docs future sessions read before touching queries or caching.

- [ ] **Step 1: Write performance.md**

Create `docs/knowledge/binan-conventions/performance.md`. Content requirements (write in plain sentences for the team, no em dashes, no filler):

```markdown
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
"fix" it with FULLTEXT.

## Dashboard stat caching
`DashboardModel::stats()` caches its counts for 60 seconds under
`DashboardModel::STATS_CACHE_KEY`. `AuditTrailsModel::logAction()` deletes that
key, so any audited mutation refreshes the tiles on the next visit. The TTL is
only a fallback for direct DB edits. If you add a new cached aggregate, follow
the same pattern: constant for the key, delete in the mutation funnel, short
TTL as backstop.

## Dev server
Run `PHP_CLI_SERVER_WORKERS=8 php spark serve --port 8090`. One worker
serializes all asset requests and makes every page feel seconds slower.

## Comment style
Comments and these docs are read by the devs on this team, not by tooling.
Explain how the code works and why it is built that way, in plain sentences.
No em dashes, no boilerplate.
```

- [ ] **Step 2: Register performance.md in the binan-conventions skill**

Open `.claude/skills/binan-conventions/SKILL.md`, find the decision table / grep index over `docs/knowledge/`, and add a row pointing queries about indexes, caching, EXPLAIN, or slow pages to `docs/knowledge/binan-conventions/performance.md`. Match the existing row format exactly.

- [ ] **Step 3: Update violations.md**

Open `docs/knowledge/violations.md`. Tick (mark fixed) any listed item this work resolved (look for entries about unindexed queries, dashboard recomputation, or dev-server setup). Do not add new entries unless you verified them during this work.

- [ ] **Step 4: Save the V17 memory note**

Write a Claude memory file (memory dir listed in the system prompt) named `dump-v17-indexes.md`, type `project`: accesscardV17.sql = V16 plus `idx_member_deleted_name`, `idx_member_created`, `idx_audit_created`; patch script at `sql/patches/v17-indexes.sql`. Add the one-line pointer to `MEMORY.md`.

- [ ] **Step 5: Run full test suite**

Run: `vendor/bin/phpunit`
Expected: green (docs-only task; this is a regression guard before the final commit).

- [ ] **Step 6: Commit**

```bash
git add docs/knowledge/binan-conventions/performance.md .claude/skills/binan-conventions/SKILL.md docs/knowledge/violations.md
git commit -m "docs(knowledge): performance conventions (indexes, caching, dev server)"
```
