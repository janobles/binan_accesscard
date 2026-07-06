# SB Admin Adapter (current reality)

The UI is **Bootstrap 5.3.3 + a homegrown adapter stylesheet** —
`public/css/sb-admin-adapter.css:1` — NOT a vendored SB Admin theme. The
adapter recreates the SB Admin shell (sidebar + topbar + content frame) on
top of stock Bootstrap. Target migration: `target-theme.md`.

## What the adapter provides — use these, don't hand-roll

Theme tokens (override here, not per-view) —
`public/css/sb-admin-adapter.css:1`:

```css
:root {
    --sb-sidebar-width: clamp(11.5rem, 17vw, 14rem);
    --sb-sidebar-bg: #145c3b;          /* Biñan green */
    --sb-content-bg: #f4f7f6;
    --sb-radius: 0.4rem;
    --ui-control-height: 2.5rem;
    ...
}
```

Shell frame (IDs the layouts depend on):
- `#wrapper` (`public/css/sb-admin-adapter.css:37`) — flex page frame.
- `#content-wrapper` (`:42`), `#content` (`:50`) — main column.
- `#sidebarToggle` (`:162`) — collapse control.

Sidebar component (SB Admin class names):
- `.sidebar`, `.sidebar-brand`, `.sidebar-brand-icon`, `.sidebar-brand-text`,
  `.sidebar-divider`, `.sidebar-heading`
  (`public/css/sb-admin-adapter.css:55`–`:114`).
- Nav states: `.sidebar .nav-link`, `:hover/:focus/.active`
  (`public/css/sb-admin-adapter.css:127`).
- `.bg-gradient-primary` (`:67`) — SB Admin's sidebar gradient class, mapped
  to the Biñan green.

Consumers: `app/Views/components/dashboard_sidebar.php:1` and the role
layout shells; loaded via `asset_styles()`
(`app/Helpers/asset_helper.php:39`).

## Rules

1. New chrome styling goes into the adapter (or a page CSS file), themed via
   the `--sb-*` / `--ui-*` variables — never hardcoded colors in views.
2. Keep SB Admin's class vocabulary (`.sidebar`, `.sidebar-brand`,
   `.bg-gradient-primary`, ...) so the eventual theme swap
   (`target-theme.md`) is a stylesheet change, not a markup rewrite.
3. Don't duplicate what Bootstrap 5.3 already ships — the adapter covers only
   the shell and theme tokens; components (cards, tables, buttons, modals)
   are stock Bootstrap (`docs/knowledge/binan-conventions/views-bootstrap.md`).
