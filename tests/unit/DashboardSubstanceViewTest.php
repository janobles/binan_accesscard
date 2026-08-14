<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DashboardSubstanceViewTest extends CIUnitTestCase
{
    private function dashboardSource(): string
    {
        // Views/Pages, capital P: a lowercase path resolves on a macOS dev
        // machine and returns false on CI's case-sensitive filesystem, where
        // every assertion below would then pass against an empty string.
        $src = file_get_contents(APPPATH . 'Views/Pages/dashboard.php');
        $this->assertIsString($src, 'Views/Pages/dashboard.php could not be read');

        return $src;
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
     * A station row opens the modal rather than navigating into the kiosk
     * shell, so it carries the scanner id as data and no href. The role-gated
     * ?scanner= override on the scanner endpoints is what makes reading another
     * account's figures safe; the modal is the caller of it now.
     */
    public function testStationsTableOpensTheModalRatherThanTheKioskShell(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-table.php');

        $this->assertStringContainsString('data-scanner-id=', $src);
        $this->assertStringNotContainsString('scanner/performance', $src);
        $this->assertStringNotContainsString('<a ', $src);
    }

    /**
     * scanner/stats answers Scanner, Admin and Developer only, while the
     * dashboard renders for every staff role. An Encoder or Viewer must get an
     * inert row, not a control that 403s when they press it.
     *
     * data-scanner-id is what makes a row a control: station-modal.js matches
     * tr[data-scanner-id] and nothing else. So the assertion is that the
     * attribute is emitted once and only from inside the $canDrillIn branch.
     * Checking that the literals appear somewhere in the file would pass with
     * the gate inverted or deleted, since data-can-drill-in is written
     * unconditionally on the table itself.
     */
    public function testStationRowIsOnlyInteractiveForRolesThatCanReadIt(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-table.php');

        $this->assertSame(
            1,
            substr_count($src, 'data-scanner-id'),
            'data-scanner-id must be emitted from exactly one place, the gated branch.'
        );
        $this->assertMatchesRegularExpression(
            "/if \(\\\$canDrillIn && \(int\) \\\$row\['userID'\] > 0\) \{\s*echo ' data-scanner-id=/",
            $src,
            'The row control must be emitted only inside the $canDrillIn branch.'
        );

        // The table still declares the gate for the live poll to honour when it
        // rebuilds the rows.
        $this->assertStringContainsString('data-can-drill-in="<?= $canDrillIn ? \'1\' : \'0\' ?>"', $src);

        $overview = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertMatchesRegularExpression(
            "/\\\$canDrillInStations\s*=\s*in_array\(\\\$role \?\? '', \['Developer', 'Admin'\], true\)/",
            $overview
        );
    }

    /**
     * The squares grid the Stations table replaced is gone, along with the
     * live-poll function that painted it. A stray reference would repaint a
     * container that no longer exists.
     */
    public function testTheSquaresGridIsGone(): void
    {
        $this->assertFileDoesNotExist(APPPATH . 'Views/Admin/batch-stations-grid.php');

        $js = file_get_contents(FCPATH . 'assets/js/dashboard/scanner-reports.js');
        $this->assertStringNotContainsString('applyStations', $js);
        $this->assertStringNotContainsString('stationsGrid', $js);

        $css = file_get_contents(FCPATH . 'css/theme.css');
        $this->assertStringNotContainsString('.station-square', $css);
    }

    /**
     * The #stationsTable element must render even with zero stations, so the
     * live poll has somewhere to land an open batch's first station without a
     * page reload, and so the modal's delegated click has something to bind to.
     */
    public function testStationsTableAlwaysRenders(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-stations-table.php');
        $tablePos = strpos($src, 'id="stationsTable"');
        $emptyCheckPos = strpos($src, '$byScanner === []');

        $this->assertNotFalse($tablePos, 'stationsTable not found');
        $this->assertNotFalse($emptyCheckPos, 'empty-state check not found');
        $this->assertLessThan(
            $emptyCheckPos,
            $tablePos,
            'the stationsTable element must open before the empty-state branch, not be replaced by it'
        );
    }

    /**
     * The pane's sub-tabs are gone: Barangay, Stations and Remaining are cards
     * that all render together. A reinstated ?tab= here would put the reader
     * back to clicking between three separate subjects.
     */
    public function testTheBatchSubTabStripIsGone(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $this->assertStringNotContainsString('batchBodyTab', $src);
        $this->assertStringNotContainsString("components/page_tabs", $src);

        $builder = file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');
        $this->assertStringNotContainsString('$batchBodyTab', $builder);
    }

    /**
     * The Stations card gets its own All/Per day strip (task 8b), sharing the
     * card_tabs stripId contract the Barangay and Activity strips already
     * follow: pane ids are "<stripId>-pane-<key>" against "<stripId>-tab-<key>".
     */
    public function testStationsCardHasAnAllAndPerDayStrip(): void
    {
        $overview = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');

        $this->assertStringContainsString("'stripId' => 'stations'", $overview);
        $this->assertStringContainsString("['key' => 'all', 'label' => 'All']", $overview);
        $this->assertStringContainsString("['key' => 'day', 'label' => 'Per day']", $overview);
        $this->assertStringContainsString('id="stations-pane-all"', $overview);
        $this->assertStringContainsString('id="stations-pane-day"', $overview);
    }

    /**
     * Neither of the Per day pane's two non-table states may look like a
     * broken table: no day picked says which control to use, and a picked day
     * with no rows says that instead of rendering zero rows.
     */
    public function testStationsPerDayPaneNamesBothItsEmptyStates(): void
    {
        $overview = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');

        $this->assertStringContainsString('Use the Day picker above to choose a day.', $overview);
        $this->assertStringContainsString('No station logged a scan on', $overview);
    }

    /**
     * batch-stations-table.php renders twice on this page once a day is
     * picked (the All pane and the Per day pane). CI4 carries one view() call's
     * data into the next, so every parameter has to be passed explicitly on
     * both calls rather than relying on the partial's own defaults - the same
     * fix the Activity card's Hours/Weekdays panes already carry.
     */
    public function testBothStationsTableRendersPassEveryParameterExplicitly(): void
    {
        $overview = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
        $marker   = "view('Admin/batch-stations-table', [";

        $calls = [];
        $offset = 0;
        while (($pos = strpos($overview, $marker, $offset)) !== false) {
            $close  = strpos($overview, ']) ?>', $pos);
            $calls[] = substr($overview, $pos, $close - $pos);
            $offset  = $close + 1;
        }

        $this->assertCount(2, $calls, 'batch-stations-table.php must be rendered from exactly two call sites: All and Per day.');

        foreach ($calls as $call) {
            foreach (['byScanner', 'batchId', 'canDrillIn'] as $param) {
                $this->assertStringContainsString(
                    "'" . $param . "'",
                    $call,
                    "'" . $param . "' must be passed explicitly on every batch-stations-table.php render call."
                );
            }
        }
    }

    /**
     * SubsidyStatsModel::batchSnapshot() gains byScannerByDay alongside the
     * batch-wide byScanner, and every one of the three empty-shape literals
     * that describe an empty snapshot (ReportsController::stats(),
     * DashboardPageBuilder::emptyBatchSnapshot(), and batch-overview.php's own
     * fallback) has to carry it too, or a missing key renders a blank card
     * with no error anywhere.
     */
    public function testByScannerByDayIsInEveryEmptySnapshotLiteral(): void
    {
        $controller = file_get_contents(APPPATH . 'Controllers/Admin/ReportsController.php');
        $builder    = file_get_contents(APPPATH . 'Libraries/DashboardPageBuilder.php');
        $overview   = file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');

        $this->assertStringContainsString("'byScannerByDay'", $controller);
        $this->assertStringContainsString("'byScannerByDay' => []", $builder);
        $this->assertStringContainsString("'byScannerByDay' => []", $overview);
    }
}
