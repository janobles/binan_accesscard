<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;

/**
 * The Schedule tab rendering for real: the tab strip carries it, the calendar
 * mount point and the form modal are on the page, and a Viewer gets neither
 * the add button nor the modal.
 */
final class ScheduleCalendarViewTest extends CIUnitTestCase
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
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    public function testScheduleTabRendersTheCalendar(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        $this->assertStringContainsString('id="scheduleCalendar"', $body);
        $this->assertStringContainsString('data-feed-url', $body);
        $this->assertStringContainsString('scheduleFormModal', $body);
    }

    public function testTabStripListsScheduleFirst(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution')
            ->getBody();

        $this->assertMatchesRegularExpression('/tab=schedule.*tab=batches.*tab=log/s', $body);
    }

    public function testViewerGetsNoWriteAffordances(): void
    {
        $body = $this->withSession(['user_id' => 3, 'username' => 'looker', 'role' => 'Viewer', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        $this->assertStringContainsString('id="scheduleCalendar"', $body);
        $this->assertStringNotContainsString('scheduleFormModal', $body);
    }

    public function testDateAndTimeInputsAreIndividuallyLabelled(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        $this->assertStringContainsString('aria-label="First day"', $body);
        $this->assertStringContainsString('aria-label="Last day"', $body);
        $this->assertStringContainsString('aria-label="Daily start time"', $body);
        $this->assertStringContainsString('aria-label="Daily end time"', $body);
    }

    public function testOldBatchCreateModalIsGone(): void
    {
        $this->assertFileDoesNotExist(APPPATH . 'Views/Admin/batch-create-modal.php');
    }
}
