<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Covers Fix A's search: remainingBuilder()'s optional keyword narrows to
 * family name or barangay, and wildcard characters in the typed keyword
 * ('%', '_') are treated literally, not as SQL LIKE wildcards. Exercised
 * through remainingCount(), which shares remainingBuilder() with
 * remainingPage() so this proves the filter both use.
 *
 * Fix B (the paging ordering tiebreaker) is proven live, not here: the
 * project's `tests` DB group runs SQLite with a forced table prefix
 * (app/Config/Database.php, "DO NOT REMOVE FOR CI DEVS"), and
 * remainingPage()'s COALESCE(barangay.name, ...) select - unchanged by this
 * branch, shared with byBarangay()/remaining() - is not prefix-safe against
 * that raw SQLite string, so it silently returns zero rows under this test
 * group regardless of what Fix B does. A green suite would not catch a
 * regression here even if this test called remainingPage(); the
 * residual-fixes-report documents the direct-SQL/live-paging proof instead
 * (dev DB, MySQL, no prefix - the production path this ships to).
 *
 * Schema comes from the dump, so it runs against the isolated test DB rather
 * than the dev database.
 */
final class SubsidyStatsRemainingPageTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
        $this->seedRoster();
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    /**
     * Six roster families, none served: three share barangay 1 and the
     * surname Cruz, three sit in barangay 2, two of them with a surname
     * carrying a literal % and _ to prove those aren't read as wildcards.
     */
    private function seedRoster(): void
    {
        $db = db_connect();

        $db->table('barangay')->insert(['barangayID' => 1, 'name' => 'Zapote']);
        $db->table('barangay')->insert(['barangayID' => 2, 'name' => 'Poblacion']);

        $rows = [
            [1, 'Ana', 'Cruz', 1],
            [2, 'Ben', 'Cruz', 1],
            [3, 'Cid', 'Cruz', 1],
            [4, 'Dan', 'Reyes', 2],
            [5, 'Eve', 'Reyes50%off', 2],
            [6, 'Fox', 'Santos_', 2],
        ];
        foreach ($rows as [$id, $first, $last, $brgy]) {
            $db->table('member')->insert([
                'memberID' => $id, 'firstname' => $first, 'lastname' => $last, 'barangayID' => $brgy,
            ]);
            $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => $id]);
        }
        $db->table('distribution_batch')->insert(['batch_id' => 1, 'eligible_count' => 6]);
    }

    public function testNoKeywordCountsWholeRoster(): void
    {
        $this->assertSame(6, (new SubsidyStatsModel())->remainingCount(1));
    }

    public function testKeywordMatchesSurname(): void
    {
        $this->assertSame(3, (new SubsidyStatsModel())->remainingCount(1, 'Cruz'));
    }

    public function testKeywordMatchesBarangay(): void
    {
        $this->assertSame(3, (new SubsidyStatsModel())->remainingCount(1, 'Poblacion'));
    }

    /**
     * "%" and "_" are SQL LIKE wildcards. A staff member searching for the
     * literal text "50%off" or "Santos_" must get only the row that actually
     * contains it, not every row (which is what an unescaped wildcard would
     * match: % against anything, _ against any single character).
     */
    public function testPercentSignIsTreatedLiterally(): void
    {
        $this->assertSame(1, (new SubsidyStatsModel())->remainingCount(1, '50%off'));
    }

    public function testUnderscoreIsTreatedLiterally(): void
    {
        $this->assertSame(1, (new SubsidyStatsModel())->remainingCount(1, 'Santos_'));
    }

    public function testKeywordWithNoMatchesReturnsZero(): void
    {
        $this->assertSame(0, (new SubsidyStatsModel())->remainingCount(1, 'Nobody Here'));
    }
}
