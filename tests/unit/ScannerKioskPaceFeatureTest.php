<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * Drives scanner/stats end to end, the public path kioskSnapshot() answers
 * through. ScannerPerformanceScopeTest's fold+derive test exercises the same
 * arithmetic kioskSnapshot() now uses, but never calls the method itself, so
 * it would still pass against the wall-clock bug that arithmetic replaced.
 * This test seeds a real batch and a real scanner and reads the endpoint's
 * JSON back, so a wall-clock division reintroduced anywhere inside
 * kioskSnapshot() fails it, not just a grep of the method body.
 *
 * The seed is chosen so the two arithmetics disagree loudly. The scanner
 * works a steady cadence (one scan every 300 seconds, comfortably under
 * ScannerMetrics::IDLE_GAP_SECONDS) on the first and third day of a batch that
 * spans three days and stands closed. The correct pace, active-time only,
 * comes out to 3600 / 300 = 12 an hour on both days, regardless of how many
 * scans that cadence produced or how long the batch's calendar span was. The
 * old wall-clock arithmetic divided total families by started_at-to-closed_at
 * (about 60 hours here), which for this family count rounds to 1 an hour -
 * a twelvefold difference, not a rounding wobble either arithmetic could
 * produce by accident.
 */
final class ScannerKioskPaceFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const BATCH_ID    = 9;
    private const SCANNER_ID  = 5;
    private const SCANS_PER_DAY = 16;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();

        $db = db_connect();
        DumpSchema::create($db);

        $db->table('users')->insert([
            'userID' => self::SCANNER_ID, 'username' => 'scanner1',
            'account_level' => 'scanner', 'isactive' => 'Enable', 'password' => 'x',
        ]);

        // A three-day span, closed, so the old wall-clock arithmetic (which
        // divided by started_at to closed_at) has a fixed, computable
        // denominator rather than one that depends on when the test runs.
        $db->table('distribution_batch')->insert([
            'batch_id'        => self::BATCH_ID,
            'name'            => 'August rice',
            'subsidy_type_id' => 1,
            'started_at'      => '2026-08-01 08:00:00',
            'closed_at'       => '2026-08-03 20:00:00',
            'eligible_count'  => self::SCANS_PER_DAY * 2,
        ]);

        // One head per scan across both active days, so each scan is a
        // distinct family (control number) and "families" is not undercounted
        // by a repeat scan of the same head.
        $headIds = range(1, self::SCANS_PER_DAY * 2);
        ReferentialFixture::claimParents($db, $headIds);

        $roster = array_map(
            static fn (int $headId): array => ['batch_id' => self::BATCH_ID, 'headID' => $headId],
            $headIds
        );
        $db->table('batch_eligibility')->insertBatch($roster);

        $rows  = [];
        $headId = 1;
        foreach (['2026-08-01', '2026-08-03'] as $day) {
            for ($i = 0; $i < self::SCANS_PER_DAY; $i++) {
                $rows[] = [
                    'control_no'      => 100 + $headId,
                    'memberID'        => $headId,
                    'subsidy_type_id' => 1,
                    'claim_date'      => $day,
                    'batch_id'        => self::BATCH_ID,
                    'userID'          => self::SCANNER_ID,
                    // 300 seconds apart: under the 900 second idle threshold,
                    // so every gap between consecutive scans counts as active.
                    'dt_created'      => date('Y-m-d H:i:s', strtotime($day . ' 08:00:00') + $i * 300),
                ];
                $headId++;
            }
        }
        $db->table('subsidy_distribution')->insertBatch($rows);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        cache()->clean();

        parent::tearDown();
    }

    /**
     * The regression this pins: pace read from the live endpoint must be the
     * cadence figure (12 an hour), not the wall-clock figure the old
     * arithmetic would have produced for this same seed (1 an hour, from
     * families / hours = 32 / 60 rounded).
     */
    public function testStatsEndpointReportsCadencePaceNotWallClockPace(): void
    {
        $result = $this->withSession([
            'is_logged_in' => true, 'role' => 'Scanner',
            'user_id' => self::SCANNER_ID, 'username' => 'scanner1',
        ])->get('scanner/stats?batch=' . self::BATCH_ID);

        $body = json_decode($result->getJSON(), true);

        $this->assertIsArray($body['metrics'] ?? null, 'metrics must be present for a scanner with scans in this batch');
        $this->assertSame(self::SCANS_PER_DAY * 2, $body['metrics']['families']);
        $this->assertEqualsWithDelta(12.0, (float) $body['metrics']['pace'], 0.0001);

        // Documents the number the old arithmetic would have answered with,
        // so a reviewer reading a future failure sees the wrong-but-plausible
        // value a reintroduced wall-clock division would produce, not just
        // that 12.0 was missed.
        $wallClockFamilies = self::SCANS_PER_DAY * 2;
        $wallClockHours    = (strtotime('2026-08-03 20:00:00') - strtotime('2026-08-01 08:00:00')) / 3600;
        $this->assertSame(1, (int) round($wallClockFamilies / $wallClockHours));
        $this->assertNotEqualsWithDelta(
            (float) round($wallClockFamilies / $wallClockHours),
            (float) $body['metrics']['pace'],
            0.0001,
            'pace must not match the wall-clock figure the old arithmetic produced'
        );
    }
}
