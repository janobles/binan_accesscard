<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Guards the aid-to-subsidy rename. The schema calls a subsidy type a subsidy;
 * the code must use the same word, in class names and in table names.
 */
final class SubsidyNamingTest extends CIUnitTestCase
{
    public function testSubsidyModelsExistUnderTheSchemaName(): void
    {
        $this->assertTrue(class_exists(\App\Models\Scanner\SubsidyTypeModel::class));
        $this->assertTrue(class_exists(\App\Models\Scanner\SubsidyDistributionModel::class));
        $this->assertTrue(class_exists(\App\Models\Scanner\SubsidyStatsModel::class));
    }

    public function testAidModelsAreGone(): void
    {
        $this->assertFalse(class_exists(\App\Models\Scanner\AidTypeModel::class));
        $this->assertFalse(class_exists(\App\Models\Scanner\AidDistributionModel::class));
        $this->assertFalse(class_exists(\App\Models\Scanner\AidStatsModel::class));
    }

    public function testDistributionModelPointsAtTheRenamedTable(): void
    {
        $model = new \App\Models\Scanner\SubsidyDistributionModel();
        $this->assertSame('subsidy_distribution', $model->table);
        $this->assertSame('distribution_id', $model->primaryKey);
    }

    /**
     * Reads whichever dump is current rather than naming one, so retiring a
     * superseded dump does not take this guard down with it.
     */
    public function testTheDumpUsesSubsidyNamesOnly(): void
    {
        $path = DumpSchema::dumpPath();
        $this->assertNotNull($path, 'no accesscardV*.sql in the project root');

        $dump = (string) file_get_contents($path);
        $this->assertStringContainsString('CREATE TABLE `subsidy_distribution`', $dump);
        $this->assertStringContainsString('`distribution_id`', $dump);
        $this->assertStringNotContainsString('aid_distribution', $dump);
        $this->assertStringNotContainsString('aidID', $dump);
        $this->assertStringNotContainsString('idx_db_aidtype', $dump);
    }
}
