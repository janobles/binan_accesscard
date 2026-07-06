<?php

namespace Tests\Unit;

use App\Models\Scanner\QrControlModel;
use CodeIgniter\Test\CIUnitTestCase;

final class QrControlModelTest extends CIUnitTestCase
{
    public function testRejectsNonPositiveControl(): void
    {
        $this->assertNull((new QrControlModel())->headForControl(0));
        $this->assertNull((new QrControlModel())->headForControl(-5));
    }

    public function testControlForHeadRejectsNonPositive(): void
    {
        $this->assertNull((new QrControlModel())->controlForHead(0));
        $this->assertNull((new QrControlModel())->controlForHead(-3));
    }

    public function testTakenByOtherHeadRejectsNonPositiveControl(): void
    {
        $this->assertFalse((new QrControlModel())->takenByOtherHead(0, 5));
    }

    public function testUpsertForHeadRejectsNonPositiveArgs(): void
    {
        $model = new QrControlModel();
        // Non-positive args are a no-op (mirrors assign()); must not throw.
        $model->upsertForHead(0, 5);
        $model->upsertForHead(5, 0);
        $this->assertTrue(true);
    }

    public function testUpsertForHeadThrowsWhenControlTakenByAnotherHead(): void
    {
        $model = new QrControlModel();

        if (! $model->db->tableExists('qr_control')) {
            $this->markTestSkipped('qr_control table not available in this environment.');
        }

        // Use a control number far outside the seeded/demo range and clean up
        // afterwards so this test doesn't depend on or pollute fixture data.
        $controlNo = 999999;
        $ownerHead = 999991;
        $otherHead = 999992;

        $model->where('control_no', $controlNo)->delete();
        $model->where('headID', $ownerHead)->delete();
        $model->where('headID', $otherHead)->delete();

        $model->insert(['control_no' => $controlNo, 'headID' => $ownerHead]);

        try {
            $this->expectException(\RuntimeException::class);
            $model->upsertForHead($controlNo, $otherHead);
        } finally {
            $model->where('control_no', $controlNo)->delete();
        }
    }
}
