<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyDistributionModelTest extends CIUnitTestCase
{
    public function testHistoryForNonPositiveControlIsEmpty(): void
    {
        $this->assertSame([], (new SubsidyDistributionModel())->historyFor(0));
    }

    public function testAllowedFieldsCoverInsertPayload(): void
    {
        $model  = new SubsidyDistributionModel();
        $fields = (new \ReflectionClass($model))->getProperty('allowedFields');
        $fields->setAccessible(true);
        // batch_id is part of the scan insert payload, so leaving it out of
        // allowedFields would make Model::insert() drop it silently.
        foreach (['control_no', 'memberID', 'subsidy_type_id', 'claim_date', 'userID', 'batch_id'] as $col) {
            $this->assertContains($col, $fields->getValue($model));
        }
    }

    public function testAllDistributionsReturnsArray(): void
    {
        $this->assertIsArray((new \App\Models\Scanner\SubsidyDistributionModel())->allDistributions());
    }

    public function testVoidMethodExists(): void
    {
        $this->assertTrue(method_exists(new \App\Models\Scanner\SubsidyDistributionModel(), 'void'));
    }

    public function testHasClaimsRejectsNonPositiveControl(): void
    {
        $this->assertFalse((new SubsidyDistributionModel())->hasClaims(0));
        $this->assertFalse((new SubsidyDistributionModel())->hasClaims(-1));
    }

    public function testFamiliesForUserInBatchRejectsNonPositiveIds(): void
    {
        $m = new SubsidyDistributionModel();
        $this->assertSame(0, $m->familiesForUserInBatch(0, 1));
        $this->assertSame(0, $m->familiesForUserInBatch(1, 0));
    }

    public function testLogAidAcceptsBatchIdKeyWithoutError(): void
    {
        // No DB in unit posture: invalid control_no short-circuits before insert,
        // proving the signature tolerates the new key.
        $m = new SubsidyDistributionModel();
        $this->assertSame(0, $m->logAid(['control_no' => 0, 'batch_id' => 5]));
    }

    public function testLogAidMapsBatchIdOntoTheInsertPayload(): void
    {
        // The guard test above only proves logAid() tolerates the key. This one
        // proves the key survives into what insert() is handed: a batch_id that
        // is accepted and then dropped would log a handout against no batch.
        $model = new class () extends SubsidyDistributionModel {
            /** @var array<string, mixed> */
            public array $inserted = [];

            public function insert($row = null, bool $returnID = true)
            {
                $this->inserted = (array) $row;

                return 1;
            }

            public function getInsertID(): int
            {
                return 1;
            }
        };

        $model->logAid([
            'control_no'      => 42,
            'memberID'        => 7,
            'subsidy_type_id' => 3,
            'claim_date'      => '2026-08-07 09:00:00',
            'batch_id'        => 5,
        ]);

        $this->assertSame(5, (int) ($model->inserted['batch_id'] ?? 0));
    }

    public function testLogAidStampsDtCreatedFromPhp(): void
    {
        // The batch lifecycle compares dt_created against PHP's own now(), so a
        // scan row must carry a PHP-stamped time rather than fall back to the
        // column's current_timestamp() default, which runs on MySQL's clock.
        $model = new class () extends SubsidyDistributionModel {
            /** @var array<string, mixed> */
            public array $inserted = [];

            public function insert($row = null, bool $returnID = true)
            {
                $this->inserted = (array) $row;

                return 1;
            }

            public function getInsertID(): int
            {
                return 1;
            }
        };

        $before = date('Y-m-d H:i:s');
        $model->logAid([
            'control_no'      => 42,
            'memberID'        => 7,
            'subsidy_type_id' => 3,
            'claim_date'      => '2026-08-07',
            'batch_id'        => 5,
        ]);
        $after = date('Y-m-d H:i:s');

        $this->assertArrayHasKey('dt_created', $model->inserted);
        $this->assertGreaterThanOrEqual($before, $model->inserted['dt_created']);
        $this->assertLessThanOrEqual($after, $model->inserted['dt_created']);
    }
}
