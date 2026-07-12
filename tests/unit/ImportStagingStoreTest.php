<?php

namespace Tests\Unit;

use App\Libraries\ImportStagingStore;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit coverage for the import staging store, with the emphasis on sweep(): it DELETES
 * files holding family PII, so every guard that keeps it from eating a live import is
 * pinned down here. Runs against a throwaway directory — no database, no writable/.
 *
 * @internal
 */
final class ImportStagingStoreTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'staging-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . DIRECTORY_SEPARATOR . '*') as $file) {
            @unlink((string) $file);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    private function store(): ImportStagingStore
    {
        return new ImportStagingStore($this->dir);
    }

    /** Writes a staging file and back-dates it $hoursOld hours. */
    private function seed(int $jobId, int $hoursOld = 0): string
    {
        $store = $this->store();
        $store->save($jobId, ['phase' => 'review', 'rows' => [['row' => 2]]]);

        $path = $store->path($jobId);

        if ($hoursOld > 0) {
            touch($path, time() - ($hoursOld * 3600));
        }

        return $path;
    }

    // -- save / load / delete --------------------------------------------------

    public function testSaveLoadRoundTrip(): void
    {
        $this->store()->save(7, ['phase' => 'review', 'rows' => [['row' => 2, 'familyNo' => '6001']]]);

        $bundle = $this->store()->load(7);

        $this->assertIsArray($bundle);
        $this->assertSame('review', $bundle['phase']);
        $this->assertSame('6001', $bundle['rows'][0]['familyNo']);
    }

    public function testLoadReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->store()->load(999));
    }

    public function testDeleteRemovesTheFile(): void
    {
        $path = $this->seed(7);
        $this->assertFileExists($path);

        $this->store()->delete(7);

        $this->assertFileDoesNotExist($path);
    }

    // -- sweep -----------------------------------------------------------------

    public function testSweepRemovesAnAbandonedFilePastTheTtl(): void
    {
        $path = $this->seed(7, 48);

        $this->assertSame(1, $this->store()->sweep([], 24));
        $this->assertFileDoesNotExist($path);
    }

    public function testSweepKeepsAFileInsideTheTtl(): void
    {
        $path = $this->seed(7, 2);

        $this->assertSame(0, $this->store()->sweep([], 24));
        $this->assertFileExists($path);
    }

    /**
     * The one that matters: a write job can sit `pending` for hours when the worker is
     * stopped. Sweeping the rows it is queued to persist would kill the import.
     */
    public function testSweepNeverTouchesAProtectedStagingId(): void
    {
        $protected = $this->seed(7, 72);
        $orphan    = $this->seed(8, 72);

        $this->assertSame(1, $this->store()->sweep([7], 24));
        $this->assertFileExists($protected);
        $this->assertFileDoesNotExist($orphan);
    }

    public function testSweepIgnoresFilesThatAreNotStagingBundles(): void
    {
        $guard = $this->dir . DIRECTORY_SEPARATOR . 'index.html';
        file_put_contents($guard, '');
        touch($guard, time() - (72 * 3600));

        $this->assertSame(0, $this->store()->sweep([], 24));
        $this->assertFileExists($guard);
    }

    public function testSweepOnAMissingDirectoryIsANoOp(): void
    {
        $store = new ImportStagingStore($this->dir . DIRECTORY_SEPARATOR . 'nope');

        $this->assertSame(0, $store->sweep([], 24));
    }
}
