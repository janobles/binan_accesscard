<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class BarangayMapViewTest extends CIUnitTestCase
{
    public function testMapPanelSitsBesideTheTable(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringContainsString('Admin/batch-barangay-map', $src);
    }

    public function testMapUsesBootstrapPopoverNotAModal(): void
    {
        $js = file_get_contents(FCPATH . 'assets/js/dashboard/barangay-map.js');
        $this->assertStringContainsString('Popover', $js);
        $this->assertStringNotContainsString('Modal', $js);
    }

    public function testMapListensForTheDaySelection(): void
    {
        $js = file_get_contents(FCPATH . 'assets/js/dashboard/barangay-map.js');
        $this->assertStringContainsString('rollout:day', $js);
    }

    public function testMapHighlightsTheMatchingTableRow(): void
    {
        $js = file_get_contents(FCPATH . 'assets/js/dashboard/barangay-map.js');
        $this->assertStringContainsString('data-barangay', $js);
    }
}
