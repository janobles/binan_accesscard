<?php

namespace Tests\Unit;

use App\Models\Scanner\AidTypeModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidTypeModelTest extends CIUnitTestCase
{
    public function testActiveReturnsArray(): void
    {
        // Without a DB this returns []; the assertion pins the return contract.
        $this->assertIsArray((new AidTypeModel())->active());
    }
}
