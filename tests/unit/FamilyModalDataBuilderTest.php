<?php

namespace Tests\Unit;

use App\Libraries\FamilyModalDataBuilder;
use CodeIgniter\Test\CIUnitTestCase;

final class FamilyModalDataBuilderTest extends CIUnitTestCase
{
    public function testShapeMembersAttachesSectorAndServiceIds(): void
    {
        $builder = new FamilyModalDataBuilder();
        $shaped  = $builder->shapeMembers(
            [['memberID' => 7, 'firstname' => 'Ana', 'Salary' => '1500', 'sectorID' => null]],
            [7 => [1, 2]]
        );

        $this->assertCount(1, $shaped);
        $this->assertSame('Ana', $shaped[0]['firstname']);
        $this->assertSame('1500', $shaped[0]['salary']);
        $this->assertSame(['1', '2'], $shaped[0]['service_ids']);
        $this->assertSame([], $shaped[0]['sector_ids']);
    }

    public function testServiceNameMapEmptyWhenNoAssignments(): void
    {
        $this->assertSame([], (new FamilyModalDataBuilder())->serviceNameMap([]));
    }
}
