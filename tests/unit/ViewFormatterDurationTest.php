<?php

namespace Tests\Unit;

use App\Libraries\ViewFormatter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * ViewFormatter::duration() renders the Stations table's three time columns
 * (on station, idle, typical time per family), which span a whole shift at one
 * end and a single handover at the other. The cases below pin the boundaries
 * between the four readings, since a value landing in the wrong band is still
 * a plausible-looking string and would not fail anywhere else.
 */
final class ViewFormatterDurationTest extends CIUnitTestCase
{
    /**
     * Two rows of one column must not spell the same unit two ways. Every band
     * that prints minutes prints them as "m", which the cases below cover at
     * each boundary; this pins the rule itself so a future band cannot
     * reintroduce "min" without failing here.
     */
    public function testMinutesHaveOneSpellingAtEveryBand(): void
    {
        foreach ([60, 76, 299, 300, 2460, 3599, 24300] as $seconds) {
            $this->assertStringNotContainsString(
                'min',
                ViewFormatter::duration($seconds),
                $seconds . ' seconds spelled minutes as "min"'
            );
        }
    }

    /** @return list<array{int, string}> */
    public static function durations(): array
    {
        return [
            'nothing measured'      => [0, '-'],
            'never negative'        => [-40, '-'],
            'seconds only'          => [45, '45 s'],
            'last second under one minute' => [59, '59 s'],
            'a flat minute'         => [60, '1 m'],
            'a service time'        => [76, '1 m 16 s'],
            'last second under five minutes' => [299, '4 m 59 s'],
            'five minutes drops the seconds' => [300, '5 m'],
            'most of an hour'       => [2460, '41 m'],
            'last second under an hour' => [3599, '59 m'],
            'a flat hour'           => [3600, '1 h'],
            'a shift'               => [24300, '6 h 45 m'],
        ];
    }

    /**
     * @dataProvider durations
     */
    public function testDurationReadsAsTheColumnIntends(int $seconds, string $expected): void
    {
        $this->assertSame($expected, ViewFormatter::duration($seconds));
    }
}
