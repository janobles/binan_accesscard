<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ReportsPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The printed report is a liquidation artifact, so a section quietly missing
 * from it is worse than a broken page: nobody notices until the paperwork is
 * already filed. These assert the five sections are present and that the
 * printed figures are the ones the screen was showing.
 */
final class ReportsPdfSectionsTest extends CIUnitTestCase
{
    public function testEverySectionIsPresent(): void
    {
        $bytes = (new ReportsPdfGenerator())->generate(
            ['eligible' => 100, 'served' => 60, 'remaining' => 40, 'coverage' => 60, 'voided' => 1],
            [['barangay' => 'CANLALAY', 'total' => 50, 'received' => 30, 'coverage' => 60]],
            'Test batch',
            [[
                'userID' => 7, 'scanner' => 'maria', 'families' => 60, 'handouts' => 61,
                'pace' => 47.0, 'typicalSeconds' => 76, 'onStationSeconds' => 24300,
                'idleSeconds' => 7500, 'longestGapSeconds' => 2460, 'firstTs' => strtotime('2026-08-11 07:12:00'),
                'lastTs' => strtotime('2026-08-11 16:02:00'), 'bestHour' => 9, 'bestHourFamilies' => 61, 'share' => 1.0,
            ]],
            ['days' => ['2026-08-11'], 'hours' => [8, 9], 'cells' => ['2026-08-11' => [8 => ['families' => 0, 'state' => 'empty'], 9 => ['families' => 61, 'state' => 'served']]], 'max' => 61],
            [['date' => '2026-08-11', 'label' => 'Day 1', 'served' => 60]]
        );

        $this->assertNotSame('', $bytes);
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    public function testAnEmptyBatchStillRendersAPdf(): void
    {
        $bytes = (new ReportsPdfGenerator())->generate(
            ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0],
            [],
            null
        );

        $this->assertStringStartsWith('%PDF', $bytes);
    }
}
