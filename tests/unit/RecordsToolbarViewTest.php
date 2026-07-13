<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Renders components/toolbar the way Family/list.php drives it (client mode,
 * sector/barangay/status filter groups) and asserts the DOM hooks
 * family-datatable.js depends on. Markup details can change freely; these
 * hooks are the contract.
 */
final class RecordsToolbarViewTest extends CIUnitTestCase
{
    private function render(bool $canEdit = true): string
    {
        helper('ui');

        $actionsHtml = '';
        if ($canEdit) {
            $actionsHtml .= '<button class="' . btn('add') . '" type="button" data-family-add-record>Add</button>';
            $actionsHtml .= '<button class="' . btn('import') . '" type="button">Import</button>';
        }

        return view('components/toolbar', [
            'formId' => 'familyDataTableFilters',
            'disableGenericFilterJs' => true,
            'isClient' => true,
            'formAria' => 'Family records search and filters',
            'searchPlaceholder' => 'Search all family records...',
            'keyword' => '',
            'searchAttrs' => 'data-records-database-keyword',
            'pillsId' => 'familyFilterPills',
            'narrow' => true,
            'actionsHtml' => $actionsHtml,
            'filterGroups' => [
                [
                    'name' => 'sectorIds[]',
                    'label' => 'Sector',
                    'type' => 'checkbox',
                    'scroll' => true,
                    'attrs' => 'data-records-filter="sector"',
                    'options' => [['value' => '1', 'label' => 'IP - Indigenous People', 'pill' => 'IP', 'checked' => false]],
                ],
                [
                    'name' => 'barangays[]',
                    'label' => 'Barangay',
                    'type' => 'checkbox',
                    'scroll' => true,
                    'attrs' => 'data-records-filter="barangay"',
                    'options' => [['value' => 'Canlalay', 'label' => 'Canlalay', 'pill' => 'Canlalay', 'checked' => false]],
                ],
                [
                    'name' => 'status',
                    'label' => 'Status',
                    'type' => 'radio',
                    'options' => [
                        ['value' => 'all', 'label' => 'All', 'checked' => true, 'default' => true],
                        ['value' => 'active', 'label' => 'Active', 'pill' => 'Active', 'checked' => false],
                    ],
                ],
            ],
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
        $html = $this->render(false);

        $this->assertStringNotContainsString('data-family-add-record', $html);
        $this->assertStringNotContainsString('Import', $html);
    }
}
