<?php

namespace Tests\Unit;

use App\Models\SearchModel;
use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * Two grouped queries that MySQL 8 would reject and MariaDB 10.4 accepts,
 * because ONLY_FULL_GROUP_BY is absent from its default sql_mode (and from
 * XAMPP's). Neither production nor the CI job can see the problem, so the mode
 * is switched on here for the duration of these cases only. Enabling it for the
 * whole job would fail the suite over behaviour production cannot experience.
 *
 * SQLite has no equivalent mode, so these cases run on the MariaDB job only.
 */
final class OnlyFullGroupByTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();

        if (DumpSchema::isTranslatedDriver($db)) {
            $this->markTestSkipped('ONLY_FULL_GROUP_BY is a MySQL mode; this runs on the MariaDB job.');
        }

        DumpSchema::create($db);
        $db->query("SET SESSION sql_mode = CONCAT(@@sql_mode, ',ONLY_FULL_GROUP_BY')");
    }

    protected function tearDown(): void
    {
        $db = db_connect();
        $db->query("SET SESSION sql_mode = REPLACE(@@sql_mode, ',ONLY_FULL_GROUP_BY', '')");
        DumpSchema::drop($db);

        parent::tearDown();
    }

    public function testPerScannerSurvivesTheMode(): void
    {
        $db = db_connect();

        ReferentialFixture::claimParents($db, [1], 100);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 1,
        ]);
        $db->table('subsidy_distribution')->insert([
            'distribution_id' => 1, 'control_no' => 101, 'memberID' => 1,
            'subsidy_type_id' => 1, 'batch_id' => 1, 'userID' => null,
            'claim_date' => '2026-08-13 09:00:00',
        ]);

        $rows = (new SubsidyStatsModel())->perScanner(1);

        $this->assertCount(1, $rows);
        $this->assertSame('Unknown', $rows[0]['scanner'], 'a NULL userID is the developer account');
    }

    public function testAllMembersHeadIdsSurvivesTheMode(): void
    {
        $db = db_connect();

        ReferentialFixture::heads($db, [1, 2]);

        $ids = (new SearchModel())->allMembersHeadIds('FIXTURE', [], 50, 0, 'name', 'asc');

        $this->assertSame([1, 2], $ids);
    }
}
