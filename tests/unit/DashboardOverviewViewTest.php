<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DashboardOverviewViewTest extends CIUnitTestCase
{
    private function source(string $path): string
    {
        return file_get_contents(APPPATH . $path);
    }

    public function testOverviewRendersTheFourCards(): void
    {
        $src = $this->source('Views/Pages/dashboard-overview.php');
        foreach (['Families profiled', 'Distributions hosted', 'Families ever served', 'Families never served'] as $label) {
            $this->assertStringContainsString($label, $src);
        }
    }

    /** KPI tiles carry no icon, per the spec's exception to the card convention. */
    public function testKpiCardsCarryNoIcons(): void
    {
        $src = $this->source('Views/Pages/dashboard-overview.php');
        $this->assertStringNotContainsString('<i class="bi', $src);
        $this->assertStringNotContainsString('card-header', $src);
    }

    public function testDashboardRendersTheOuterTabStrip(): void
    {
        $src = $this->source('Views/Pages/dashboard.php');
        $this->assertStringContainsString("'param' => 'view'", $src);
        $this->assertStringContainsString('Overview', $src);
        $this->assertStringContainsString('Distribution', $src);
    }

    /** The old flat strip is gone; its two numbers now live on cards. */
    public function testProgramStripIsGone(): void
    {
        $src = $this->source('Views/Pages/dashboard.php');
        $this->assertStringNotContainsString('program-strip', $src);
    }

    public function testDistributionRowsLinkIntoTheDistributionTab(): void
    {
        $src = $this->source('Views/Pages/dashboard-overview.php');
        $this->assertStringContainsString('view=distribution', $src);
    }
}
