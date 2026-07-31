<?php

namespace App\Libraries;

/**
 * File-backed staging for a family import under review.
 *
 * A 10k-member import stages to ~7 MB+ of JSON - far past MySQL's max_allowed_packet,
 * so it CANNOT live in job_queue.result_json (a single UPDATE with that blob fails with
 * error 1153 / 2006 and crashes the worker). Instead the parsed rows + errors are written
 * to writable/import-staging/job-<id>.json, and job_queue.result_json keeps only a tiny
 * summary (phase + counts). No schema change; scales to any file size.
 *
 * Files hold PII, so they live under writable/ (never web-served) and are deleted on
 * commit / cancel.
 *
 * The bundle is stored as three files per job: `job-<id>.json` holds the small
 * meta (phase, file name, columns, counts, changes), `job-<id>-rows.json` the
 * staged rows, and `job-<id>-errors.json` the validation errors. An in-review
 * edit changes rows and errors but not the meta, and rewriting one file instead
 * of a single ~7 MB blob is what keeps a per-row Apply cheap on a 10,000-row
 * import.
 */
class ImportStagingStore
{
    /** Age after which an abandoned staging file is swept (see sweep()). */
    public const TTL_HOURS = 24;

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (WRITEPATH . 'import-staging');
    }

    /** Absolute path of a job's staging file. */
    public function path(int $jobId): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'job-' . $jobId . '.json';
    }

    /** Absolute path of a job's staged rows file. */
    public function rowsPath(int $jobId): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'job-' . $jobId . '-rows.json';
    }

    /** Absolute path of a job's staged errors file. */
    public function errorsPath(int $jobId): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'job-' . $jobId . '-errors.json';
    }

    /** Writes the full staged bundle across the meta, rows and errors files. */
    public function save(int $jobId, array $bundle): bool
    {
        $this->ensureDir();

        $meta = $bundle;
        unset($meta['rows'], $meta['errors']);

        $ok = $this->put($this->path($jobId), $meta);
        $ok = $this->put($this->rowsPath($jobId), $bundle['rows'] ?? []) && $ok;

        return $this->put($this->errorsPath($jobId), $bundle['errors'] ?? []) && $ok;
    }

    /** Rewrites only the staged rows, leaving the errors and meta files untouched. */
    public function saveRows(int $jobId, array $rows): bool
    {
        $this->ensureDir();

        return $this->put($this->rowsPath($jobId), $rows);
    }

    /**
     * Rewrites the errors file and the counts in the meta together, since a fresh
     * validation always produces both and a half-written pair would show the
     * operator a count that disagrees with the flags.
     */
    public function saveErrors(int $jobId, array $errors, array $counts): bool
    {
        $this->ensureDir();

        $meta = $this->read($this->path($jobId));
        $meta['counts'] = $counts;

        $ok = $this->put($this->errorsPath($jobId), $errors);

        return $this->put($this->path($jobId), $meta) && $ok;
    }

    /** Reads a job's staged bundle, or null when the meta is missing/unreadable. */
    public function load(int $jobId): ?array
    {
        if (! is_file($this->path($jobId))) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($this->path($jobId)), true);

        if (! is_array($meta)) {
            return null;
        }

        $meta['rows']   = $this->read($this->rowsPath($jobId));
        $meta['errors'] = $this->read($this->errorsPath($jobId));

        return $meta;
    }

    /** Removes every staging file for a job (best effort). */
    public function delete(int $jobId): void
    {
        foreach ([$this->path($jobId), $this->rowsPath($jobId), $this->errorsPath($jobId)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function ensureDir(): void
    {
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    /** @param array<mixed> $data */
    private function put(string $path, array $data): bool
    {
        return file_put_contents($path, json_encode($data)) !== false;
    }

    /** @return array<mixed> the decoded file, or [] when missing/unreadable */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Deletes staging files older than $ttlHours, skipping any an unfinished job still
     * reads. Commit and cancel already clean up after themselves; this is the backstop for
     * the review nobody finished - the operator closed the tab, fixed the spreadsheet, and
     * uploaded it as a NEW job. That first file is orphaned, and it holds family PII, so it
     * must not sit on disk forever.
     *
     * $protectedIds MUST carry JobQueueModel::activeStagingIds(): a queued write job can
     * wait hours for a stopped worker, and sweeping its rows out from under it kills the
     * import. Returns the number of files removed.
     *
     * @param list<int> $protectedIds staging IDs a pending/running job still needs
     */
    public function sweep(array $protectedIds = [], int $ttlHours = self::TTL_HOURS): int
    {
        if (! is_dir($this->dir)) {
            return 0;
        }

        $protected = array_flip(array_map('intval', $protectedIds));
        $cutoff    = time() - (max(1, $ttlHours) * 3600);
        $removed   = 0;

        foreach ((array) glob($this->dir . DIRECTORY_SEPARATOR . 'job-*.json') as $file) {
            // Only the meta file names a job. The -rows / -errors siblings are removed
            // with it, so matching them here would double-count and race the unlink.
            if (! preg_match('/^job-(\d+)\.json$/', basename((string) $file), $match)) {
                continue;
            }

            $jobId = (int) $match[1];

            if (isset($protected[$jobId])) {
                continue;
            }

            $mtime = @filemtime((string) $file);

            if ($mtime === false || $mtime > $cutoff) {
                continue;
            }

            $this->delete($jobId);
            $removed++;
        }

        return $removed;
    }
}
