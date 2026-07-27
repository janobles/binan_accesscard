# Family Entry Form — Bootstrap Rework, Layout, Validation (Branch 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the family entry form's two-step tab wizard and hand-rolled form
system with one scrolling Bootstrap 5.3 form: standard controls, standard
validation, flattened sector/service panels, and footer buttons that match the app
button standard.

**Architecture:** Four files change together — the view
(`app/Views/Family/family-modal.php`), its render helper
(`app/Helpers/family_modal_helper.php`), the page CSS
(`public/css/familymodal.css`), and the behaviour
(`public/assets/js/dashboard/manage-family-modal.js`). The view is the contract:
a new `tests/unit/FamilyModalViewTest.php` renders it in PHPUnit (proved to work —
`family_modal_prepare()` defaults every input) and asserts the DOM hooks the JS
depends on. Each task writes the failing view assertion first, then makes it pass.

**Tech Stack:** CodeIgniter 4.7.3 views + helpers (PHP 8.2), vendored Bootstrap
5.3.3 (no Sass build), vanilla ES5-style JS in an IIFE, PHPUnit 10.5, Playwright
MCP for visual verification.

## Global Constraints

- **Source spec:** `docs/superpowers/specs/2026-07-27-family-entry-form-design.md`.
  This plan implements its "Branch 2" section only.
- **Branch:** `feat/family-form-bootstrap`, already created off
  `origin/feat/family-form-uppercase` (PR #36, not yet merged). Do not rebase onto
  `main`.
- **No migrations, no schema change.** Dump stays `accesscardV18.sql`. This branch
  touches no DB.
- **No jQuery in this file.** `manage-family-modal.js` stays vanilla; it already
  uses `window.fetch`. Style is ES5-flavoured (`var`, `function`) — match it.
- **Bootstrap markup comes from Context7 `/websites/getbootstrap_5_3`**, pinned in
  `docs/knowledge/sources.md:25` to the vendored 5.3.3 copy. Already retrieved for
  this plan: `needs-validation`/`novalidate` + `.invalid-feedback`,
  `.input-group.has-validation`, accordion with `data-bs-parent` omitted for
  always-open.
- **Button colors come from the `btn()` standard** (`app/Helpers/ui_helper.php:20-27`,
  documented in `docs/knowledge/binan-conventions/ui-design-system.md`): search/generate
  blue, clear red, add green `#198754`, import yellow, filter outline-secondary. Never
  Biñan green on a button.
- **Client validation is UX only.** `FamilyController::rulesForEntryType()` and
  `firstIncompleteMember()` stay authoritative and unchanged. No controller or model
  file is edited in this branch.
- **Preserve the import-fix flow.** The same view is reused by the Excel import
  review screen. These must keep working and are asserted in the view test:
  the alert block (`family-modal.php:161-176`), `data-family-import-field-issues`
  (`:140`), the `qrLocked` readonly QR + note (`:215-227`), the `import_family_no`
  (`:191-193`) and `import_row` (`:195-197`) hidden fields, and
  `applyImportFieldIssues()`/`markImportField()` (`js:1629-1692`) which resolve
  fields by `[name="…"]` and insert a note after the field or its `.js-other-input`.
- **Preserve the truncation guard.** `members_meta_count` (`:188`) and the
  `_form_end` sentinel (`:406`, MUST stay the last named field in the form).
- **Deferred to Branch 3 — do not build here:** read-only collapsed member rows,
  member `idPrefix`/label association, "No middle name", the contact-number pattern,
  the age display, the `FamilyAgeEligibility` threshold accessor, and stopping
  `refreshAgeEligibility()` from silently unchecking.
- **Two spec deviations, decided with the user before planning:**
  1. Contact does **not** get a `+63` input-group addon. The field holds an
     11-digit `09XXXXXXXXX` value, so a `+63` prefix would display a wrong number,
     and Branch 3 widens the rule to landlines. Contact stays a plain
     `form-control`.
  2. The filter input goes on the **services accordion only**. Sectors is 10
     checkboxes fully visible after the flatten, so a filter there has nothing to
     hide.
- **Verify with `vendor/bin/phpunit` after every task.** DB/session tests skip
  without the `sqlite3` extension; that is expected.
- **Comment style:** plain-language developer comments, no em dashes, no
  AI-slop phrasing. Match the surrounding file.

## File Structure

| File | Responsibility after this branch |
|---|---|
| `app/Views/Family/family-modal.php` | One scrolling form: head card, sector grid, services accordion, member cards, split footer. No tabs, no head summary. Owns `$renderMemberRow`, `$renderSectorGrid`, `$renderServiceAccordion` closures. |
| `app/Helpers/family_modal_helper.php` | `family_modal_prepare()` unchanged in shape; `family_modal_render_person_fields()` gains an `.invalid-feedback` div per field plus `aria-describedby`. |
| `public/css/familymodal.css` | Page-unique rules only. The control reimplementation (`:597-634`), the raw-checkbox rules (`:701-712`), `.family-field-error`, and the modal-compact overrides that target those selectors are deleted. A `.family-entry-form` custom-property block replaces them. |
| `public/assets/js/dashboard/manage-family-modal.js` | No step machinery, no head summary. Validation writes into `.invalid-feedback`. Adds services accordion auto-expand, the services filter, the QR status addon, the draft-saved indicator, and the Set-as-Head confirm. |
| `tests/unit/FamilyModalViewTest.php` | New. Renders the view and asserts its DOM contract (structure, Bootstrap classes, import-fix hooks, truncation sentinel). |

Task order is dependency order. Task 1 removes the wizard (view + JS together,
because the JS references the panes it deletes). Task 3 is atomic by necessity:
the unclassed controls only render correctly because of the CSS being deleted.

---

### Task 1: Remove the wizard — tabs, panes, head summary, redundant headings

**Files:**
- Create: `tests/unit/FamilyModalViewTest.php`
- Modify: `app/Views/Family/family-modal.php:141-145,199-213,231,328-331,363-367,385-386,397-398`
- Modify: `public/assets/js/dashboard/manage-family-modal.js:229-261,708-787,1013-1090,1218-1231,1358-1381,1486,1500-1502`
- Modify: `public/css/familymodal.css:483-489,527-562,564-569,580-586,636-647,761-782,813-824,830-834,836-839,841-849,851-854,871-873`

**Interfaces:**
- Consumes: nothing from earlier tasks (first task).
- Produces: `tests/unit/FamilyModalViewTest.php` with a `private function render(array $data = []): string`
  helper that later tasks extend with more assertions. The view keeps these DOM
  hooks for later tasks and for the JS: `[data-family-entry-form]`,
  `[data-family-members]`, `[data-family-member-template]`,
  `[data-family-member-row]`, `[data-family-add-member]`, `[data-family-save]`,
  `[data-family-clear]`, `[data-members-count]`, `input[name="_form_end"]`.

- [ ] **Step 1: Write the failing view test**

Create `tests/unit/FamilyModalViewTest.php`:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renders Family/family-modal the way FamilyModalDataBuilder drives it and
 * asserts the DOM hooks manage-family-modal.js depends on, plus the import-fix
 * contract. Markup details can change freely; these hooks are the contract.
 */
final class FamilyModalViewTest extends CIUnitTestCase
{
    /**
     * @param array<string, mixed> $data
     */
    private function render(array $data = []): string
    {
        return view('Family/family-modal', array_merge([
            'action' => '/families',
            'qrCheckUrl' => '/families/qr-available',
            'sectorCatalog' => [[
                ['sectorID' => 1, 'shortcode' => 'SC', 'name' => 'Senior Citizen'],
                ['sectorID' => 2, 'shortcode' => 'B', 'name' => 'Bata (Children)'],
            ]],
            'servicesByCategory' => [
                'Senior Citizen' => [['serviceID' => 5, 'code' => 'PEN', 'name' => 'Pension']],
                'Financial Assistance' => [['serviceID' => 9, 'code' => 'FA', 'name' => 'Cash Aid']],
            ],
            'barangayOptions' => ['Canlalay', 'Zapote'],
            'relationshipOptions' => ['Son', 'Daughter'],
            'sexOptions' => ['Male', 'Female'],
        ], $data));
    }

    public function testRendersOneScrollingFormWithNoStepWizard(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('data-family-step-target', $html);
        $this->assertStringNotContainsString('family-entry-steps', $html);
        $this->assertStringNotContainsString('tab-pane', $html);
        $this->assertStringNotContainsString('role="tab"', $html);
        $this->assertStringNotContainsString('data-family-next', $html);
        $this->assertStringNotContainsString('data-family-prev', $html);
    }

    public function testDropsTheDuplicateHeadSummaryBlock(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('data-head-summary', $html);
        $this->assertStringNotContainsString('family-head-summary', $html);
        $this->assertStringNotContainsString('family-summary-value', $html);
        $this->assertStringNotContainsString('Current Record Head', $html);
    }

    public function testDropsRedundantHeadings(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('family-entry-header', $html);
        $this->assertStringNotContainsString('family-entry-title', $html);
        $this->assertStringNotContainsString('Personal Information', $html);
        $this->assertStringNotContainsString('family-member-card-title', $html);
    }

    public function testHeadAndMembersAreBothPresentInOneFlow(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('name="head_lastname"', $html);
        $this->assertStringContainsString('data-family-members', $html);
        $this->assertStringContainsString('data-family-member-template', $html);
        $this->assertStringContainsString('data-family-add-member', $html);
        $this->assertStringContainsString('data-family-save', $html);
    }

    public function testKeepsTheTruncationGuardAndSentinelLast(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-members-count', $html);
        $this->assertMatchesRegularExpression(
            '/name="_form_end"[^>]*>\s*<\/form>/',
            $html,
            '_form_end must stay the last named field in the form'
        );
    }

    public function testKeepsTheImportFixContract(): void
    {
        $html = $this->render([
            'importFamilyNo' => '12345',
            'importRow' => 7,
            'qrLocked' => true,
            'importFieldIssues' => [['name' => 'head_lastname', 'severity' => 'blocking', 'message' => 'Missing']],
            'importIssues' => [
                ['severity' => 'blocking', 'person' => 'Juan', 'column' => 'Last Name', 'message' => 'Missing'],
                ['severity' => 'warning', 'person' => 'Juan', 'column' => 'Religion', 'message' => 'Unknown value'],
            ],
        ]);

        $this->assertStringContainsString('data-family-import-field-issues', $html);
        $this->assertStringContainsString('data-family-import-issues', $html);
        $this->assertStringContainsString('alert alert-danger', $html);
        $this->assertStringContainsString('alert alert-warning', $html);
        $this->assertStringContainsString('name="import_family_no"', $html);
        $this->assertStringContainsString('name="import_row"', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('Locked: subsidy already recorded under this number.', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — `testRendersOneScrollingFormWithNoStepWizard` fails on
`data-family-step-target` still being present.

- [ ] **Step 3: Delete the wizard chrome from the view**

In `app/Views/Family/family-modal.php`:

Delete the `.family-entry-header` block (`:141-145`) entirely — the modal header
already shows the title. Keep the `<div class="family-entry-form" …>` opening tag
above it.

Delete the `.family-entry-steps` toolbar (`:199-210`).

Replace the wrapper `<div class="tab-content family-entry-content">` (`:212`) and
its head pane `<div class="tab-pane fade show active" id="…HeadPane" …>` (`:213`)
with a single container:

```php
        <div class="family-entry-content">
```

Delete `<h3 class="family-section-title">Personal Information</h3>` (`:231`).

Delete the closing `</div>` that ended the head pane (`:328`) and the entire
member-pane opening `<div class="tab-pane fade" id="…MemberPane" …>` (`:330`)
together with the whole `<section class="family-entry-section family-head-summary">`
block (`:331-363`), so the members `<section>` (`:365`) follows the sector/service
`</section>` directly. Delete one `</div>` at `:385-386` to match (the member pane's
close), keeping the single `</div>` that closes `.family-entry-content`.

In the members section, keep one label only: delete
`<h3 class="family-section-title">Family Members</h3>` (`:366`) and keep the helper
paragraph (`:367`), reworded so it names the section:

```php
                    <p class="text-muted small mb-3">Family members in this household. Leave empty if there are none.</p>
```

In `$renderMemberRow`, delete `<strong class="family-member-card-title">Member</strong>`
(`:45`) and give the header a flex spacer so the buttons stay right-aligned:

```php
        <div class="family-member-card-header">
            <span class="text-muted small" data-family-member-name></span>
            <div class="btn-group btn-group-sm">
```

`[data-family-member-name]` is an empty hook here; Branch 3 fills it with the
member's name. It renders as nothing today.

- [ ] **Step 4: Remove the step machinery from the JS**

In `public/assets/js/dashboard/manage-family-modal.js`:

Delete `validateHeadStep()` (`:1015-1046`) and `showStep()` (`:1054-1090`), and
replace them with one head-validation function that no longer looks up a pane:

```javascript
    // ---- head validation ---------------------------------------------------

    // The head fields are always visible now, so "the head section" is simply the
    // head-scoped required controls. No pane lookup, no step gate.
    function validateHead(container) {
        var form = container.querySelector('form');
        var fields = form
            ? Array.from(form.querySelectorAll('[required]')).filter(function (field) {
                return !field.closest('[data-family-member-row]');
            })
            : [];
        var firstInvalid = null;

        fields.forEach(function (field) {
            var isEmpty = String(field.value || '').trim() === '';
            var invalid = !field.checkValidity();

            setFieldError(field, invalid ? (isEmpty ? 'This field is required.' : field.validationMessage) : '');

            if (invalid && !firstInvalid) {
                firstInvalid = field;
            }
        });

        var contact = container.querySelector('[name="head_contactnumber"]');

        if (contact && !validateContact(contact) && !firstInvalid) {
            firstInvalid = contact;
        }

        if (firstInvalid) {
            firstInvalid.focus();

            return false;
        }

        return true;
    }
```

Delete `renderHeadSummary()` (`:761-787`), `setSummary()` (`:716-722`),
`setSummaryList()` (`:724-739`), and `checkedLabels()` (`:708-714`). `escapeHtml()`
(`:38`) stays — `showFormError()` uses it. Keep `fieldDisplayValue()` for now; it is
unused after this deletion, so delete it too (`:742-759`) along with the
`// ---- summary ---` banner comment.

Delete every `renderHeadSummary(root)` / `renderHeadSummary(container)` call site:
`:260`, `:702`, `:1088`, `:1403`, `:1426`, `:1439`, `:1484`, `:1500`.

In `submitFamilyForm()` (`:1218-1231`) drop the step redirects:

```javascript
    function submitFamilyForm(root, form) {
        if (!validateHead(root)) {
            return;
        }

        var badContact = validateMemberContacts(form);

        if (badContact) {
            badContact.focus();
            return;
        }
```

In `initFamilyEntryModal()` delete the step-trigger loop (`:1358-1363`), the
`nextButton` block (`:1365-1372`), the `previousButton` block (`:1374-1381`), the
`showStep(root, 'head')` inside the reset handler (`:1486`), and the
`showStep(root, 'head')` at `:1502`.

- [ ] **Step 5: Delete the orphaned CSS**

In `public/css/familymodal.css` delete these rules, which now match nothing:

- `:483-489` — the `.modal-footer` and `.family-entry-header` hiding hack.
- `:506-525` — `.family-entry-header`, `.family-entry-kicker`, `.family-entry-title`.
- `:527-562` — `.family-entry-steps`, its `.btn` rules, `.family-step-number`.
- `:580-586` — change the selector list to drop `.family-summary-title`, keeping
  `.family-section-title` (still used by nothing after this task; delete the whole
  rule).
- `:588-595` — drop `.family-summary-label` from the selector list, keep
  `.family-entry-form .form-label`.
- `:597-611`, `:636-639` — drop `.family-summary-value, .family-summary-list` from
  the selector lists. **Do not delete the input/select/textarea rules yet — Task 3
  owns that, atomically with adding the Bootstrap classes.**
- `:641-647` — drop `.family-head-summary` from the selector list, keep
  `.family-sector-service`.
- `:813-824` — `.is-family-entry-modal .family-entry-steps` rules.
- `:830-849`, `:851-854`, `:871-873` — drop every `.family-summary-*` and
  `.family-head-summary` selector from these compact-mode lists, keeping the rest.

Keep `.family-entry-content` (`:564-569`); it is now the single scroll region.

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest && vendor/bin/phpunit`
Expected: PASS. Suite green apart from the usual `sqlite3` skips.

- [ ] **Step 7: Commit**

```bash
git add tests/unit/FamilyModalViewTest.php app/Views/Family/family-modal.php \
  public/assets/js/dashboard/manage-family-modal.js public/css/familymodal.css
git commit -m "refactor: replace the family form tab wizard with one scrolling form

The Head/Members split hid the head behind a tab, which is why a read-only
'Current Record Head' block duplicated all nine head fields on step 2. With the
head always visible the duplicate has no reason to exist, and no required field
can be unreachable at submit time, so the silent submit hang is structurally
impossible.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Footer buttons to the app standard, plus a draft-saved indicator

**Files:**
- Modify: `app/Views/Family/family-modal.php:388-401` (post-Task-1 line numbers shift up by roughly 30; find the `<footer class="btn-toolbar family-entry-actions">` block)
- Modify: `public/assets/js/dashboard/manage-family-modal.js:617-623` (`saveDraftNow`)
- Modify: `public/css/familymodal.css:761-782`
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: the single-form view from Task 1.
- Produces: `[data-family-draft-status]` — a `<span>` in the footer that
  `saveDraftNow(form)` updates. `[data-family-save]`, `[data-family-clear]` keep
  their existing selectors so the JS bindings are untouched.

- [ ] **Step 1: Write the failing test**

Append to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testFooterUsesTheAppButtonStandard(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression(
            '/class="btn btn-primary"[^>]*data-family-save/',
            $html,
            'Save is the commit action and must be primary blue'
        );
        $this->assertMatchesRegularExpression(
            '/class="btn btn-danger"[^>]*data-family-clear/',
            $html,
            'Clear must match the clear role in btn()'
        );
        $this->assertStringContainsString('class="btn btn-success" type="button" data-family-add-member', $html);
        $this->assertStringContainsString('btn btn-outline-secondary" type="button" data-bs-dismiss="modal"', $html);
        $this->assertStringNotContainsString('btn btn-secondary" type="button" data-bs-dismiss="modal"', $html);
        $this->assertStringNotContainsString('btn btn-warning', $html);
    }

    public function testFooterCarriesADraftSavedIndicator(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-family-draft-status', $html);
    }

    public function testPrintQrLinkOnlyRendersForASavedRecordAndIsNotInsideTheButtonGroup(): void
    {
        $this->assertStringNotContainsString('Print QR card', $this->render());

        $html = $this->render(['headId' => 42]);

        $this->assertStringContainsString('Print QR card', $html);
        $this->assertStringNotContainsString('btn-outline-secondary btn-sm', $html);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — Save currently renders `hidden` and Close is `btn btn-secondary`.

- [ ] **Step 3: Rewrite the footer**

Replace the whole `<footer …>` block in `app/Views/Family/family-modal.php` with a
split footer: dismissal and reset on the left, the commit action on the right. The
Print QR link leaves the button group so a `btn-sm` no longer breaks the group
geometry.

```php
        <footer class="family-entry-actions d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-danger" type="reset" data-family-clear>Clear</button>
                <?php if ($headId > 0): ?>
                    <a class="btn btn-link btn-sm px-1"
                       href="<?= site_url('admin/cards/card/' . $headId) ?>"
                       target="_blank" rel="noopener">Print QR card</a>
                <?php endif; ?>
            </div>
            <?php /* States the guarantee that Close is safe, instead of asking the worker to
                     infer it from a button color. manage-family-modal.js fills it on each draft save. */ ?>
            <span class="text-muted small ms-auto" data-family-draft-status aria-live="polite"></span>
            <button class="btn btn-primary" type="submit" data-family-save <?= $saveDisabled ? 'disabled aria-disabled="true"' : '' ?>><?= esc($submitLabel) ?></button>
        </footer>
```

Note the `hidden` attribute is gone from Save — with no tabs it is always shown.

- [ ] **Step 4: Fill the indicator on draft save**

In `manage-family-modal.js`, replace `saveDraftNow()` (`:617-623`) with:

```javascript
    function setDraftStatus(form, text) {
        var root = form.closest('[data-family-entry-form]') || form;
        var status = root.querySelector('[data-family-draft-status]');

        if (status) {
            status.textContent = text;
        }
    }

    function saveDraftNow(form) {
        try {
            window.localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshotForm(form)));
            setDraftStatus(form, 'Draft saved');
        } catch (error) {
            /* storage unavailable / quota */
            setDraftStatus(form, '');
        }
    }
```

In `clearDraft()` (`:523`) the form is not in scope, so clear the indicator at the
two call sites that reset the form instead. In `initFamilyEntryModal()`'s reset
handler, after `clearDraft()`:

```javascript
                    if (isCreateForm(root)) {
                        clearDraft();
                        setDraftStatus(formEl, '');
                    }
```

and in the restore prompt's discard branch (`:1513`):

```javascript
                    } else {
                        clearDraft();
                        setDraftStatus(formEl, '');
                    }
```

Edit mode keeps no draft (`scheduleSave` returns early when `!isCreateForm`), so
the indicator stays empty there, which is correct.

- [ ] **Step 5: Update the footer CSS**

In `public/css/familymodal.css` replace `:761-782`:

```css
.family-entry-actions {
    flex: 0 0 auto;
    position: sticky;
    z-index: 5;
    bottom: 0;
    padding: 0.65rem 1rem;
    border-top: 1px solid #cfe0f5;
    background: #f8fbff;
}

.family-entry-actions .btn:not(.btn-link) {
    min-width: 7rem;
    min-height: 2.35rem;
    font-size: 0.84rem;
    font-weight: 800;
}
```

`justify-content: flex-end` and the `.btn-group { gap }` rule go away with the
btn-group; `border-radius: … !important` goes away with it too, since standalone
buttons keep their own radius. Delete the now-unmatched
`.family-entry-actions .btn-group` rule and drop `.btn-group` from the compact
override at `:875-879` if present.

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest && vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Views/Family/family-modal.php public/assets/js/dashboard/manage-family-modal.js \
  public/css/familymodal.css tests/unit/FamilyModalViewTest.php
git commit -m "fix: bring the family form footer to the app button standard

Next was btn-success, competing with Add Member for the green role while Save was
the only blue. Next and Previous are gone with the tabs, so the footer is now
Close (outline) and Clear (danger) on the left, Save (primary) on the right, with
Print QR card out of the button group so its btn-sm no longer breaks the group
geometry. Close stays neutral rather than warning-yellow; a 'Draft saved'
indicator states the guarantee directly.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Bootstrap controls in, custom control CSS out (atomic)

The three unclassed controls only render correctly because of the CSS being
deleted. Splitting this task ships a broken form.

**Files:**
- Modify: `app/Views/Family/family-modal.php` (QR input, Address input, Barangay select, every `.family-choice` label)
- Modify: `app/Helpers/family_modal_helper.php:189` (`family-form-hidden` → `d-none`)
- Modify: `public/css/familymodal.css:597-634,687-712,841-849,861-864,1283-1285`
- Modify: `public/assets/js/dashboard/manage-family-modal.js` (`family-form-hidden` references)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: the view from Tasks 1-2.
- Produces: every control in the form carries a Bootstrap class
  (`form-control`, `form-select`, or `form-check-input`). Checkbox markup becomes
  `<div class="form-check"><input class="form-check-input" id="…"><label class="form-check-label" for="…">`,
  so `input.closest('.family-choice')` in JS resolves to that wrapper — the
  `.family-choice` class stays on the wrapper as the JS hook.

- [ ] **Step 1: Write the failing test**

Append to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testEveryControlCarriesItsBootstrapClass(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/<input[^>]+name="qr_control_no"[^>]+class="form-control"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="head_address"[^>]+class="form-control"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="head_barangay"[^>]+class="form-select"/', $html);
        $this->assertStringNotContainsString('family-form-hidden', $html);
    }

    public function testCheckboxesUseRealFormCheckMarkup(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('class="form-check-input"', $html);
        $this->assertMatchesRegularExpression('/<label class="form-check-label" for="[^"]+"/', $html);
        // The old markup put the class on a <label class="form-check"> wrapping a bare input.
        $this->assertStringNotContainsString('<label class="form-check family-choice', $html);
    }
```

Note: the test asserts on attribute order, so write the view attributes in the
order the regex expects (`name` before `class` on the inputs, `name` before
`class` on the select) or relax the regex — prefer writing the markup to match.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — `qr_control_no` carries no `class` attribute at all.

- [ ] **Step 3: Class the three unclassed controls**

In `app/Views/Family/family-modal.php`, the QR input (note: Task 5 rewraps this in
an input group; here it only gains its class):

```php
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadQr" name="qr_control_no" class="form-control" type="text"
```

Address:

```php
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadAddress" name="head_address" class="form-control" type="text" value="<?= esc($oldValue('head_address'), 'attr') ?>" minlength="2" required>
```

(`data-summary="address"` is dropped — the summary reader it fed was deleted in
Task 1. Same for `data-summary` on barangay below. The `data-summary` attributes
inside `family_modal_render_person_fields()` are left alone: Branch 3's closed-row
rendering will reuse them.)

Barangay:

```php
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay" name="head_barangay" class="form-select" required>
                                <?= $selectOptions($barangayOptions, $oldValue('head_barangay'), 'Barangay') ?>
                            </select>
```

- [ ] **Step 4: Convert every checkbox to real form-check markup**

There are four checkbox loops (head sectors, head services, member sectors, member
services). Give each input a stable id built from its field name and value, so the
`<template>`'s `__INDEX__` substitution (`js:1104`) rewrites member ids along with
member names.

Head sectors — replace the `<label class="form-check family-choice…">` block with:

```php
                                                <?php $choiceId = $fieldPrefix . 'HeadSector' . $sectorId; ?>
                                                <div class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                                    <input class="form-check-input" type="checkbox" id="<?= esc($choiceId, 'attr') ?>" name="sector_ids[]" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" data-sector-code="<?= esc((string) ($sector['shortcode'] ?? $sector['code'] ?? ''), 'attr') ?>" data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="<?= esc($choiceId, 'attr') ?>"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></label>
                                                </div>
```

Head services — same shape, with `$choiceId = $fieldPrefix . 'HeadService' . $serviceId;`
and `name="service_ids[]"`.

Member sectors, inside `$renderMemberRow` — the id must carry the row index so
rows do not collide:

```php
                                <?php $choiceId = 'familyMember' . $i . 'Sector' . $sectorId; ?>
                                <div class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                    <input class="form-check-input" type="checkbox" id="<?= esc($choiceId, 'attr') ?>" name="<?= esc($field('sector_ids') . '[]', 'attr') ?>" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" data-sector-code="<?= esc((string) ($sector['shortcode'] ?? $sector['code'] ?? ''), 'attr') ?>" data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedSectors, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= esc($choiceId, 'attr') ?>"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></label>
                                </div>
```

`$i` is `'__INDEX__'` inside the `<template>`, so the generated id becomes
`familyMember__INDEX__Sector3` and `addMemberRow()`'s global replace turns it into
`familyMember0Sector3`. Member services: same, `$choiceId = 'familyMember' . $i . 'Service' . $serviceId;`.

- [ ] **Step 5: Replace family-form-hidden with d-none**

In `app/Helpers/family_modal_helper.php:189`:

```php
                            'class' => 'form-control mt-2 js-other-input d-none',
```

In `app/Views/Family/family-modal.php`, the relationship "Other" input:

```php
                <input class="form-control mt-2 js-other-input d-none" data-other-for="relationship" placeholder="Enter relationship">
```

In `manage-family-modal.js`, `setHidden()` (`:32`) is the single toggle point —
point it at `d-none`:

```javascript
    function setHidden(el, hidden) {
        if (el) {
            el.classList.toggle('d-none', !!hidden);
        }
    }
```

Grep for any remaining `family-form-hidden` (`grep -rn "family-form-hidden" app public`)
and convert each hit; delete the CSS rule at `:1283-1285`.

- [ ] **Step 6: Delete the custom control CSS and scope the theme to custom properties**

In `public/css/familymodal.css`, delete `:597-634` outright — the
`input/select/textarea` block, its `:focus` block, the
`.family-entry-form .is-invalid { … !important }` rule, `.family-field-error`, and
`.family-entry-form select { cursor: pointer }`. Delete `.family-choice input[type="checkbox"]`
(`:701-708`) and `.family-entry-form input[type="checkbox"]:checked` (`:710-712`).
Delete the compact-mode control override at `:841-849`.

Replace them with a custom-property block scoped to the form. There is no Sass
build (`bootstrap.min.css` is vendored), so custom properties are the available
route to variable-based theming:

```css
/* Bootstrap 5.3 reads these per-component custom properties, so the form keeps its
   look without a parallel implementation of .form-control / .form-select /
   .form-check-input. Values match what the old bare-element rules produced. */
.family-entry-form {
    --bs-border-color: #b9d7f7;
    --bs-body-color: #001f3f;
    --bs-form-control-bg: #fbfdff;
    --bs-form-check-bg: #fbfdff;
    --bs-form-check-checked-bg-color: #168a73;
    --bs-form-check-checked-border-color: #168a73;
}

.family-entry-form .form-control,
.family-entry-form .form-select {
    background-color: var(--bs-form-control-bg);
    font-size: 0.84rem;
}

.floating-family-modal.is-family-entry-modal .family-entry-form .form-control,
.floating-family-modal.is-family-entry-modal .family-entry-form .form-select {
    font-size: 0.82rem;
}
```

Keep the uppercase rule at the end of the file (`:1334-1338`) — it is Branch 1's,
and its bare-element selectors still match. Keep `.family-choice` (`:687-699`) as
the layout hook, but drop `display: flex; align-items: center; gap` from it, since
`.form-check` now owns that layout:

```css
.family-choice {
    min-height: 1.85rem;
    margin: 0;
    color: #062c57;
    font-size: 0.84rem;
}
```

Keep `.import-field-error` / `.import-field-warn` (`:1306-1314`) untouched — they
use `!important` on `border-color` and work against `.form-control` unchanged.

- [ ] **Step 7: Run the tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest && vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Views/Family/family-modal.php app/Helpers/family_modal_helper.php \
  public/css/familymodal.css public/assets/js/dashboard/manage-family-modal.js \
  tests/unit/FamilyModalViewTest.php
git commit -m "refactor: use Bootstrap's form components instead of restyling bare elements

familymodal.css reimplemented .form-control and .form-select through bare element
selectors, which is why the QR, address, and barangay controls carried no
Bootstrap class and only rendered correctly because of that CSS. Both sides change
together: the controls get their classes, the reimplementation goes, and the theme
is preserved through Bootstrap's own custom properties scoped to
.family-entry-form. Checkboxes move to real form-check markup with for/id.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: Flatten the sector and service panels

Four containers deep becomes two: the section card holds a two-column sector grid
and a services accordion. Services are deliberately **not** gated behind a sector
tick — Financial Assistance, Social Welfare Programs, and Emergency / Disaster
apply regardless of sector, and a person may have no sector at all.

**Files:**
- Modify: `app/Views/Family/family-modal.php` (both sector blocks, both service blocks, `$renderMemberRow`)
- Modify: `public/css/familymodal.css:656-663,714-759,861-864,1250`
- Modify: `public/assets/js/dashboard/manage-family-modal.js:789-1011` (`refreshSuggestions` → accordion auto-expand), `:930` (`.family-option-box` lookup)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: the `form-check` markup from Task 3.
- Produces:
  - `$renderSectorGrid(callable $fieldName, array $selectedIds, string $idPrefix): string` — a
    `.row.row-cols-2` of `.form-check` cells, no scroll container.
  - `$renderServiceAccordion(callable $fieldName, array $selectedIds, string $accordionId, string $idPrefix): string` —
    `.accordion` with one `.accordion-item` per category, `data-bs-parent` omitted so
    several stay open, each `.accordion-collapse` carrying
    `data-service-category` (the hook `refreshAgeEligibility` already reads) and
    `data-family-service-panel`.
  - `[data-family-service-filter]` — the filter input above each accordion.
  - JS: `refreshServiceCategories(scopeEl)` replaces `refreshSuggestions(scopeEl)`,
    same call sites, same single argument.

- [ ] **Step 1: Write the failing test**

Append to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testSectorsRenderAsATwoColumnGridWithNoScrollBox(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('family-option-box', $html);
        $this->assertStringContainsString('data-family-sector-grid', $html);
        $this->assertStringContainsString('row-cols-2', $html);
    }

    public function testServicesRenderAsAnAlwaysOpenAccordionPerCategory(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('class="accordion"', $html);
        $this->assertStringContainsString('accordion-item', $html);
        $this->assertStringContainsString('data-bs-toggle="collapse"', $html);
        $this->assertStringNotContainsString('data-bs-parent', $html);
        $this->assertStringContainsString('data-service-category="Senior Citizen"', $html);
        $this->assertStringContainsString('data-service-category="Financial Assistance"', $html);
    }

    public function testServicesAreNotGatedBehindASectorTick(): void
    {
        $html = $this->render();

        // Every category is present and reachable with no sector ticked.
        $this->assertStringContainsString('name="service_ids[]" value="5"', $html);
        $this->assertStringContainsString('name="service_ids[]" value="9"', $html);
        $this->assertStringNotContainsString('family-suggested', $html);
    }

    public function testServiceAccordionHasAFilterAndSectorsDoNot(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-family-service-filter', $html);
        $this->assertStringNotContainsString('data-family-sector-filter', $html);
    }

    public function testHeadChoicesExpandOnCreateAndCollapseOnEdit(): void
    {
        $create = $this->render(['modalMode' => 'create']);
        $edit = $this->render(['modalMode' => 'update', 'headId' => 42]);

        $this->assertMatchesRegularExpression('/class="collapse show"[^>]*data-family-choices/', $create);
        $this->assertDoesNotMatchRegularExpression('/class="collapse show"[^>]*data-family-choices/', $edit);
    }

    public function testTheChoicesToggleIsASelectionSummaryNotASecondHeading(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-family-choices-summary', $html);
        // "Sectors" and "Services and Programs" already label the two columns; the
        // toggle must not repeat them at a second heading level.
        $this->assertSame(1, substr_count($html, '>Sectors</h5>'));
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — `family-option-box` is still in the markup.

- [ ] **Step 3: Add the two render closures to the view**

At the top of `app/Views/Family/family-modal.php`, beside `$renderMemberRow`, add:

```php
/**
 * Sectors is a fixed list of ten, so it needs no scroll region: a two-column grid
 * shows all of them at once. $fieldName builds the posted name (head vs member row),
 * $idPrefix keeps checkbox ids unique between the head and each member row.
 */
$renderSectorGrid = static function (string $fieldName, array $selectedIds, string $idPrefix) use ($sectorCatalog, $sectorLabel): string {
    ob_start();
    ?>
    <div class="row row-cols-1 row-cols-sm-2 g-1" data-family-sector-grid>
        <?php if ($sectorCatalog === []): ?>
            <div class="col"><p class="text-muted mb-0">No sectors available.</p></div>
        <?php endif; ?>
        <?php foreach ($sectorCatalog as $sectorGroup): ?>
            <?php foreach (array_values((array) $sectorGroup) as $sector): ?>
                <?php
                $sector = (array) $sector;
                $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                $label = $sectorLabel($sector);

                if ($sectorId === '' || $label === '') {
                    continue;
                }

                $isArchived = ! empty($sector['is_archived']);
                $choiceId = $idPrefix . 'Sector' . $sectorId;
                ?>
                <div class="col">
                    <div class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                        <input class="form-check-input" type="checkbox" id="<?= esc($choiceId, 'attr') ?>" name="<?= esc($fieldName, 'attr') ?>" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" data-sector-code="<?= esc((string) ($sector['shortcode'] ?? $sector['code'] ?? ''), 'attr') ?>" data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= esc($choiceId, 'attr') ?>"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};

/**
 * Services are a long list across several categories, so each category is an
 * accordion item. data-bs-parent is deliberately omitted so several categories can
 * stay open at once; manage-family-modal.js expands the ones matching ticked sectors.
 * Nothing is hidden: categories that match no sector stay collapsed but present,
 * because programs like Financial Assistance apply regardless of sector.
 */
$renderServiceAccordion = static function (string $fieldName, array $selectedIds, string $accordionId, string $idPrefix) use ($servicesByCategory, $serviceLabel): string {
    ob_start();
    ?>
    <div class="mb-2">
        <input type="search" class="form-control form-control-sm" placeholder="Filter programs..." aria-label="Filter programs" data-family-service-filter>
    </div>
    <div class="accordion" id="<?= esc($accordionId, 'attr') ?>" data-family-service-accordion>
        <?php if ($servicesByCategory === []): ?>
            <p class="text-muted mb-0">No services available.</p>
        <?php endif; ?>
        <?php $categoryIndex = 0; ?>
        <?php foreach ($servicesByCategory as $category => $services): ?>
            <?php
            $categoryIndex++;
            $panelId = $accordionId . 'Panel' . $categoryIndex;
            ?>
            <div class="accordion-item" data-family-service-item>
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($panelId, 'attr') ?>" aria-expanded="false" aria-controls="<?= esc($panelId, 'attr') ?>">
                        <?= esc((string) $category) ?>
                    </button>
                </h2>
                <div id="<?= esc($panelId, 'attr') ?>" class="accordion-collapse collapse" data-family-service-panel data-service-category="<?= esc((string) $category, 'attr') ?>">
                    <div class="accordion-body py-2">
                        <?php foreach ((array) $services as $service): ?>
                            <?php
                            $service = (array) $service;
                            $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                            $label = $serviceLabel($service);

                            if ($serviceId === '' || $label === '') {
                                continue;
                            }

                            $isArchived = ! empty($service['is_archived']);
                            $choiceId = $idPrefix . 'Service' . $serviceId;
                            ?>
                            <div class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                <input class="form-check-input" type="checkbox" id="<?= esc($choiceId, 'attr') ?>" name="<?= esc($fieldName, 'attr') ?>" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($serviceId, $selectedIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= esc($choiceId, 'attr') ?>"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};
```

`$renderMemberRow` must be able to call these, so add both to its `use (…)` list
and declare them before it (PHP closures capture by value at definition time).

- [ ] **Step 4: Use the closures in both places**

In `$renderMemberRow`, replace the whole `<div class="row g-4 mt-1">` block (both
`.family-option-box` columns) with:

```php
        <div class="row g-3 mt-1">
            <div class="col-12 col-lg-5">
                <h5 class="family-column-title">Sectors</h5>
                <?= $renderSectorGrid($field('sector_ids') . '[]', $selectedSectors, 'familyMember' . $i) ?>
            </div>
            <div class="col-12 col-lg-7">
                <h5 class="family-column-title">Services and Programs</h5>
                <?= $renderServiceAccordion($field('service_ids') . '[]', $selectedServices, 'familyMember' . $i . 'Services', 'familyMember' . $i) ?>
            </div>
        </div>
```

In the head section, replace the whole `<section class="family-entry-section family-sector-service">`
body with a collapsible block. It is expanded on create (the worker is ticking
boxes off a paper form) and collapsed on edit (reference information):

```php
                <section class="family-entry-section family-sector-service">
                    <?php $choicesId = $fieldPrefix . 'HeadChoices'; $choicesOpen = $modalMode !== 'update'; ?>
                    <?php /* The toggle carries the selection summary rather than repeating the
                             column headings at a second level. JS keeps the text current. */ ?>
                    <button class="btn btn-link p-0 mb-2 text-decoration-none" type="button"
                            data-bs-toggle="collapse" data-bs-target="#<?= esc($choicesId, 'attr') ?>"
                            aria-expanded="<?= $choicesOpen ? 'true' : 'false' ?>" aria-controls="<?= esc($choicesId, 'attr') ?>">
                        <span data-family-choices-summary>Nothing selected yet</span>
                    </button>
                    <div class="collapse<?= $choicesOpen ? ' show' : '' ?>" id="<?= esc($choicesId, 'attr') ?>" data-family-choices>
                        <div class="row g-3">
                            <div class="col-12 col-lg-5">
                                <h5 class="family-column-title">Sectors</h5>
                                <?= $renderSectorGrid('sector_ids[]', $selectedSectorIds, $fieldPrefix . 'Head') ?>
                            </div>
                            <div class="col-12 col-lg-7">
                                <h5 class="family-column-title">Services and Programs</h5>
                                <?= $renderServiceAccordion('service_ids[]', $selectedServiceIds, $fieldPrefix . 'HeadServices', $fieldPrefix . 'Head') ?>
                            </div>
                        </div>
                    </div>
                </section>
```

- [ ] **Step 5: Replace the suggestion shuffle with accordion auto-expand**

The old `refreshSuggestions()` physically moved DOM groups into a yellow panel that
scrolled inside a 15.6rem box. Auto-expand does the same job without moving
anything. In `manage-family-modal.js`, delete `stampGroupOrder()` (`:889-895`),
`returnGroupHome()` (`:898-911`), and `refreshSuggestions()` (`:914-1011`), and add:

```javascript
    // A service category is "linked" to a sector when its name matches the sector's
    // name (the same convention the server uses for archive cascades). Linked
    // categories auto-expand; the rest stay collapsed but present, so nothing is
    // hidden behind a sector tick.
    function refreshServiceCategories(scopeEl) {
        if (!scopeEl || !scopeEl.querySelectorAll) {
            return;
        }

        var row = scopeEl.matches && scopeEl.matches('[data-family-member-row]') ? scopeEl : null;
        var searchRoot = row || scopeEl;
        var sectorSelector = row ? 'input[name$="[sector_ids][]"]:checked' : 'input[name="sector_ids[]"]:checked';
        var checkedKeys = {};

        searchRoot.querySelectorAll(sectorSelector).forEach(function (input) {
            checkedKeys[normName(input.dataset.sectorName)] = true;
        });

        var panels = Array.from(searchRoot.querySelectorAll('[data-family-service-panel]')).filter(function (panel) {
            return row ? true : !panel.closest('[data-family-member-row]');
        });

        panels.forEach(function (panel) {
            var matched = !!checkedKeys[normName(panel.dataset.serviceCategory)];
            var hasChecked = panel.querySelector('input[type="checkbox"]:checked') !== null;

            if (matched || hasChecked) {
                openServicePanel(panel, true);
            }
        });
    }

    // Drives a collapse panel without requiring bootstrap.Collapse to be instantiated
    // first, and keeps the header button's aria-expanded in step.
    function openServicePanel(panel, open) {
        var isOpen = panel.classList.contains('show');

        if (isOpen === open) {
            return;
        }

        var button = panel.closest('.accordion-item');
        button = button ? button.querySelector('.accordion-button') : null;

        if (window.bootstrap && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false })[open ? 'show' : 'hide']();
        } else {
            panel.classList.toggle('show', open);
        }

        if (button) {
            button.classList.toggle('collapsed', !open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }
```

Rename every `refreshSuggestions(` call site to `refreshServiceCategories(`:
`:879` (end of `refreshAgeEligibility`), `:1423`, `:1432`.

`refreshAgeEligibility()` (`:830-880`) needs one change: it read the category from
`input.closest('[data-service-category]')`, which still resolves — the attribute
moved from `.family-option-group` to `.accordion-collapse`, and the inputs are
inside it. No edit needed. Verify by reading the selector at `:851`.

- [ ] **Step 6: Wire the services filter**

In `initFamilyEntryModal()`, inside the existing `root.addEventListener('input', …)`
handler (`:1384`), add before `scheduleSave(root)`:

```javascript
            if (target && target.matches('[data-family-service-filter]')) {
                filterServiceAccordion(target);
            }
```

and add the function beside `refreshServiceCategories`:

```javascript
    // Type-to-narrow over one accordion: non-matching choices hide, categories with a
    // match expand, and clearing the box restores the default collapsed state.
    function filterServiceAccordion(input) {
        var accordion = input.parentElement ? input.parentElement.nextElementSibling : null;

        if (!accordion || !accordion.matches('[data-family-service-accordion]')) {
            return;
        }

        var term = String(input.value || '').trim().toLowerCase();

        accordion.querySelectorAll('[data-family-service-item]').forEach(function (item) {
            var panel = item.querySelector('[data-family-service-panel]');
            var matches = 0;

            item.querySelectorAll('.family-choice').forEach(function (choice) {
                var label = choice.querySelector('.form-check-label');
                var hit = term === '' || (label ? label.textContent.toLowerCase().indexOf(term) !== -1 : false);

                choice.classList.toggle('d-none', !hit);

                if (hit) {
                    matches++;
                }
            });

            item.classList.toggle('d-none', term !== '' && matches === 0);

            if (panel && term !== '') {
                openServicePanel(panel, matches > 0);
            }
        });

        if (term === '') {
            refreshServiceCategories(input.closest('[data-family-member-row]') || input.closest('[data-family-entry-form]'));
        }
    }
```

- [ ] **Step 6b: Keep the collapse toggle's summary current**

The head block's toggle is the only label for a collapsed panel, so it has to say
what is inside it. Add beside `refreshServiceCategories`:

```javascript
    // The collapse toggle doubles as the summary of what is ticked, so a collapsed
    // block (edit mode) still tells the worker what this person is categorised as.
    function refreshChoicesSummary(root) {
        var target = root.querySelector('[data-family-choices-summary]');

        if (!target) {
            return;
        }

        var codes = Array.from(root.querySelectorAll('input[name="sector_ids[]"]:checked')).map(function (input) {
            return String(input.dataset.sectorCode || input.dataset.label || '').trim();
        }).filter(Boolean);
        var services = root.querySelectorAll('input[name="service_ids[]"]:checked').length;

        if (codes.length === 0 && services === 0) {
            target.textContent = 'Nothing selected yet';
            return;
        }

        target.textContent = 'Sectors: ' + (codes.length ? codes.join(', ') : 'none')
            + ' (' + services + ' program' + (services === 1 ? '' : 's') + ')';
    }
```

Call it from the `change` handler in `initFamilyEntryModal()` (next to the existing
`scheduleSave(root)`), from the archived-item `.then()` branch, and once during
init beside `refreshAllAgeEligibility(root)`.

- [ ] **Step 7: Delete the panel CSS**

In `public/css/familymodal.css` delete `.family-option-box` (`:656-663`), the whole
`.family-suggested*` block including the keyframes (`:714-759`), the compact
`.family-option-box` override (`:861-864`), and `.family-option-box--sm` (`:1250`).
Keep `.family-option-group*` only if a selector still uses it after this task —
grep first (`grep -rn "family-option-group" app public`) and delete the rules if
nothing matches. Add one rule so a long accordion cannot outgrow the modal:

```css
.family-entry-form .accordion-body {
    max-height: 16rem;
    overflow-y: auto;
}
```

- [ ] **Step 8: Run the tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest && vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Views/Family/family-modal.php public/css/familymodal.css \
  public/assets/js/dashboard/manage-family-modal.js tests/unit/FamilyModalViewTest.php
git commit -m "refactor: flatten the sector and service panels

The lists sat four containers deep, inside a fixed 15.6rem scroll box, with a
yellow Suggested panel scrolling inside it and consuming most of the visible area
(about four checkboxes readable at a time). Sectors is a fixed list of ten, so it
becomes a two-column grid with no scroll region. Services become an accordion, one
item per category, with data-bs-parent omitted so several stay open; categories
matching the ticked sectors auto-expand, which does the Suggested panel's job
without relocating DOM. Services stay ungated: Financial Assistance, Social
Welfare, and Emergency apply regardless of sector.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: Bootstrap validation — needs-validation, invalid-feedback, QR status addon

**Files:**
- Modify: `app/Views/Family/family-modal.php` (form tag, QR field)
- Modify: `app/Helpers/family_modal_helper.php:174-211` (per-field feedback div + `aria-describedby`)
- Modify: `public/assets/js/dashboard/manage-family-modal.js:265-289` (`setFieldError`), `:325-374` (QR check), `:1048-1052` (`clearFieldError`), `initFamilyEntryModal` (blur binding), `submitFamilyForm` (member completeness + focus)
- Test: `tests/unit/FamilyModalViewTest.php`

**Interfaces:**
- Consumes: Bootstrap-classed controls from Task 3.
- Produces:
  - Each rendered field is followed by `<div class="invalid-feedback" id="<fieldId>Feedback" data-family-field-error></div>`,
    and the control carries `aria-describedby="<fieldId>Feedback"`. Fields with no id
    (member rows, until Branch 3) get the div with `data-family-field-error` only;
    `setFieldError()` finds it by that attribute either way.
  - `MEMBER_REQUIRED_FIELDS` — JS constant mirroring `firstIncompleteMember()`:
    `['birthday', 'sex', 'civilstatus', 'education', 'job', 'salary']`.
  - `[data-family-qr-status]` — the QR input-group addon element.

- [ ] **Step 1: Write the failing test**

Append to `tests/unit/FamilyModalViewTest.php`:

```php
    public function testFormOptsIntoBootstrapValidation(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/<form[^>]+class="[^"]*needs-validation[^"]*"[^>]*novalidate/', $html);
    }

    public function testEveryFieldCarriesAnInvalidFeedbackTarget(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('class="invalid-feedback"', $html);
        $this->assertStringContainsString('data-family-field-error', $html);
        $this->assertStringNotContainsString('family-field-error', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]+Feedback"/', $html);
    }

    public function testQrFieldIsAnInputGroupWithAStatusAddon(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('input-group has-validation', $html);
        $this->assertStringContainsString('data-family-qr-status', $html);
    }
```

Note `assertStringNotContainsString('family-field-error', …)` also matches
`data-family-field-error`; use the negative assertion on the CSS class only:

```php
        $this->assertStringNotContainsString('class="family-field-error"', $html);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest`
Expected: FAIL — the `<form>` has no `needs-validation` class.

- [ ] **Step 3: Opt the form into Bootstrap validation**

In `app/Views/Family/family-modal.php`:

```php
    <form class="needs-validation" method="post" action="<?= esc($action, 'attr') ?>" autocomplete="off" novalidate>
```

`novalidate` suppresses the browser's own bubbles so Bootstrap's feedback styling
is what the worker sees. It is not load-bearing for the submit hang — removing the
tabs handled that in Task 1.

- [ ] **Step 4: Emit a feedback div per field in the helper**

In `app/Helpers/family_modal_helper.php`, inside the `foreach` of
`family_modal_render_person_fields()`, add before the closing `</div>` of each
column (`:211`) and thread `aria-describedby` into both control branches:

```php
            $feedbackId = $id !== '' ? $id . 'Feedback' : '';
            ?>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label"<?= $attrs(['for' => $id]) ?>><?= esc($label) ?></label>
                <?php if ($type === 'select'): ?>
                    <select<?= $attrs([
                        'class' => 'form-select' . ($hasOther ? ' js-other-select' : ''),
                        'id' => $id,
                        'name' => $field($name),
                        'aria-describedby' => $feedbackId,
                        'data-summary' => $summary,
                        'required' => $required,
                        'data-other-field' => $hasOther ? $otherKey : '',
                        'data-initial-value' => $hasOther ? $val($name) : '',
                    ]) ?>><?= $selectOptions($options, $val($name), 'Select') ?></select>
```

(the `<input>` branch takes the same `'aria-describedby' => $feedbackId,` line),
then, after the `<?php endif; ?>` that closes the select/input branch:

```php
                <div class="invalid-feedback"<?= $attrs(['id' => $feedbackId]) ?> data-family-field-error></div>
            </div>
```

Bootstrap only shows `.invalid-feedback` next to an `.is-invalid` control, so an
empty div renders as nothing.

Add the same feedback div by hand to the four fields the helper does not render —
QR (inside the input group, see Step 5), address, barangay, and the member
relationship select:

```php
                            <div class="invalid-feedback" id="<?= esc($fieldPrefix, 'attr') ?>HeadAddressFeedback" data-family-field-error></div>
```

- [ ] **Step 5: Wrap QR in an input group with a status addon**

Replace the QR column in `app/Views/Family/family-modal.php`:

```php
                        <div class="col-12 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadQr">QR Number</label>
                            <div class="input-group has-validation">
                                <input id="<?= esc($fieldPrefix, 'attr') ?>HeadQr" name="qr_control_no" class="form-control" type="text"
                                    inputmode="numeric" pattern="0*[1-9][0-9]{0,6}"
                                    title="QR number must be numeric and should not exceed 9,999,999"
                                    aria-describedby="<?= esc($fieldPrefix, 'attr') ?>HeadQrFeedback"
                                    data-qr-check-url="<?= esc($qrCheckUrl, 'attr') ?>"
                                    value="<?= esc($oldValue('qr_control_no'), 'attr') ?>"
                                    <?= $qrLocked ? 'readonly' : 'required' ?>>
                                <?php /* Makes the async uniqueness check legible: spinner while checking,
                                         tick or cross when it lands. manage-family-modal.js drives it. */ ?>
                                <span class="input-group-text" data-family-qr-status aria-live="polite"></span>
                                <div class="invalid-feedback" id="<?= esc($fieldPrefix, 'attr') ?>HeadQrFeedback" data-family-field-error></div>
                            </div>
                            <?php if ($qrLocked): ?>
                                <small class="text-muted">Locked: subsidy already recorded under this number.</small>
                            <?php endif; ?>
                        </div>
```

`.has-validation` is required for the feedback div to sit correctly inside an input
group (Bootstrap 5.3 validation docs).

- [ ] **Step 6: Point setFieldError at the Bootstrap feedback element**

Replace `setFieldError()` (`js:265-289`). It keeps its logic — it now writes into
the server-rendered div instead of creating a `.family-field-error`:

```javascript
    function setFieldError(field, message) {
        if (!field) {
            return;
        }

        var scope = field.closest('.input-group') || field.closest('[class*="col-"]') || field.parentElement;
        var feedback = scope ? scope.querySelector('[data-family-field-error]') : null;

        field.classList.toggle('is-invalid', message !== '');

        if (feedback) {
            feedback.textContent = message;
        }
    }
```

The `hidden` toggle is gone: Bootstrap shows `.invalid-feedback` only when the
sibling control carries `.is-invalid`, so the class toggle is the single switch.

- [ ] **Step 7: Make the QR check clear its custom validity on every outcome**

In `scheduleQrAvailabilityCheck()` (`js:325-374`), drive the addon and add a
timeout so an in-flight request can never wedge the save. Replace the body from
`field.setCustomValidity('Checking whether this QR number already exists.');`
onwards:

```javascript
        setQrStatus(field, 'checking');
        field.setCustomValidity('Checking whether this QR number already exists.');

        qrCheckTimer = window.setTimeout(function () {
            var url = new URL(field.dataset.qrCheckUrl, window.location.href);
            var headId = root.querySelector('[name="head_id"]');
            url.searchParams.set('control_no', field.value);
            url.searchParams.set('head_id', headId ? headId.value : '0');

            // A hung request must never leave setCustomValidity parked: that blocks
            // submit with no visible message. Release it and let the server decide.
            var release = window.setTimeout(function () {
                if (sequence === qrCheckSequence && field.isConnected) {
                    field.setCustomValidity('');
                    setFieldError(field, '');
                    setQrStatus(field, '');
                }
            }, 5000);

            window.fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                window.clearTimeout(release);

                if (sequence !== qrCheckSequence || !field.isConnected) {
                    return;
                }

                var available = result.ok && result.data.available;
                var message = available ? '' : (result.data.message || 'The QR number could not be validated.');
                field.setCustomValidity(message);
                setFieldError(field, message);
                setQrStatus(field, available ? 'ok' : 'bad');
            }).catch(function () {
                window.clearTimeout(release);

                if (sequence === qrCheckSequence && field.isConnected) {
                    field.setCustomValidity('');
                    setFieldError(field, '');
                    setQrStatus(field, '');
                }
            });
        }, 350);
    }

    function setQrStatus(field, state) {
        var group = field.closest('.input-group');
        var status = group ? group.querySelector('[data-family-qr-status]') : null;

        if (!status) {
            return;
        }

        var icons = {
            checking: '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span class="visually-hidden">Checking QR number</span>',
            ok: '<i class="bi bi-check-lg text-success" aria-hidden="true"></i><span class="visually-hidden">QR number available</span>',
            bad: '<i class="bi bi-x-lg text-danger" aria-hidden="true"></i><span class="visually-hidden">QR number not available</span>'
        };

        status.innerHTML = icons[state] || '';
    }
```

Also clear the addon at the top of the function, next to the existing
`setFieldError(field, '')` at `:336`: `setQrStatus(field, '');`.

- [ ] **Step 8: Validate on blur, and mirror member completeness on submit**

In `initFamilyEntryModal()`, add a blur listener next to the existing `input` and
`change` listeners (capture phase, because `blur` does not bubble):

```javascript
        // Per-field validation on blur, so an error lands at the field the worker just
        // left instead of only after a submit round-trip.
        root.addEventListener('blur', function (event) {
            var target = event.target;

            if (!target || !target.matches('input, select, textarea')) {
                return;
            }

            if (isContactField(target)) {
                validateContact(target);
                return;
            }

            if (target.matches('[required]')) {
                var isEmpty = String(target.value || '').trim() === '';
                var invalid = !target.checkValidity();

                setFieldError(target, invalid ? (isEmpty ? 'This field is required.' : target.validationMessage) : '');
            }
        }, true);
```

Add the member-completeness mirror beside `validateMemberContacts()` (`js:1206`):

```javascript
    // Mirrors FamilyController::firstIncompleteMember() so the worker sees the gap at
    // the field. Same six fields, same skip-if-unnamed rule as hasMemberData(): a row
    // with no name at all is treated as an empty row, not an incomplete one. The server
    // keeps its own copy and stays authoritative.
    var MEMBER_REQUIRED_FIELDS = ['birthday', 'sex', 'civilstatus', 'education', 'job', 'salary'];

    function memberHasData(row) {
        return ['lastname', 'firstname'].some(function (key) {
            var field = row.querySelector('[name$="[' + key + ']"]');

            return field && String(field.value || '').trim() !== '';
        });
    }

    function validateMembers(form) {
        var firstInvalid = null;

        Array.from(form.querySelectorAll('[data-family-member-row]')).forEach(function (row) {
            if (!memberHasData(row)) {
                MEMBER_REQUIRED_FIELDS.forEach(function (key) {
                    var field = row.querySelector('[name$="[' + key + ']"]');

                    if (field) {
                        setFieldError(field, '');
                    }
                });

                return;
            }

            MEMBER_REQUIRED_FIELDS.forEach(function (key) {
                var field = row.querySelector('[name$="[' + key + ']"]');

                if (!field) {
                    return;
                }

                var empty = String(field.value || '').trim() === '';
                setFieldError(field, empty ? 'This field is required.' : '');

                if (empty && !firstInvalid) {
                    firstInvalid = field;
                }
            });
        });

        return firstInvalid;
    }
```

Then in `submitFamilyForm()`, run it and focus the first invalid field:

```javascript
    function submitFamilyForm(root, form) {
        form.classList.add('was-validated');

        if (!validateHead(root)) {
            return;
        }

        var badMember = validateMembers(form) || validateMemberContacts(form);

        if (badMember) {
            badMember.focus();
            badMember.scrollIntoView({ block: 'center' });
            return;
        }
```

Branch 3 adds "expand its member row if closed" here, once closed rows exist.

- [ ] **Step 9: Run the tests**

Run: `vendor/bin/phpunit --filter FamilyModalViewTest && vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Views/Family/family-modal.php app/Helpers/family_modal_helper.php \
  public/assets/js/dashboard/manage-family-modal.js tests/unit/FamilyModalViewTest.php
git commit -m "feat: replace the hand-rolled field errors with Bootstrap validation

The form opts into needs-validation/novalidate and every field renders an
invalid-feedback div wired to the control through aria-describedby, so
setFieldError writes into the standard element instead of injecting its own. QR
becomes an input group with a status addon that shows the async check, and the
check now releases setCustomValidity on failure and on timeout rather than only on
success. Member completeness mirrors firstIncompleteMember() client-side; the
server keeps its copy and stays authoritative.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: Set as Head — confirm, then show the result

Under tabs this happened across a tab boundary and was effectively invisible. On
one scrolling page it happens in front of the worker.

**Files:**
- Modify: `public/assets/js/dashboard/manage-family-modal.js:229-261` (`promoteMemberToHead`), `:1466-1476` (the click handler)

**Interfaces:**
- Consumes: `askModalDialog(form, options)` (`js:378`), already used by
  `askRemoveArchivedItem` / `askRestoreDraft`.
- Produces: `promoteMemberToHead(root, row)` returns nothing but is now called from
  a `.then()` after confirmation.

Two of the spec's Set-as-Head bullets need collapsible member rows, which Branch 3
builds: "the promoted member's row collapses out of the member list" and "the
previous head becomes a member row, appended to the list, expanded". Today every
row is expanded and the swap happens in place, so those two land in Branch 3. The
confirm and the scroll are what Branch 2 can deliver, and they are the parts that
make the swap visible.

- [ ] **Step 1: Add the confirm dialog**

Add beside the other `ask*` helpers (`js:456-503`):

```javascript
    function askPromoteToHead(form, name) {
        return askModalDialog(form, {
            title: 'Make this person the head?',
            message: (name ? name + ' ' : 'This member ') + 'will take the head position and hold the QR card. The current head becomes a member, and you will need to set their relationship.',
            iconClass: 'bi bi-person-up',
            tone: 'warning',
            cancelLabel: 'Cancel',
            confirmLabel: 'Make head',
            confirmClass: 'btn btn-primary'
        });
    }
```

- [ ] **Step 2: Scroll to the head after the swap**

Replace the tail of `promoteMemberToHead()` (`js:257-261`):

```javascript
        writePersonField(memberField('relationship'), '');

        refreshAllAgeEligibility(root);

        // The swap rewrites who holds the QR card, so put the result in front of the
        // worker instead of leaving it below the fold.
        var headField = root.querySelector('[name="head_lastname"]');

        if (headField) {
            headField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
```

(The `renderHeadSummary(root)` call was already deleted in Task 1.)

- [ ] **Step 3: Gate the click handler behind the confirm**

Replace the `setHeadButton` branch in the click handler (`js:1466-1476`):

```javascript
            var setHeadButton = event.target.closest('[data-family-set-head]');

            if (setHeadButton) {
                event.preventDefault();
                var headRow = setHeadButton.closest('[data-family-member-row]');

                if (!headRow || !formEl) {
                    return;
                }

                var firstName = headRow.querySelector('[name$="[firstname]"]');
                var lastName = headRow.querySelector('[name$="[lastname]"]');
                var memberName = [
                    firstName ? String(firstName.value || '').trim() : '',
                    lastName ? String(lastName.value || '').trim() : ''
                ].filter(Boolean).join(' ');

                askPromoteToHead(formEl, memberName).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    promoteMemberToHead(root, headRow);
                    scheduleSave(root);
                });
            }
```

- [ ] **Step 4: Run the suite**

Run: `vendor/bin/phpunit`
Expected: PASS (this task is JS-only; the suite must stay green).

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/dashboard/manage-family-modal.js
git commit -m "feat: confirm Set as Head and scroll to the result

Under the tab split this swap happened across a tab boundary and was effectively
invisible. On one scrolling page it needs defined behaviour: it rewrites who holds
the QR card, so it asks first, then scrolls the head card into view so the worker
sees what changed.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 7: Verify in the browser, including the import-fix flow

No code deliverable unless verification finds a defect. This task is the gate
before the branch is offered for review.

**Files:**
- Modify (only if a defect is found): whichever file the defect is in.

**Interfaces:**
- Consumes: everything above.
- Produces: a verification record pasted into the branch's PR description.

- [ ] **Step 1: Start the dev server**

Run: `php spark serve --port 8090` (background). Confirm `http://localhost:8090`
answers. Use the intl-enabled `php`, not XAMPP's.

- [ ] **Step 2: Confirm every route still resolves**

Run: `php spark routes`
Expected: no errors. Nothing in this branch touches routing, so this is a smoke
check.

- [ ] **Step 3: Log in and open the Add form**

With the Playwright MCP: navigate to `http://localhost:8090`, log in as
`developer` / `developer123`, go to Manage Records, click Add.

`browser_snapshot` and assert:
- No tablist / step buttons in the tree.
- The head fields and the member section are in one scrolling region.
- Sectors renders as a visible grid of 10, no inner scrollbar.
- Services renders as an accordion; ticking `SC - Senior Citizen` expands the
  Senior Citizen category and leaves the others collapsed but present.
- Footer reads Close (outline) / Clear (red) on the left, Save (blue) on the right.

- [ ] **Step 4: Assert the submit hang is gone**

Clear the QR field and press Save. Expected: focus lands on the QR field with a
visible message under it, and the modal does not sit silently. This is the specific
regression Branch 2 exists to fix — today it hangs with no feedback.

Then type into the head fields and confirm "Draft saved" appears in the footer.

- [ ] **Step 5: Screenshot both widths**

`browser_take_screenshot` at desktop, then `browser_resize` to 390px wide and
screenshot again. Compare against Manage Records (`docs/knowledge/binan-conventions/ui-design-system.md`
is the standard). At 390px assert: no horizontal page scroll, the sector grid
collapses to one column (`row-cols-1`), the footer buttons wrap rather than
overflow.

- [ ] **Step 6: Verify the Edit path**

Open an existing record from Manage Records. Assert the head's Sectors and Programs
block renders **collapsed** (edit mode), expands on click, and that every dropdown
still shows its stored value — Branch 1's round-trip guarantee must survive the
markup change.

- [ ] **Step 7: Verify the import-fix flow**

Go to the Excel import review screen and open a flagged family through
`.js-import-fix-edit`. Assert:
- The blocking (red) and warning (amber) alert block renders above the form.
- Flagged fields carry the red/amber outline with the note beneath, and the flag
  clears when the field is edited (`markImportField`, `js:1663-1692`).
- The QR field is readonly with the "Locked: subsidy already recorded under this
  number." note, and the status addon does not spin (the check is skipped for a
  readonly field, `js:326`).
- Saving returns to the review screen with the report re-rendered.

- [ ] **Step 8: Run the full suite one more time**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 9: Record the verification**

Write the findings (what was checked, at which widths, which screenshots) into the
PR description when the branch is opened. If any step failed, fix it, re-run
steps 3-8, and only then continue.

---

## After the plan

Follow `CLAUDE.md`'s review workflow before merging:

1. `coderabbit auth status`, then `coderabbit review --base feat/family-form-uppercase --agent`
   in the background and wait for it.
2. Triage every finding against the code and the non-negotiables
   (`superpowers:receiving-code-review` posture). Do not blind-apply.
3. Fix the in-scope bugs, re-run `vendor/bin/phpunit`.
4. Park the rest in a GitHub issue citing the PR # and branch, in the issue format
   documented in `CLAUDE.md`.

Branch 3 (member rows and field guards) starts from this branch, not from `main`,
for the same reason this one started from `feat/family-form-uppercase`.
