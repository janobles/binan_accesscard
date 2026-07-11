# Scanner Scan Page Redesign — Design Spec

**Date:** 2026-07-07
**Scope:** `app/Views/Scanner/scan.php` (+ `public/css/scanner.css`). View/JS only —
no controller, model, route, or schema changes. Reports/manage pages are out of
scope this round (parked: Apply and Download buttons share `btn-primary`, weak
differentiation).

## Context

Scanner-role staff log aid distributions on desktop/laptop with a USB QR gun
(keyboard wedge: types the control number, then sends Enter). The backend IPO is
sound — lookup JSON, claimant-in-family guard, transaction-wrapped insert +
audit trail, duplicate-claim warning — and is untouched. The current UI is a
temporary single-column card stack with two problems:

1. **Auto-clear bug:** after a scan triggers lookup, `controlInput` keeps its
   value; the next gun burst appends to it (e.g. "42" + "57" → "4257").
2. **Post-log feedback:** a 2.5s "logged successfully" banner while the whole
   family panel vanishes — no proof of what was logged.

## Goals

- Scan loop for high-volume days: **scan → glance → Enter → scan**, one keypress
  per family beyond the scan itself.
- Fix auto-clear so consecutive gun scans never concatenate.
- Everything visible without scrolling on desktop; responsive single column on
  smaller screens.
- Passive step guidance for new staff without slowing experienced staff.
- Post-log receipt that proves what landed.

## Design

### Layout (responsive)

Full-width top strip: step indicator, then aid-type picker + scan input side by
side. Below, Bootstrap grid: `col-lg-7` family panel (head, members),
`col-lg-5` confirm panel + aid history. Below the `lg` breakpoint the grid
stacks in DOM order: scan input → family → confirm → history. Bootstrap
utilities + adapter classes only (no inline styles); page-specific rules go in
the scanner page CSS registered via `asset_helper.php` lists.

Aid type is chosen once per session and persists across scans (existing
behavior, by design — time and motion).

### Step indicator (passive)

Three-step strip: `1 Aid type → 2 Scan QR → 3 Confirm`. Highlight follows
state automatically:

- No aid type → step 1 active; scan input visually de-emphasized with hint.
- Aid type set → step 2 active, input armed.
- Family loaded → step 3 active; Confirm button gains emphasis.
- After logging → back to step 2 (aid type persists).

Purely visual state reflection. It never intercepts input or adds a keypress.

### Scan loop mechanics

- **Clear-on-lookup:** `lookup()` clears `controlInput` and refocuses it as the
  fetch starts. On lookup failure, the scanned value is restored into the input
  and selected, with the error alert shown.
- **Empty-Enter confirm:** Enter pressed while the input is *empty* and a
  family panel is loaded submits the log form. The gun always sends
  code+Enter (never a bare Enter), so there is no ambiguity: non-empty input +
  Enter = lookup; empty input + Enter = confirm. Confirm button label:
  "Confirm (Enter)".
- **Focus guard:** a `window` keydown handler refocuses `controlInput` when an
  alphanumeric key is pressed and focus is not in an input/select/textarea — a
  stray click can never break the gun flow.
- A new scan at any moment replaces the currently loaded family.

### Post-log receipt

Replace the vanish-and-banner with a **success receipt card** in place of the
confirm panel:

> ✓ Logged: {aid type} → {claimant} (Family #{control}), {date}
> Ready for next scan…

- Family panel stays visible (slightly dimmed) with the refreshed history —
  the new row is the proof (optional brief green highlight on that row).
- Input already cleared and focused.
- Receipt persists until the next scan; no timeout race.

### Button semantics (kept)

Green Confirm; switches to warning-yellow with duplicate alert when the same
aid type was already claimed today (existing `evaluateDuplicate` logic
unchanged).

## Error handling

Unchanged server contract. Client paths:

- Lookup 404/network → alert, value restored + selected, family panel hidden.
- Log 422/500 → field errors shown in confirm panel; family panel stays;
  receipt not shown.

## Testing

- `vendor/bin/phpunit` before/after (no PHP logic change expected to break).
- Manual gun smoke test: consecutive scans never concatenate; scan → Enter
  logs; failed lookup restores value; duplicate path shows yellow confirm;
  receipt shows correct aid/claimant/date; layout at ≥992px shows two columns
  with no page scroll, below 992px stacks cleanly.
