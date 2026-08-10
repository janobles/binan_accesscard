<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/**
 * The application clock runs on Philippine time, not UTC.
 *
 * This is not a display preference. BatchScheduleFilter asks
 * BatchScheduleWindow whether a batch opens or closes using date('Y-m-d'),
 * so a UTC clock leaves the server a day behind between midnight and 8 AM
 * local and a batch scheduled for today refuses to open.
 */
final class AppTimezoneTest extends CIUnitTestCase
{
    public function testConfiguredTimezoneIsPhilippineTime(): void
    {
        $this->assertSame('Asia/Manila', (new App())->appTimezone);
    }

    public function testRuntimeClockFollowsTheConfiguredTimezone(): void
    {
        $this->assertSame('Asia/Manila', date_default_timezone_get());
    }
}
