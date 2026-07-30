<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression coverage for Task 8 fix round 1 findings 3, 4, and 5:
 * Family/_fields.php must source its static option lists from
 * FamilyFormOptionsModel::staticOptionLists() instead of retyping them out of
 * sorted order, an existing record's control number must stay postable
 * (readonly, never disabled - a disabled control submits nothing), and the
 * edit-mode field id prefix must actually take effect.
 */
final class FamilyFieldsPartialTest extends CIUnitTestCase
{
    private function render(array $head = []): string
    {
        helper('family_modal');

        return view('Family/_fields', [
            'head'       => $head,
            'members'    => [],
            'readOnly'   => false,
            'sectors'    => [],
            'services'   => [],
            'categories' => [],
        ]);
    }

    public function testBarangayOptionsAreAlphabetizedNotDeclarationOrder(): void
    {
        $html = $this->render();

        // FamilyProfilingFormV2::barangays() declares "Santo Tomas (Calabuso)"
        // before "Canlalay"; FamilyFormOptionsModel::staticOptionLists() sorts
        // them alphabetically, so Canlalay must render first once sorted.
        $this->assertLessThan(
            (int) strpos($html, 'Santo Tomas'),
            (int) strpos($html, 'Canlalay'),
            'Barangay options must be alphabetized via FamilyFormOptionsModel, not left in raw declaration order.'
        );
    }

    public function testExistingHeadControlNumberIsReadonlyNotDisabled(): void
    {
        $html = $this->render(['headID' => 5, 'qr_control_no' => '12345']);

        preg_match('/<input[^>]*name="qr_control_no"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, 'Expected a qr_control_no input for an existing head.');
        $this->assertStringContainsString('readonly', $matches[0]);
        $this->assertStringNotContainsString('disabled', $matches[0],
            'A disabled control number field would not be submitted, failing update() validation.');
    }

    public function testEditModeFieldPrefixTakesEffect(): void
    {
        $html = $this->render(['headID' => 5, 'qr_control_no' => '12345']);

        $this->assertStringContainsString('id="family-updateHeadQr"', $html);
        $this->assertStringNotContainsString('id="family-addHeadQr"', $html);
    }
}
