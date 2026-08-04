<?php

namespace Tests\Unit;

use App\Models\Lookups\BarangayModel;
use CodeIgniter\Test\CIUnitTestCase;

final class BarangayModelTest extends CIUnitTestCase
{
    public function testActiveListReturnsArray(): void
    {
        $this->assertIsArray((new BarangayModel())->activeList());
    }

    public function testNameMapKeysAreIntegers(): void
    {
        foreach ((new BarangayModel())->nameMap() as $id => $name) {
            $this->assertIsInt($id);
            $this->assertIsString($name);
        }
    }
}
