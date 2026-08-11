<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Which schema path DumpSchema takes for a given connection.
 *
 * The dump is MySQL, so a MySQLi connection reads it natively and the CI job
 * imports it before the suite runs. Every other driver needs the SQLite
 * translation the helper performs. Getting this branch wrong sends SQLite DDL
 * to MariaDB, which fails loudly, or skips schema creation on SQLite, which
 * fails as a missing table.
 */
final class DumpSchemaDriverTest extends CIUnitTestCase
{
    public function testSqliteNeedsTheTranslation(): void
    {
        $db = db_connect();

        if ($db->DBDriver !== 'SQLite3') {
            $this->markTestSkipped('this case describes the SQLite connection');
        }

        $this->assertTrue(
            DumpSchema::isTranslatedDriver($db),
            'SQLite has no native reader for the MySQL dump'
        );
    }

    public function testMysqlReadsTheDumpNatively(): void
    {
        $db = db_connect();

        if ($db->DBDriver !== 'MySQLi') {
            $this->markTestSkipped('this case describes the MariaDB connection');
        }

        $this->assertFalse(
            DumpSchema::isTranslatedDriver($db),
            'the CI job imports the dump, so the translation must not run'
        );
    }

    public function testCreateLeavesEveryDumpTableEmpty(): void
    {
        $db = db_connect();
        DumpSchema::create($db);

        $this->assertSame(
            0,
            $db->table('users')->countAllResults(),
            'the imported dump seeds users, so a test starts from empty only if create() emptied it'
        );

        DumpSchema::drop($db);
    }
}
