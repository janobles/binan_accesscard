# QR Access Cards for Family Heads — Design

Biñan Access Card (CodeIgniter 4). Absorb the standalone `cswd-qr` prototype into
this system so every registered head of family has a printable QR access card used
for relief-goods / aid distribution.

## Goal

- Generate printable QR cards for registered family heads (batch PDF/ZIP and
  single-card reprint).
- Resolve a scanned QR back to the head's record ("vice-versa" lookup).
- **No database schema change. No migrations.** (Per project Non-Negotiables —
  schema source of truth is the external `accesscardV3.0.sql` dump.)

## Core Invariant: One Head ⇄ One QR

The control number is **derived from the head's `memberID`**, not stored. Because
`memberID` is the unique primary key of the `member` table, the mapping is
bijective:

- One head = one `memberID` = one control number = one QR code.
- Reprinting a card produces the **same** QR — never a new or duplicate code.
- No path exists to mint a second code for a head, or to share one code across
  heads.

`ControlNumber` (new class) is the single source of this bijection:

- `format(int $memberID): string` → `str_pad((string) $id, 6, '0', STR_PAD_LEFT)`
  → e.g. `000042`. Width from config (`controlNumberWidth`, default 6).
- `resolve(string $control): ?int` → `(int) ltrim($control, '0')`, then verify the
  row exists **and is a head** (`headID == memberID`). Returns `null` otherwise.
- QR payload = `qrUrlPrefix . control`. Default `qrUrlPrefix = ''` → bare control
  number (no PII, host-independent, offline-readable). `.env`-overridable to a
  full lookup URL later with no code change.

## Architecture

Reuse the prototype's tested, framework-agnostic libraries; feed them this
system's real registered heads instead of a blank numeric range. Controllers
decide, libraries build (per project guideline).

### Libraries (`app/Libraries/Qr/`)

Copied from prototype, namespace-adjusted to `App\Libraries\Qr`:

- `QrImageGenerator`, `QrPngOutput` — pure-PHP QR rendered as PNG data URI
  (requires `ext-gd`). A fresh `QRCode` instance per control number (reusing one
  overflows capacity — documented prototype gotcha).
- `QrBatchPlanner` — **adapted**: takes a list of heads (id, name, barangay)
  instead of a start/end range; chunks for large batches.
- `QrBatchPdfGenerator` — dompdf renderer, fixed CSS-table 3×4 grid (12 cards /
  US-Letter page). Card now prints head **name + barangay** (prototype left these
  blank) plus the QR and control number. Single chunk → one PDF; multiple → ZIP
  via `ZipArchive` (temp file cleaned in `finally`).
- `ControlNumber` — new; the bijection above.

### Controller (`app/Controllers/Cards/QrCardController`)

- `batch()` — POST. Accepts filter (all heads / by barangay / by sektor;
  archived excluded). Queries heads, streams PDF or ZIP with
  `Content-Disposition: attachment`. Writes an `audit_trails` entry (batch card
  issuance is a family-data event — do not bypass audit).
- `card(int $memberID)` — GET. Single head → one-card PDF (reprint; same QR as
  always).
- `lookup(string $control)` — GET. `ControlNumber::resolve()` → redirect to the
  existing record page `admin/manage-family/view/{memberID}`. Invalid / non-head
  control → 404 + flash.

### Model

Reuse `App\Models\Families\MemberModel`. Add one read method:

- `headsForCards(array $filter): array` — returns `memberID`, name parts,
  `barangay` for heads only (`headID = memberID`), honoring filter and excluding
  archived rows.

### Views

- `app/Views/Cards/batch_form.php` — filter + generate page, rendered inside the
  admin shell. AJAX submit with `responseType: 'blob'`, synthetic-anchor download,
  blob-read error handling (ported from prototype `home.php`).
- `app/Views/Cards/pdf/*` — card grid + styles (ported `pdf/batch_page.php`,
  `pdf/_styles.php`).
- A **"Print QR"** button on the existing manage-family view page → `card(:num)`.

### Config (`app/Config/QrCardSettings.php`)

Ported from prototype `QrBatchSettings`: `qrUrlPrefix`, grid (cols/rows),
chunk size, `controlNumberWidth = 6`, `maxQuantity`, file-name patterns. All
`.env`-overridable.

### Composer dependencies

Add to this app: `chillerlan/php-qrcode:^6`, `dompdf/dompdf:^3` (already proven
in prototype).

## Routes (under existing `admin` group — same auth gate)

```
admin/cards               GET  -> Admin\DashboardController::cards   (shell page)
admin/cards/generate      POST -> Cards\QrCardController::batch
admin/cards/card/(:num)   GET  -> Cards\QrCardController::card/$1
admin/cards/lookup/(:any) GET  -> Cards\QrCardController::lookup/$1
```

## Data Flow

- **Batch:** form filter → `MemberModel::headsForCards()` → `QrBatchPlanner`
  chunks → `QrBatchPdfGenerator` (one QR per head's control number) → blob
  download + audit entry.
- **Scan:** reader yields `000042` → staff opens `admin/cards/lookup/000042`
  (or pastes the number into manage-records search) → redirect to head record.

## Error Handling (carried from prototype)

- Missing `ext-gd` → fail fast with a clear message.
- Empty head set for filter → 400 JSON: "no heads match filter".
- Generation error → log detail, return generic client-safe message (no leak).
- Large batches → raise `memory_limit` to 512MB when lower, `set_time_limit(300)`
  per chunk.
- `lookup` invalid or non-head control → 404.

## Testing (`vendor/bin/phpunit` before and after)

- Port prototype tests (planner, QR generation, PDF render, 12-cards/page
  pagination, chunk orchestration, HTTP endpoint).
- `ControlNumberTest` — `format`/`resolve` round-trip, leading-zero handling,
  non-head and missing-row rejection.
- `headsForCards` filter (barangay/sektor/all, archived excluded).
- `lookup` redirect-on-valid and 404-on-invalid.

## Out of Scope (YAGNI)

- No receipt / distribution logging at scan time.
- No `control_number` column or any schema/migration change.
- No full-URL QR payload now (one-line `.env` switch later).
- No CSV roster import.
