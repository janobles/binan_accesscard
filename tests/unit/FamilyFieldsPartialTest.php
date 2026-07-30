<?php

namespace Tests\Unit;

use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Family/_fields.php renders the option lists it is given (formOptions) rather
 * than sourcing them itself, keeps an existing record's control number postable,
 * and gives its edit-mode field ids the right prefix.
 */
final class FamilyFieldsPartialTest extends CIUnitTestCase
{
    private function render(array $head = []): string
    {
        helper('family_modal');

        return view('Family/_fields', [
            'head'        => $head,
            'members'     => [],
            'readOnly'    => false,
            'sectors'     => [],
            'services'    => [],
            'categories'  => [],
            'formOptions' => (new FamilyFormOptionsModel())->staticOptionLists(),
        ]);
    }

    public function testBarangayOptionsRenderInTheOrderFormOptionsGivesThem(): void
    {
        $html = $this->render();

        // FamilyFormOptionsModel::staticOptionLists() alphabetizes barangays, so
        // "Canlalay" sorts before "Santo Tomas (Calabuso)" - declaration order in
        // FamilyProfilingFormV2::barangays() has it the other way round, so this
        // only passes if the partial is actually rendering formOptions's order.
        $canlalayPos = strpos($html, 'Canlalay');
        $santoTomasPos = strpos($html, 'Santo Tomas');

        $this->assertNotFalse($canlalayPos, 'Expected "Canlalay" to appear in the rendered barangay options.');
        $this->assertNotFalse($santoTomasPos, 'Expected "Santo Tomas" to appear in the rendered barangay options.');
        $this->assertLessThan($santoTomasPos, $canlalayPos);
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
