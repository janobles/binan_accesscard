<?php

namespace Tests\Unit;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Saving a plotted schedule: the refusals that protect the one open batch
 * invariant, and the colour allowlist.
 *
 * Schema comes from the dump; rows are only the ones each case asserts on.
 */
final class ScheduleSaveTest extends CIUnitTestCase
{
    private DistributionBatchModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        DumpSchema::create($db);
        $db->table('subsidy')->insert(['subsidy_type_id' => 1, 'name' => 'Rice']);

        $this->model = new DistributionBatchModel();
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'batch_id'         => 0,
            'name'             => 'Relief Distribution - Zone 4',
            'venue'            => 'Canlalay Covered Court',
            'subsidy_type_id'  => 1,
            'scheduled_start'  => '2026-08-20',
            'scheduled_end'    => '2026-08-21',
            'daily_start_time' => '08:00:00',
            'daily_end_time'   => '17:00:00',
            'color'            => 'blue',
            'barangay_ids'     => [],
            'sector_ids'       => [],
        ], $overrides);
    }

    public function testSavesAScheduleWithoutStartingIt(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        $this->assertGreaterThan(0, $id);

        $row = $this->model->find($id);
        $this->assertNull($row['started_at'], 'a plotted batch has not started');
        $this->assertNull($row['closed_at']);
        $this->assertSame('Canlalay Covered Court', $row['venue']);
    }

    public function testRefusesABlankName(): void
    {
        $this->assertSame(0, $this->model->saveSchedule($this->payload(['name' => '  ']), 1));
    }

    public function testRefusesAnEndBeforeItsStart(): void
    {
        $this->assertSame(0, $this->model->saveSchedule(
            $this->payload(['scheduled_start' => '2026-08-21', 'scheduled_end' => '2026-08-20']),
            1
        ));
    }

    public function testUnknownColourFallsBackToGreen(): void
    {
        $id  = $this->model->saveSchedule($this->payload(['color' => 'chartreuse']), 1);
        $row = $this->model->find($id);
        $this->assertSame('green', $row['color']);
    }

    public function testOverlappingScheduleIsReported(): void
    {
        $this->model->saveSchedule($this->payload(), 1);

        $clash = $this->model->overlapping('2026-08-21', '2026-08-22');
        $this->assertIsArray($clash);
        $this->assertSame('Relief Distribution - Zone 4', $clash['name']);
    }

    public function testAdjacentDatesDoNotOverlap(): void
    {
        $this->model->saveSchedule($this->payload(), 1);
        $this->assertNull($this->model->overlapping('2026-08-22', '2026-08-23'));
    }

    public function testABatchDoesNotOverlapItself(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        $this->assertNull($this->model->overlapping('2026-08-20', '2026-08-21', $id));
    }

    public function testHasDistributionsSeesScanRows(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        $this->assertFalse($this->model->hasDistributions($id));

        db_connect()->table('subsidy_distribution')->insert([
            'distribution_id' => 1,
            'control_no'      => 5,
            'memberID'        => 5,
            'subsidy_type_id' => 1,
            'claim_date'      => '2026-08-20',
            'batch_id'        => $id,
            'dt_created'      => '2026-08-20 09:10:00',
        ]);

        $this->assertTrue($this->model->hasDistributions($id));
    }

    public function testLastScanAtIgnoresVoidedRows(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        db_connect()->table('subsidy_distribution')->insertBatch([
            ['distribution_id' => 1, 'control_no' => 5, 'memberID' => 5, 'subsidy_type_id' => 1,
                'claim_date' => '2026-08-20', 'batch_id' => $id, 'dt_created' => '2026-08-20 09:10:00', 'dt_voided' => null],
            ['distribution_id' => 2, 'control_no' => 6, 'memberID' => 6, 'subsidy_type_id' => 1,
                'claim_date' => '2026-08-20', 'batch_id' => $id, 'dt_created' => '2026-08-20 16:40:00', 'dt_voided' => '2026-08-20 17:00:00'],
        ]);

        $this->assertSame('2026-08-20 09:10:00', $this->model->lastScanAt($id));
    }

    public function testScheduledBetweenReturnsBatchesTouchingTheRange(): void
    {
        $this->model->saveSchedule($this->payload(), 1);
        $this->assertCount(1, $this->model->scheduledBetween('2026-08-01', '2026-08-31'));
        $this->assertCount(0, $this->model->scheduledBetween('2026-09-01', '2026-09-30'));
    }

    public function testDeleteRefusesWhenScansExist(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        db_connect()->table('subsidy_distribution')->insert([
            'distribution_id' => 1, 'control_no' => 5, 'memberID' => 5, 'subsidy_type_id' => 1,
            'claim_date' => '2026-08-20', 'batch_id' => $id, 'dt_created' => '2026-08-20 09:10:00',
        ]);

        $this->assertFalse($this->model->deleteSchedule($id));
        $this->assertNotNull($this->model->find($id));
    }

    public function testDeleteRemovesAPlanWithNoScans(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        $this->assertTrue($this->model->deleteSchedule($id));
        $this->assertNull($this->model->find($id));
    }

    public function testDeleteAllowsAStartedBatchWithNoScans(): void
    {
        // deleteSchedule() only tests hasDistributions(), unlike saveSchedule()'s
        // stricter started_at check: a batch plotted for today that has already
        // opened but served nobody yet may still be removed.
        $id = $this->model->saveSchedule($this->payload(), 1);
        db_connect()->table('distribution_batch')->where('batch_id', $id)
            ->update(['started_at' => '2026-08-20 08:00:00']);

        $this->assertTrue($this->model->deleteSchedule($id));
        $this->assertNull($this->model->find($id));
    }

    public function testEditIsRefusedOnceTheBatchHasStarted(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        db_connect()->table('distribution_batch')->where('batch_id', $id)
            ->update(['started_at' => '2026-08-20 08:00:00']);

        $result = $this->model->saveSchedule($this->payload(['batch_id' => $id, 'subsidy_type_id' => 1, 'name' => 'Renamed']), 1);

        $this->assertSame(0, $result);
        $this->assertSame('Relief Distribution - Zone 4', $this->model->find($id)['name'], 'the started batch is untouched');
    }

    public function testEditIsRefusedOnceTheBatchHasScans(): void
    {
        $id = $this->model->saveSchedule($this->payload(), 1);
        db_connect()->table('subsidy_distribution')->insert([
            'distribution_id' => 1, 'control_no' => 5, 'memberID' => 5, 'subsidy_type_id' => 1,
            'claim_date' => '2026-08-20', 'batch_id' => $id, 'dt_created' => '2026-08-20 09:10:00',
        ]);

        $result = $this->model->saveSchedule($this->payload(['batch_id' => $id, 'scheduled_start' => '2026-09-01', 'scheduled_end' => '2026-09-02']), 1);

        $this->assertSame(0, $result);
        $this->assertSame('2026-08-20', $this->model->find($id)['scheduled_start'], 'the scanned batch keeps its dates');
    }

    public function testSavingAgainstAMissingBatchIdIsRefused(): void
    {
        $this->assertSame(0, $this->model->saveSchedule(
            $this->payload(['batch_id' => 999999, 'barangay_ids' => [1], 'sector_ids' => [2]]),
            1
        ));

        $db = db_connect();
        $this->assertSame(0, $db->table('batch_barangay')->where('batch_id', 999999)->countAllResults());
        $this->assertSame(0, $db->table('batch_sector')->where('batch_id', 999999)->countAllResults());
    }
}
