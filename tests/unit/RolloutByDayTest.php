<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class RolloutByDayTest extends CIUnitTestCase
{
    /** The chart lives in the Activity card's Days view now, not the pane root. */
    public function testChartOnlyRendersForMultiDayBatches(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-activity-card.php');
        $this->assertStringContainsString('count($byDay) > 1', $src);
        $this->assertStringContainsString('chartRollout', $src);
    }

    public function testByDayReachesTheClient(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringContainsString("'byDay'", $src);
    }

    public function testStatsEndpointCarriesByDay(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Admin/ReportsController.php');
        $this->assertStringContainsString("'byDay'", $src);
    }

    public function testChartJsRepaintsTheDaySeries(): void
    {
        $src = file_get_contents(FCPATH . 'assets/js/dashboard/scanner-reports.js');
        $this->assertStringContainsString('chartRollout', $src);
        $this->assertStringContainsString('fresh.byDay', $src);
    }
}
