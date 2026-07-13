# Batches → Services Refactor + QR Pages UI/UX (V18)

**Date:** 2026-07-13
**Status:** Approved design, pending implementation plan

## Problem

The `aid_type` table (Financial/Rice/Grocery) duplicates what the existing
reference data already models: `services` (programs, with `shortcode` like
EDA8) grouped by `category` (with `code` like FA/SWPS/EDA). Aid types are
irrelevant and are fully superseded by services/programs. Batches must bind
to a service, not an aid type. The `admin/distribution` page also crams
three tabs (Aid Types / Batches / All Distributions) into one page with
unfaithful Bootstrap markup.

## Decisions (user-approved)

1. **Batch links to `service_id`** on both `distribution_batch` and
   `aid_distribution` (category derives from the service).
2. **Batch creation via modal** on the Batches page: category select →
   service select filtered client-side (services embedded as JSON, no AJAX).
3. **Page split:** two sidebar pages replace the tabbed `admin/distribution`
   page — `admin/batches` and `admin/distributions`. QR Code sidebar group
   becomes: Generate, Batches, Distributions, Reports.
4. **V18 delivered as dump + patch.** Old batch/scan demo data wiped (no
   aid-type→service mapping).
5. **Two PRs:** PR 1 = schema + backend + page split; PR 2 = UI/UX pass.
6. **Delete `accesscardV16.sql` now; delete `accesscardV17.sql` in the same
   PR once V18 import + smoke test pass.**
7. **Use reference-data codes for information density:** show
   `services.shortcode` and `category.code` as badges/prefixes wherever a
   service is displayed (e.g. "EDA8 — Relief Food Pack").

## PR 1 — Schema + backend

### V18 dump (`accesscardV18.sql`, from V17)

- Drop `aid_type` table entirely.
- `distribution_batch.aid_type_id` → `service_id int NOT NULL`, index
  `idx_db_service`.
- `aid_distribution.aid_type_id` → `service_id`, index `idx_ad_service`.
- Remove test seed rows: sector 11 (`TS`), category 8 (`TSC`), services
  47–48 (`TS1`, `TSC1`).
- Developer account: `users` row `developer` / `developer123` (argon2id),
  `account_level 'developer'`, compatible with database-backed developer
  enforcement.
- No family/QR data (V14 demo-workflow rule).

### Patch (`sql/patches/v18-batch-service.sql`, V17 → V18 in place)

Truncate `distribution_batch` + `aid_distribution`; alter the two columns;
drop `aid_type`; delete the test reference rows; upsert developer account.

### Code changes

- Delete `AidTypeModel`, aid-type CRUD in `Admin\DistributionController`,
  `distribution-aidtypes-body.php`, aid-type routes.
- `DistributionBatchModel::open()` takes `service_id`; single-open-batch
  invariant unchanged. Batch queries join `services` for
  shortcode/name/category display.
- `ScanController::logAid()` stamps `service_id` from the open batch;
  409-when-no-batch unchanged.
- Kiosk header badge: service shortcode + name (category code as small
  text) instead of aid type.
- `AidStatsModel`, `admin/reports`, `ReportsPdfGenerator`, `pdf/report.php`:
  aid-type columns → service/category (with codes).
- New pages/controllers under `admin/`:
  - `admin/batches` — batch list, open/close, create modal (`modal.php`
    partial): name field, category → service selects, POST
    `admin/batches/open`.
  - `admin/distributions` — paged, searchable all-distributions log.
  - Old `admin/distribution` route removed.
- Batch open/close remain audited (`audit_trails`), Admin/Developer only.
- Update tests; rewrite
  `docs/knowledge/binan-conventions/scanner-batches.md`; update CLAUDE.md
  dump reference.

## PR 2 — UI/UX pass (QR Code group)

Manage Records is the design source of truth (toolbar standard: search
blue, add `#198754`, `btn()` helper, pills, content-sized panels, dual
search wording). Playwright-verified per page at desktop and 390px, logged
in as developer/developer123.

- **Batches** — card + toolbar ("New Batch" opens modal), status pill on
  the open batch, table of past batches (shortcode+service, category code,
  opened/closed, opened by, handout count).
- **Distributions** — toolbar + paged table (scan time, control_no,
  shortcode+service, batch, scanner); "Search all distributions..."
  server-side.
- **Generate (`admin/cards`)** — restyle to the standard; no behavior
  change.
- **Reports (`admin/reports`)** — redesign unparked; same standard;
  per-kiosk table + PDF export kept, columns show service/category codes.
- **Kiosk (scan/performance)** — polish only, inside kiosk shell rules: no
  sidebar, no added per-scan keypresses, fits viewport.

## Out of scope

- Any schema change beyond the batch/service swap and test-row cleanup.
- Migrations (repo rule: SQL dump is schema source of truth).
- Retrofitting non-QR pages to the design system.

## Verification

- `php spark routes` resolves; `vendor/bin/phpunit` green before/after.
- Fresh import of V18 + smoke test (login, open batch via modal, kiosk
  scan logs service_id, close batch, reports render) → then delete V17.
- Playwright snapshots/screenshots for every touched page (PR 2).
- CodeRabbit CLI review per repo workflow before merging each PR. If the
  CodeRabbit trial has lapsed and the CLI refuses, fall back to a self
  code review (/code-review) with the same triage discipline.
