<?php

namespace App\Jobs;

/**
 * Immutable result a JobHandler returns to the worker: the terminal status, a
 * human-readable message, and an optional structured result (stored as the job's
 * result_json, e.g. an import's per-row errors + counts).
 */
final class JobOutcome
{
    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?array $result,
    ) {
    }

    /** Every unit succeeded. */
    public static function done(string $message, ?array $result = null): self
    {
        return new self('done', $message, $result);
    }

    /** Some units succeeded, some failed (result should describe the failures). */
    public static function partial(string $message, ?array $result = null): self
    {
        return new self('partial', $message, $result);
    }

    /** Nothing usable was produced. */
    public static function failed(string $message, ?array $result = null): self
    {
        return new self('failed', $message, $result);
    }
}
