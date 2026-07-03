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
}
