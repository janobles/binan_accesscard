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
    private function render(array $head = [], ?array $formOptions = null): string
    {
        helper('family_modal');

        return view('Family/_fields', [
            'head'        => $head,
            'members'     => [],
            'readOnly'    => false,
            'sectors'     => [],
            'services'    => [],
            'categories'  => [],
            'formOptions' => $formOptions ?? (new FamilyFormOptionsModel())->staticOptionLists(),
        ]);
    }

    public function testBarangayOptionsRenderInTheOrderFormOptionsGivesThem(): void
    {
        // A deliberately non-alphabetical, non-model order: if the partial ever
        // re-sources the barangay list itself (instead of rendering the given
        // formOptions), FamilyFormOptionsModel's alphabetized order would put
        // "Aaa Sentinel" first and this assertion would flip.
        $html = $this->render([], array_merge(
            (new FamilyFormOptionsModel())->staticOptionLists(),
            ['barangayOptions' => ['Zzz Sentinel', 'Aaa Sentinel']]
        ));

        $zzzPos = strpos($html, 'Zzz Sentinel');
        $aaaPos = strpos($html, 'Aaa Sentinel');

        $this->assertNotFalse($zzzPos, 'Expected "Zzz Sentinel" to appear in the rendered barangay options.');
        $this->assertNotFalse($aaaPos, 'Expected "Aaa Sentinel" to appear in the rendered barangay options.');
        $this->assertLessThan($aaaPos, $zzzPos, 'Expected the given formOptions order to survive in the output.');
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
