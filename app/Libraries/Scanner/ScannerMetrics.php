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
 * "Families" is distinct control numbers and "handouts" is rows, matching
 * SubsidyStatsModel::perScanner()'s SQL definitions exactly.
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
        $total = self::aggregate($byScanner, count($totalCtl), $handouts);

        $scanners = [];
        foreach ($byScanner as $row) {
            $scanners[] = self::finishRow($row);
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
    private static function aggregate(array $byScanner, int $families, int $handouts): array
    {
        $total = self::emptyRow(0);
        $total['handouts'] = $handouts;

        foreach ($byScanner as $row) {
            $total['activeSeconds'] += (int) $row['activeSeconds'];
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

        $total = self::finishRow($total);
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
            // Populated in Task 2, which is also where finishRow() consumes it.
            'gaps'              => [],
            'activeSeconds'     => 0,
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
    private static function finishRow(array $row): array
    {
        $row['families'] = count($row['controls']);
        unset($row['controls']);
        ksort($row['byHour']);

        return $row;
    }
}
