# Navigation Taxonomy, Single URL Space, and Profiling Surfaces - Design

**Date:** 2026-07-29
**Scope:** Terminology alignment (role + subsidy), collapse of the three role-prefixed
URL spaces into one, single layout driven by a navigation manifest, and rebuild of the
four profiling surfaces (Family Records, Data Entry, Import, Family Profile). Scanner
kiosk untouched.

## Problem

Three problems, all the same shape: one concept is spelled several ways.

**1. One page exists three times.** `admin/manage-records`, `employee/manage-records`,
and `viewer/manage-records` render the same table through three layouts
(`Admin/layout.php` 417 lines, `Employee/layout.php` 247, `Viewer/layout.php` 175).
The sidebar nav is hand-copied into all three (`components/dashboard_sidebar.php` for
admin, inlined at `Employee/layout.php:44-49` and `Viewer/layout.php:52-56`). The
`manage-family/*` route group is duplicated verbatim between admin and employee. A
`routeBase` string is threaded through 8 files to paper over the prefix difference,
and 72 `site_url()` call sites hard-code a role prefix.

Any navigation change therefore has to be made three times.

**2. Two terms have three spellings each.**

| Concept | Database | Internal label | UI label |
|---|---|---|---|
| The encoding role | `encoder` (`accesscardV18.sql:276`) | `Employee` (`RoleAccess::normalizeRole`) | `Encoder` (`RoleAccess::auditRoleLabel`) |
| Subsidy type | `subsidy`, `subsidy_type_id` | `AidTypeModel` | "Subsidy Type" |

`auditRoleLabel()` exists only to translate between two of its own app's spellings.
The `aid` to `subsidy` rename was started in the schema and never carried into the
code: table `subsidy` is correct, but `aid_distribution`, `aidID`, and index
`idx_db_aidtype` are not, and 38 code files still say aid. CLAUDE.md compounds this by
documenting the role as `User`, which no longer matches V18.

**3. The profiling surfaces do not match the work.** The data entry form is a modal,
which cannot hold a 15-member household comfortably. The import review page renders its
own `<html>` shell, its own dark navbar, and its own stylesheet
(`import-review.php:44-50`), bypassing the layout entirely; it shows a stat card row
(`:73`) and per-family card containers (`:75`) instead of the flat, correctable table
the task actually needs. The records table can flatten every member into its own row
(`FamilyDataTablePresenter::row()`, `$allMembersScope`), so a 4-person household reads
as four unrelated rows.

## Goals

- One spelling per concept, in the database, the code, and the UI.
- One URL per page. Role controls visibility, not the prefix.
- One layout and one navigation manifest. Adding a link is one array entry.
- Profiling surfaces that match the paper form the officer is transcribing from.
- Bootstrap 5 components and grid throughout. No new structural CSS.
- No behavior change to the audit trail; every family mutation still writes one.

## Non-goals

Distribution calendar, announcements, and password-reset tickets are out of scope. Each
needs schema of its own and none blocks this work. Scanner kiosk pages are untouched.

## 1. Terminology

### Role: Encoder

The V18 enum is already `encoder`, so this costs no schema change.

- `RoleAccess::normalizeRole()` returns `'Encoder'` in place of `'Employee'`.
- `RoleAccess::auditRoleLabel()` is **deleted**. It exists only to bridge the two
  spellings; with one spelling it has no work to do. Its callers use
  `normalizeRole()`.
- The legacy aliases `'user'` and `'employee'` are dropped from the match expression.
  The system is not deployed, so there are no stale sessions or pre-migration rows to
  keep resolving.
- `App\Controllers\Employee\` and `app/Views/Employee/` dissolve into the shared
  controller and layout (section 3).
- CLAUDE.md's non-negotiable is corrected: employee accounts store as `encoder`, not
  `User`.

### Subsidy: finish the rename

New dump `accesscardV19.sql` plus `sql/patches/v19-subsidy-rename.sql`, following the
V17/V18 patch pattern.

| Now | Becomes |
|---|---|
| table `aid_distribution` | `subsidy_distribution` |
| `aid_distribution.aidID` | `distribution_id` |
| index `idx_db_aidtype` | `idx_db_subsidy` |
| `AidTypeModel` | `SubsidyTypeModel` |
| `AidDistributionModel` | `SubsidyDistributionModel` |
| `AidStatsModel` | `SubsidyStatsModel` |
| `Admin\AidTypesController` | `Admin\SubsidyTypesController` |
| `admin/aidtypes/*` | `reference-data/subsidy-types/*` |
| `Admin/aidtypes-body.php` | `Admin/subsidy-types-body.php` |
| `Admin/aidtype-create-modal.php` | `Admin/subsidy-type-modal.php` |

Table `subsidy` and column `subsidy_type_id` are already correct and are not touched.
The Excel template and importer do not reference aid, so import is unaffected.

The dump must land in the same commit as the model rename: the scanner writes to
`aid_distribution` on every scan, and a half-applied rename breaks distribution
logging.

## 2. Single URL space

```
/dashboard        /records            /records/entry      /records/import
/records/{id}     /cards              /distribution       /reference-data
/accounts         /audit-trails       /scanner/*   (kiosk, unchanged)
```

The `admin/`, `employee/`, and `viewer/` prefixes are removed. No redirects from the
old URIs: the system is not deployed, so nothing links to them.

Consequences:

- The duplicated `manage-family/*` route group collapses to one definition.
- `routeBase` is deleted from all 8 files that thread it. A route is now the same
  string for every role.
- Role enforcement moves from per-action `RoleAccess::requireRole()` calls to one
  filter that reads the navigation manifest: route key maps to allowed roles.
  `requireRole()` itself stays for the handful of actions that guard on something
  other than the page (for example the developer-only account endpoints).
- Viewer safety is preserved structurally: mutation routes are registered with
  `roles` that exclude Viewer, so a Viewer session cannot reach a POST endpoint at
  all rather than being bounced by a guard.

## 3. One layout, one navigation manifest

**`app/Config/Navigation.php`** is the single source of truth for the sidebar. One
entry per link:

```php
['key' => 'records', 'label' => 'Family Records', 'icon' => 'bi-people-fill',
 'route' => 'records', 'heading' => 'Profiling',
 'roles' => ['Developer', 'Admin', 'Encoder', 'Viewer']],
```

**`app/Views/layout.php`** replaces `Admin/layout.php`, `Employee/layout.php`, and
`Viewer/layout.php` (839 lines total, target roughly 250). It renders the topnav, the
sidebar, and the page body switch.

**`components/dashboard_sidebar.php`** renders the manifest filtered by the session
role. Active state derives from the current route key, so the hand-maintained
`$navActive` map in `ViewLayoutModel` and in each layout is deleted.

The three `DashboardController` classes (`Admin\`, `Employee\`, `Viewer\`) merge into
one. Read-only behavior for Viewer is expressed as data, not as a separate controller:
the manifest withholds the mutation routes and the views render fields `disabled`.

## 4. Sidebar taxonomy

Seven links in four headings, the same count as the 2026-07-20 reorg left behind but
regrouped, and holding that count while adding three new pages. Grouping follows the
lifecycle the office actually works in
(profile, then card, then distribute), which is a real sequence here: a family cannot
be carded before it is profiled, nor receive a subsidy before it is carded.

| Heading | Link | Route | Icon |
|---|---|---|---|
| Core | Dashboard | `/dashboard` | `bi-house-door` |
| Profiling | Family Records | `/records` | `bi-people-fill` |
| | Reference Data | `/reference-data` | `bi-collection` |
| Distribution | Access Cards | `/cards` | `bi-qr-code` |
| | Distribution | `/distribution` | `bi-clipboard-check-fill` |
| Administration | Account Management | `/accounts` | `bi-person-fill-gear` |
| | Audit Trails | `/audit-trails` | `bi-clock-history` |

Reference Data sits under Profiling because it populates the two CSWDO-filled columns
of the paper form (Sektor, and Nakatanggap ng mga Programa at Serbisyo). It is
operational data an encoder needs weekly, not administrative data.

Data Entry and Bulk Import are **pages but not sidebar links**. Nobody sets out to
visit "Data Entry"; they set out to add a family, which begins at Family Records. Both
are reached from that page's toolbar. This is what holds the count at seven: Data
Entry, Import, and Family Profile are three new pages that add no sidebar links.

Per-role visibility:

| Link | Developer | Admin | Encoder | Viewer |
|---|---|---|---|---|
| Dashboard | yes | yes | yes | yes |
| Family Records | yes | yes | yes | read-only |
| Reference Data | yes | yes | read-only | read-only |
| Access Cards | yes | yes | yes | no |
| Distribution | yes | yes | no | read-only |
| Account Management | yes | yes | no | no |
| Audit Trails | yes | yes | own actions only | no |

Future distribution features (calendar, announcements) fold into `/distribution` as
tabs rather than new sidebar links, so seven remains the ceiling.

## 5. Family Records - `/records`

One row per household head, never per member.

```
Control No.  Head of Family      Members  Sector    Address      Actions
000142       DELA CRUZ, JUAN        4     SC, PWD   Canlalay     ...
000143       SANTOS, PEDRO          3     SP        Malaban      ...
```

- `$allMembersScope` and its relationship-subline branch are removed from
  `FamilyDataTablePresenter`. A new `Members` column carries the household count.
- Searching a member's name still matches; the hit resolves to that member's head row.
- Toolbar (existing design system from PR #23): `[+ Add Family]` green, to
  `/records/entry`; `[Import Excel]` to `/records/import`; search left with icon,
  entries right.
- Row click opens `/records/{id}`.

## 6. Data Entry - `/records/entry`

Create only. The control number resolves first, alone; the rest of the form follows on
the same page.

```
Control Number  [______]  available

  Head            Head of Family
  Members         Household Members
  Sectors         Sectors
  Services        Services and Programs
  [ Save ]
```

**Not a wizard.** The officer is transcribing from a single paper sheet where PUNO NG
PAMILYA sits above the MYEMBRO rows and both are visible at once, and each member's
`Relasyon sa Puno ng Pamilya` is defined relative to the head. Hiding the head while
members are entered would contradict the page the officer is reading from. This also
avoids reintroducing the two failures the 2026-07-27 audit found in the old tab split:
the duplicate read-only head summary that the tabs made necessary, and the submit-hang
caused by submitting while a pane was `display:none`.

The control number gate fixes that submit-hang structurally rather than by patching the
validation call: the async `qr-check` resolves before any other field exists, so a
pending or failed check can no longer wedge a save.

The left rail is a Bootstrap `list-group` driven by Scrollspy
(`data-bs-spy="scroll"`), which ships in the vendored bundle
(`public/assets/bootstrap/js/bootstrap.bundle.js`). No custom JS, no custom CSS.

## 7. Import - `/records/import`

Three steps: **Upload**, then **Review and Fix**, then **Done**.

There is no column-mapping step. The workbook is generated by this application
(`FamilyExcelTemplate`, route `records/template`), so the columns are known. A mapping
step would be ceremony.

The step indicator reuses the existing `nav-pills .segmented-tabs` component with
non-current steps `.disabled`. The chevron connectors seen in common import wizards are
dropped; they would be the only part of this design requiring new CSS, and the pills
carry the same information.

**The page renders inside the shared layout.** The private `<html>` shell, the dark
navbar, and `css/import-review.css` are deleted.

Review table, one row per person:

```
 !  Family      Role   Last Name  First Name  DOB         Sex
 !  DELA CRUZ   Head   DELA CRUZ  JUAN        [        ]  Male
    DELA CRUZ   Asawa  DELA CRUZ  MARIA       03/12/1988  Female
 !  DELA CRUZ   Anak   DELA CRUZ  JOSE        01/40/2010  [    ]
    SANTOS      Head   SANTOS     PEDRO       07/02/1975  Male

 [x] Only show rows with problems        Showing 2 of 350 rows
```

- Per-person rows put the error flag on the exact bad value; `Family` and `Role`
  columns carry the household grouping.
- Flagged cells are click-to-edit inline, backed by the existing
  `import/review/{id}/family/cell` endpoint.
- `Only show rows with problems` defaults to on. With a clean file the officer sees an
  empty table and presses Import.
- `#importReviewStats` (the stat card row) and `#importReviewGroups` (the per-family
  cards) are deleted. Row counts live in the table footer.

Backend staging is unchanged: `import` uploads, `import/status/{id}` polls the job
queue, `import/review/{id}` renders the staged rows, `commit` or `cancel` ends it.
Nothing is written to `member` until commit, and commit still writes the audit trail.

## 8. Family Profile - `/records/{id}`

One surface for an existing record: it both displays and edits. The separate view page
and edit modal are removed.

```
Head of Family                              Control No. 000142

  [JD]  DELA CRUZ, JUAN

  row: col-md-4 DOB | col-md-4 Civil Status | col-md-4 Sex
  row: col-md-6 Occupation | col-md-6 Monthly Income
  Sectors: SC, PWD     Services: FA2, SWPS4

  Household Members (4)                        [+ Add Member]
    col-md-6  DELA CRUZ, MARIA   Asawa, 38, F
    col-md-6  DELA CRUZ, JOSE    Anak, 16, M

  [ Save ]
```

The head is the outer card; members are `col-md-6` sub-cards nested inside it, which is
the containment the paper form implies. Plain Bootstrap grid, no custom layout CSS.

**Fields render as form controls at all times, with Save at the bottom.** There is no
read-mode-to-edit-mode toggle: a toggle would mean two render paths for thirteen fields
across every member, for no gain over a page that is already only reachable by someone
who came to change something. Viewer sessions render the same controls `disabled` and
get no Save button, and the update route's manifest entry excludes Viewer, so the POST
is unreachable for them (section 2).

`Family/family-modal.php` (508 lines) stops being a modal. It becomes the shared field
partial included by both this page and the entry page, so the field set is defined
once instead of the current create-and-update dual mode.

Routes `manage-family/view/{id}` and `manage-family/edit/{id}` are removed.

## Error handling

- Unknown or unauthorized route: the manifest filter returns a 404 for a role that has
  no entry for that key, rather than a redirect that reveals the page exists.
- Import: a failed upload or a failed job leaves the staging row untouched and shows
  the failure on step 1; nothing reaches `member`. Cancel discards staging.
- Control number: an unavailable or unverifiable number blocks the entry page from
  rendering the rest of the form, with the reason shown inline.
- Family save: `FamilyRecordWriteException` behavior is unchanged, and the audit write
  stays inside the same transaction as the mutation.

## Testing and verification

- `php spark routes`: every new URI resolves; no `admin/`, `employee/`, or `viewer/`
  prefix remains.
- `vendor/bin/phpunit` before and after. Tests naming removed routes are swept;
  `AdminReorgRoutesTest` is rewritten against the new table. Model tests renamed with
  their models (`AidTypeModelTest`, `AidDistributionModelTest`, `AidStatsModelTest`).
- `composer lint` (docblock sniff plus comment-style check) before the PR.
- Playwright against the dev server at desktop and 390px: all seven sidebar pages plus
  `/records/entry`, `/records/import`, and `/records/{id}`, logged in as each of
  Developer, Admin, Encoder, and Viewer. Compare against the PR #23 design system.
- Smoke: login and per-role landing, family create through the entry page, family
  update through the profile page, audit row written for both, import upload through
  commit, batch open and close, a scanner scan writing to `subsidy_distribution`.

## Risks

- **The 72 role-prefixed `site_url()` call sites** are the largest mechanical risk. They
  are grep-able, but a missed one is a 404 that no test necessarily covers. Mitigation:
  sweep by grep, then `php spark routes` plus the Playwright pass over every page.
- **The V19 dump must land with the model rename.** A half-applied rename breaks scanner
  writes, which is the one path with no UI to reveal the failure until a distribution
  event.
- **Branch size.** This lands as one PR touching schema, routes, layouts, and four page
  rebuilds. Copilot rejects diffs over roughly 20,000 lines, so CodeRabbit CLI is the
  reviewer as usual. Mitigation: commits are ordered plumbing first (terminology
  rename, then URL space, then layout and manifest), then one commit per page, so the
  branch stays bisectable even though it merges as a unit.
- **Link count discipline.** Seven links only holds if later distribution features land as
  tabs inside `/distribution`. Recorded here so the next spec inherits the constraint.
