<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guards the timeline axis labels. The buckets are ordered by time, but the
 * labels used to print the clock alone, so a batch running over several days
 * produced an axis reading 8:54 AM, 9:36 AM, 8:41 AM, 9:22 AM - correct data
 * under labels that look like they run backwards.
 */
final class SubsidyStatsTimelineLabelTest extends CIUnitTestCase
{
    /** A single day's scanning is the live-monitoring case; the date is noise. */
    public function testSingleDayKeepsTheBareClock(): void
    {
        $format = SubsidyStatsModel::timelineLabelFormat([
            '2026-08-04 08:15:00',
            '2026-08-04 09:30:00',
            '2026-08-04 16:45:00',
        ]);

        $this->assertSame('g:i A', $format);
    }

    /** Once the batch spans days, the clock alone stops being monotonic. */
    public function testMultiDayCarriesTheDate(): void
    {
        $format = SubsidyStatsModel::timelineLabelFormat([
            '2026-08-04 09:36:00',
            '2026-08-05 08:41:00',
            '2026-08-06 09:22:00',
        ]);

        $this->assertSame('M j, g:i A', $format);
    }

    /**
     * Two buckets minutes apart but astride midnight are still two days, and
     * an axis that read 11:52 PM then 12:04 AM with no date would be the same
     * backwards-looking axis in miniature.
     */
    public function testMidnightCrossingCountsAsTwoDays(): void
    {
        $format = SubsidyStatsModel::timelineLabelFormat([
            '2026-08-04 23:52:00',
            '2026-08-05 00:04:00',
        ]);

        $this->assertSame('M j, g:i A', $format);
    }

    public function testEmptyTimelineFallsBackToTheBareClock(): void
    {
        $this->assertSame('g:i A', SubsidyStatsModel::timelineLabelFormat([]));
    }

    /**
     * Labels are built from the same format for every bucket in a series. A
     * per-row decision would mix formats on one axis.
     */
    public function testTheFormatAppliesToEveryBucketInTheSeries(): void
    {
        $stamps = ['2026-08-04 09:36:00', '2026-08-06 09:22:00'];
        $format = SubsidyStatsModel::timelineLabelFormat($stamps);

        $labels = array_map(static fn (string $ts): string => date($format, strtotime($ts)), $stamps);

        $this->assertSame(['Aug 4, 9:36 AM', 'Aug 6, 9:22 AM'], $labels);
    }
}
