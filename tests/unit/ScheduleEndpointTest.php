<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * The schedule endpoints through the router: the feed's shape, the overlap
 * refusals, and the role gate that keeps a Viewer out of the write paths.
 */
final class ScheduleEndpointTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        \Config\Services::resetSingle('renderer');

        $db = db_connect();
        DumpSchema::create($db);
        $db->table('users')->insertBatch([
            ['userID' => 1, 'username' => 'boss', 'account_level' => 'administrator', 'isactive' => 'Enable', 'password' => 'x'],
            ['userID' => 3, 'username' => 'looker', 'account_level' => 'viewer', 'isactive' => 'Enable', 'password' => 'x'],
        ]);
        $db->table('subsidy')->insert(['subsidy_type_id' => 1, 'name' => 'Rice']);
        // Member 5 holds card 5, the scan row one case inserts.
        ReferentialFixture::heads($db, [5]);
        ReferentialFixture::cards($db, [5], 0);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    private function asAdmin(): array
    {
        return ['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true];
    }

    private function asViewer(): array
    {
        return ['user_id' => 3, 'username' => 'looker', 'role' => 'Viewer', 'is_logged_in' => true];
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
            'daily_start_time' => '08:00',
            'daily_end_time'   => '17:00',
            'color'            => 'blue',
        ], $overrides);
    }

    public function testSaveCreatesAScheduleAndTheFeedReturnsIt(): void
    {
        $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload());

        $result = $this->withSession($this->asAdmin())
            ->get('distribution/schedule/feed?from=2026-08-01&to=2026-08-31');

        $result->assertStatus(200);
        $events = json_decode($result->getJSON(), true);

        $this->assertCount(1, $events);
        $this->assertSame('Relief Distribution - Zone 4', $events[0]['title']);
        $this->assertSame('2026-08-20', $events[0]['start']);
        $this->assertSame('2026-08-22', $events[0]['end'], 'FullCalendar treats end as exclusive');
        $this->assertSame('Canlalay Covered Court', $events[0]['venue']);
        $this->assertSame('upcoming', $events[0]['status']);
        $this->assertTrue($events[0]['deletable'], 'no scans yet, so the plan may be removed');
    }

    public function testFeedMarksAStartedBatchWithNoScansAsDeletableButNotEditable(): void
    {
        // saveSchedule() and deleteSchedule() judge different rules: a started
        // batch with no scans may still be removed but not re-planned. The
        // feed's editable and deletable flags must not collapse into one.
        $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload());
        db_connect()->table('distribution_batch')->where('batch_id', 1)
            ->update(['started_at' => '2026-08-20 08:00:00']);

        $result = $this->withSession($this->asAdmin())
            ->get('distribution/schedule/feed?from=2026-08-01&to=2026-08-31');
        $events = json_decode($result->getJSON(), true);

        $this->assertFalse($events[0]['editable']);
        $this->assertTrue($events[0]['deletable']);
    }

    public function testOverlapIsRefusedAndNamesTheClash(): void
    {
        $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload());

        $result = $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload([
            'name'            => 'Senior Citizen Pension Q3',
            'scheduled_start' => '2026-08-21',
            'scheduled_end'   => '2026-08-22',
        ]));

        $result->assertStatus(409);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('overlap', $body['error']);
        $this->assertSame('Relief Distribution - Zone 4', $body['clash']['name']);
        $this->assertTrue($body['clash']['replaceable'], 'no scans yet, so the plan may be replaced');
    }

    public function testOverlapWithScansIsNotReplaceable(): void
    {
        $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload());
        db_connect()->table('subsidy_distribution')->insert([
            'distribution_id' => 1, 'control_no' => 5, 'memberID' => 5, 'subsidy_type_id' => 1,
            'claim_date' => '2026-08-20', 'batch_id' => 1, 'dt_created' => '2026-08-20 09:10:00',
        ]);

        $result = $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload([
            'scheduled_start' => '2026-08-21', 'scheduled_end' => '2026-08-22',
        ]));

        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['clash']['replaceable']);
    }

    public function testViewerCannotSave(): void
    {
        $result = $this->withSession($this->asViewer())->post('distribution/schedule/save', $this->payload());
        $this->assertNotSame(200, $result->response()->getStatusCode());
        $this->assertSame([], (new \App\Models\Scanner\DistributionBatchModel())->allBatches());
    }

    public function testJsSendsTheParameterNamesTheFeedReads(): void
    {
        // FullCalendar's own defaults are start/end; the calendar remaps them to
        // from/to via startParam/endParam so prev/next actually reaches the
        // month the feed reads (DistributionController::scheduleFeed()).
        $js = file_get_contents(FCPATH . 'assets/js/dashboard/schedule-calendar.js');
        $this->assertStringContainsString("startParam: 'from'", $js);
        $this->assertStringContainsString("endParam: 'to'", $js);
    }

    public function testDeleteRemovesAPlan(): void
    {
        $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload());
        $this->withSession($this->asAdmin())->post('distribution/schedule/1/delete');

        $this->assertSame([], (new \App\Models\Scanner\DistributionBatchModel())->allBatches());
    }

    public function testMissingOrMalformedDailyTimesFallBackToTheDefaultHours(): void
    {
        // The time inputs are required in the form, so this only happens on a
        // hand-made request - but appending ':00' to a blank field stored
        // 00:00:00, and a batch that closes at midnight is not what a missing
        // field means.
        $this->withSession($this->asAdmin())->post('distribution/schedule/save', $this->payload([
            'daily_start_time' => '',
            'daily_end_time'   => 'half five',
        ]));

        $row = (new \App\Models\Scanner\DistributionBatchModel())->find(1);

        $this->assertSame('08:00:00', $row['daily_start_time']);
        $this->assertSame('17:00:00', $row['daily_end_time']);
    }

    public function testDeletingANonexistentScheduleAnswers404WithNoAuditRow(): void
    {
        $result = $this->withSession($this->asAdmin())->post('distribution/schedule/999999/delete');

        $result->assertStatus(404);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('not_found', $body['error']);

        $rows = db_connect()->table('audit_trails')->get()->getResultArray();
        $this->assertSame([], $rows, 'a delete on a missing id must not write an audit row');
    }
}
