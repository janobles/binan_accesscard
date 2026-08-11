<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;

/**
 * The FullCalendar bundle (~275KB) ships only to the distribution page's
 * Schedule tab, never to the rest of the dashboard. Regression cover for the
 * `layout.php` gate on `$activePage`/`$distributionTab`, which is easy to
 * undo by moving the scripts back into the shared `admin` asset context.
 */
final class ScheduleAssetScopeTest extends CIUnitTestCase
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
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    public function testDashboardOverviewExcludesTheCalendarBundle(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('dashboard')
            ->getBody();

        $this->assertStringNotContainsString('assets/fullcalendar/index.global.min.js', $body);
        $this->assertStringNotContainsString('assets/js/dashboard/schedule-calendar.js', $body);
    }

    public function testDistributionScheduleTabIncludesTheCalendarBundle(): void
    {
        $body = $this->withSession(['user_id' => 1, 'username' => 'boss', 'role' => 'Admin', 'is_logged_in' => true])
            ->get('distribution?tab=schedule')
            ->getBody();

        $this->assertStringContainsString('assets/fullcalendar/index.global.min.js', $body);
        $this->assertStringContainsString('assets/js/dashboard/schedule-calendar.js', $body);
    }
}
