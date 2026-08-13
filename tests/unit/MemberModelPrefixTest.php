<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The importer's three existence probes against a prefixed connection.
 *
 * Each builds its condition with escaping disabled, which is the one form the
 * query builder does not send through protectIdentifiers(), so a table
 * qualifier written by hand never picks up DBPrefix. Production runs
 * unprefixed and cannot see it; a prefixed connection turns it into a missing
 * table. The same shape was a live importer bug in activePeopleByLastname().
 */
final class MemberModelPrefixTest extends CIUnitTestCase
{
    private ?BaseConnection $prefixedDb = null;

    /** A prefixed in-memory member table holding one head and one relative under it. */
    private function seededModel(): ?MemberModel
    {
        if (! extension_loaded('sqlite3')) {
            return null;
        }

        $db = db_connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => 'db_',
            'DBDebug'  => true,
        ], false);
        $this->prefixedDb = $db;

        $db->query(
            'CREATE TABLE db_member (
                memberID INTEGER PRIMARY KEY,
                headID INTEGER,
                firstname TEXT, middlename TEXT, lastname TEXT, suffix TEXT,
                birthday TEXT, sex TEXT, civilstatus TEXT, contactnumber TEXT, relationship TEXT,
                address TEXT, barangayID INTEGER, religion TEXT, job TEXT,
                dt_created TEXT, dt_updated TEXT, dt_deleted TEXT
            )'
        );

        $db->table('member')->insert([
            'memberID' => 1, 'headID' => 1,
            'firstname' => 'JUAN', 'middlename' => '', 'lastname' => 'CRUZ', 'suffix' => '',
            'birthday' => null, 'relationship' => 'Head', 'dt_deleted' => null,
        ]);
        $db->table('member')->insert([
            'memberID' => 2, 'headID' => 1,
            'firstname' => 'ANA', 'middlename' => '', 'lastname' => 'CRUZ', 'suffix' => '',
            'birthday' => null, 'relationship' => 'Daughter', 'dt_deleted' => null,
        ]);

        return new MemberModel($db);
    }

    public function testActiveHeadExistsFindsTheSeededHead(): void
    {
        $model = $this->seededModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        $this->assertTrue($model->activeHeadExists('Juan', 'Cruz', null));
        $this->assertFalse($model->activeHeadExists('Pedro', 'Cruz', null));
    }

    public function testIdentitiesForHeadsReturnsTheHeadRow(): void
    {
        $model = $this->seededModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        $identities = $model->identitiesForHeads([1, 2]);

        $this->assertArrayHasKey(1, $identities, 'memberID 1 is its own head');
        $this->assertArrayNotHasKey(2, $identities, 'memberID 2 is a relative, not a head');
        $this->assertSame('JUAN', $identities[1]['firstname']);
    }

    public function testMemberExistsUnderHeadFindsTheRelative(): void
    {
        $model = $this->seededModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        $this->assertTrue($model->memberExistsUnderHead(1, 'Ana', 'Cruz', null));
        $this->assertFalse($model->memberExistsUnderHead(1, 'Rosa', 'Cruz', null));
    }
}
