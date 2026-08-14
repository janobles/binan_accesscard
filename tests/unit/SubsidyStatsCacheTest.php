<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ScannerMetrics;
use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

final class SubsidyStatsCacheTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
    }

    protected function tearDown(): void
    {
        cache()->delete(SubsidyStatsModel::cacheKey(7));
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    public function testCacheKeyIsPerBatch(): void
    {
        $this->assertSame('subsidy_batch_stats_7', SubsidyStatsModel::cacheKey(7));
    }

    public function testForgetBatchClearsTheKey(): void
    {
        cache()->save(SubsidyStatsModel::cacheKey(7), ['x' => 1], 60);
        (new SubsidyStatsModel())->forgetBatch(7);
        $this->assertNull(cache(SubsidyStatsModel::cacheKey(7)));
    }

    /**
     * A closed batch caches with ttl 0, and the key is the batch id alone, so
     * an entry that outlives the batch it describes (a database reimport that
     * reuses id 1) would otherwise be served forever. The payload carries a
     * fingerprint of the batch row and the read path compares it, so a cache
     * entry that does not carry one is ignored rather than trusted.
     */
    public function testSnapshotCacheIsFingerprinted(): void
    {
        $source = file_get_contents(APPPATH . 'Models/Scanner/SubsidyStatsModel.php');
        $body   = substr($source, strpos($source, 'function batchSnapshot'));
        $body   = substr($body, 0, strpos($body, "\n    }"));

        $this->assertStringContainsString('batchFingerprint', $body);
        $this->assertStringContainsString("\$cached['fingerprint'] ?? null) === \$fingerprint", $body);
        // An unfingerprintable batch (missing row, dead DB) must not be cached.
        $this->assertStringContainsString("\$fingerprint !== ''", $body);
    }

    /**
     * Voiding a distribution changes coverage()'s served/voided counts, so it
     * must invalidate that batch's cached snapshot the same way a new scan
     * does - otherwise a closed batch (ttl 0, cached forever) never recovers.
     * Asserted against the controller source, matching this file's existing
     * pattern for behaviour that isn't practical to exercise without a full
     * DB-backed request (see SubsidyDistributionVoidTest::testVoidIsNotADelete).
     */
    public function testVoidDistributionInvalidatesItsOwnBatchCache(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Admin/DistributionController.php');
        $body   = substr($source, strpos($source, 'function voidDistribution'));
        $body   = substr($body, 0, strpos($body, "\n    }"));

        $this->assertStringContainsString('forgetBatch', $body);
        // Must key off the voided row's own batch_id, not a freshly-looked-up
        // active batch - a void can land on a closed batch.
        $this->assertStringContainsString("row['batch_id']", $body);
    }

    /**
     * The snapshot is one cached payload and the pane reads nothing else, so a
     * key missing here is a card that renders empty with no error anywhere.
     */
    public function testSnapshotCarriesTheHeatmapScannerRowsAndDays(): void
    {
        $snapshot = model(SubsidyStatsModel::class)->batchSnapshot(0, false);

        $this->assertArrayHasKey('heatmap', $snapshot);
        $this->assertArrayHasKey('byScanner', $snapshot);
        $this->assertArrayHasKey('days', $snapshot);
    }

    /**
     * batchSnapshot(0, false) short-circuits scannerRows() to an empty list, so
     * the sort, the TOTAL row and the one-query username lookup need a real
     * fold to exercise. scannerRows() is private, so it is called through
     * reflection the way this repo's other tests reach a private method (see
     * FamilyRulesTest, DashboardScheduleCardTest).
     *
     * Family 500 is scanned by both scanner1 and the ghost userID, so the
     * TOTAL row's families (the batch's distinct control numbers) has to come
     * out lower than the sum of the per-scanner families - proving TOTAL is
     * folded, not added up from the rows above it.
     */
    public function testScannerRowsAreSortedTotalledAndFallBackToUnknown(): void
    {
        $db = db_connect();

        // Heads 500, 600, 700 give 3 distinct families in the batch.
        ReferentialFixture::claimParents($db, [500, 600, 700], 0);
        $db->table('users')->insert(['userID' => 1, 'username' => 'scanner1', 'password' => 'x']);
        $db->table('users')->insert(['userID' => 2, 'username' => 'scanner2', 'password' => 'x']);
        // userID 3 has no users row: a deleted or otherwise unresolvable account.
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 3,
        ]);
        foreach ([500, 600, 700] as $head) {
            $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => $head]);
        }

        // scanner1: families 500 and 600 (2 families).
        // scanner2: family 700 (1 family).
        // ghost userID 3 (no users row): family 500 again, overlapping scanner1.
        foreach ([
            ['userID' => 1, 'control_no' => 500, 'memberID' => 500, 'dt_created' => '2026-03-01 09:00:00'],
            ['userID' => 1, 'control_no' => 600, 'memberID' => 600, 'dt_created' => '2026-03-01 09:05:00'],
            ['userID' => 2, 'control_no' => 700, 'memberID' => 700, 'dt_created' => '2026-03-01 09:10:00'],
            ['userID' => 3, 'control_no' => 500, 'memberID' => 500, 'dt_created' => '2026-03-01 09:15:00'],
        ] as $row) {
            $db->table('subsidy_distribution')->insert([
                'control_no' => $row['control_no'], 'memberID' => $row['memberID'], 'subsidy_type_id' => 1,
                'claim_date' => '2026-03-01', 'batch_id' => 1,
                'userID' => $row['userID'], 'dt_created' => $row['dt_created'],
            ]);
        }

        $stats  = new SubsidyStatsModel();
        $fold   = ScannerMetrics::fold($stats->scanEvents(1));
        $method = new \ReflectionMethod(SubsidyStatsModel::class, 'scannerRows');
        $method->setAccessible(true);
        $rows = $method->invoke($stats, $fold);

        // 4 rows: scanner1, scanner2, the ghost userID, then TOTAL.
        $this->assertCount(4, $rows);

        $total = array_pop($rows);
        $this->assertSame('TOTAL', $total['scanner']);
        $this->assertSame(0, $total['userID']);
        // Distinct families across the batch (500, 600, 700), not
        // 2 + 1 + 1 = 4, which is what summing the rows above would give.
        $this->assertSame(3, $total['families']);

        // The remaining rows are ordered families descending.
        $families = array_column($rows, 'families');
        $sorted   = $families;
        rsort($sorted);
        $this->assertSame($sorted, $families);

        $ghost = null;
        foreach ($rows as $row) {
            if ($row['userID'] === 3) {
                $ghost = $row;
            }
        }
        $this->assertNotNull($ghost, 'The scanner with no users row must still appear.');
        $this->assertSame('Unknown', $ghost['scanner']);

        // share is families / the batch total (3), not families / itself,
        // which would read 1.0 on every row regardless of size.
        $byUserId = [];
        foreach ($rows as $row) {
            $byUserId[$row['userID']] = $row;
        }
        $this->assertEqualsWithDelta(2 / 3, $byUserId[1]['share'], 0.0001);
        $this->assertEqualsWithDelta(1 / 3, $byUserId[2]['share'], 0.0001);
        $this->assertEqualsWithDelta(1 / 3, $byUserId[3]['share'], 0.0001);
        foreach ($rows as $row) {
            $this->assertNotSame(1.0, $row['share'], 'A share of exactly 1.0 means it was divided by its own families, not the batch total.');
        }
    }
}
