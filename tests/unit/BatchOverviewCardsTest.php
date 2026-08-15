<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class BatchOverviewCardsTest extends CIUnitTestCase
{
    private function source(): string
    {
        return file_get_contents(APPPATH . 'Views/Admin/batch-overview.php');
    }

    public function testFourCardsAreRendered(): void
    {
        $src = $this->source();
        foreach (['Eligible', 'Served', 'Peak hour', 'Scanners active'] as $label) {
            $this->assertStringContainsString($label, $src);
        }
    }

    /**
     * The day filter and the live poll both rewrite these four, from the same
     * payload, through one contract rather than a list of ids. A value without
     * its data-metric is a figure that silently stops following the day.
     */
    public function testEveryHeadlineFigureCarriesItsMetricContract(): void
    {
        $src = $this->source();
        foreach (['eligible', 'served', 'peakHour', 'scannersActive'] as $metric) {
            $this->assertStringContainsString('data-metric="' . $metric . '"', $src);
            $this->assertStringContainsString('data-metric-sub="' . $metric . '"', $src);
        }
    }

    /** The day picker is the other half of the heatmap's day selection. */
    public function testTheDayPickerIsRendered(): void
    {
        $this->assertStringContainsString('id="dayPick"', $this->source());
    }

    /**
     * scanner-reports.js writes the live poll straight into these ids. Losing
     * one turns the poll into a silent no-op on that figure.
     */
    public function testLivePollTargetsSurvive(): void
    {
        $src = $this->source();
        foreach ([
            'progressServed', 'progressEligible', 'progressCoverage',
            'remainingTileValue', 'voidedTileWrap', 'voidedTileValue',
            'coverageProgress', 'coverageProgressFill', 'lastUpdated',
        ] as $id) {
            $this->assertStringContainsString($id, $src, $id . ' is a live-poll target.');
        }
    }

    public function testOldProgressSentenceIsGone(): void
    {
        $this->assertStringNotContainsString('batch-progress-line', $this->source());
    }
}
