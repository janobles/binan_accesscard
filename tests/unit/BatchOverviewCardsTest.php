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
        foreach (['Eligible families', 'Served', 'Remaining', 'Busiest day'] as $label) {
            $this->assertStringContainsString($label, $src);
        }
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
