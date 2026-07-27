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

    public function testEveryControlCarriesItsBootstrapClass(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/<input[^>]+name="qr_control_no"[^>]+class="form-control"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="head_address"[^>]+class="form-control"/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+name="head_barangay"[^>]+class="form-select"/', $html);
        $this->assertStringNotContainsString('family-form-hidden', $html);
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
        $this->assertStringContainsString('class="btn btn-success" type="button" data-family-add-member', $html);
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
        $this->assertStringNotContainsString('btn-outline-secondary btn-sm', $html);
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
