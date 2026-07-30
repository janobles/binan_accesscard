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
        $this->assertStringContainsString('col-md-6', $this->render());
    }

    public function testThereIsNoReadToEditToggle(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('data-edit-toggle', $html);
        $this->assertStringContainsString('data-family-save', $html);
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
