<?php

namespace Tests\Unit;

use App\Models\Scanner\AidStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidStatsModelTest extends CIUnitTestCase
{
    public function testReceivedVsNotReturnsExpectedKeys(): void
    {
        $out = (new AidStatsModel())->receivedVsNot();
        $this->assertSame(['total', 'received', 'notReceived', 'coverage'], array_keys($out));
        foreach ($out as $v) {
            $this->assertIsInt($v);
        }
    }

    public function testByBarangayReturnsArray(): void
    {
        $this->assertIsArray((new AidStatsModel())->byBarangay('2026-01-01', '2026-01-31'));
    }

    public function testByAidTypeReturnsArray(): void
    {
        $this->assertIsArray((new AidStatsModel())->byAidType());
    }

    public function testMethodsAcceptNullRangeWithoutError(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->byBarangay(null, null));
        $this->assertIsArray($m->byAidType(null, null));
    }
}
