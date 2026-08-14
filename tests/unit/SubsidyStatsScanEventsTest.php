<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * scanEvents() is the single source behind every per-scanner figure and the
 * batch heatmap, so the two filters that keep the rest of the pane honest have
 * to hold here as well: a voided scan and a scan for a family outside the
 * frozen roster are both invisible. Without the batch_eligibility join the
 * Stations table would outrun the Served card above it.
 *
 * Schema comes from the dump; rows are only the ones each case asserts on.
 */
final class SubsidyStatsScanEventsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    public function testVoidedScansAreExcluded(): void
    {
        $db = db_connect();

        // Batch 1, roster head 500, one live scan and one voided scan.
        ReferentialFixture::claimParents($db, [500], 0);
        $db->table('users')->insert(['userID' => 1, 'username' => 'scanner1', 'password' => 'x']);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 1,
        ]);
        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 500]);

        $db->table('subsidy_distribution')->insert([
            'control_no' => 500, 'memberID' => 500, 'subsidy_type_id' => 1,
            'claim_date' => '2026-03-01', 'userID' => 1, 'batch_id' => 1,
            'dt_created' => '2026-03-01 09:00:00',
        ]);
        $db->table('subsidy_distribution')->insert([
            'control_no' => 500, 'memberID' => 500, 'subsidy_type_id' => 1,
            'claim_date' => '2026-03-01', 'userID' => 1, 'batch_id' => 1,
            'dt_created' => '2026-03-01 09:05:00', 'dt_voided' => '2026-03-01 09:06:00',
        ]);

        $stats  = new SubsidyStatsModel();
        $events = $stats->scanEvents(1);

        $this->assertCount(1, $events);
        $this->assertSame(500, $events[0]['control_no']);
    }

    public function testScansForFamiliesOutsideTheRosterAreExcluded(): void
    {
        $db = db_connect();

        // Batch 1, roster head 500 only, plus a scan for head 999.
        ReferentialFixture::claimParents($db, [500, 999], 0);
        $db->table('users')->insert(['userID' => 1, 'username' => 'scanner1', 'password' => 'x']);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 1,
        ]);
        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 500]);

        $db->table('subsidy_distribution')->insert([
            'control_no' => 500, 'memberID' => 500, 'subsidy_type_id' => 1,
            'claim_date' => '2026-03-01', 'userID' => 1, 'batch_id' => 1,
            'dt_created' => '2026-03-01 09:00:00',
        ]);
        // headID 999 was never granted eligibility for batch 1.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 999, 'memberID' => 999, 'subsidy_type_id' => 1,
            'claim_date' => '2026-03-01', 'userID' => 1, 'batch_id' => 1,
            'dt_created' => '2026-03-01 09:01:00',
        ]);

        $stats  = new SubsidyStatsModel();
        $events = $stats->scanEvents(1);

        $this->assertCount(1, $events, 'The off-roster scan must not appear at all.');
        foreach ($events as $event) {
            $this->assertNotSame(999, $event['control_no']);
        }
    }

    public function testEventsComeBackOrderedByScannerThenTime(): void
    {
        $db = db_connect();

        ReferentialFixture::claimParents($db, [500], 0);
        $db->table('users')->insert(['userID' => 1, 'username' => 'scanner1', 'password' => 'x']);
        $db->table('users')->insert(['userID' => 2, 'username' => 'scanner2', 'password' => 'x']);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 1,
        ]);
        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 500]);

        // Inserted out of both userID and time order, to prove the query orders
        // them rather than returning insertion order.
        foreach ([
            ['userID' => 2, 'dt_created' => '2026-03-01 09:10:00'],
            ['userID' => 1, 'dt_created' => '2026-03-01 09:05:00'],
            ['userID' => 1, 'dt_created' => '2026-03-01 09:00:00'],
            ['userID' => 2, 'dt_created' => '2026-03-01 09:20:00'],
        ] as $row) {
            $db->table('subsidy_distribution')->insert([
                'control_no' => 500, 'memberID' => 500, 'subsidy_type_id' => 1,
                'claim_date' => '2026-03-01', 'batch_id' => 1,
                'userID' => $row['userID'], 'dt_created' => $row['dt_created'],
            ]);
        }

        $events = (new SubsidyStatsModel())->scanEvents(1);

        $this->assertCount(4, $events);

        $previous = null;
        foreach ($events as $event) {
            if ($previous !== null && $previous['userID'] === $event['userID']) {
                $this->assertGreaterThanOrEqual($previous['ts'], $event['ts']);
            }
            $previous = $event;
        }

        // userID 1's rows come before userID 2's rows.
        $userIds = array_column($events, 'userID');
        $this->assertSame([1, 1, 2, 2], $userIds);
    }

    public function testAnInvalidBatchIdReturnsEmpty(): void
    {
        $this->assertSame([], (new SubsidyStatsModel())->scanEvents(0));
    }

    /**
     * Day-of-week extraction is the one place the two CI backends genuinely
     * differ, so the normalised 0 to 6 output is asserted rather than assumed,
     * and against real seeded rows rather than an empty table, which would
     * pass the same assertion vacuously.
     */
    public function testWeekdayHistogramReturnsSundayAsZeroAndExcludesVoided(): void
    {
        $db = db_connect();

        ReferentialFixture::claimParents($db, [500, 600, 700], 0);

        // Two live scans, same day and hour, different families: one bucket
        // with families = 2.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 500, 'memberID' => 500, 'subsidy_type_id' => 1,
            'claim_date' => '2026-08-10', 'dt_created' => '2026-08-10 09:15:00',
        ]);
        $db->table('subsidy_distribution')->insert([
            'control_no' => 600, 'memberID' => 600, 'subsidy_type_id' => 1,
            'claim_date' => '2026-08-10', 'dt_created' => '2026-08-10 09:47:00',
        ]);
        // A voided scan in a different hour: must never surface as a bucket.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 700, 'memberID' => 700, 'subsidy_type_id' => 1,
            'claim_date' => '2026-08-11', 'dt_created' => '2026-08-11 14:20:00',
            'dt_voided'  => '2026-08-11 14:21:00',
        ]);

        $buckets = (new SubsidyStatsModel())->weekdayHistogram();

        foreach ($buckets as $bucket) {
            $this->assertGreaterThanOrEqual(0, $bucket['dow']);
            $this->assertLessThanOrEqual(6, $bucket['dow']);
            $this->assertGreaterThanOrEqual(0, $bucket['hour']);
            $this->assertLessThanOrEqual(23, $bucket['hour']);
        }

        $expectedDow = (int) date('w', strtotime('2026-08-10 09:15:00'));

        $liveBucket = null;
        $voidedHour = null;
        foreach ($buckets as $bucket) {
            if ($bucket['dow'] === $expectedDow && $bucket['hour'] === 9) {
                $liveBucket = $bucket;
            }
            if ($bucket['hour'] === 14) {
                $voidedHour = $bucket;
            }
        }

        $this->assertNotNull($liveBucket, 'The two live scans must produce a bucket.');
        $this->assertSame(2, $liveBucket['families']);
        $this->assertNull($voidedHour, 'The voided scan must not surface as a bucket.');
    }
}
