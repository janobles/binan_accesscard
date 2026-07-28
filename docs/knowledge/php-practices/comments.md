# Comment and docblock standard

The linter catches documentation that lies. Humans decide what deserves
documenting. A docblock is required on every class, every view, and every
stylesheet. A docblock is not required on every method: write one only when
it says something the signature cannot.

## Class and file docblocks

Two to four lines of prose: what it is, who calls it, why it exists.

```php
/**
 * Assembles view data for every dashboard page. Controllers pick WHICH page;
 * this class gathers the model data and renders the shell view. First place to
 * look when a dashboard page shows the wrong thing.
 */
class DashboardPageBuilder
```

## Method docblocks

Not required. Write one when it carries something the signature cannot, and skip it
otherwise. A docblock on `__construct(private IncomingRequest $request)` or on a
self-describing `encodeToPng()` is noise, and no tool will ask you for it.

Write one when there is a non-obvious side effect (writes an audit row, returns a
redirect instead of HTML), an invariant (rows are archived, never deleted), an array
shape, a unit, or a meaning a bare type cannot carry (what a null signifies).

When you do write one: what it does, plus anything surprising. `@param`/`@return`
**only** when the native type is insufficient.

```php
/**
 * Guards Developer/Admin access, then renders the admin shell on the given tab.
 * Returns a redirect instead of HTML when access is denied.
 *
 * @param string $activePage Tab slug, must match a key in TAB_VIEWS.
 */
public function renderAdminPage(string $activePage): string|RedirectResponse
```

`@param string $id The id.` is a lint error.

## Inline comments

Explain *why*, never *what*. A comment restating the line below it is deleted.

## View headers

Purpose, data source, and any non-obvious rendering constraint. No variable list.

```php
<?php
/**
 * Sector list page (Admin > Reference Data > Sectors).
 *
 * Data comes from sector_management_view_data(); this view never touches a
 * model. Counts are whole-table, not the current page.
 */
```

## View data contracts

The 10 `*_view_data()` functions in `app/Helpers/dashboard_view_helper.php` each get an
array-shape return docblock:

```php
/**
 * Builds the Sectors page bundle: the paged list plus the Add-Sector modal data.
 *
 * @return array{
 *     sectors: list<array<string, mixed>>,
 *     existingShortcodes: list<string>,
 *     activeCount: int,
 *     archivedCount: int,
 *     canManage: bool
 * }
 */
```

Shapes are read off the producing code, never guessed. Where a shape cannot be
determined with certainty from the source, prose describes the keys instead of an
invented `array{}`.

## CSS headers

`public/css/theme.css` already carries the model header. Every stylesheet matches its
form: what it styles, which manifest context loads it, and the scope boundary.

```css
/* Biñan green skin + house component rules layered over the vendored
   SB Admin 1 theme (assets/sb-admin/css/styles.css). Loaded via the `head`
   manifest context (asset_helper.php) so every dashboard shell gets it.
   Scope: theme tokens, shell colors, card tables, pagination, stat cards,
   topbar account menu. Page-specific rules stay in their page CSS files. */
```

Bootstrap 5.3 CSS-variable overrides stay exactly as they are, at both levels:
`:root` tokens in `theme.css`, and component-scoped `--bs-btn-*` blocks in page CSS.
That is Bootstrap's documented no-build customization path and the repo already uses
it correctly.

## Banned everywhere

- Em dashes (like the one you would have used to join this clause to the last).
- `// ---- section ----` and `/* ==== section ==== */` dividers. Replace with a blank
  line and a short prose sentence, or split the unit.
- `@author`, `@created`, `@version`.
- Comments describing a change someone wanted rather than what the code does.
- Historical residue (`the old records-multiselect widget was retired...`).
- AI-slop register. Plain language, matching `docs/knowledge/` house style.

## Written for the manual

- View headers name the page by **UI path** (`Admin > Reference Data > Sectors`), not
  file path.
- Controller docblocks state the **user-facing action**, not HTTP mechanics.
