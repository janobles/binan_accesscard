<?php

namespace App\Jobs;

/**
 * Contract every background job type implements. A handler is resolved from the
 * job's `type` via Config\Queue::$handlers and run by the `queue:work` worker.
 *
 * Implementations should be self-contained: catch their own per-item errors and
 * return a JobOutcome describing the result. Throwing is reserved for genuinely
 * unexpected failures — the worker will retry (up to the job's max_attempts) or,
 * once exhausted, mark the job failed.
 */
interface JobHandlerInterface
{
    /**
     * @param array<string, mixed> $payload Decoded job payload (the input enqueued with the job).
     * @param array<string, mixed> $job     The full job_queue row — gives `checkpoint`, `userID`,
     *                                       `ip_address`, `user_agent`, `result_json` for resuming.
     * @param JobReporter          $reporter Used to publish total + mid-job progress so the UI can poll.
     */
    public function handle(array $payload, array $job, JobReporter $reporter): JobOutcome;
}
