<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;

/**
 * The dashboard overview's schedule card: a month grid with a bar per batch
 * and the next batches listed underneath. Read only, so no form and no button.
 */
final class DashboardScheduleCardTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        \Config\Services::resetSingle('renderer');

        $db = db_connect();
        DumpSchema::create($db);
        $db->table('users')->insert(['userID' => 1, 'username' => 'boss', 'account_level' => 'administrator', 'isactive' => 'Enable', 'password' => 'x']);
        $db->table('subsidy')->insert(['subsidy_type_id' => 1, 'name' => 'Rice']);
        $db->table('distribution_batch')->insert([
            'batch_id'         => 1,
            'name'             => 'Senior Citizen Pension Q3',
            'venue'            => 'City Hall Quadrangle',
            'subsidy_type_id'  => 1,
            'scheduled_start'  => date('Y-m-d', strtotime('+2 days')),
            'scheduled_end'    => date('Y-m-d', strtotime('+3 days')),
            'daily_start_time' => '08:00:00',
            'daily_end_time'   => '16:00:00',
            'color'            => 'purple',
            'started_at'       => null,
            'closed_at'        => null,
            'eligible_count'   => 0,
        ]);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    public function testCardNamesTheUpcomingBatchAndItsVenue(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'administrator', 'is_logged_in' => true])
            ->get('dashboard')
            ->getBody();

        $this->assertStringContainsString('Senior Citizen Pension Q3', $body);
        $this->assertStringContainsString('City Hall Quadrangle', $body);
    }

    public function testCardIsReadOnly(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'administrator', 'is_logged_in' => true])
            ->get('dashboard')
            ->getBody();

        $this->assertStringNotContainsString('id="scheduleFormModal"', $body);
        $this->assertStringContainsString('tab=schedule', $body, 'the card links to the calendar');
    }
}
