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

    public function testReplaceConfirmationIsASecondPaneOfTheSameDialog(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        // schedule-calendar.js looks these up by id and silently falls back to
        // window.confirm when they are missing, so absent markup is invisible
        // until someone hits an overlap.
        $this->assertStringContainsString('id="scheduleFormPane"', $body);
        $this->assertStringContainsString('id="scheduleConflictPane"', $body);
        $this->assertStringContainsString('id="scheduleConflictConfirm"', $body);
        $this->assertStringContainsString('id="scheduleConflictBack"', $body);
        $this->assertStringContainsString('data-conflict-message', $body);
    }

    public function testConfirmationIsNotASecondModal(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        // Bootstrap does not support two open modals: a dialog of its own here
        // would render centred over the form with no backdrop between them.
        // The pane has to sit inside #scheduleFormModal with no .modal opening
        // in between.
        $this->assertDoesNotMatchRegularExpression(
            '/id="scheduleFormModal".*class="modal fade".*id="scheduleConflictPane"/s',
            $body
        );
        $this->assertMatchesRegularExpression(
            '/id="scheduleFormModal".*id="scheduleConflictPane"/s',
            $body
        );
    }

    public function testViewerGetsNoConflictPane(): void
    {
        $body = $this->withSession(['user_id' => 3, 'username' => 'looker', 'role' => 'Viewer', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        $this->assertStringNotContainsString('scheduleConflictPane', $body);
    }

    public function testOldBatchCreateModalIsGone(): void
    {
        $this->assertFileDoesNotExist(APPPATH . 'Views/Admin/batch-create-modal.php');
    }

    public function testBatchesTabCarriesTheCloseConfirmation(): void
    {
        // batch-close-modal.js looks for #batchCloseModal and quietly falls
        // back to window.confirm when it is absent, which is how the modal
        // went unrendered by any page without anything breaking.
        db_connect()->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'AICS Payout - Cluster 1', 'venue' => 'Alonte Sports Arena',
            'subsidy_type_id' => 1,
            'scheduled_start' => date('Y-m-d'), 'scheduled_end' => date('Y-m-d'),
            'daily_start_time' => '00:00:00', 'daily_end_time' => '23:59:59',
            'color' => 'green', 'started_at' => date('Y-m-d') . ' 00:00:00',
            'closed_at' => null, 'eligible_count' => 0,
        ]);

        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution?tab=batches')
            ->getBody();

        $this->assertStringContainsString('js-batch-close-form', $body);
        $this->assertStringContainsString('id="batchCloseModal"', $body);
        $this->assertStringContainsString('js-batch-close-confirm', $body);
    }

    public function testViewerGetsNoCloseConfirmation(): void
    {
        $body = $this->withSession(['user_id' => 3, 'username' => 'looker', 'role' => 'Viewer', 'is_logged_in' => true])
            ->get('distribution?tab=batches')
            ->getBody();

        $this->assertStringNotContainsString('batchCloseModal', $body);
    }
}
