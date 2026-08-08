<?php

namespace App\Libraries;

/**
 * Decides when a scheduled distribution batch opens and closes.
 *
 * Called by DistributionBatchModel::reconcileSchedule(), which is the only
 * thing that writes the answer to storage. This class touches no database and
 * no framework state so the rules can be exercised directly.
 *
 * Two ideas carry the whole thing. Dates gate: scanning is allowed on any day
 * inside the batch's span and on no other day, so staff who start early or
 * late need no override. Times advise: daily_end_time is where the closing
 * countdown starts, and every scan past it pushes that anchor forward one
 * grace step, so a distribution running late is never cut off mid queue.
 *
 * The verdict is a function of the schedule and the scan times. "Now" decides
 * only whether a transition has come due, never what gets stored, which is why
 * a reconcile that runs hours late still writes the correct closed_at.
 */
final class BatchScheduleWindow
{
    /** Minutes of quiet after the anchor before a day counts as finished. */
    public const GRACE_MINUTES = 30;

    /**
     * @param array{scheduled_start:string,scheduled_end:string,daily_end_time:string,started_at:?string,closed_at:?string} $batch
     * @param string|null $lastScanAt newest un-voided scan in this batch, 'Y-m-d H:i:s'
     * @param string      $now        'Y-m-d H:i:s'
     * @return array{action:string,closed_at:?string} action is open, close or none
     */
    public static function verdict(array $batch, ?string $lastScanAt, string $now): array
    {
        $none  = ['action' => 'none', 'closed_at' => null];
        $today = substr($now, 0, 10);

        $start = (string) ($batch['scheduled_start'] ?? '');
        $end   = (string) ($batch['scheduled_end'] ?? '');
        if ($start === '' || $end === '') {
            return $none;
        }

        $closedAt = $batch['closed_at'] ?? null;
        $inSpan   = $today >= $start && $today <= $end;

        if (! $inSpan) {
            // Before the first day there is nothing to do. After the last day
            // an open batch closes on its final scheduled day, never on today,
            // so a reconcile that runs a week late still records the truth.
            if ($today < $start || $closedAt !== null) {
                return $none;
            }

            return ['action' => 'close', 'closed_at' => self::anchor($end, $batch, $lastScanAt)];
        }

        if ($closedAt !== null) {
            // Reopen only on evidence that the close was premature: a later
            // scan, or a new day of the same batch. Without this an idle
            // evening would reopen the batch it just closed.
            $newDay   = substr($closedAt, 0, 10) < $today;
            $resumed  = $lastScanAt !== null && $lastScanAt > $closedAt;

            return $newDay || $resumed ? ['action' => 'open', 'closed_at' => null] : $none;
        }

        if (($batch['started_at'] ?? null) === null) {
            return ['action' => 'open', 'closed_at' => null];
        }

        $anchor = self::anchor($today, $batch, $lastScanAt);
        $due    = date('Y-m-d H:i:s', strtotime($anchor) + (self::GRACE_MINUTES * 60));

        return $now >= $due ? ['action' => 'close', 'closed_at' => $anchor] : $none;
    }

    /**
     * The day's closing time: daily_end_time, pushed forward in grace steps
     * until it sits past the last scan. A scan at 17:15 against a 17:00 end
     * gives 17:30; a scan at 18:40 gives 19:00.
     *
     * @param string $day 'Y-m-d'
     * @param array{daily_end_time:string} $batch
     */
    private static function anchor(string $day, array $batch, ?string $lastScanAt): string
    {
        $anchor = strtotime($day . ' ' . (string) ($batch['daily_end_time'] ?? '17:00:00'));
        $step   = self::GRACE_MINUTES * 60;

        if ($lastScanAt !== null) {
            $last = strtotime($lastScanAt);
            while ($last >= $anchor) {
                $anchor += $step;
            }
        }

        return date('Y-m-d H:i:s', $anchor);
    }
}
