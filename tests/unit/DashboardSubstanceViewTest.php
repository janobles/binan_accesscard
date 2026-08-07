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
     * A station square opens the modal rather than navigating into the kiosk
     * shell, so it carries the scanner id as data and no href. The role-gated
     * ?scanner= override on the scanner endpoints is what makes reading another
     * account's figures safe; the modal is the caller of it now.
     */
    public function testStationsGridOpensTheModalRatherThanTheKioskShell(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-grid.php');

        $this->assertStringContainsString('data-scanner-id=', $src);
        $this->assertStringNotContainsString('scanner/performance', $src);
        $this->assertStringNotContainsString('<a ', $src);
    }

    /**
     * scanner/stats answers Scanner, Admin and Developer only, while the
     * dashboard renders for every staff role. An Encoder or Viewer must get an
     * inert tile, not a control that 403s when they press it.
     */
    public function testStationSquareIsOnlyInteractiveForRolesThatCanReadIt(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-grid.php');

        $this->assertStringContainsString('$canDrillIn', $src);
        $this->assertStringContainsString('station-square is-static', $src);

        $overview = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertMatchesRegularExpression(
            "/\\\$canDrillInStations\s*=\s*in_array\(\\\$role \?\? '', \['Developer', 'Admin'\], true\)/",
            $overview
        );
    }

    /**
     * The live poll rebuilds the grid, so its squares have to match what the
     * server rendered. Emitting an anchor or an always-clickable button there
     * would hand a role a control the page withheld.
     */
    public function testLivePollRebuildsSquaresWithTheSameRoleGate(): void
    {
        $js = file_get_contents(FCPATH . 'assets/js/dashboard/scanner-reports.js');

        $this->assertStringContainsString("data-can-drill-in", $js);
        $this->assertStringContainsString('is-static', $js);
        $this->assertStringNotContainsString('data-performance-url', $js);
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
