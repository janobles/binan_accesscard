<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The profile page is the one surface for an existing record: it displays and edits.
 * Fields are always form controls with one Save at the bottom, because a read-to-edit
 * toggle would mean two render paths for every field on a page only reachable by
 * someone who came to change something.
 */
final class FamilyProfilePageTest extends CIUnitTestCase
{
    private function render(bool $readOnly = false): string
    {
        helper(['ui', 'family_modal']);

        return view('Family/profile', [
            'head' => ['memberID' => 7, 'lastname' => 'DELA CRUZ', 'firstname' => 'JUAN'],
            'members' => [
                ['memberID' => 8, 'lastname' => 'DELA CRUZ', 'firstname' => 'MARIA', 'relationship' => 'Asawa'],
            ],
            'controlNumber' => 142,
            'readOnly' => $readOnly,
            'sectors' => [], 'services' => [], 'categories' => [],
        ]);
    }

    public function testMembersAreNestedInsideTheHeadCard(): void
    {
        $html = $this->render();

        $headPos = strpos($html, 'data-head-card');
        $memberPos = strpos($html, 'data-member-card');
        $this->assertNotFalse($headPos);
        $this->assertNotFalse($memberPos);
        $this->assertLessThan($memberPos, $headPos, 'Members belong inside the head card.');
    }

    public function testMembersUseTheBootstrapGrid(): void
    {
        // The literal wrapper tag, not a bare 'col-md-6' substring check: that class
        // also appears inside _fields.php's person-field columns (rendered
        // unconditionally, including in the unused member-editor <template>), so a
        // bare substring match would still pass with the grid wrapper deleted.
        $this->assertStringContainsString('<div class="col-md-6" data-member-card>', $this->render());
    }

    public function testEditableRenderHasOneSaveButton(): void
    {
        $this->assertStringContainsString('data-family-save', $this->render(false));
    }

    public function testFieldsAreOneRenderPathNotATogglableDisplay(): void
    {
        // The same head_lastname input exists in both renders - `disabled` is the
        // only difference - proving there is one render path for a field, not a
        // display value plus a button that swaps in an editable one.
        preg_match('/<input[^>]*name="head_lastname"[^>]*>/', $this->render(false), $editable);
        preg_match('/<input[^>]*name="head_lastname"[^>]*>/', $this->render(true), $viewerOnly);

        $this->assertNotEmpty($editable, 'Expected a head_lastname input in the editable render.');
        $this->assertNotEmpty($viewerOnly, 'Expected the same head_lastname input in the read-only render.');
        $this->assertStringNotContainsString('disabled', $editable[0]);
        $this->assertStringContainsString('disabled', $viewerOnly[0]);
    }

    public function testViewerGetsDisabledControlsAndNoSave(): void
    {
        $html = $this->render(true);

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('data-family-save', $html);
    }

    public function testTheFormPostsToTheFlatUpdateUri(): void
    {
        // The form action is esc(..., 'attr')-encoded (site_url() output in an
        // attribute), which entity-encodes "/" and ":", so decode before matching
        // the plain URI rather than weakening the escaping to make this pass.
        $html = html_entity_decode($this->render(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('records/7/update', $html);
    }
}
