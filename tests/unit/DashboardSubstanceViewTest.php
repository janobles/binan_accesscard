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
        $src = file_get_contents(APPPATH . 'Views/Admin/dashboard-batch-body.php');
        $this->assertStringContainsString('Remaining', $src);
    }

    /**
     * scanner/performance derives its user from the session, not a query
     * param, so a link on a station's name would silently show the clicked
     * admin's own (usually zero) numbers rather than that station's. The
     * Stations tab must render the scanner name as plain text, not an <a>
     * to that route.
     */
    public function testStationsTabDoesNotLinkToScannerPerformance(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/dashboard-batch-body.php');
        $this->assertStringNotContainsString("site_url('scanner/performance')", $src);
        $this->assertStringNotContainsString('<a href=', $src);
    }

    /** The dashboard's batch sub-tabs must carry ?batch= through the switch. */
    public function testBatchSubTabsCarryTheBatchSelectionThrough(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/dashboard-batch-body.php');
        $this->assertStringContainsString("'queryParams'", $src);
        $this->assertStringContainsString("'batch' => \$batchId", $src);
    }
}
