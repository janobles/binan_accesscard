<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renders components/records_toolbar with representative props and asserts the
 * DOM hooks family-datatable.js depends on. Markup details can change freely;
 * these hooks are the contract.
 */
final class RecordsToolbarViewTest extends CIUnitTestCase
{
    private function render(array $overrides = []): string
    {
        helper('ui');

        return view('components/records_toolbar', $overrides + [
            'routeBase'        => 'admin/manage-family',
            'keyword'          => '',
            'status'           => 'all',
            'sectorOptions'    => [['sectorID' => '1', 'shortcode' => 'ip', 'sector_name' => 'Indigenous People']],
            'barangayOptions'  => ['Canlalay', 'Malaban'],
            'selectedSectorIds' => [],
            'selectedBarangays' => [],
            'sectorOptionLabel' => static fn (array $s): string => 'IP - Indigenous People',
            'canEdit'          => true,
        ]);
    }

    public function testRendersJsHooks(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="familyDataTableFilters"', $html);
        $this->assertStringContainsString('data-records-database-keyword', $html);
        $this->assertStringContainsString('data-records-panel', $html);
        $this->assertStringContainsString('data-records-filter="sector"', $html);
        $this->assertStringContainsString('data-records-filter="barangay"', $html);
        $this->assertStringContainsString('data-records-narrow', $html);
        $this->assertStringContainsString('data-records-clear', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('id="familyFilterPills"', $html);
    }

    public function testViewerRoleHidesAddAndImport(): void
    {
        $html = $this->render(['canEdit' => false]);

        $this->assertStringNotContainsString('data-family-add-record', $html);
        $this->assertStringNotContainsString('Import', $html);
    }
}
