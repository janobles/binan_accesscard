<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ScannerMetrics;
use CodeIgniter\Test\CIUnitTestCase;

final class ScannerPerformanceScopeTest extends CIUnitTestCase
{
    private function source(): string
    {
        return file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
    }

    /** @return list<array{userID:int,ts:int,control_no:int}> */
    private function cadenceEvents(int $userId, array $days): array
    {
        $events = [];
        $control = 0;
        foreach ($days as $day) {
            // Five scans, 300 seconds apart: a gap well under the 900 second
            // idle threshold, so every gap counts as active time.
            for ($i = 0; $i < 5; $i++) {
                $events[] = [
                    'userID'     => $userId,
                    'ts'         => strtotime($day . ' 08:00:00') + $i * 300,
                    'control_no' => $userId * 1000 + $control++,
                ];
            }
        }

        return $events;
    }

    /**
     * The bug kioskSnapshot() used to have: pace divided a scanner's families
     * by the batch's wall clock (started_at to now), so a scanner who worked
     * two of a three-day batch's days had one idle night folded into their
     * denominator and read at a fraction of their real rate. kioskSnapshot()
     * now folds each scanner's own event stream through ScannerMetrics, which
     * derives active time from the scans themselves, so pace for a two-day
     * scanner matches an equally fast three-day scanner exactly - this test
     * pins that property by running the same fold+derive kioskSnapshot() runs,
     * since the method itself is private and DB-backed.
     */
    public function testPaceIgnoresDaysNotWorkedTheSameWayKioskSnapshotComputesIt(): void
    {
        $twoOfThreeDays = $this->cadenceEvents(7, ['2026-08-11', '2026-08-13']);
        $allThreeDays   = $this->cadenceEvents(8, ['2026-08-11', '2026-08-12', '2026-08-13']);

        $rowTwo   = ScannerMetrics::fold($twoOfThreeDays)['scanners'][0];
        $rowThree = ScannerMetrics::fold($allThreeDays)['scanners'][0];

        // derive()'s pace is typed ?float, but PHP's own / operator hands back
        // an int when both operands divide evenly, so the two are compared as
        // floats rather than with assertSame(), which would fail on that type
        // wobble alone and not on the property this test exists to pin.
        $paceTwo   = (float) ScannerMetrics::derive($rowTwo, 1)['pace'];
        $paceThree = (float) ScannerMetrics::derive($rowThree, 1)['pace'];

        $this->assertEqualsWithDelta($paceThree, $paceTwo, 0.0001);
        $this->assertEqualsWithDelta(12.0, $paceTwo, 0.0001);
    }

    /**
     * Regression guard for the wall-clock bug directly: kioskSnapshot() must
     * no longer read started_at/closed_at to build a denominator, and must
     * fold the batch's scan events through ScannerMetrics instead.
     */
    public function testKioskSnapshotNoLongerDividesByBatchWallClock(): void
    {
        $src   = $this->source();
        $start = strpos($src, 'function kioskSnapshot(');
        $this->assertNotFalse($start, 'kioskSnapshot() not found');
        $end = strpos($src, 'function ', $start + 20);
        $this->assertNotFalse($end, 'could not find the method after kioskSnapshot()');
        $body = substr($src, $start, $end - $start);

        $this->assertStringNotContainsString('started_at', $body);
        $this->assertStringNotContainsString('closed_at', $body);
        $this->assertStringContainsString('ScannerMetrics::fold', $body);
        $this->assertStringContainsString('ScannerMetrics::derive', $body);
    }

    public function testPerformanceAcceptsAScannerOverride(): void
    {
        $this->assertStringContainsString("getGet('scanner')", $this->source());
    }

    /** Only Admin and Developer may look at somebody else's numbers. */
    public function testOverrideIsRoleGated(): void
    {
        $src = $this->source();
        $this->assertStringContainsString('resolveViewedScanner', $src);
        $this->assertStringContainsString("['Admin', 'Developer']", $src);
        $this->assertStringContainsString('RoleAccess::normalizeRole', $src);
    }

    /** The target must actually be a scanner account, not any user id. */
    public function testOverrideChecksTheTargetIsAScanner(): void
    {
        $this->assertStringContainsString("'scanner'", $this->source());
    }

    /**
     * scanner/stats feeds the same page's 5-second poll. If it read the
     * session directly instead of resolveViewedScanner(), an admin looking at
     * a station's server-rendered figures would watch them get overwritten by
     * their own (usually zero) numbers a few seconds after page load - the
     * same silent wrong answer the override exists to prevent, just delayed.
     * Scoped to the stats() method body so this fails if that one call site
     * regresses, not merely if the string appears anywhere in the file.
     */
    public function testStatsPollHonoursTheSameOverrideAsThePage(): void
    {
        $src = $this->source();

        $start = strpos($src, 'function stats(');
        $this->assertNotFalse($start, 'stats() method not found');
        $end = strpos($src, 'function ', $start + 20);
        $this->assertNotFalse($end, 'could not find the method after stats()');
        $body = substr($src, $start, $end - $start);

        $this->assertStringContainsString('resolveViewedScanner()', $body);
        $this->assertStringNotContainsString("session('user_id')", $body);
    }
}
