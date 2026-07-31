<?php

namespace App\Libraries;

/**
 * Memoizes the two existing-record lookups a review revalidate needs.
 *
 * FamilyExcelImporter::existingHeadsForRows() and existingPeopleForRows() are the
 * heaviest queries in the import path: one collects every QR in the file and joins
 * the QR control table, the other collects every distinct lastname and scans the
 * member table. A review session revalidates once per fix, so on a 10,000-row file
 * with 800 problems they would run 1,600 times over unchanged data.
 *
 * Both are derived only from the QRs and the lastnames in the staged rows, so an
 * edit can only stale them by changing one of those two fields. Everything else
 * (sex, birthday, barangay, relationship) reuses the cache. The result is written
 * beside the staging files under writable/, and holds the same PII, so it is
 * removed with them.
 */
class ImportLookupCache
{
    /** The only staged fields whose value feeds the cached lookups. */
    public const INVALIDATING_FIELDS = ['familyno', 'lastname'];

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (WRITEPATH . 'import-staging');
    }

    /** Whether an edit touching these fields forces the lookups to be rerun. */
    public static function invalidatedBy(array $fields): bool
    {
        return array_intersect($fields, self::INVALIDATING_FIELDS) !== [];
    }

    /**
     * The cached lookups for a job, querying only when the cache is cold or the
     * caller says the rows moved underneath it.
     *
     * @param list<array> $rows the staged rows
     * @return array{heads: array, people: array}
     */
    public function lookupsFor(int $jobId, array $rows, FamilyExcelImporter $importer, bool $rebuild = false): array
    {
        if (! $rebuild) {
            $cached = $this->read($jobId);

            if ($cached !== null) {
                return $cached;
            }
        }

        $lookups = [
            'heads'  => $importer->existingHeadsForRows($rows),
            'people' => $importer->existingPeopleForRows($rows),
        ];

        $this->write($jobId, $lookups);

        return $lookups;
    }

    /** Drops a job's cache (on commit, cancel, or an invalidating edit). */
    public function forget(int $jobId): void
    {
        $path = $this->path($jobId);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Absolute path of a job's lookup cache. */
    public function path(int $jobId): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'job-' . $jobId . '-lookups.json';
    }

    /** @return array{heads: array, people: array}|null */
    private function read(int $jobId): ?array
    {
        $path = $this->path($jobId);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return (is_array($data) && isset($data['heads'], $data['people'])) ? $data : null;
    }

    /** @param array{heads: array, people: array} $lookups */
    private function write(int $jobId, array $lookups): void
    {
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }

        file_put_contents($this->path($jobId), json_encode($lookups));
    }
}
