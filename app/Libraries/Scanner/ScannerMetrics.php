<?php

namespace App\Libraries\Scanner;

/**
 * Turns a batch's scan event stream into per-scanner performance figures and
 * the day-by-hour grid behind the peak-hours heatmap. No database and no
 * framework calls, matching the scanner module's no-DB test posture, so every
 * figure here is unit-testable without a fixture.
 *
 * One source per view is the point. The station modal, the kiosk performance
 * page and the PDF all read this fold, so the three cannot disagree about what
 * a scanner's pace was. Deriving the heatmap here too, rather than from a
 * second GROUP BY, keeps a family scanned at two stations in one hour counted
 * once instead of twice.
 *
 * "Families" is distinct control numbers and "handouts" is rows, the same
 * definitions the grouped SQL query used before this fold replaced it.
 */
final class ScannerMetrics
{
    /**
     * A gap longer than this is somebody standing at an empty queue, not work.
     * It is the denominator's whole reason for existing: the figure this
     * replaced divided by the batch's wall clock, so a three-day batch counted
     * two nights as scanning time and reported every station at a quarter of
     * its real pace.
     */
    public const IDLE_GAP_SECONDS = 900;

    /**
     * @param list<array{userID:int,ts:int,control_no:int}> $events sorted by userID then ts
     * @return array{scanners:list<array>,total:array,byDayHour:array<string,array<int,int>>,days:list<string>}
     */
    public static function fold(array $events, int $idleGapSeconds = self::IDLE_GAP_SECONDS): array
    {
        $byScanner = [];
        $dayHour   = [];
        $totalCtl  = [];
        $handouts  = 0;

        foreach ($events as $event) {
            $userId    = (int) $event['userID'];
            $timestamp = (int) $event['ts'];
            $control   = (int) $event['control_no'];

            $byScanner[$userId] ??= self::emptyRow($userId);
            $row = &$byScanner[$userId];

            $row['handouts']++;
            $row['controls'][$control] = true;

            // The previous timestamp is still in lastTs at this point, so the
            // gap is measured before the assignment below overwrites it.
            if ($row['lastTs'] !== null) {
                $gap = $timestamp - $row['lastTs'];
                $row['gaps'][] = $gap;
                $row['longestGapSeconds'] = max($row['longestGapSeconds'], $gap);
                if ($gap <= $idleGapSeconds) {
                    $row['activeSeconds'] += $gap;
                    $row['activeGapCount']++;
                }
            }
            $row['firstTs'] = $row['firstTs'] === null ? $timestamp : min($row['firstTs'], $timestamp);
            $row['lastTs']  = $row['lastTs'] === null ? $timestamp : max($row['lastTs'], $timestamp);
            $handouts++;
            $totalCtl[$control] = true;

            $hour = (int) date('G', $timestamp);
            $day  = date('Y-m-d', $timestamp);
            $row['byHour'][$hour] = ($row['byHour'][$hour] ?? 0) + 1;

            // Distinct control numbers per cell, so the same family scanned
            // twice in one hour is one family in the heatmap.
            $dayHour[$day][$hour][$control] = true;

            unset($row);
        }

        // The total is aggregated first, while the raw accumulators still carry
        // their gap lists and hour buckets; finishRow() consumes them.
        $total = self::aggregate($byScanner, count($totalCtl), $handouts, $idleGapSeconds);

        $scanners = [];
        foreach ($byScanner as $row) {
            $scanners[] = self::finishRow($row, $idleGapSeconds);
        }

        $days = array_keys($dayHour);
        sort($days);

        $grid = [];
        foreach ($dayHour as $day => $hours) {
            ksort($hours);
            foreach ($hours as $hour => $controls) {
                $grid[$day][$hour] = count($controls);
            }
        }
        ksort($grid);

        return [
            'scanners'  => $scanners,
            'total'     => $total,
            'byDayHour' => $grid,
            'days'      => $days,
        ];
    }

    /**
     * The same fold, partitioned by calendar day first. Each day's figures come
     * from the same fold() the batch total does, so a day's pace and the
     * batch's pace can never disagree about what pace means.
     *
     * Partitioning before folding, rather than folding once and slicing the
     * result, is what keeps a gap from ever spanning midnight: an overnight gap
     * would exceed the idle threshold and be discarded anyway, so making the
     * boundary explicit here is the correct reading, not just a convenient one.
     *
     * $events is already sorted by userID then ts (scanEvents()'s contract), and
     * splitting it into day buckets in that same order preserves that ordering
     * inside each bucket, so every day's fold sees its events in the order
     * fold() requires without a re-sort.
     *
     * @param list<array{userID:int,ts:int,control_no:int}> $events sorted by userID then ts
     * @return array<string,array{scanners:list<array>,total:array,byDayHour:array<string,array<int,int>>,days:list<string>}>
     */
    public static function foldByDay(array $events, int $idleGapSeconds = self::IDLE_GAP_SECONDS): array
    {
        $byDay = [];
        foreach ($events as $event) {
            $day = date('Y-m-d', (int) $event['ts']);
            $byDay[$day][] = $event;
        }
        ksort($byDay);

        $out = [];
        foreach ($byDay as $day => $dayEvents) {
            $out[$day] = self::fold($dayEvents, $idleGapSeconds);
        }

        return $out;
    }

    /**
     * The TOTAL row, aggregated from the per-scanner accumulators rather than
     * walked separately. Deriving it from the same rows is what stops a total
     * being a differently computed number that happens to sit at the bottom of
     * the table, and it is why families comes in as a distinct control count
     * across every scanner: a family served at two stations is one family to
     * the batch even though it is one to each of them.
     *
     * Active time and the gap pool are summed across stations, so TOTAL pace
     * reads as the batch's combined rate over the time its stations were
     * actually working. On station spans first scan anywhere to last scan
     * anywhere.
     *
     * @param array<int,array<string,mixed>> $byScanner raw accumulators, pre-finishRow
     * @return array<string,mixed>
     */
    private static function aggregate(array $byScanner, int $families, int $handouts, int $idleGapSeconds): array
    {
        $total = self::emptyRow(0);
        $total['handouts'] = $handouts;

        foreach ($byScanner as $row) {
            $total['activeSeconds'] += (int) $row['activeSeconds'];
            $total['activeGapCount'] += (int) $row['activeGapCount'];
            $total['longestGapSeconds'] = max($total['longestGapSeconds'], (int) $row['longestGapSeconds']);
            $total['gaps'] = array_merge($total['gaps'], $row['gaps']);

            foreach ($row['byHour'] as $hour => $count) {
                $total['byHour'][$hour] = ($total['byHour'][$hour] ?? 0) + $count;
            }

            if ($row['firstTs'] !== null) {
                $total['firstTs'] = $total['firstTs'] === null
                    ? (int) $row['firstTs']
                    : min((int) $total['firstTs'], (int) $row['firstTs']);
            }
            if ($row['lastTs'] !== null) {
                $total['lastTs'] = max((int) $total['lastTs'], (int) $row['lastTs']);
            }
        }

        $total = self::finishRow($total, $idleGapSeconds);
        // finishRow() counts the row's own control set, which the total does not
        // keep: holding every control number twice to recount them here would
        // double the fold's memory on a citywide batch for a number the caller
        // already has.
        $total['families'] = $families;

        return $total;
    }

    /** @return array<string,mixed> */
    private static function emptyRow(int $userId): array
    {
        return [
            'userID'            => $userId,
            'handouts'          => 0,
            'controls'          => [],
            'byHour'            => [],
            // Raw gap list, filtered to non-idle gaps and consumed by
            // finishRow(), which reduces it to medianGapSeconds and drops it.
            'gaps'              => [],
            'activeSeconds'     => 0,
            // Count of the gaps folded into activeSeconds, kept separately so
            // pace can be a rate over those transitions rather than over the
            // family count, which includes the arrival with no gap before it.
            'activeGapCount'    => 0,
            'medianGapSeconds'  => null,
            'firstTs'           => null,
            'lastTs'            => null,
            'longestGapSeconds' => 0,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function finishRow(array $row, int $idleGapSeconds): array
    {
        $row['families'] = count($row['controls']);
        unset($row['controls']);
        ksort($row['byHour']);

        $row['medianGapSeconds'] = self::median(array_values(array_filter(
            $row['gaps'],
            static fn (int $gap): bool => $gap <= $idleGapSeconds
        )));
        unset($row['gaps']);

        return $row;
    }

    /**
     * Middle value of an ordered list, the mean of the middle pair when the
     * count is even. Null for an empty list, which is a scanner with a single
     * scan: there is no gap, so there is nothing to report, and printing a
     * zero there would read as an instantaneous handout.
     *
     * @param list<int> $values
     */
    private static function median(array $values): ?int
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }

        sort($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /**
     * The figures that need a denominator outside the row itself, kept separate
     * from fold() so the fold stays a pure accumulation and this stays pure
     * arithmetic over one row.
     *
     * pace is families per active hour, not per elapsed hour. A station that
     * served fifty families in an hour of scanning and then waited three hours
     * for the next barangay to arrive was working at fifty an hour, and the
     * waiting is reported separately as idle rather than folded into the rate.
     *
     * The rate is counted in transitions, not families: a scanner's first
     * family arrives with no gap behind it, so counting it as a unit of rate
     * would understate the pace by folding in an arrival that consumed no
     * active time.
     *
     * @param array<string,mixed> $row a row from fold()
     * @return array{pace:?float,typicalSeconds:?int,share:float,onStationSeconds:int,idleSeconds:int,bestHour:?int,bestHourFamilies:int}
     */
    public static function derive(array $row, int $totalFamilies): array
    {
        $active         = (int) $row['activeSeconds'];
        $activeGapCount = (int) $row['activeGapCount'];
        $families       = (int) $row['families'];

        $onStation = $row['firstTs'] === null || $row['lastTs'] === null
            ? 0
            : (int) $row['lastTs'] - (int) $row['firstTs'];

        $bestHour  = null;
        $bestCount = 0;
        foreach (($row['byHour'] ?? []) as $hour => $count) {
            if ($count > $bestCount) {
                $bestCount = (int) $count;
                $bestHour  = (int) $hour;
            }
        }

        return [
            'pace'             => $activeGapCount > 0 ? $activeGapCount / ($active / 3600) : null,
            'typicalSeconds'   => $row['medianGapSeconds'],
            'share'            => $totalFamilies > 0 ? $families / $totalFamilies : 0.0,
            'onStationSeconds' => $onStation,
            'idleSeconds'      => max(0, $onStation - $active),
            'bestHour'         => $bestHour,
            'bestHourFamilies' => $bestCount,
        ];
    }

    /**
     * The grid the heatmap table renders. Hours span the batch's declared daily
     * window, widened to include any hour that actually saw a scan: the window
     * decides what counts as closed, never whether real work is shown.
     *
     * @param array{byDayHour:array<string,array<int,int>>,days:list<string>} $fold
     * @param string|null $dailyStart HH:MM:SS from distribution_batch, null when unset
     * @param string|null $dailyEnd HH:MM:SS from distribution_batch, null when unset
     * @return array{days:list<string>,hours:list<int>,cells:array<string,array<int,array{families:int,state:string}>>,max:int}
     */
    public static function heatmap(array $fold, ?string $dailyStart, ?string $dailyEnd): array
    {
        $days = $fold['days'];
        if ($days === []) {
            return ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0];
        }

        $openFrom = self::hourOf($dailyStart);
        $openTo   = self::hourOf($dailyEnd);

        $hours = [];
        if ($openFrom !== null && $openTo !== null && $openFrom < $openTo) {
            for ($hour = $openFrom; $hour < $openTo; $hour++) {
                $hours[$hour] = true;
            }
        }
        foreach ($fold['byDayHour'] as $dayHours) {
            foreach (array_keys($dayHours) as $hour) {
                $hours[(int) $hour] = true;
            }
        }
        $hourList = array_keys($hours);
        sort($hourList);

        $cells = [];
        $max   = 0;
        foreach ($days as $day) {
            foreach ($hourList as $hour) {
                $families = (int) ($fold['byDayHour'][$day][$hour] ?? 0);
                $max      = max($max, $families);

                if ($families > 0) {
                    $state = 'served';
                } elseif ($openFrom === null || $openTo === null || $openFrom >= $openTo) {
                    // No usable window means no evidence the station was shut, so no
                    // hour can be called closed.
                    $state = 'empty';
                } else {
                    $state = $hour >= $openFrom && $hour < $openTo ? 'empty' : 'closed';
                }

                $cells[$day][$hour] = ['families' => $families, 'state' => $state];
            }
        }

        return ['days' => $days, 'hours' => $hourList, 'cells' => $cells, 'max' => $max];
    }

    /** Hour component of an HH:MM:SS column value, null when it is not one. */
    private static function hourOf(?string $clockTime): ?int
    {
        if ($clockTime === null || $clockTime === '') {
            return null;
        }

        $parts = explode(':', $clockTime);

        return is_numeric($parts[0]) ? (int) $parts[0] : null;
    }
}
