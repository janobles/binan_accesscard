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
        $map = (new BarangayModel())->nameMap();
        // Asserted before the loop so the case still says something without a
        // database, where nameMap() legitimately comes back empty and the loop
        // never runs.
        $this->assertIsArray($map);

        foreach ($map as $id => $name) {
            $this->assertIsInt($id);
            $this->assertIsString($name);
        }
    }
}
