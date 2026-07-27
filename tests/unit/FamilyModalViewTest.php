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
            // view() keeps its data between calls by default, so one test's headId
            // would leak into the next. saveData => false renders each case clean.
        ], $data), ['saveData' => false]);
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
        // The generic per-card "Member" strong is gone; the card title now names the
        // actual person, filled by JS.
        $this->assertStringNotContainsString('<strong class="family-member-card-title">Member</strong>', $html);
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

    public function testEveryControlCarriesItsBootstrapClass(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/<input[^>]+name="qr_control_no"[^>]+class="form-control"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="head_address"[^>]+class="form-control"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="head_barangay"[^>]+class="form-select"/', $html);
        $this->assertStringNotContainsString('family-form-hidden', $html);
    }

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
        $this->assertStringNotContainsString('class="family-field-error"', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]+Feedback"/', $html);
    }

    public function testControlNumberIsAPlainFieldWithNoStatusAddon(): void
    {
        $html = $this->render();

        // A taken number turns the field red and states why underneath it, so the
        // field needs no addon repeating that in its own box.
        $this->assertStringNotContainsString('data-family-qr-status', $html);
        $this->assertStringNotContainsString('input-group', $html);
        $this->assertStringContainsString('name="qr_control_no"', $html);
    }

    public function testCheckboxesUseRealFormCheckMarkup(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('class="form-check-input"', $html);
        $this->assertMatchesRegularExpression('/<label class="form-check-label" for="[^"]+"/', $html);
        // The old markup put form-check on a <label> wrapping a bare input.
        $this->assertStringNotContainsString('<label class="form-check family-choice', $html);
    }

    public function testSectorsRenderAsATwoColumnGridWithNoScrollBox(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('family-option-box', $html);
        $this->assertStringContainsString('data-family-sector-grid', $html);
        $this->assertStringContainsString('row-cols-sm-2', $html);
    }

    /**
     * esc(..., 'attr') entity-encodes spaces and brackets, so the raw HTML shows
     * name="service_ids&#x5B;&#x5D;". The browser decodes them before the JS sees
     * them, so assertions about attribute values run against the decoded markup.
     *
     * @param array<string, mixed> $data
     */
    private function renderDecoded(array $data = []): string
    {
        return html_entity_decode($this->render($data), ENT_QUOTES | ENT_HTML5);
    }

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

    public function testServicesRenderAsAnAlwaysOpenAccordionPerCategory(): void
    {
        $html = $this->renderDecoded();

        $this->assertStringContainsString('class="accordion"', $html);
        $this->assertStringContainsString('accordion-item', $html);
        $this->assertStringContainsString('data-family-service-panel', $html);
        $this->assertStringContainsString('data-service-category="Senior Citizen"', $html);
        $this->assertStringContainsString('data-service-category="Financial Assistance"', $html);
        // No data-bs-parent, so more than one category can stay open at a time.
        $this->assertStringNotContainsString('data-bs-parent', $html);
    }

    public function testMemberRowActionsLiveInAnActionsMenu(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('actions-menu-toggle', $html);
        $this->assertMatchesRegularExpression('/class="dropdown-item" type="button" data-family-set-head/', $html);
        $this->assertMatchesRegularExpression('/class="dropdown-item text-danger" type="button" data-family-member-remove/', $html);
        // No more competing outline buttons in colors that carry no role.
        $this->assertStringNotContainsString('btn btn-outline-primary', $html);
    }

    public function testHeadAndMembersUseTheSameCardChrome(): void
    {
        $html = $this->render();

        // Head and member are the same kind of thing, so they render the same card.
        $this->assertSame(2, substr_count($html, 'family-person-card-header'));
        $this->assertStringContainsString('>Head of Family</h3>', $html);
        $this->assertStringContainsString('data-family-member-title', $html);
    }

    public function testQrLeadsTheHeadCardGrid(): void
    {
        $html = $this->render();

        // The QR is the first field inside the head card, not a strip or header of
        // its own above it.
        $this->assertMatchesRegularExpression(
            '/family-person-card-header.*Head of Family.*qr_control_no.*head_lastname/s',
            $html
        );
        $this->assertStringNotContainsString('family-record-strip', $html);
    }

    public function testControlNumberIsLabelledAsSuchAndOwnsTheFirstRow(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('>Control Number</label>', $html);
        $this->assertStringNotContainsString('>QR Number</label>', $html);
        // Full width: it identifies the record, so it does not share a row with the
        // name fields. The posted name is unchanged.
        $this->assertMatchesRegularExpression('/<div class="col-12">\s*<label[^>]*>Control Number/', $html);
        $this->assertStringContainsString('name="qr_control_no"', $html);
    }

    public function testEveryPersonCardCarriesTheSameChoicesBlock(): void
    {
        $html = $this->render();

        // One for the head, one for the member template.
        $this->assertSame(2, substr_count($html, 'data-family-choices-block'));
        $this->assertSame(2, substr_count($html, 'data-family-choices-summary'));
    }

    public function testAddressAndBarangaySplitTheRowEightFour(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/col-12 col-xl-8">\s*<label[^>]*>Address/', $html);
        $this->assertMatchesRegularExpression('/col-12 col-xl-4">\s*<label[^>]*>Barangay/', $html);
    }

    public function testServicesAreNotGatedBehindASectorTick(): void
    {
        $html = $this->renderDecoded();

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
        // "Sectors" already labels the column; the toggle must not repeat it as a
        // second heading level. One head block plus one member template = 2.
        $this->assertSame(2, substr_count($html, '>Sectors</h5>'));
    }

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
        $this->assertMatchesRegularExpression('/class="btn btn-success[^"]*"[^>]*data-family-add-member/', $html);
        $this->assertStringContainsString('btn btn-outline-secondary" type="button" data-bs-dismiss="modal"', $html);
        $this->assertStringNotContainsString('btn btn-secondary" type="button" data-bs-dismiss="modal"', $html);
        $this->assertStringNotContainsString('btn btn-warning', $html);
    }

    public function testFooterCarriesADraftSavedIndicator(): void
    {
        $this->assertStringContainsString('data-family-draft-status', $this->render());
    }

    public function testPrintQrLinkOnlyRendersForASavedRecordAndIsNotInsideTheButtonGroup(): void
    {
        $this->assertStringNotContainsString('Print QR card', $this->render());

        $html = $this->render(['headId' => 42]);

        $this->assertStringContainsString('Print QR card', $html);
        // It is an anchor, not a btn-sm wedged into a default-size button group.
        $this->assertMatchesRegularExpression('/<a\b[^>]*class="btn btn-link btn-sm[^"]*"[^>]*>\s*Print QR card/', $html);
        $this->assertStringNotContainsString('btn-group', $html);
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
