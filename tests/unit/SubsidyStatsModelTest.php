<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyStatsModelTest extends CIUnitTestCase
{
    public function testReceivedVsNotReturnsExpectedKeys(): void
    {
        $out = (new SubsidyStatsModel())->receivedVsNot();
        $this->assertSame(['total', 'received', 'notReceived', 'coverage'], array_keys($out));
        foreach ($out as $v) {
            $this->assertIsInt($v);
        }
    }

    public function testByBarangayReturnsArray(): void
    {
        $this->assertIsArray((new SubsidyStatsModel())->byBarangay());
    }

    public function testByServiceReturnsArray(): void
    {
        $this->assertIsArray((new SubsidyStatsModel())->bySubsidyType());
    }

    public function testMethodsAcceptNullBatchIdWithoutError(): void
    {
        $m = new SubsidyStatsModel();
        $this->assertIsArray($m->byBarangay(null));
        $this->assertIsArray($m->bySubsidyType(null));
    }

    public function testMethodsAcceptBatchIdWithoutError(): void
    {
        $m = new SubsidyStatsModel();
        $this->assertIsArray($m->receivedVsNot(3));
        $this->assertIsArray($m->byBarangay(3));
        $this->assertIsArray($m->bySubsidyType(3));
    }

    public function testPerScannerReturnsArrayAndRejectsBadBatch(): void
    {
        $m = new SubsidyStatsModel();
        $this->assertIsArray($m->perScanner(1));
        $this->assertSame([], $m->perScanner(0));
    }
}
