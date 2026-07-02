<?php

namespace App\Jobs;

use App\Models\Jobs\JobQueueModel;

/**
 * Thin progress channel handed to a JobHandler so it can publish its total and
 * mid-job progress without knowing the queue's storage. The polling UI reads these
 * via FamilyController::importStatus() (or any future status endpoint).
 */
final class JobReporter
{
    public function __construct(
        private JobQueueModel $model,
        private int $jobId,
        private int $throttleMs = 0,
    ) {
    }

    /**
     * Yields the DB to interactive users between chunks: sleeps the configured
     * throttle (ms). A handler calls this after each batch so a very large job does
     * not monopolise the database and slow down everyone else on the system.
     */
    public function pause(): void
    {
        if ($this->throttleMs > 0) {
            usleep($this->throttleMs * 1000);
        }
    }

    /** Declare how many units this job will process (the progress denominator). */
    public function setTotal(int $total): void
    {
        $this->model->setTotal($this->jobId, $total);
    }

    /**
     * Persist progress: how many units are done, the resume checkpoint, and an
     * optional structured snapshot (stored as result_json).
     *
     * @param array<string, mixed>|null $result
     */
    public function checkpoint(int $done, int $checkpoint, ?array $result = null): void
    {
        $this->model->progress(
            $this->jobId,
            $done,
            $checkpoint,
            $result !== null ? json_encode($result) : null,
        );
    }
}
