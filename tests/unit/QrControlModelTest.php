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
}
