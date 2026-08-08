<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;

/**
 * The scanner's state banner.
 *
 * The people at the venue are the least technical users in the system and the
 * furthest from anyone who can help, so the screen states what is scheduled,
 * where, and what the system believes the date and time to be. That last part
 * is the only defence against a laptop with a wrong clock, since the database
 * shares the same wrong clock.
 */
final class ScannerScheduleBannerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        \Config\Services::resetSingle('renderer');

        $db = db_connect();
        DumpSchema::create($db);
        $db->table('users')->insert(['userID' => 2, 'username' => 'kiosk', 'account_level' => 'scanner', 'isactive' => 'Enable', 'password' => 'x']);
        $db->table('subsidy')->insert(['subsidy_type_id' => 1, 'name' => 'Rice']);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    private function session(): array
    {
        return ['user_id' => 2, 'username' => 'kiosk', 'role' => 'scanner', 'is_logged_in' => true];
    }

    public function testIdleBannerNamesTheNextBatch(): void
    {
        db_connect()->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Senior Citizen Pension Q3', 'venue' => 'City Hall Quadrangle',
            'subsidy_type_id' => 1,
            'scheduled_start' => date('Y-m-d', strtotime('+4 days')),
            'scheduled_end' => date('Y-m-d', strtotime('+5 days')),
            'daily_start_time' => '08:00:00', 'daily_end_time' => '16:00:00',
            'color' => 'purple', 'started_at' => null, 'closed_at' => null, 'eligible_count' => 0,
        ]);

        $body = $this->withSession($this->session())->get('scanner/scan')->getBody();

        $this->assertStringContainsString('Nothing scheduled today', $body);
        $this->assertStringContainsString('Senior Citizen Pension Q3', $body);
        $this->assertStringContainsString('City Hall Quadrangle', $body);
    }

    public function testBannerPrintsTheSystemDate(): void
    {
        $body = $this->withSession($this->session())->get('scanner/scan')->getBody();
        $this->assertStringContainsString(date('F j, Y'), $body);
    }

    /**
     * Calls the private scheduleBanner() directly rather than through
     * GET scanner/scan: that route runs BatchScheduleFilter first, which
     * would reconcile (and so auto-open) any batch whose span includes today
     * before the controller ever builds the banner, masking exactly the
     * not-yet-opened state these two cases pin.
     */
    private function banner(?array $activeBatch): array
    {
        $method = new \ReflectionMethod(\App\Controllers\Scanner\ScanController::class, 'scheduleBanner');
        $method->setAccessible(true);

        return $method->invoke(new \App\Controllers\Scanner\ScanController(), $activeBatch);
    }

    public function testIdleBannerNamesABatchScheduledForTodayThatHasNotOpenedYet(): void
    {
        // scheduledBetween() sees the row (it touches today) even though
        // reconcileSchedule() has not opened it yet. The old range started at
        // tomorrow and would miss it, claiming nothing is plotted ahead when a
        // batch is, in fact, plotted for right now.
        db_connect()->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'AICS Payout - Cluster 1', 'venue' => 'Alonte Sports Arena',
            'subsidy_type_id' => 1,
            'scheduled_start' => date('Y-m-d'), 'scheduled_end' => date('Y-m-d'),
            'daily_start_time' => '08:00:00', 'daily_end_time' => '17:00:00',
            'color' => 'green', 'started_at' => null, 'closed_at' => null, 'eligible_count' => 0,
        ]);

        $banner = $this->banner(null);

        $this->assertStringContainsString('Scheduled today, not open yet.', implode(' ', $banner['lines']));
        $this->assertStringContainsString('AICS Payout - Cluster 1', implode(' ', $banner['lines']));
        $this->assertStringContainsString('Alonte Sports Arena', implode(' ', $banner['lines']));
    }

    public function testIdleBannerWithNoVenueHasNoDoubleComma(): void
    {
        db_connect()->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'AICS Payout - Cluster 1', 'venue' => '',
            'subsidy_type_id' => 1,
            'scheduled_start' => date('Y-m-d', strtotime('+2 days')),
            'scheduled_end'   => date('Y-m-d', strtotime('+3 days')),
            'daily_start_time' => '08:00:00', 'daily_end_time' => '17:00:00',
            'color' => 'green', 'started_at' => null, 'closed_at' => null, 'eligible_count' => 0,
        ]);

        $banner = $this->banner(null);

        $this->assertStringNotContainsString('AICS Payout - Cluster 1, ,', implode(' ', $banner['lines']));
    }

    public function testOpenBannerNamesTheVenueAndTheDay(): void
    {
        db_connect()->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'AICS Payout - Cluster 1', 'venue' => 'Alonte Sports Arena',
            'subsidy_type_id' => 1,
            'scheduled_start' => date('Y-m-d'), 'scheduled_end' => date('Y-m-d'),
            'daily_start_time' => '08:00:00', 'daily_end_time' => '17:00:00',
            'color' => 'green', 'started_at' => date('Y-m-d') . ' 08:00:00',
            'closed_at' => null, 'eligible_count' => 0,
        ]);

        $body = $this->withSession($this->session())->get('scanner/scan')->getBody();

        $this->assertStringContainsString('Alonte Sports Arena', $body);
        $this->assertStringContainsString('Day 1 of 1', $body);
    }
}
