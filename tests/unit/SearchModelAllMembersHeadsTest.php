<?php

namespace Tests\Unit;

use App\Models\SearchModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * SearchModel::allMembersHeadIds()/countAllMembersHeads() must operate on
 * distinct households, not the members that matched a keyword: a household
 * with several matching members still counts and paginates as one entry, so
 * a page of results never splits or repeats a household.
 *
 * Runs against a throwaway in-memory SQLite `member` table built in this
 * file, never the shared MySQL database, so the assertions are deterministic
 * regardless of what family data (if any) happens to be seeded locally.
 */
final class SearchModelAllMembersHeadsTest extends CIUnitTestCase
{
    /** The connection behind the most recent seededSearchModel(), for tests that edit the fixture. */
    private ?BaseConnection $seededDb = null;

    /**
     * Two households: head 1 has three members matching "DELA" (a full
     * household match), head 4 is a single-member household that also
     * matches. Four member rows, two households.
     */
    private function seededSearchModel(): ?SearchModel
    {
        if (! extension_loaded('sqlite3')) {
            return null;
        }

        $db = db_connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBDebug' => true], false);
        $this->seededDb = $db;

        // V22 column set: no sectorID on member (it moved to member_sectors), and
        // the sector tables exist so the listing queries that read them run here
        // instead of only in the MySQL job.
        $db->query(
            'CREATE TABLE member (
                memberID INTEGER PRIMARY KEY,
                headID INTEGER,
                firstname TEXT, middlename TEXT, lastname TEXT, suffix TEXT,
                birthday TEXT, contactnumber TEXT, relationship TEXT,
                address TEXT, barangayID INTEGER, religion TEXT, job TEXT,
                dt_created TEXT, dt_updated TEXT, dt_deleted TEXT
            )'
        );
        $db->query('CREATE TABLE member_sectors (memberID INTEGER, sectorID INTEGER)');
        $db->query('CREATE TABLE sector (sectorID INTEGER PRIMARY KEY, name TEXT, shortcode TEXT, description TEXT)');
        $db->table('sector')->insert([
            'sectorID' => 1, 'name' => 'SENIOR CITIZEN', 'shortcode' => 'SC', 'description' => '',
        ]);
        $db->table('member_sectors')->insert(['memberID' => 1, 'sectorID' => 1]);

        $this->insertMember($db, 1, 1, 'JUAN', 'DELA CRUZ');
        $this->insertMember($db, 2, 1, 'PEDRO', 'DELA CRUZ');
        $this->insertMember($db, 3, 1, 'ANA', 'DELA CRUZ');
        $this->insertMember($db, 4, 4, 'ROSA', 'DELA TORRE');

        return new SearchModel($db);
    }

    private function insertMember(BaseConnection $db, int $id, int $headId, string $first, string $last): void
    {
        $db->table('member')->insert([
            'memberID' => $id, 'headID' => $headId,
            'firstname' => $first, 'middlename' => '', 'lastname' => $last, 'suffix' => '',
            'birthday' => null, 'contactnumber' => '', 'relationship' => $headId === $id ? 'Head' : 'Member',
            'address' => '', 'barangayID' => null, 'religion' => '', 'job' => '',
            'dt_created' => '2026-01-01', 'dt_updated' => '2026-01-01', 'dt_deleted' => null,
        ]);
    }

    public function testCountAllMembersHeadsCountsHouseholdsNotMembers(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        // Four member rows match "DELA", across two households: recordsFiltered
        // has to read 2, not 4.
        $this->assertSame(2, $model->countAllMembersHeads('DELA', []));
    }

    public function testAllMembersHeadIdsCollapsesAMultiMemberHouseholdToOneEntry(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        $headIds = $model->allMembersHeadIds('DELA', [], 10, 0);

        // Head 1's three matching members collapse to its one head ID.
        $this->assertCount(2, $headIds);
        $this->assertContains(1, $headIds);
        $this->assertContains(4, $headIds);
    }

    public function testNoHouseholdStraddlesTwoConsecutivePages(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        $page1 = $model->allMembersHeadIds('DELA', [], 1, 0);
        $page2 = $model->allMembersHeadIds('DELA', [], 1, 1);

        $this->assertCount(1, $page1);
        $this->assertCount(1, $page2);
        $this->assertNotSame($page1[0], $page2[0], 'The same household must not appear on both pages.');

        $union = array_merge($page1, $page2);
        sort($union);
        $this->assertSame([1, 4], $union, 'Both households together must cover the whole result set exactly once.');
    }

    public function testEmptyMatchReturnsNoHeadsWithoutError(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        // The same shape as a real "matches nothing" search: no exception, no
        // rows, so the controller's empty-array whereIn guard has real
        // input this data set can never produce a false positive on.
        $this->assertSame([], $model->allMembersHeadIds('NO_SUCH_KEYWORD_XYZ', []));
        $this->assertSame(0, $model->countAllMembersHeads('NO_SUCH_KEYWORD_XYZ', []));
    }

    public function testAMemberArchivedOnItsOwnDoesNotPutItsActiveHeadOnTheArchivedTab(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        // Member 2 is archived; its head (member 1) is not. The caller loads the
        // head row to display, so returning head 1 here would print an active
        // household on the Archived tab.
        $this->seededDb->table('member')->where('memberID', 2)->update(['dt_deleted' => '2026-02-01']);

        $this->assertSame([], $model->allMembersHeadIds('DELA', ['status' => 'archived']));
        $this->assertSame(0, $model->countAllMembersHeads('DELA', ['status' => 'archived']));
    }

    /**
     * Both listing queries used to select member.sectorID, a column V22 dropped,
     * which made every deep search and the recent-families list a SQL error
     * rather than an empty result.
     */
    public function testTheListingQueriesReadSectorsFromTheJunctionNotAMemberColumn(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        $members = $model->allMembers('DELA');
        $this->assertCount(4, $members);

        $head = array_values(array_filter(
            $members,
            static fn (array $row): bool => (int) $row['memberID'] === 1
        ))[0];
        $this->assertSame([1], $head['sector_ids']);
        $this->assertStringContainsString('SENIOR CITIZEN', $head['sector_name']);

        // families() selects the same dropped column through a different builder.
        $this->assertCount(2, $model->families('DELA'));
    }

    public function testAnArchivedFamilyStillListsOnTheArchivedTab(): void
    {
        $model = $this->seededSearchModel();

        if ($model === null) {
            $this->markTestSkipped('sqlite3 extension not available.');
        }

        // Archiving a family stamps every row including the head, which is what
        // MemberModel::archiveFamily() does.
        $this->seededDb->table('member')->where('headID', 1)->update(['dt_deleted' => '2026-02-01']);

        $this->assertSame([1], $model->allMembersHeadIds('DELA', ['status' => 'archived']));
        $this->assertSame([4], $model->allMembersHeadIds('DELA', ['status' => 'active']));
    }
}
