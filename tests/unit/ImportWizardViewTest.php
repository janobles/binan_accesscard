<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The import review renders inside the shared layout as one flat per-person table.
 * Its steps are a real sequence, so a wizard fits here where it did not on the entry
 * form: step 2 never needs step 1 visible and the server state changes between them.
 */
final class ImportWizardViewTest extends CIUnitTestCase
{
    private function render(): string
    {
        helper('ui');

        return view('Family/import-review', [
            'jobId' => 5,
            'step'  => 2,
        ]);
    }

    public function testItCarriesNoPrivateHtmlShell(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('import-review.css', $html);
        $this->assertStringNotContainsString('navbar-dark', $html);
    }

    public function testStepsUseTheSegmentedTabsComponent(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('segmented-tabs', $html);
        $this->assertStringContainsString('Upload', $html);
        $this->assertStringContainsString('Review and Fix', $html);
        $this->assertStringNotContainsString('Column Mapping', $html,
            'The workbook comes from our own template; columns are known.');
    }

    public function testTheStatCardRowAndGroupContainersAreGone(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('importReviewStats', $html);
        $this->assertStringNotContainsString('importReviewGroups', $html);
    }

    public function testTheTableIsPerPersonWithAProblemsToggle(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="importReviewTable"', $html);

        foreach (['Family', 'Role', 'Last Name', 'First Name'] as $column) {
            $this->assertStringContainsString($column, $html);
        }

        $this->assertStringContainsString('data-problems-only', $html);
        $this->assertStringContainsString('checked', $html);
    }

    public function testTheUploadStepIsAPageNotAModal(): void
    {
        helper('ui');

        $html = view('Family/import-upload', [
            'action'      => site_url('records/import'),
            'templateUrl' => site_url('records/template'),
        ]);

        $this->assertStringContainsString('segmented-tabs', $html);
        $this->assertStringContainsString('data-family-import', $html);
        $this->assertStringNotContainsString('data-bs-dismiss="modal"', $html);
    }
}
