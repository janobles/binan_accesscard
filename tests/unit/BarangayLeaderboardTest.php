<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class BarangayLeaderboardTest extends CIUnitTestCase
{
    public function testModelSortsBestCoverageFirst(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Scanner/SubsidyStatsModel.php');
        $this->assertStringContainsString(
            "\$b['coverage'] <=> \$a['coverage']",
            $src,
            'Best-first: the leaderboard reads from the top.'
        );
    }

    public function testBarangayChartIsGone(): void
    {
        $view = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringNotContainsString('chartBarangay', $view);

        $js = file_get_contents(FCPATH . 'assets/js/dashboard/scanner-reports.js');
        $this->assertStringNotContainsString('chartBarangay', $js);
    }

    /** The empty-chart branch existed only for the removed chart. */
    public function testEmptyChartBranchIsGone(): void
    {
        $view = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringNotContainsString('emptyChart', $view);
        $this->assertStringNotContainsString('no coverage to plot', $view);
    }
}
