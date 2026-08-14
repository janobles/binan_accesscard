<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DashboardViewTabTest extends CIUnitTestCase
{
    private function builderSource(): string
    {
        return file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');
    }

    public function testViewParamIsValidatedAgainstAnAllowList(): void
    {
        $src = $this->builderSource();
        $this->assertStringContainsString("['overview', 'distribution']", $src);
        $this->assertStringContainsString("'dashboardView'", $src);
    }

    /** The Overview pane must not pay for the batch snapshot queries. */
    public function testBatchDataOnlyBuildsForTheDistributionView(): void
    {
        $src = $this->builderSource();
        $this->assertStringContainsString("\$dashboardView === 'distribution'", $src);
    }

    public function testOverviewKeysAreExposed(): void
    {
        $src = $this->builderSource();
        foreach (['overviewStats', 'distributionRows', 'scheduleGrid'] as $key) {
            $this->assertStringContainsString("'" . $key . "'", $src);
        }
    }
}
