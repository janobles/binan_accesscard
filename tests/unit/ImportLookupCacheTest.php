<?php

namespace Tests\Unit;

use App\Libraries\FamilyExcelImporter;
use App\Libraries\ImportLookupCache;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The existing-record lookups are the two heaviest queries in a revalidate, and a
 * review session runs one revalidate per fix. Caching them is what stops 800 fixes
 * costing 1,600 whole-file queries.
 *
 * Correctness rests on one claim: the lookups are derived only from the QRs and
 * lastnames in the staged rows, so nothing else can stale them. These tests pin that
 * claim, because a wrong invalidation would let the review pass a file the write step
 * then skips.
 *
 * @internal
 */
final class ImportLookupCacheTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/import-lookup-' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $file) {
            @unlink((string) $file);
        }

        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testAChangedLastnameOrQrInvalidatesTheCache(): void
    {
        $this->assertTrue(ImportLookupCache::invalidatedBy(['lastname']));
        $this->assertTrue(ImportLookupCache::invalidatedBy(['familyno']));
        $this->assertTrue(ImportLookupCache::invalidatedBy(['sex', 'familyno']));
    }

    public function testAnyOtherFieldLeavesTheCacheValid(): void
    {
        // Sex, birthday, barangay and the rest cannot change which existing records
        // the file collides with, so a fix to one must not pay for the queries.
        $this->assertFalse(ImportLookupCache::invalidatedBy(['sex']));
        $this->assertFalse(ImportLookupCache::invalidatedBy(['birthday', 'barangay', 'relationship']));
        $this->assertFalse(ImportLookupCache::invalidatedBy([]));
    }

    public function testItQueriesOnceThenServesFromTheCache(): void
    {
        $importer = new CountingImporter();
        $cache    = new ImportLookupCache($this->dir);
        $rows     = [['sheetRow' => 3, 'data' => ['familyno' => '6001', 'lastname' => 'Cruz']]];

        $first  = $cache->lookupsFor(9, $rows, $importer);
        $second = $cache->lookupsFor(9, $rows, $importer);

        $this->assertSame(1, $importer->headCalls);
        $this->assertSame(1, $importer->peopleCalls);
        $this->assertSame($first, $second);
    }

    public function testARebuildQueriesAgainAndReplacesTheCache(): void
    {
        $importer = new CountingImporter();
        $cache    = new ImportLookupCache($this->dir);
        $rows     = [['sheetRow' => 3, 'data' => ['familyno' => '6001', 'lastname' => 'Cruz']]];

        $cache->lookupsFor(9, $rows, $importer);
        $cache->lookupsFor(9, $rows, $importer, true);

        $this->assertSame(2, $importer->headCalls);
        $this->assertSame(2, $importer->peopleCalls);
    }

    public function testForgetDropsTheCacheSoTheNextCallQueries(): void
    {
        $importer = new CountingImporter();
        $cache    = new ImportLookupCache($this->dir);
        $rows     = [['sheetRow' => 3, 'data' => ['familyno' => '6001', 'lastname' => 'Cruz']]];

        $cache->lookupsFor(9, $rows, $importer);
        $cache->forget(9);
        $cache->lookupsFor(9, $rows, $importer);

        $this->assertSame(2, $importer->headCalls);
    }
}

/**
 * A FamilyExcelImporter that counts the two lookups instead of touching the DB, so
 * the cache can be tested without a database.
 */
final class CountingImporter extends FamilyExcelImporter
{
    public int $headCalls = 0;
    public int $peopleCalls = 0;

    public function existingHeadsForRows(array $rows): array
    {
        $this->headCalls++;

        return ['6001' => ['headID' => 1, 'name' => 'Juan Cruz', 'record' => []]];
    }

    public function existingPeopleForRows(array $rows): array
    {
        $this->peopleCalls++;

        return [];
    }
}
