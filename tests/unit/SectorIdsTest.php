<?php

use App\Support\SectorIds;
use App\Validation\SectorRules;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SectorIdsTest extends CIUnitTestCase
{
    public function testNormalizesFinalDatabaseJsonArrayString(): void
    {
        $this->assertSame([1, 2, 3], SectorIds::normalize('[1,2,"3",2]'));
        $this->assertSame('[1,2,3]', SectorIds::toStorage(['1', 2, '3', '2']));
        $this->assertSame('[10]', SectorIds::toStorage('10'));
        $this->assertSame('[1,6]', SectorIds::toStorage('1,6'));
    }

    public function testRejectsMalformedSectorPayloads(): void
    {
        $this->assertTrue(SectorIds::hasMalformedIds(['sector' => 1]));
        $this->assertTrue(SectorIds::hasMalformedIds('[1,"bad"]'));
        $this->assertTrue(SectorIds::hasMalformedIds('{"id":1}'));
    }

    public function testSectorValidationAcceptsOnlyRealSectorIdLists(): void
    {
        $rules = new SectorRules();
        $validation = service('validation');

        $this->assertTrue($rules->valid_sector_array('[10]'));
        $this->assertTrue($rules->valid_sector_array('10'));
        $this->assertTrue($rules->valid_sector_array(['1', '6']));
        $this->assertTrue($validation->check('10', 'valid_sector_array'));
        $this->assertFalse($rules->valid_sector_array('[]'));
        $this->assertFalse($rules->valid_sector_array('[1,"bad"]'));
        $this->assertFalse($rules->valid_sector_array('{"id":1}'));
    }

    public function testBuildsNamesFromStoredArray(): void
    {
        $names = [
            1 => 'Registered PWD in Binan',
            2 => 'Project Aruga',
        ];

        $this->assertSame('Registered PWD in Binan, Project Aruga', SectorIds::toNames('[1,2]', $names));
    }
}
