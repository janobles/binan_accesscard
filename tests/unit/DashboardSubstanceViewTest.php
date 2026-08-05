<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DashboardSubstanceViewTest extends CIUnitTestCase
{
    private function dashboardSource(): string
    {
        return file_get_contents(APPPATH . 'Views/pages/dashboard.php');
    }

    public function testVanityTilesAreGone(): void
    {
        $src = $this->dashboardSource();
        $this->assertStringNotContainsString('Registered Members', $src);
        $this->assertStringNotContainsString('Active Sectors', $src);
        $this->assertStringNotContainsString('Services and Programs', $src);
    }

    public function testRecentRecordsTableIsGone(): void
    {
        $this->assertStringNotContainsString('Recent Records', $this->dashboardSource());
    }

    public function testBatchBodyRendersRemainingTab(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringContainsString('Remaining', $src);
    }

    /**
     * The Stations grid links each square to that scanner's performance page.
     * This used to be forbidden, because scanner/performance read the viewer
     * from the session and an admin clicking through would silently see their
     * own numbers under a scanner's name. ScanController::performance() now
     * takes a role-gated ?scanner= override, so the link is safe and the grid
     * is the orphaned page's entry point.
     */
    public function testStationsGridLinksToScannerPerformance(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-grid.php');
        $this->assertStringContainsString('scanner/performance', $src);
        $this->assertStringContainsString('scanner=', $src);
    }

    /**
     * The #stationsGrid container must render even with zero stations, so the
     * live poll (scanner-reports.js applyStations()) has somewhere to land an
     * open batch's first station without a page reload. Fix round 1 caught
     * this rendering only a bare <p> and omitting the container entirely.
     */
    public function testStationsGridContainerAlwaysRenders(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-grid.php');
        $gridPos = strpos($src, 'id="stationsGrid"');
        $emptyCheckPos = strpos($src, '$perScanner === []');

        $this->assertNotFalse($gridPos, 'stationsGrid container not found');
        $this->assertNotFalse($emptyCheckPos, 'empty-state check not found');
        $this->assertLessThan(
            $emptyCheckPos,
            $gridPos,
            'the stationsGrid container must open before the empty-state branch, not be replaced by it'
        );
    }

    /** The dashboard's batch sub-tabs must carry ?batch= through the switch. */
    public function testBatchSubTabsCarryTheBatchSelectionThrough(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringContainsString("'queryParams'", $src);
        $this->assertStringContainsString("'batch' => \$batchId", $src);
    }
}
