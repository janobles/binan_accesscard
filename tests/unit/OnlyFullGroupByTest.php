<?php

namespace Tests\Unit;

use App\Models\SearchModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * A grouped query that MySQL 8 would reject and MariaDB 10.4 accepts, because
 * ONLY_FULL_GROUP_BY is absent from its default sql_mode (and from XAMPP's).
 * Neither production nor the CI job can see the problem, so the mode is
 * switched on here for the duration of this case only. Enabling it for the
 * whole job would fail the suite over behaviour production cannot experience.
 *
 * SQLite has no equivalent mode, so this case runs on the MariaDB job only.
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

    public function testAllMembersHeadIdsSurvivesTheMode(): void
    {
        $db = db_connect();

        ReferentialFixture::heads($db, [1, 2]);

        $ids = (new SearchModel())->allMembersHeadIds('FIXTURE', [], 50, 0, 'name', 'asc');

        $this->assertSame([1, 2], $ids);
    }
}
