---
name: ui-verification
description: Use after making any UI or UX change in this repo, to verify it visually before calling it done. Covers driving the dev server with the Playwright MCP, logging in, choosing between an accessibility snapshot and a screenshot, the two viewport widths to check, and what to compare against.
---

# Verifying a UI change

A UI change is not done because the markup looks right. Verify it against the
running application.

## Get the app up

The dev server should be on `app.baseURL`, typically `http://localhost:8090`. If
it is down, start it. The `run-app` skill covers the modes and the worker-count
flag that stops the page feeling artificially slow.

Log in as `developer` / `developer123`.

## Snapshot or screenshot

Pick by what you are asserting.

**`browser_snapshot`** returns the accessibility tree. Use it for structure: that
a heading exists, that a table has the columns you expect, that a button is
reachable and labelled, that an empty state rendered. It is also the honest test
of whether a control is announced to a screen reader, which matters here because
the interface is used by staff of widely varying abilities.

**`browser_take_screenshot`** returns pixels. Use it for appearance: spacing,
alignment, colour, whether a panel is the size you meant.

Most changes want the snapshot. Reach for the screenshot when the change is
visual.

## Two widths, every affected tab

Check each affected tab at desktop width and at **390px**. The 390px pass is not
optional: staff use this on phones at distribution venues, and the toolbar and
filter panel are where narrow widths break first.

## Compare against Manage Records

`/records` is the design source of truth. When your page and Manage Records
disagree about toolbar placement, card chrome, table typography, search
placeholder wording, or the page-size control, Manage Records is right.

`docs/20-frontend.md` states those rules, including the button colour roles from
`btn()`, the toolbar anatomy, the filter panel behaviour, and the pill rules. Read
it rather than inferring the standard from whatever page you happen to be on.

## Things worth checking that are easy to miss

- The toolbar renders **above** the card, never inside it.
- No button carries a hardcoded `btn-*` colour class; colours come from `btn()`.
- The filter panel has no Apply or Reset button inside it.
- A default option such as Active or All renders no pill.
- The page search says "Search this page..." and the toolbar search says
  "Search all `<entity>`...".
- Nothing conveys state by colour alone.
- Interactive elements keep a 44px minimum touch target even in compact views.

## If Chromium is missing

If the MCP reports no browser:

```bash
npx playwright install chrome
```

## A version trap that looks like a CSS bug

If a Bootstrap class from the documentation appears to do nothing, check whether
it is a 5.3-only class before debugging further. Dashboard pages load Bootstrap
compiled into SB Admin v7.0.7, which is 5.2.3, so 5.3 additions such as
`.nav-underline` silently no-op there. The login page is a different stylesheet
and does have 5.3. `docs/reference/version-pins.md` has the detail.
