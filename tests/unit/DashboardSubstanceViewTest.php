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
}
