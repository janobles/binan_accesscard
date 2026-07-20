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
        $this->assertIsArray((new AidStatsModel())->byBarangay());
    }

    public function testByServiceReturnsArray(): void
    {
        $this->assertIsArray((new AidStatsModel())->byAidType());
    }

    public function testMethodsAcceptNullBatchIdWithoutError(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->byBarangay(null));
        $this->assertIsArray($m->byAidType(null));
    }

    public function testMethodsAcceptBatchIdWithoutError(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->receivedVsNot(3));
        $this->assertIsArray($m->byBarangay(3));
        $this->assertIsArray($m->byAidType(3));
    }

    public function testPerScannerReturnsArrayAndRejectsBadBatch(): void
    {
        $m = new AidStatsModel();
        $this->assertIsArray($m->perScanner(1));
        $this->assertSame([], $m->perScanner(0));
    }
}
