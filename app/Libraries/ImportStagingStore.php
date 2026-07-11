<?php

namespace App\Libraries;

/**
 * File-backed staging for a family import under review.
 *
 * A 10k-member import stages to ~7 MB+ of JSON — far past MySQL's max_allowed_packet,
 * so it CANNOT live in job_queue.result_json (a single UPDATE with that blob fails with
 * error 1153 / 2006 and crashes the worker). Instead the parsed rows + errors are written
 * to writable/import-staging/job-<id>.json, and job_queue.result_json keeps only a tiny
 * summary (phase + counts). No schema change; scales to any file size.
 *
 * Files hold PII, so they live under writable/ (never web-served) and are deleted on
 * commit / cancel.
 */
class ImportStagingStore
{
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

    /** Writes the full staged bundle (rows/errors/fileErrors/counts/file). */
    public function save(int $jobId, array $bundle): bool
    {
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }

        return file_put_contents($this->path($jobId), json_encode($bundle)) !== false;
    }

    /** Reads a job's staged bundle, or null when it is missing/unreadable. */
    public function load(int $jobId): ?array
    {
        $path = $this->path($jobId);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** Removes a job's staging file (best effort). */
    public function delete(int $jobId): void
    {
        $path = $this->path($jobId);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
