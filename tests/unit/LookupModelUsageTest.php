<?php

namespace Tests\Unit;

use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use CodeIgniter\Test\CIUnitTestCase;

final class LookupModelUsageTest extends CIUnitTestCase
{
    public function testServiceModelExposesUsageAndInsertHelpers(): void
    {
        $this->assertTrue(method_exists(ServiceModel::class, 'isInUse'));
        $this->assertTrue(method_exists(ServiceModel::class, 'insertWithNextId'));
    }

    public function testSectorModelExposesUsageHelper(): void
    {
        $this->assertTrue(method_exists(SectorModel::class, 'isInUse'));
    }

    public function testControllersNoLongerBuildRawQueries(): void
    {
        $service = file_get_contents(APPPATH . 'Controllers/Lookups/ServiceController.php');
        $sector  = file_get_contents(APPPATH . 'Controllers/Lookups/SectorController.php');
        $this->assertStringNotContainsString('->table(', $service);
        $this->assertStringNotContainsString('->table(', $sector);
        $this->assertStringNotContainsString('Database::connect', $service);
    }
}
