<?php

namespace Tests\Unit;

use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The entry page is one page, not a wizard: the control number resolves first and
 * gates the rest, and head and members are then both visible at once because the
 * officer is transcribing from one paper sheet where they both are.
 */
final class FamilyEntryPageTest extends CIUnitTestCase
{
    private function render(): string
    {
        helper(['ui', 'family_modal']);

        return view('Family/entry', [
            'head'        => [],
            'members'     => [],
            'readOnly'    => false,
            'sectors'     => [['sectorID' => 1, 'shortcode' => 'SC', 'name' => 'Senior Citizen']],
            'services'    => [['serviceID' => 1, 'shortcode' => 'FA2', 'name' => 'Burial Assistance']],
            'categories'  => [],
            'formOptions' => (new FamilyFormOptionsModel())->staticOptionLists(),
        ]);
    }

    public function testControlNumberGatesTheRestOfTheForm(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-control-number-gate', $html);
        $this->assertStringContainsString('data-entry-body', $html);
        // esc(..., 'attr') entity-encodes the slashes in the URL (finding 6), so
        // "qr-check" alone - unaffected by that encoding - is what still identifies it.
        $this->assertStringContainsString('qr-check', $html);
    }

    public function testHeadAndMembersAreBothPresentOnOnePage(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="section-head"', $html);
        $this->assertStringContainsString('id="section-members"', $html);
        $this->assertStringNotContainsString('tab-pane', $html,
            'A tab split would hide the head while members are entered.');
    }

    public function testTheRailIsBootstrapScrollspyNotCustomCss(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-bs-spy="scroll"', $html);
        $this->assertStringContainsString('list-group', $html);
    }

    public function testThereIsExactlyOneSaveButton(): void
    {
        $this->assertSame(1, substr_count($this->render(), 'data-family-save'));
    }
}
