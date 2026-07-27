# Family Member Rows and Field Guards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make each family member a compact read-only row that expands to edit one at a time, and fix the four field guards (age display and non-destructive age eligibility, required relationship, "no middle name", contact-number format) on the family entry form.

**Architecture:** The member card splits into a persistent *shell* (header, summary, actions) and a swappable *editor*. A row is either **open** — the full field set is mounted from a `<template>` — or **closed** — the editor is empty and the row's values ride along as `<input type="hidden">` elements with the same `name`s. Because the names are identical in both states, `new FormData(form)` and every existing `[name$="[key]"]` query keep working, and `FamilyController::store()/update()` need no change at all. Age thresholds stop being hardcoded in JS: `App\Support\FamilyAgeEligibility` exposes its bounds, the view stamps them on each age-restricted checkbox as `data-min-age` / `data-max-age`, and the JS reads the attributes.

**Tech Stack:** CodeIgniter 4 views + helpers (PHP 8.2, typed signatures, no `declare(strict_types=1)`), vanilla ES5-style JS in `public/assets/js/dashboard/manage-family-modal.js` (no jQuery in this file), vendored Bootstrap 5.3.3, PHPUnit for the render/unit tests, Playwright MCP for UI verification.

## Global Constraints

- **Branch 3 of** `docs/superpowers/specs/2026-07-27-family-entry-form-design.md`. Branches 1 (uppercase) and 2 (Bootstrap rework) already merged (PR #39, commit `0278ff1`); this plan starts from that state.
- **No migrations, no schema change.** Dump stays **V18**. If any step turns out to need schema or seeded reference rows, stop and flag it — do not cut V19 silently.
- **No jQuery in `manage-family-modal.js`.** It is vanilla `fetch` / `querySelector` throughout; keep it that way.
- **Server stays authoritative.** `FamilyController::rulesForEntryType()`, `firstIncompleteMember()`, `hasMemberData()` and `FamilyAgeEligibility::selectionError()` keep their behavior. Client-side validation is UX only.
- **The posted contract is unchanged:** `head_*` for the head, `members[i][key]` and `members[i][sector_ids][]` / `members[i][service_ids][]` for members, `members_meta_count`, and `_form_end` **must stay the last named field in the form**.
- **The import-fix flow reuses this same view** (`FamilyImportController` → `Family/family-modal`). Every task must preserve: the blocking/warning alert block, `data-family-import-field-issues`, the `qrLocked` readonly control number and its note, and the `import_family_no` / `import_row` hidden fields.
- **Every family mutation still writes an audit trail** via `Audit/AuditTrailsModel`. Nothing in this plan touches the write path.
- **Comment style:** plain-language developer comments, no em dashes, no AI-slop phrasing. Match the existing comments in these files.
- **Bootstrap markup reference:** Context7 `/websites/getbootstrap_5_3`, pinned in `docs/knowledge/sources.md:25`.
- **Before editing app code**, use the `binan-conventions` skill (`.claude/skills/binan-conventions/SKILL.md`).
- **Run `vendor/bin/phpunit` before and after every task.** Baseline at plan time: green (`Tests: 29, Assertions: 98` for the two filtered suites; full suite green).

## Spec correction (carry into the work)

The spec says closed rows make the `max_input_vars` pressure "largely disappear". That is not accurate and no task should rely on it: **unchecked checkboxes never post**, so today's POST already carries only ~13 scalars plus the ticked sector/service ids per member. Hidden inputs post exactly the same set, so the posted variable count is unchanged. The real win is DOM size (a closed row drops ~10 sector checkboxes plus every service checkbox from the document) and the reading experience. The truncation guard (`members_meta_count`) and the `_form_end` sentinel therefore stay **load-bearing, not just insurance**. Do not weaken them.

## File Structure

| File | Responsibility after this plan |
|---|---|
| `app/Support/FamilyAgeEligibility.php` | Server rule (unchanged behavior) **plus** public accessors that expose the age bounds by sector shortcode and by service category, so the view and JS stop guessing |
| `app/Views/Family/family-modal.php` | Renders the head card, the member row **shell**, the member **editor** template, hidden-value blocks for server-rendered closed rows, and stamps `data-min-age` / `data-max-age` on age-restricted checkboxes |
| `app/Helpers/family_modal_helper.php` | Person-field definitions and renderer; gains the birthday age note, the middle-name affordance hook, and the contact-number pattern |
| `public/assets/js/dashboard/manage-family-modal.js` | Row open/close, value serialization to and from hidden inputs, row summary text, age note, non-destructive eligibility, contact validation |
| `public/css/familymodal.css` | Styles for the closed-row summary and its badges |
| `tests/unit/FamilyModalViewTest.php` | Render contract for the new DOM hooks (shell, editor template, hidden values, age attributes, guards) |
| `tests/unit/FamilyAgeEligibilityTest.php` | Bounds accessors alongside the existing `selectionError()` cases |

There is **no JS test harness in this repo**. JS behavior is verified with the Playwright MCP against the dev server, and each JS task below names the exact browser check that stands in for a unit test.

---

## Task 1: Split the member card into a shell and a mounted editor

**Files:**
- Modify: `app/Views/Family/family-modal.php:166-230` (the `$renderMemberRow` closure), `:367-375` (the members container and template)
- Modify: `public/assets/js/dashboard/manage-family-modal.js:1030-1058` (`addMemberRow`)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  - PHP closure `$renderMemberEditor(string|int $index, array $m = []): string` — the field grid, the relationship field, and the choices block for one member, with `__INDEX__` still substitutable.
  - PHP closure `$renderMemberRow(string|int $index, array $m = [], bool $open = true): string` — the card shell; mounts the editor inline when `$open`, leaves `[data-family-member-editor]` empty when not.
  - DOM hooks: `[data-family-member-editor]` (mount point, one per row), `[data-family-member-editor-template]` (one per form), `[data-family-member-summary]` (empty in this task), `[data-family-member-toggle]` (button, wired in Task 2).
  - JS `buildMemberEditor(root, row)` → the mount element, or `null`.
  - JS `memberIndex(row)` → the row's numeric index as a string, or `null`.

- [ ] **Step 1: Write the failing render test**

Add to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testMemberRowSplitsIntoAShellAndAMountedEditor(): void
    {
        $html = $this->render();

        // The row template is the shell only: its editor mount is empty and the
        // fields live in a separate template that JS mounts into it.
        $this->assertStringContainsString('data-family-member-editor-template', $html);
        $this->assertStringContainsString('data-family-member-editor', $html);
        $this->assertStringContainsString('data-family-member-summary', $html);
        $this->assertStringContainsString('data-family-member-toggle', $html);
        $this->assertMatchesRegularExpression(
            '/<div[^>]+data-family-member-editor[^>]*>\s*<\/div>\s*<div[^>]+data-family-member-values/',
            $html,
            'the shell template must ship an empty editor mount followed by the values mount'
        );
    }

    public function testTheEditorTemplateCarriesTheMemberFieldsAndKeepsTheIndexPlaceholder(): void
    {
        $html = $this->renderDecoded();

        $this->assertStringContainsString('members[__INDEX__][lastname]', $html);
        $this->assertStringContainsString('members[__INDEX__][relationship]', $html);
        $this->assertStringContainsString('members[__INDEX__][sector_ids][]', $html);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — `data-family-member-editor-template` is not in the markup.

- [ ] **Step 3: Split the row closure in the view**

In `app/Views/Family/family-modal.php`, replace the whole `$renderMemberRow` closure (currently `:166-230`) with these two closures:

```php
/**
 * The editable field set for one member row. It is mounted into the row shell:
 * inline for a row that opens with the form, or by manage-family-modal.js when the
 * worker opens a closed row. $index is an int for an existing member, or the
 * literal '__INDEX__' placeholder inside the <template>, which the JS swaps for the
 * row's own index (names and ids alike).
 */
$renderMemberEditor = static function ($index, array $m = []) use (
    $personFields,
    $personFieldOptions,
    $selectOptions,
    $relationshipOptions,
    $renderChoicesBlock,
    $fieldPrefix,
    $modalMode
): string {
    $i = (string) $index;
    $field = static fn (string $name): string => 'members[' . $i . '][' . $name . ']';
    $val = static fn (string $key): string => (string) ($m[$key] ?? '');
    $idPrefix = $fieldPrefix . 'Member' . $i;
    $selectedSectors = array_map('strval', (array) ($m['sector_ids'] ?? []));
    $selectedServices = array_map('strval', (array) ($m['service_ids'] ?? []));

    ob_start();
    ?>
    <div class="row g-3">
        <?= family_modal_render_person_fields([
            'personFields' => $personFields,
            'optionsByKey' => $personFieldOptions,
            'selectOptions' => $selectOptions,
            'field' => $field,
            'value' => $val,
            // Members require the same personal fields as the head (Address/Barangay are
            // head-only, members inherit them, so they are not part of this component).
            'idPrefix' => $idPrefix,
            'required' => true,
        ]) ?>
        <div class="col-12 col-md-6 col-xl-3">
            <label class="form-label" for="<?= esc($idPrefix . 'Relationship', 'attr') ?>">Relationship</label>
            <select id="<?= esc($idPrefix . 'Relationship', 'attr') ?>" class="form-select js-other-select" data-other-field="relationship<?= esc($i, 'attr') ?>" data-initial-value="<?= esc($val('relationship'), 'attr') ?>" name="<?= esc($field('relationship'), 'attr') ?>" aria-describedby="<?= esc($idPrefix . 'RelationshipFeedback', 'attr') ?>"><?= $selectOptions($relationshipOptions, $val('relationship'), 'Select') ?></select>
            <input class="form-control mt-2 js-other-input d-none" data-other-for="relationship<?= esc($i, 'attr') ?>" placeholder="Enter relationship" aria-label="Other relationship">
            <div class="invalid-feedback" id="<?= esc($idPrefix . 'RelationshipFeedback', 'attr') ?>" data-family-field-error></div>
        </div>
    </div>

    <?= $renderChoicesBlock(
        'familyMember' . $i . 'Choices',
        $field('sector_ids') . '[]',
        $selectedSectors,
        $field('service_ids') . '[]',
        $selectedServices,
        $idPrefix,
        $modalMode !== 'update'
    ) ?>
    <?php
    return (string) ob_get_clean();
};

/**
 * The persistent shell of one member row: header, summary line, and the two mount
 * points. The editor is rendered inline when the row opens with the form; a closed
 * row leaves the mount empty and carries its values as hidden inputs instead.
 * Field names post as members[$index][...] to match FamilyController::store()/update().
 */
$renderMemberRow = static function ($index, array $m = [], bool $open = true) use ($renderMemberEditor): string {
    $i = (string) $index;

    ob_start();
    ?>
    <div class="family-person-card" data-family-member-row data-family-member-open="<?= $open ? '1' : '0' ?>">
        <div class="family-person-card-header">
            <?php /* manage-family-modal.js keeps the number and name current as rows are
                     added, removed, or renamed. */ ?>
            <h3 class="family-person-card-title" data-family-member-title>Member</h3>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-family-member-toggle aria-expanded="<?= $open ? 'true' : 'false' ?>">
                    <?= $open ? 'Done' : 'Edit' ?>
                </button>
                <?php /* Row actions live in the same actions menu the records table uses, rather
                         than two competing outline buttons in colors that carry no role. */ ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Member actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" data-family-set-head>Set as head</button></li>
                        <li><button class="dropdown-item text-danger" type="button" data-family-member-remove>Remove</button></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php /* Filled by manage-family-modal.js from the row's own values, so a closed row
                 still says who it is. */ ?>
        <div class="family-member-summary d-none" data-family-member-summary></div>
        <div data-family-member-editor><?= $open ? $renderMemberEditor($index, $m) : '' ?></div>
        <div data-family-member-values></div>
    </div>
    <?php
    return (string) ob_get_clean();
};
```

- [ ] **Step 4: Add the editor template beside the row template**

In `app/Views/Family/family-modal.php`, replace the single `<template>` (currently `:373-375`) with:

```php
                    <?php /* The shell of a new row. Its editor mount arrives empty; the JS
                             fills it from the editor template below. */ ?>
                    <template data-family-member-template>
                        <?= $renderMemberRow('__INDEX__', [], false) ?>
                    </template>

                    <?php /* The field set for one row, mounted on Add and on Edit. The JS
                             swaps __INDEX__ for the row's index across names and ids. */ ?>
                    <template data-family-member-editor-template>
                        <?= $renderMemberEditor('__INDEX__') ?>
                    </template>
```

- [ ] **Step 5: Mount the editor from JS when a row is added**

In `public/assets/js/dashboard/manage-family-modal.js`, add these helpers immediately above `addMemberRow` (currently `:1030`):

```js
    function memberIndex(row) {
        var match = /^members\[(\d+)\]$/.exec((row && row.dataset.memberFieldPrefix) || '');

        return match ? match[1] : null;
    }

    // Mounts the field set into a row's editor slot. The template still carries the
    // __INDEX__ placeholder, so one replace covers both the posted names and the ids
    // the labels point at.
    function buildMemberEditor(root, row) {
        var template = root.querySelector('[data-family-member-editor-template]');
        var mount = row ? row.querySelector('[data-family-member-editor]') : null;
        var index = memberIndex(row);

        if (!template || !mount || index === null) {
            return null;
        }

        mount.innerHTML = (template.innerHTML || '').replace(/__INDEX__/g, index).trim();
        initOtherSelects(mount);

        return mount;
    }
```

Then in `addMemberRow`, replace the tail (`initOtherSelects(row); refreshAgeEligibility(row); return row;`) with:

```js
        buildMemberEditor(root, row);
        row.dataset.familyMemberOpen = '1';
        refreshAgeEligibility(row);
        refreshServiceCategories(row);

        return row;
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit`
Expected: PASS, including the two new `FamilyModalViewTest` cases.

- [ ] **Step 7: Browser check (stands in for the missing JS test)**

Start the dev server if it is down (`php spark serve`, base URL from `.env`, e.g. `http://localhost:8090`). With the Playwright MCP: log in as `developer/developer123`, open Manage Records → Add, click **Add Member** twice, and `browser_snapshot`. Expected: two member cards, each with its own complete field set, and the second row's inputs named `members[1][...]`. Confirm no console errors with `browser_console_messages`.

- [ ] **Step 8: Commit**

```bash
git add app/Views/Family/family-modal.php public/assets/js/dashboard/manage-family-modal.js tests/unit/FamilyModalViewTest.php
git commit -m "refactor: split the member card into a shell and a mounted editor"
```

---

## Task 2: Closed rows carry hidden values and a read-only summary

**Files:**
- Modify: `app/Views/Family/family-modal.php` (the `$renderMemberRow` call site for existing members, plus a hidden-values renderer)
- Modify: `public/assets/js/dashboard/manage-family-modal.js` (`snapshotForm`, `validateMembers`, `validateMemberContacts`, `submitFamilyForm`, `refreshChoicesSummary`, the click handler)
- Modify: `public/css/familymodal.css`
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: `$renderMemberEditor`, `$renderMemberRow(..., bool $open)`, `buildMemberEditor(root, row)`, `memberIndex(row)` from Task 1.
- Produces:
  - PHP closure `$renderMemberValues(string|int $index, array $m): string` — the hidden inputs for a closed row, including `data-label` / `data-sector-code` on sector ids so the summary can name them.
  - JS `readMemberData(row)` → `{lastname, firstname, ..., sector_ids: [], service_ids: [], sector_labels: []}` — works whether the row is open or closed.
  - JS `collapseMemberRow(row)` / `expandMemberRow(root, row)`.
  - JS `renderMemberSummary(row)`.
  - JS `refreshAllChoicesSummaries(root)`.

- [ ] **Step 1: Write the failing render test**

Add to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testExistingMembersRenderClosedWithHiddenValues(): void
    {
        $html = $this->renderDecoded([
            'modalMode' => 'update',
            'headId' => 42,
            'existingMembers' => [[
                'lastname' => 'DELA CRUZ',
                'firstname' => 'JUAN',
                'birthday' => '1960-01-02',
                'relationship' => 'Son',
                'sector_ids' => [1],
                'service_ids' => [5],
            ]],
        ]);

        // Closed: no editable control for the member, values ride as hidden inputs.
        $this->assertStringContainsString('data-family-member-open="0"', $html);
        $this->assertStringContainsString('<input type="hidden" name="members[0][lastname]" value="DELA CRUZ"', $html);
        $this->assertStringContainsString('name="members[0][sector_ids][]" value="1"', $html);
        $this->assertStringContainsString('name="members[0][service_ids][]" value="5"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<select[^>]+name="members\[0\]\[civilstatus\]"/',
            $html,
            'a closed row must not render editable controls'
        );
    }

    public function testClosedRowSectorValuesCarryTheirLabelForTheSummary(): void
    {
        $html = $this->renderDecoded([
            'modalMode' => 'update',
            'headId' => 42,
            'existingMembers' => [['lastname' => 'DELA CRUZ', 'sector_ids' => [1]]],
        ]);

        $this->assertMatchesRegularExpression(
            '/name="members\[0\]\[sector_ids\]\[\]" value="1"[^>]*data-sector-code="SC"/',
            $html
        );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — existing members still render open, so `data-family-member-open="0"` is absent.

- [ ] **Step 3: Render existing members closed**

In `app/Views/Family/family-modal.php`, add this closure directly after `$renderMemberEditor` (it needs `$sectorCatalog` for the shortcode lookup):

```php
/**
 * The hidden inputs that stand in for a closed row's editor. Same names as the
 * controls they replace, so FormData and every [name$="[key]"] lookup are unaffected.
 * Sector ids carry their shortcode so the row summary can show badges without
 * re-rendering the checkbox list.
 */
$renderMemberValues = static function ($index, array $m) use ($sectorCatalog): string {
    $i = (string) $index;
    $scalarKeys = ['lastname', 'firstname', 'middlename', 'suffix', 'birthday', 'sex',
        'civilstatus', 'contactnumber', 'religion', 'education', 'job', 'salary', 'relationship'];
    $codes = [];

    foreach ($sectorCatalog as $sectorGroup) {
        foreach (array_values((array) $sectorGroup) as $sector) {
            $sector = (array) $sector;
            $codes[(string) ($sector['sectorID'] ?? $sector['id'] ?? '')] = (string) ($sector['shortcode'] ?? $sector['code'] ?? '');
        }
    }

    ob_start();
    foreach ($scalarKeys as $key): ?>
        <input type="hidden" name="<?= esc('members[' . $i . '][' . $key . ']', 'attr') ?>" value="<?= esc((string) ($m[$key] ?? ''), 'attr') ?>">
    <?php endforeach; ?>
    <?php foreach (array_map('strval', (array) ($m['sector_ids'] ?? [])) as $sectorId): ?>
        <input type="hidden" name="<?= esc('members[' . $i . '][sector_ids][]', 'attr') ?>" value="<?= esc($sectorId, 'attr') ?>" data-sector-code="<?= esc($codes[$sectorId] ?? '', 'attr') ?>">
    <?php endforeach; ?>
    <?php foreach (array_map('strval', (array) ($m['service_ids'] ?? [])) as $serviceId): ?>
        <input type="hidden" name="<?= esc('members[' . $i . '][service_ids][]', 'attr') ?>" value="<?= esc($serviceId, 'attr') ?>">
    <?php endforeach; ?>
    <?php
    return (string) ob_get_clean();
};
```

Give `$renderMemberRow` access to it — add `$renderMemberValues` to its `use (...)` list, and change the values mount line to:

```php
        <div data-family-member-values><?= $open ? '' : $renderMemberValues($index, $m) ?></div>
```

Then render existing members closed (currently `:368-370`):

```php
                        <?php foreach (array_values($existingMembers) as $i => $member): ?>
                            <?= $renderMemberRow($i, (array) $member, false) ?>
                        <?php endforeach; ?>
```

- [ ] **Step 4: Run the render tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: PASS.

- [ ] **Step 5: Add the JS read/write layer for a row**

In `manage-family-modal.js`, add above `snapshotForm` (currently `:575`):

```js
    // One reader for both row states. An open row is read from its controls, a closed
    // row from the hidden inputs that replaced them; the names are the same either way.
    function readMemberData(row) {
        var data = { sector_ids: [], service_ids: [], sector_labels: [] };

        Array.from(row.querySelectorAll('input, select')).forEach(function (field) {
            var match = /members\[\d+\]\[([a-z_]+)\](\[\])?$/.exec(field.name || '');

            if (!match) {
                return;
            }

            var key = match[1];

            if (key === 'sector_ids' || key === 'service_ids') {
                if (field.type === 'hidden' || field.checked) {
                    data[key].push(field.value);

                    if (key === 'sector_ids') {
                        data.sector_labels.push(String(field.dataset.sectorCode || field.dataset.label || '').trim());
                    }
                }

                return;
            }

            data[key] = field.classList.contains('js-other-select') ? selectedFieldValue(field) : field.value;
        });

        return data;
    }

    function writeMemberData(row, data) {
        Object.keys(data).forEach(function (key) {
            if (key === 'sector_ids' || key === 'service_ids') {
                checkBoxes(row, row.dataset.memberFieldPrefix + '[' + key + '][]', data[key]);

                return;
            }

            if (key === 'sector_labels') {
                return;
            }

            var field = row.querySelector('[name="' + row.dataset.memberFieldPrefix + '[' + key + ']"]');

            if (!field) {
                return;
            }

            if (field.classList.contains('js-other-select')) {
                setSelectValueWithOther(field, data[key]);
            } else {
                field.value = data[key];
            }
        });
    }

    function hiddenInput(name, value, sectorCode) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;

        if (sectorCode) {
            input.dataset.sectorCode = sectorCode;
        }

        return input;
    }
```

Then simplify `snapshotForm`'s member mapper (currently `:582-606`) to reuse the reader:

```js
        var members = Array.from(form.querySelectorAll('[data-family-member-row]')).map(function (row) {
            var data = readMemberData(row);

            delete data.sector_labels;

            return data;
        });
```

- [ ] **Step 6: Add collapse, expand, and the summary line**

Add below the helpers from Step 5:

```js
    var MEMBER_VALUE_KEYS = ['lastname', 'firstname', 'middlename', 'suffix', 'birthday', 'sex',
        'civilstatus', 'contactnumber', 'religion', 'education', 'job', 'salary', 'relationship'];

    // Closed rows read as text, so a household of six is a list instead of six full
    // forms. The values move into hidden inputs under the same names, which is what
    // keeps FormData and the server contract unchanged.
    function collapseMemberRow(row) {
        var editor = row.querySelector('[data-family-member-editor]');
        var values = row.querySelector('[data-family-member-values]');

        if (!editor || !values || row.dataset.familyMemberOpen === '0') {
            return;
        }

        var data = readMemberData(row);
        var prefix = row.dataset.memberFieldPrefix;

        values.innerHTML = '';

        MEMBER_VALUE_KEYS.forEach(function (key) {
            values.appendChild(hiddenInput(prefix + '[' + key + ']', data[key] || ''));
        });

        data.sector_ids.forEach(function (id, position) {
            values.appendChild(hiddenInput(prefix + '[sector_ids][]', id, data.sector_labels[position]));
        });

        data.service_ids.forEach(function (id) {
            values.appendChild(hiddenInput(prefix + '[service_ids][]', id));
        });

        editor.innerHTML = '';
        row.dataset.familyMemberOpen = '0';
        setMemberToggleState(row, false);
        renderMemberSummary(row);
    }

    function expandMemberRow(root, row) {
        if (!row || row.dataset.familyMemberOpen === '1') {
            return;
        }

        var values = row.querySelector('[data-family-member-values]');
        var data = readMemberData(row);

        if (!buildMemberEditor(root, row)) {
            return;
        }

        if (values) {
            values.innerHTML = '';
        }

        row.dataset.familyMemberOpen = '1';
        writeMemberData(row, data);
        setMemberToggleState(row, true);
        renderMemberSummary(row);
        refreshAgeEligibility(row);
        refreshServiceCategories(row);
        refreshChoicesSummary(row);
    }

    function setMemberToggleState(row, open) {
        var toggle = row.querySelector('[data-family-member-toggle]');
        var summary = row.querySelector('[data-family-member-summary]');

        if (toggle) {
            toggle.textContent = open ? 'Done' : 'Edit';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        setHidden(summary, open);
    }

    // The closed row has to say who it is: name, relationship, age, and the sector
    // codes, which is everything the worker scans a household list for.
    function renderMemberSummary(row) {
        var target = row.querySelector('[data-family-member-summary]');

        if (!target) {
            return;
        }

        var data = readMemberData(row);
        var middle = String(data.middlename || '').trim();
        var given = [String(data.firstname || '').trim(), middle !== '' ? middle.charAt(0) + '.' : '', String(data.suffix || '').trim()]
            .filter(Boolean).join(' ');
        var last = String(data.lastname || '').trim();
        var name = last !== '' && given !== '' ? last + ', ' + given : (last || given);
        var age = completedAge(data.birthday);
        var parts = [String(data.relationship || '').trim(), age === null ? '' : age + ' yrs'].filter(Boolean);

        target.innerHTML = '';

        var nameEl = document.createElement('span');
        nameEl.className = 'family-member-summary-name';
        nameEl.textContent = name !== '' ? name : 'Unnamed member';
        target.appendChild(nameEl);

        if (parts.length) {
            var meta = document.createElement('span');
            meta.className = 'family-member-summary-meta';
            meta.textContent = parts.join(' · ');
            target.appendChild(meta);
        }

        data.sector_labels.filter(Boolean).forEach(function (code) {
            var badge = document.createElement('span');
            badge.className = 'badge text-bg-light family-member-summary-badge';
            badge.textContent = code;
            target.appendChild(badge);
        });

        if (data.service_ids.length) {
            var services = document.createElement('span');
            services.className = 'family-member-summary-meta';
            services.textContent = data.service_ids.length + ' program' + (data.service_ids.length === 1 ? '' : 's');
            target.appendChild(services);
        }
    }
```

- [ ] **Step 7: Wire the toggle, and make the summaries refresh per row**

Replace `refreshChoicesSummary` (currently `:962-981`) so it scopes to whatever it is handed, then add the all-rows walker below it:

```js
    // The collapse toggle doubles as the summary of what is ticked, so a collapsed
    // block (edit mode) still says what this person is categorised as. Scoped, so a
    // member row summarises its own ticks instead of the head's.
    function refreshChoicesSummary(scopeEl) {
        var target = scopeEl.querySelector('[data-family-choices-summary]');

        if (!target) {
            return;
        }

        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var sectorSelector = row ? 'input[name$="[sector_ids][]"]:checked' : 'input[name="sector_ids[]"]:checked';
        var serviceSelector = row ? 'input[name$="[service_ids][]"]:checked' : 'input[name="service_ids[]"]:checked';
        var codes = Array.from(scopeEl.querySelectorAll(sectorSelector)).map(function (input) {
            return String(input.dataset.sectorCode || input.dataset.label || '').trim();
        }).filter(Boolean);
        var services = scopeEl.querySelectorAll(serviceSelector).length;

        if (codes.length === 0 && services === 0) {
            target.textContent = 'Nothing selected yet';
            return;
        }

        target.textContent = 'Sectors: ' + (codes.length ? codes.join(', ') : 'none')
            + ' (' + services + ' program' + (services === 1 ? '' : 's') + ')';
    }

    function refreshAllChoicesSummaries(root) {
        refreshChoicesSummary(root);
        root.querySelectorAll('[data-family-member-row]').forEach(function (row) {
            refreshChoicesSummary(row);
            renderMemberSummary(row);
        });
    }
```

Replace every existing `refreshChoicesSummary(root)` call site (in `promoteMemberToHead`, `restoreDraftIntoForm`, the `change` handler, the `reset` handler, and `initFamilyEntryModal`) with `refreshAllChoicesSummaries(root)`.

In the member click handler (currently `:1446`), add the toggle branch above the Add Member branch:

```js
            var toggleButton = event.target.closest('[data-family-member-toggle]');

            if (toggleButton) {
                event.preventDefault();
                var toggleRow = toggleButton.closest('[data-family-member-row]');

                if (toggleRow) {
                    if (toggleRow.dataset.familyMemberOpen === '1') {
                        collapseMemberRow(toggleRow);
                    } else {
                        expandMemberRow(root, toggleRow);
                    }

                    scheduleSave(root);
                }

                return;
            }
```

In the same handler's "Set as Head" branch, expand the row before promoting (the swap reads live controls, and the worker should see what moved):

```js
                askPromoteToHead(formEl, memberName).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    expandMemberRow(root, headRow);
                    promoteMemberToHead(root, headRow);
                    scheduleSave(root);
                });
```

In `initFamilyEntryModal`, after the loop that stamps `memberFieldPrefix` (currently `:1326-1330`), seed each row's open flag and summary:

```js
        Array.from(root.querySelectorAll('[data-family-member-row]')).forEach(function (row) {
            setMemberToggleState(row, row.dataset.familyMemberOpen === '1');
            renderMemberSummary(row);
        });
```

- [ ] **Step 8: Make validation open the row it is complaining about**

Replace `validateMembers` and `validateMemberContacts` (currently `:1156-1191`) with:

```js
    function memberField(row, key) {
        return row.querySelector('[name="' + row.dataset.memberFieldPrefix + '[' + key + ']"]');
    }

    function validateMembers(root, form) {
        var first = null;

        Array.from(form.querySelectorAll('[data-family-member-row]')).forEach(function (row) {
            var data = readMemberData(row);
            var named = ['lastname', 'firstname'].some(function (key) {
                return String(data[key] || '').trim() !== '';
            });

            MEMBER_REQUIRED_FIELDS.forEach(function (key) {
                var empty = named && String(data[key] || '').trim() === '';

                if (row.dataset.familyMemberOpen === '1') {
                    setFieldError(memberField(row, key), empty ? 'This field is required.' : '');
                }

                if (empty && !first) {
                    first = { row: row, key: key };
                }
            });
        });

        if (!first) {
            return null;
        }

        // A complaint about a field the worker cannot see is useless, so the row opens.
        expandMemberRow(root, first.row);

        var field = memberField(first.row, first.key);
        setFieldError(field, 'This field is required.');

        return field;
    }

    function validateMemberContacts(root, form) {
        var firstRow = null;

        Array.from(form.querySelectorAll('[data-family-member-row]')).forEach(function (row) {
            var value = String(readMemberData(row).contactnumber || '').trim();

            if (row.dataset.familyMemberOpen === '1') {
                validateContact(memberField(row, 'contactnumber'));
            }

            if (!contactValueIsValid(value) && !firstRow) {
                firstRow = row;
            }
        });

        if (!firstRow) {
            return null;
        }

        expandMemberRow(root, firstRow);

        var field = memberField(firstRow, 'contactnumber');
        validateContact(field);

        return field;
    }
```

`contactValueIsValid` does not exist yet — add it now beside `validateContact` (Task 6 replaces its body):

```js
    // The value test on its own, so a closed row can be checked without a control.
    function contactValueIsValid(value) {
        var digits = String(value || '').trim();

        return digits === '' || digits.length === 11;
    }
```

and make `validateContact` delegate to it:

```js
    function validateContact(el) {
        if (!el) {
            return true;
        }

        var value = String(el.value || '').trim();

        if (!contactValueIsValid(value)) {
            setFieldError(el, 'Contact number must be exactly 11 digits.');

            return false;
        }

        setFieldError(el, '');

        return true;
    }
```

Update the two call sites in `submitFamilyForm` (currently `:1201`):

```js
        var badMember = validateMembers(root, form) || validateMemberContacts(root, form);
```

- [ ] **Step 9: Style the closed row**

Append to `public/css/familymodal.css`, after the `.family-choice + .family-choice` rule (currently `:630-632`):

```css
/* A closed member row reads as one line of text, not a form. */
.family-member-summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem 0.6rem;
    color: #062c57;
    font-size: 0.84rem;
}

.family-member-summary-name {
    font-weight: 700;
}

.family-member-summary-meta {
    color: #5b6b80;
}

.family-member-summary-badge {
    font-size: 0.7rem;
    font-weight: 700;
}
```

- [ ] **Step 10: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 11: Browser check**

With Playwright, at desktop width and at 390px:
1. Manage Records → edit a family that has members. Expected: each member is one line (`DELA CRUZ, JUAN P. · Son · 67 yrs · SC`), no inputs visible, `browser_snapshot` shows no member form controls.
2. Click **Edit** on the second row. Expected: only that row expands; the first stays closed.
3. Click **Done**. Expected: it collapses and the summary reflects any edit just made.
4. Save. Expected: success toast, and the record still shows every member with unchanged values (open the record again to confirm nothing was dropped).
5. On a new record: Add Member, fill only the name, Save. Expected: the row stays open, the first missing required field is focused and shows "This field is required."
6. Add Member, fill only the name, collapse the row with **Done**, then Save. Expected: the row auto-expands and the same error appears at the field.
7. On a record with a closed member row, open the row's actions menu and choose **Set as head**. Expected: a confirm dialog first; on confirm the row expands, the head card takes the member's values, the demoted head's values land in that row with its relationship cleared, and the page scrolls to the head card.

- [ ] **Step 12: Commit**

```bash
git add app/Views/Family/family-modal.php public/assets/js/dashboard/manage-family-modal.js public/css/familymodal.css tests/unit/FamilyModalViewTest.php
git commit -m "feat: collapse saved members to read-only rows with hidden values"
```

---

## Task 3: Publish the age bounds from FamilyAgeEligibility

**Files:**
- Modify: `app/Support/FamilyAgeEligibility.php`
- Test: `tests/unit/FamilyAgeEligibilityTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `FamilyAgeEligibility::sectorAgeBounds(string $shortcode): ?array` → `['min' => int|null, 'max' => int|null]` or `null` when the sector is not age-restricted.
  - `FamilyAgeEligibility::serviceCategoryAgeBounds(string $category): ?array` → same shape.
  Bounds are inclusive completed-years: children are `['min' => null, 'max' => 17]`, seniors are `['min' => 60, 'max' => null]`.

- [ ] **Step 1: Write the failing test**

Add to `tests/unit/FamilyAgeEligibilityTest.php`:

```php
    public function testSectorAgeBoundsAreExposedForTheClient(): void
    {
        $this->assertSame(['min' => null, 'max' => 17], FamilyAgeEligibility::sectorAgeBounds('B'));
        $this->assertSame(['min' => 60, 'max' => null], FamilyAgeEligibility::sectorAgeBounds('sc'));
        $this->assertNull(FamilyAgeEligibility::sectorAgeBounds('PWD'));
    }

    public function testServiceCategoryAgeBoundsAreExposedForTheClient(): void
    {
        $this->assertSame(['min' => null, 'max' => 17], FamilyAgeEligibility::serviceCategoryAgeBounds('Bata (Children)'));
        $this->assertSame(['min' => 60, 'max' => null], FamilyAgeEligibility::serviceCategoryAgeBounds('senior citizen'));
        $this->assertNull(FamilyAgeEligibility::serviceCategoryAgeBounds('Financial Assistance Programs'));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyAgeEligibilityTest`
Expected: FAIL with "Call to undefined method App\Support\FamilyAgeEligibility::sectorAgeBounds()".

- [ ] **Step 3: Add the accessors and route the rule through them**

In `app/Support/FamilyAgeEligibility.php`, add the bounds constants and accessors below the existing constants:

```php
    private const CHILD_BOUNDS = ['min' => null, 'max' => 17];
    private const SENIOR_BOUNDS = ['min' => 60, 'max' => null];

    /**
     * Age bounds for one sector, in completed years, or null when it is open to any
     * age. The view stamps these on the checkbox so the client stops carrying its own
     * copy of the thresholds.
     *
     * @return array{min: int|null, max: int|null}|null
     */
    public static function sectorAgeBounds(string $shortcode): ?array
    {
        $code = strtoupper(trim($shortcode));

        if ($code === self::CHILD_SECTOR_CODE) {
            return self::CHILD_BOUNDS;
        }

        return $code === self::SENIOR_SECTOR_CODE ? self::SENIOR_BOUNDS : null;
    }

    /**
     * Age bounds for one service category, matched the same way selectionError()
     * matches it (trimmed, case-insensitive).
     *
     * @return array{min: int|null, max: int|null}|null
     */
    public static function serviceCategoryAgeBounds(string $category): ?array
    {
        $name = strtolower(trim($category));

        if ($name === strtolower(self::CHILD_SERVICE_CATEGORY)) {
            return self::CHILD_BOUNDS;
        }

        return $name === strtolower(self::SENIOR_SERVICE_CATEGORY) ? self::SENIOR_BOUNDS : null;
    }
```

Then make `selectionError()` compare against the same constants instead of the inline literals, so there is one source of truth inside the class too:

```php
        if (self::CHILD_BOUNDS['max'] !== null && $age > self::CHILD_BOUNDS['max'] && $hasChildSelection) {
            return 'B - Bata (Children) sector and Bata (Children) services are only available to persons below 18 years old.';
        }

        if (self::SENIOR_BOUNDS['min'] !== null && $age < self::SENIOR_BOUNDS['min'] && $hasSeniorSelection) {
            return 'SC - Senior Citizen sector and Senior Citizen programs are only available to persons 60 years old and above.';
        }
```

- [ ] **Step 4: Run the tests**

Run: `vendor/bin/phpunit --filter FamilyAgeEligibilityTest`
Expected: PASS, including every pre-existing `selectionError()` case (the boundary cases `2008-07-22` / `2008-07-23` and `1966-07-22` / `1966-07-23` must still behave identically).

- [ ] **Step 5: Commit**

```bash
git add app/Support/FamilyAgeEligibility.php tests/unit/FamilyAgeEligibilityTest.php
git commit -m "feat: expose the age eligibility bounds for the client"
```

---

## Task 4: Stamp the bounds on the checkboxes and read them in JS

**Files:**
- Modify: `app/Views/Family/family-modal.php` (`$renderSectorGrid`, `$renderServiceAccordion`)
- Modify: `public/assets/js/dashboard/manage-family-modal.js:782-831` (`refreshAgeEligibility`)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: `FamilyAgeEligibility::sectorAgeBounds()` / `serviceCategoryAgeBounds()` from Task 3.
- Produces: `data-min-age` / `data-max-age` on every age-restricted sector checkbox and on every service accordion panel; JS `ageBoundsFor(input)` → `{min, max}` or `null`.

- [ ] **Step 1: Write the failing render test**

Add to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testAgeRestrictedChoicesCarryTheirBoundsAsData(): void
    {
        $html = $this->renderDecoded();

        // B (children) is capped, SC (senior) has a floor, and the JS reads these
        // instead of carrying its own copy of 18 / 60.
        $this->assertMatchesRegularExpression('/data-sector-code="B"[^>]*data-max-age="17"/', $html);
        $this->assertMatchesRegularExpression('/data-sector-code="SC"[^>]*data-min-age="60"/', $html);
        $this->assertMatchesRegularExpression('/data-service-category="Senior Citizen"[^>]*data-min-age="60"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-service-category="Financial Assistance"[^>]*data-(min|max)-age=/', $html);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — no `data-max-age` in the markup.

- [ ] **Step 3: Emit the bounds from the view**

At the top of `app/Views/Family/family-modal.php`, after the `extract(...)` line, add a small attribute helper. Reference the class fully qualified rather than adding a `use` statement — this file is an included view, and every other class reference in these views is already fully qualified:

```php
/** Renders the age bounds of an age-restricted choice as data attributes, or nothing. */
$ageBoundsAttrs = static function (?array $bounds): string {
    if ($bounds === null) {
        return '';
    }

    return ($bounds['min'] !== null ? ' data-min-age="' . (int) $bounds['min'] . '"' : '')
        . ($bounds['max'] !== null ? ' data-max-age="' . (int) $bounds['max'] . '"' : '');
};
```

Add `$ageBoundsAttrs` to the `use (...)` list of `$renderSectorGrid` and `$renderServiceAccordion`.

In `$renderSectorGrid`, extend the checkbox tag (currently `:47`) — append immediately before the `<?= in_array(...)` checked test:

```php
<?= $ageBoundsAttrs(\App\Support\FamilyAgeEligibility::sectorAgeBounds((string) ($sector['shortcode'] ?? $sector['code'] ?? ''))) ?>
```

In `$renderServiceAccordion`, extend the panel div (currently `:86`) — append after `data-service-category="..."`:

```php
<?= $ageBoundsAttrs(\App\Support\FamilyAgeEligibility::serviceCategoryAgeBounds((string) $category)) ?>
```

- [ ] **Step 4: Run the render test**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: PASS.

- [ ] **Step 5: Read the attributes in JS instead of hardcoding**

Replace `refreshAgeEligibility` (currently `:782-831`) with:

```js
    // Where a choice's age limits come from: the view stamps them from
    // FamilyAgeEligibility, so 18 / 60 / B / SC live in exactly one place.
    function ageBoundsFor(input) {
        var source = input.matches(SERVICE_INPUT_SELECTOR)
            ? input.closest('[data-service-category]')
            : input;

        if (!source) {
            return null;
        }

        var min = source.dataset.minAge;
        var max = source.dataset.maxAge;

        if (typeof min === 'undefined' && typeof max === 'undefined') {
            return null;
        }

        return {
            min: typeof min === 'undefined' ? null : parseInt(min, 10),
            max: typeof max === 'undefined' ? null : parseInt(max, 10)
        };
    }

    function ageBoundsMessage(bounds) {
        if (bounds.max !== null) {
            return 'Available only to persons below ' + (bounds.max + 1) + ' years old.';
        }

        return 'Available only to persons ' + bounds.min + ' years old and above.';
    }

    // A person's birthday controls only that person's age-specific choices.
    function refreshAgeEligibility(scopeEl) {
        if (!scopeEl || !scopeEl.querySelectorAll) {
            return;
        }

        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var birthday = row
            ? row.querySelector('input[name$="[birthday]"]')
            : scopeEl.querySelector('input[name="head_birthday"]');
        var age = birthday ? completedAge(birthday.value) : null;
        var selector = row
            ? 'input[name$="[sector_ids][]"], input[name$="[service_ids][]"]'
            : 'input[name="sector_ids[]"], input[name="service_ids[]"]';

        scopeEl.querySelectorAll(selector).forEach(function (input) {
            var bounds = ageBoundsFor(input);

            if (bounds === null) {
                return;
            }

            var allowed = age !== null
                && (bounds.min === null || age >= bounds.min)
                && (bounds.max === null || age <= bounds.max);
            var message = age === null
                ? 'Enter a valid date of birth to determine eligibility.'
                : ageBoundsMessage(bounds);
            var choice = input.closest('.family-choice');

            input.disabled = !allowed;

            if (!allowed) {
                input.checked = false;
            }

            if (choice) {
                choice.title = allowed ? '' : message;
            }
        });
    }
```

(The silent un-tick on line `input.checked = false` is deliberately still here; Task 5 replaces it. Keeping the two changes apart keeps this task's diff reviewable.)

- [ ] **Step 6: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 7: Browser check**

With Playwright: open Add, set the head's date of birth to a year that makes them 30, expand Sectors. Expected: `B - Bata (Children)` and `SC - Senior Citizen` are disabled with the same tooltips as before; every other sector stays enabled. Set the birth date so the person is 70. Expected: `SC` becomes enabled, `B` stays disabled.

- [ ] **Step 8: Commit**

```bash
git add app/Views/Family/family-modal.php public/assets/js/dashboard/manage-family-modal.js tests/unit/FamilyModalViewTest.php
git commit -m "refactor: drive age eligibility from the rendered bounds"
```

---

## Task 5: Show the age and stop clearing selections silently

**Files:**
- Modify: `app/Helpers/family_modal_helper.php:69` (the birthday field definition), `:156-217` (the renderer)
- Modify: `public/assets/js/dashboard/manage-family-modal.js` (`refreshAgeEligibility`, the `input` handler)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: `ageBoundsFor(input)`, `ageBoundsMessage(bounds)`, `completedAge(value)` from Task 4.
- Produces: `[data-family-age-note]` beside every birthday field; JS `refreshAgeNote(scopeEl)`.

- [ ] **Step 1: Write the failing render test**

Add to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testBirthdayCarriesAnAgeNoteSlot(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression(
            '/name="head_birthday"[\s\S]{0,400}?data-family-age-note/',
            $html,
            'the head birthday field needs a note slot for the computed age'
        );
        $this->assertSame(2, substr_count($html, 'data-family-age-note'), 'head plus the member editor template');
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — `data-family-age-note` is absent.

- [ ] **Step 3: Render the note slot**

In `app/Helpers/family_modal_helper.php`, mark the birthday field (currently `:69`) with a note flag:

```php
            ['name' => 'birthday', 'label' => 'Date of birth', 'type' => 'date', 'idSuffix' => 'Birthday', 'summary' => 'birthday', 'required' => true, 'max' => date('Y-m-d'), 'ageNote' => true],
```

In `family_modal_render_person_fields`, add the slot immediately after the `.invalid-feedback` div (currently `:216`):

```php
                <div class="invalid-feedback"<?= $attrs(['id' => $feedbackId]) ?> data-family-field-error></div>
                <?php if (! empty($personField['ageNote'])): ?>
                    <?php /* A checkbox disabled on age grounds reads as broken unless the age
                             it was judged on is on screen. manage-family-modal.js fills it. */ ?>
                    <div class="form-text" data-family-age-note></div>
                <?php endif; ?>
```

- [ ] **Step 4: Run the render test**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: PASS.

- [ ] **Step 5: Fill the note and keep ineligible ticks visible**

In `manage-family-modal.js`, add above `refreshAgeEligibility`:

```js
    function refreshAgeNote(scopeEl) {
        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var birthday = row
            ? scopeEl.querySelector('input[name$="[birthday]"]')
            : scopeEl.querySelector('input[name="head_birthday"]');
        var note = birthday ? birthday.parentElement.querySelector('[data-family-age-note]') : null;

        if (!note) {
            return;
        }

        var age = completedAge(birthday.value);

        note.textContent = age === null ? '' : 'Age: ' + age;
    }
```

In `refreshAgeEligibility`, replace the un-tick block with a non-destructive one and call the note refresher. The body of the `forEach` becomes:

```js
            var bounds = ageBoundsFor(input);

            if (bounds === null) {
                return;
            }

            var allowed = age !== null
                && (bounds.min === null || age >= bounds.min)
                && (bounds.max === null || age <= bounds.max);
            var message = age === null
                ? 'Enter a valid date of birth to determine eligibility.'
                : ageBoundsMessage(bounds);
            var choice = input.closest('.family-choice');

            // A ticked box is never cleared on the worker's behalf: a mistyped birth year
            // used to wipe the ticks with no message and no way back. The tick stays,
            // says why it is wrong, and resolves itself when the date is corrected.
            // FamilyAgeEligibility::selectionError() still blocks a genuinely bad save.
            input.disabled = !allowed && !input.checked;
            input.classList.toggle('is-invalid', !allowed && input.checked);

            if (choice) {
                choice.title = allowed ? '' : message;
                choice.classList.toggle('family-choice--ineligible', !allowed && input.checked);
                var label = choice.querySelector('.form-check-label');

                if (label) {
                    label.setAttribute('title', allowed ? '' : message);
                }
            }
```

and end the function with:

```js
        refreshAgeNote(scopeEl);
    }
```

- [ ] **Step 6: Style the flagged choice**

Append to `public/css/familymodal.css`:

```css
/* A tick that is no longer eligible says so instead of vanishing. */
.family-choice--ineligible .form-check-label {
    color: #b02a37;
    text-decoration: underline dotted;
}
```

- [ ] **Step 7: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 8: Browser check**

With Playwright:
1. Add → set the head's date of birth to 1950-01-01. Expected: "Age: 76" (or whatever today gives) under the field, `SC` enabled.
2. Tick `SC - Senior Citizen`, then change the birth year to 1990. Expected: the tick is **still there**, shown in red with the explanatory title, and the age note now reads the younger age.
3. Change the year back to 1950. Expected: the red flag clears, the tick is intact.
4. Submit while the flagged tick is present. Expected: the server rejects it with the existing `selectionError()` message (this proves the server is still authoritative).

- [ ] **Step 9: Commit**

```bash
git add app/Helpers/family_modal_helper.php public/assets/js/dashboard/manage-family-modal.js public/css/familymodal.css tests/unit/FamilyModalViewTest.php
git commit -m "feat: show the computed age and stop clearing ineligible ticks"
```

---

## Task 6: Relationship required, no-middle-name affordance, contact number format

**Files:**
- Modify: `app/Views/Family/family-modal.php` (the relationship select in `$renderMemberEditor`)
- Modify: `app/Helpers/family_modal_helper.php` (middle name + contact number field definitions and renderer)
- Modify: `public/assets/js/dashboard/manage-family-modal.js` (`enforceContactDigits`, `contactValueIsValid`, `validateContact`, the `change` handler)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: `contactValueIsValid(value)` from Task 2 (its body is replaced here), `$renderMemberEditor` from Task 1.
- Produces: `[data-family-no-middlename]` checkbox beside every middle-name field; the shared contact regex `CONTACT_PATTERN` in JS.

- [ ] **Step 1: Write the failing render tests**

Add to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testRelationshipIsRequired(): void
    {
        $html = $this->renderDecoded();

        $this->assertMatchesRegularExpression(
            '/<select[^>]+name="members\[__INDEX__\]\[relationship\]"[^>]*\srequired/',
            $html
        );
    }

    public function testMiddleNameOffersANoMiddleNameCheckbox(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-family-no-middlename', $html);
        $this->assertMatchesRegularExpression('/<label class="form-check-label"[^>]*>No middle name<\/label>/', $html);
    }

    public function testContactNumberAcceptsMobileOrBinanLandline(): void
    {
        // HTML pattern is implicitly anchored, so the attribute carries no ^ or $.
        $html = $this->renderDecoded();

        $this->assertStringContainsString('pattern="09\d{9}|(049)?\d{7,8}"', $html);
        $this->assertMatchesRegularExpression('/name="head_contactnumber"[^>]+inputmode="numeric"/', $html);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL on all three (no `required` on relationship, no `data-family-no-middlename`, no `pattern` on the contact field).

- [ ] **Step 3: Mark relationship required**

In `app/Views/Family/family-modal.php`, in `$renderMemberEditor`, add `required` to the relationship `<select>`:

```php
            <select id="<?= esc($idPrefix . 'Relationship', 'attr') ?>" class="form-select js-other-select" data-other-field="relationship<?= esc($i, 'attr') ?>" data-initial-value="<?= esc($val('relationship'), 'attr') ?>" name="<?= esc($field('relationship'), 'attr') ?>" aria-describedby="<?= esc($idPrefix . 'RelationshipFeedback', 'attr') ?>" required><?= $selectOptions($relationshipOptions, $val('relationship'), 'Select') ?></select>
```

**Server side stays as it is.** `FamilyController` line 861 keeps `$this->nullableText($member['relationship'] ?? 'Member')`, and no validation rule is added: the Excel importer and older records legitimately arrive without a relationship, and rejecting them server-side is a separate decision with its own blast radius. This is a client-side guard on the entry form only — state that in the commit message.

- [ ] **Step 4: Add the "No middle name" affordance and the contact pattern**

In `app/Helpers/family_modal_helper.php`, update the two field definitions:

```php
            ['name' => 'middlename', 'label' => 'Middle Name', 'type' => 'text', 'idSuffix' => 'Middlename', 'summary' => 'name-middle', 'noneToggle' => 'No middle name'],
```

```php
            ['name' => 'contactnumber', 'label' => 'Contact number', 'type' => 'tel', 'maxlength' => '11', 'inputmode' => 'numeric', 'pattern' => '09\d{9}|(049)?\d{7,8}', 'title' => 'Enter an 11-digit mobile number (09XXXXXXXXX) or a Binan landline (7 to 8 digits, optional 049 prefix).', 'idSuffix' => 'Contact', 'summary' => 'contact'],
```

In `family_modal_render_person_fields`, pass the new attributes through on the `<input>` branch (currently `:203-214`):

```php
                    <input<?= $attrs([
                        'class' => 'form-control',
                        'id' => $id,
                        'name' => $field($name),
                        'type' => $type,
                        'value' => $val($name),
                        'aria-describedby' => $feedbackId,
                        'data-summary' => $summary,
                        'required' => $required,
                        'maxlength' => $personField['maxlength'] ?? '',
                        'max' => $personField['max'] ?? '',
                        'inputmode' => $personField['inputmode'] ?? '',
                        'pattern' => $personField['pattern'] ?? '',
                        'title' => $personField['title'] ?? '',
                    ]) ?>>
```

and render the toggle after the feedback div, next to the age note added in Task 5:

```php
                <?php if (! empty($personField['noneToggle'])): ?>
                    <?php /* Workers type placeholders into blanks (see NO_DATA_TOKENS in
                             MemberFieldNormalizer), so blank gets an explicit affordance. */ ?>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" data-family-no-middlename<?= $attrs(['id' => $id !== '' ? $id . 'None' : '']) ?>>
                        <label class="form-check-label"<?= $attrs(['for' => $id !== '' ? $id . 'None' : '']) ?>><?= esc((string) $personField['noneToggle']) ?></label>
                    </div>
                <?php endif; ?>
```

- [ ] **Step 5: Run the render tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: PASS.

- [ ] **Step 6: Teach the JS the new contact rule and the toggle**

In `manage-family-modal.js`, replace `enforceContactDigits`, `contactValueIsValid`, and `validateContact` (Task 2 left `contactValueIsValid` with the old 11-digit body) with:

```js
    // An 11-digit mobile (09XXXXXXXXX) or a Binan landline (7 to 8 digits, with the
    // 049 area code optional). The looser rule is deliberate: a mobile-only pattern
    // would block an edit to an older record whose landline nobody touched.
    var CONTACT_PATTERN = /^(09\d{9}|(049)?\d{7,8})$/;

    function enforceContactDigits(el) {
        el.value = String(el.value || '').replace(/[^0-9]/g, '').slice(0, 11);

        if (contactValueIsValid(el.value)) {
            setFieldError(el, '');
        }
    }

    function contactValueIsValid(value) {
        var digits = String(value || '').trim();

        return digits === '' || CONTACT_PATTERN.test(digits);
    }

    function validateContact(el) {
        if (!el) {
            return true;
        }

        if (!contactValueIsValid(el.value)) {
            setFieldError(el, 'Enter an 11-digit mobile number (09XXXXXXXXX) or a Binan landline (7 to 8 digits, optional 049 prefix).');

            return false;
        }

        setFieldError(el, '');

        return true;
    }
```

Add the middle-name toggle to the `change` handler in `initFamilyEntryModal`, directly after the `.js-other-select` branch:

```js
            if (target && target.matches('[data-family-no-middlename]')) {
                var middleColumn = target.closest('[class*="col-"]');
                var middleName = middleColumn ? middleColumn.querySelector('input[name$="middlename"]') : null;

                if (middleName) {
                    middleName.disabled = target.checked;

                    if (target.checked) {
                        middleName.value = '';
                        setFieldError(middleName, '');
                    }
                }
            }
```

A disabled input posts nothing, and both `head_middlename` and `members[i][middlename]` are `permit_empty` on the server (`FamilyController:815,823`), so the value lands as null exactly as an empty box would.

**One catch to handle:** `readMemberData` skips nothing by `disabled`, so a closed row would serialize the disabled middle name as `''` — which is what we want. But `collapseMemberRow` must not lose the checkbox state. Accept that: on re-expand the box is empty and the toggle is unchecked, which is honest (there is no stored "no middle name" flag, and the spec does not add a column). Note it in the commit message.

- [ ] **Step 7: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 8: Browser check**

With Playwright:
1. Add → type `09171234567` into Contact number. Expected: no error.
2. Replace it with `0491234567` (049 + 7 digits). Expected: no error.
3. Replace it with `1234567`. Expected: no error (bare landline).
4. Replace it with `0917123`. Expected: the error message under the field on blur.
5. Tick **No middle name**. Expected: the middle-name box clears and greys out. Untick it. Expected: it is editable again.
6. Add Member, leave Relationship on "Select", fill everything else, Save. Expected: the Relationship select is focused and shows "This field is required."

- [ ] **Step 9: Commit**

```bash
git add app/Views/Family/family-modal.php app/Helpers/family_modal_helper.php public/assets/js/dashboard/manage-family-modal.js tests/unit/FamilyModalViewTest.php
git commit -m "feat: require relationship, add a no-middle-name toggle, widen the contact rule"
```

---

## Task 7: End-to-end verification, import-fix regression, and the knowledge punch-list

**Files:**
- Modify: `docs/knowledge/violations.md` (tick what this branch fixed, append anything new and verified)
- No app code unless a check fails.

**Interfaces:**
- Consumes: everything above.
- Produces: a verified branch ready for `coderabbit review --base main --agent`.

- [ ] **Step 1: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS. Record the exact `Tests: N, Assertions: N` line — it goes in the PR body.

- [ ] **Step 2: Confirm every route still resolves**

Run: `php spark routes`
Expected: no errors; the families and import-review routes are unchanged.

- [ ] **Step 3: Large-household check**

With Playwright, on a new record: add **eight** members, fill each with a name plus the six required fields, collapse them all, and Save. Expected:
- the save succeeds with the success toast,
- reopening the record shows all eight members with their values intact,
- `members_meta_count` matched (no `FORM_TRUNCATED` dialog).

If `FORM_TRUNCATED` does appear, that is a genuine finding, not a regression to paper over: the guard did its job. Report the household size it triggered at and stop for a decision rather than raising `max_input_vars` unilaterally.

- [ ] **Step 4: Import-fix regression (the path that breaks silently)**

With Playwright: go to the Excel import review screen, import a file that produces at least one blocking issue, and click **Fix** on a flagged family. Expected:
- the blocking and warning alert blocks render at the top,
- the flagged fields carry their red/amber notes (`applyImportFieldIssues` still finds them by `name`),
- the control number is readonly with the "Locked: subsidy already recorded under this number." note when `qrLocked`,
- members render as closed rows and expand on **Edit**,
- saving re-renders the review screen without a page reload.

Any flagged field that lives inside a **closed** member row will not be found by `applyImportFieldIssues`, because the hidden input carries the name but cannot show a note. If that happens, fix it by expanding rows that own a flagged field during `applyImportFieldIssues`:

```js
        issues.forEach(function (issue) {
            if (!issue || !issue.name) {
                return;
            }

            var field = form.querySelector('[name="' + String(issue.name).replace(/(["\\])/g, '\\$1') + '"]');
            var row = field ? field.closest('[data-family-member-row]') : null;

            if (row && row.dataset.familyMemberOpen === '0') {
                expandMemberRow(root, row);
                field = form.querySelector('[name="' + String(issue.name).replace(/(["\\])/g, '\\$1') + '"]');
            }

            if (field) {
                markImportField(field, issue);
            }
        });
```

- [ ] **Step 5: Mobile pass**

With Playwright at 390px: repeat the open/close of a member row, the age note, and the footer. Expected: the summary line wraps instead of overflowing, no horizontal scroll, and the sticky footer still shows Save. Compare against Manage Records, the design source of truth.

- [ ] **Step 6: Draft round-trip**

With Playwright: on a new record, fill the head, add two members, close the modal, choose **Keep**, reopen, choose **Restore**. Expected: head and both members return with their values, and the restored member rows behave like normal rows (collapse, expand, validate).

- [ ] **Step 7: Update the knowledge punch-list**

Open `docs/knowledge/violations.md` and tick the items this branch resolved — the hardcoded age thresholds in JS, the missing member label association, the silent eligibility clearing, and the unlabelled relationship field, if they are listed. Append any new violation you **verified** while working (do not add speculative ones), following the file's existing format.

- [ ] **Step 8: Commit and review**

```bash
git add docs/knowledge/violations.md public/assets/js/dashboard/manage-family-modal.js
git commit -m "docs: tick the family form items this branch resolved"
coderabbit review --base main --agent
```

Then follow `superpowers:receiving-code-review`: triage every finding against the code and the non-negotiables above, fix the genuine in-scope bugs, re-run `vendor/bin/phpunit`, and park the rest in a GitHub issue citing the PR number and branch (format in `CLAUDE.md`, "GitHub issue format").

---

## Deferred with reasons (not in this plan)

- **Server-side `required` on member relationship** — see Task 6 Step 3. The importer and older records legitimately lack it; tightening the server rule needs its own decision and an importer pass.
- **A stored "no middle name" flag.** The affordance is client-side only; adding a column would mean V19, which the spec rules out.
- **Duplicate-person detection across control numbers** — out of scope per the spec.
- **`Salary` storing bracket upper bounds** — reporting concern, not entry.
