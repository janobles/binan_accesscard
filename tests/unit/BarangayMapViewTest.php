<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class BarangayMapViewTest extends CIUnitTestCase
{
    public function testMapPanelSharesTheCardWithTheTable(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringContainsString('Admin/batch-barangay-map', $src);
        $this->assertStringContainsString('data-strip-pane="map"', $src);
        $this->assertStringContainsString('data-strip-pane="table"', $src);
    }

    /**
     * The map used to be hidden below the large breakpoint. Behind a Map/Table
     * strip it does not need hiding: nobody is made to scroll past it to reach
     * the figures, so a phone reader who wants the pattern can ask for it.
     */
    public function testMapIsNoLongerHiddenBelowTheLargeBreakpoint(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringNotContainsString('d-none d-lg-block', $src);
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
