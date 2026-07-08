<?php

namespace Tests\Scanner;

use CodeIgniter\Test\CIUnitTestCase;

class AidStatsModelTest extends CIUnitTestCase
{
    public function testReceivedVsNotTakesBatchOnly(): void
    {
        if (! extension_loaded('sqlite3')) {
            $this->markTestSkipped('sqlite3 not available');
        }
        $out = (new \App\Models\Scanner\AidStatsModel())->receivedVsNot(null);
        $this->assertArrayHasKey('coverage', $out);
        $this->assertArrayHasKey('received', $out);
    }
}
