# Scanner Kiosk / Admin-Server Split — Design

**Date:** 2026-07-08
**Status:** Approved (design), pending implementation plan
**Repo:** binan_accesscard (CI4)

## Problem

The scanner module mixes two audiences under one URL prefix. When an
Admin-level user opens `/scanner/manage` or `/scanner/reports`, they land in a
"frankenshell" — kiosk pages that borrow the admin sidebar (`sidebarUserUrl =>
admin/dashboard`). Meanwhile aid-type selection happens on the kiosk itself
(`/scanner/setting`), even though choosing what aid to hand out and controlling
when distribution starts/stops is an administrative decision.

Real-world topology: **one main server** (Admin role) on a LAN serves the app;
**multiple kiosk devices** (each its own Scanner account) hit the same URL with
a QR-code gun. Admin is the overview/server; each kiosk is a scan station.

## Goals

1. **Clean shell boundary by URL prefix.** `/admin/*` always renders the admin
   shell (topbar + sidebar). `/scanner/*` always renders the kiosk shell (green
   theme, no chrome). No page mixes the two.
2. **Aid selection + batch control are admin-owned.** Admin picks the aid type
   when opening a batch and starts/ends batches. The kiosk never picks aid.
3. **Kiosk is pure scan + its own performance.** Green-themed, minimal.
4. **Performance is emphasized**, not a lone `families: n` badge. Admin sees
   overall (combined + per-kiosk drilldown); each kiosk sees its own live
   metrics/visualizations.
5. **Near-real-time via polling**, no worker/daemon, no websockets.
6. **Batch is the only report filter.** Remove the date-range selector.

Non-goals: websocket push, cross-batch "all-time" aggregate views, any change to
the QR card generation flow (already correctly under `/admin/cards`).

## Core model decision: aid type is bound to the batch

Chosen over a separate "active aid type" switch for simplicity. **One batch =
one aid type, locked at open time.** To hand out a different aid, admin closes
the batch and opens a new one. This gives clean audit semantics ("Batch 5 =
Rice") and means the kiosk needs no aid picker — every scan logs against the
active batch's aid type.

### Schema change → new dump `accesscardV16.sql`

`distribution_batch` gains an aid-type column. Per the non-negotiables, this is
done by **producing a new SQL dump version**, not a code migration.

```sql
ALTER TABLE `distribution_batch`
  ADD `aid_type_id` int(11) NOT NULL AFTER `name`,
  ADD KEY `idx_db_aidtype` (`aid_type_id`);
```

`aid_distribution.aid_type_id` stays (per-row, denormalized from the batch at
log time) — no change; it keeps historic rows self-describing and avoids a join
on every history lookup.

## Target route / shell map

**Admin shell (`/admin/*`, sidebar+topbar, Admin/Developer):**

| Route | Purpose | Origin |
|-------|---------|--------|
| `/admin/cards` | QR card generation | unchanged |
| `/admin/aid-types` | aid-type CRUD (create/archive/restore/delete) | moved from `scanner/manage` |
| `/admin/batches` | open/close batch **+ pick aid type at open**; void distributions | moved from `scanner/manage` |
| `/admin/reports` | overall stats: combined totals + per-kiosk drilldown + PDF | moved from `scanner/reports` |

**Kiosk shell (`/scanner/*`, green, no chrome, Scanner + Admin/Dev for testing):**

| Route | Purpose | Change |
|-------|---------|--------|
| `/scanner/scan` | scan a QR, log aid against active batch's aid type | aid type no longer a query param; taken from active batch |
| `/scanner/performance` | this kiosk's own live metrics/visualizations | replaces the `families: n` badge; new emphasis |
| `/scanner/lookup/{n}`, `/scanner/log` | JSON endpoints backing scan | unchanged behavior |
| `/scanner/stats` (new) | JSON endpoint for kiosk poll (own counts) | new |
| ~~`/scanner/setting`~~ | aid picker | **removed** |
| ~~`/scanner/manage`~~ | back-office | **removed** (moved to admin) |
| ~~`/scanner/reports`~~ | stats | **removed** (moved to admin) |

Route guards: admin routes `requireRole(['Admin','Developer'])`; kiosk scan +
performance allow `['Scanner','Admin','Developer']` (Admin/Dev retained so the
server can test a station).

## Behavior details

### Batch open (admin)
`/admin/batches` open form: name + **aid-type dropdown (required)**.
`DistributionBatchModel::open()` signature extends to accept `aidTypeId`.
Single-open-batch invariant unchanged (`activeBatch()` = the row with
`closed_at IS NULL`). Opening is blocked while a batch is open. Every
open/close writes an audit row (unchanged).

### Scan (kiosk)
`/scanner/scan` no longer reads `?aid_type`. It resolves the active batch; if
none is open, it shows a clear "no active batch — ask admin to start one" state
instead of redirecting to a (now-removed) setting page. `logAid()` reads the aid
type from the active batch, not from POST. Existing family-membership guard,
transaction, and audit stay intact. Scan-response still returns the refreshed
own-count so the kiosk updates instantly on its own scan.

### Performance / reports (polling)
- **Kiosk `/scanner/performance`**: own metrics only (scoped to
  `session('user_id')`), batch-scoped (default = active batch, else latest).
  Auto-polls `/scanner/stats` every **5s**; shows a "last updated Ns ago"
  timestamp and a manual **Refresh** button. Metrics: families served, over
  time / by aid, whatever the existing `perScanner` + counts support, rendered
  with the existing Chart.js setup.
- **Admin `/admin/reports`**: overall — combined totals across all kiosks +
  per-kiosk table (`AidStatsModel::perScanner($batchId)` with no user filter),
  batch selector (existing), PDF export (existing). Same 5s poll + timestamp +
  refresh button.

### Removing the date filter
`AidStatsModel` methods (`receivedVsNot`, `byBarangay`, `byAidType`) currently
take `($from, $to, $batchId)`. Drop the date params; scope is always a batch.
`applyScope()` simplifies to batch-only. Reports/PDF controllers stop reading
`from`/`to`. Default scope resolution: active batch → else most recent batch.
Batch dropdown switches to any past batch. No cross-batch aggregate.

### Concurrency (verified, no work needed)
- Two kiosks scanning simultaneously → two independent `INSERT`s, InnoDB
  row-level locking, no collision.
- Concurrent reads never conflict.
- Load ceiling is trivial (~4 writes/sec worst case at ~20 kiosks); Apache
  prefork default (~150 workers) is far above the kiosk + poll count.
- **Known narrow race (documented, optional guard):** the same family scanned
  for the same aid at two kiosks within ~50ms writes two rows. Pre-existing;
  physically unlikely (one family stands at one kiosk). Optional soft guard:
  reject a second claim for the same `control_no` in the same batch.

## Operational rule (must document)

**One Scanner account per kiosk device.** Per-kiosk metrics are keyed on the
distinct `userID` written to each `aid_distribution` row. Sharing one Scanner
login across devices merges their stats. Note the Developer account still logs
`userID` NULL (existing behavior) — it is excluded from per-kiosk attribution.

## Theme

Kiosk shell (`Scanner/kiosk-layout.php`) restyled to the Biñan green theme —
aligned to the app's SB Admin 1 target where sensible, but kiosk keeps its
chrome-less full-screen layout. Green primary surfaces/accents; large,
gun-friendly scan target and legible live counters.

## Out of scope / follow-ups

- Optional duplicate-claim guard (flagged above).
- Any admin dashboard redesign beyond adding the three moved surfaces to the
  sidebar nav.

## Test focus

- Route resolution (`php spark routes`) for every renamed/moved route.
- Login → role redirect: Scanner lands on `/scanner/scan`; Admin on admin shell.
- Batch open with aid type; scan logs that aid type; audit rows written.
- Scan with no open batch → graceful empty state (no redirect loop).
- Reports batch scoping with date params fully removed.
- Per-kiosk attribution across two distinct Scanner accounts.
