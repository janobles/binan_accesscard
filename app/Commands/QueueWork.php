<?php

namespace App\Commands;

use App\Jobs\JobHandlerInterface;
use App\Jobs\JobReporter;
use App\Models\Jobs\JobQueueModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Generic background job worker. Drains the `job_queue` (and resumes crashed
 * `processing` jobs), dispatching each to the handler registered for its `type`
 * in Config\Queue. Fired every minute by Windows Task Scheduler via
 * scripts/queue-worker.ps1 so heavy work (Excel imports, future exports/reports,
 * etc.) runs off the web request's timeout/memory limit.
 *
 * Concurrency: a single OS file lock (writable/queue-worker.lock) guarantees only
 * one worker runs at a time, so an every-minute tick that fires while a long job is
 * still going simply exits.
 *
 * Usage:
 *   php spark queue:work                    drain the queue
 *   php spark queue:work --max-seconds=50   stop claiming NEW jobs after 50s
 *   php spark queue:work --throttle=250     pause 250ms between chunks (DB breathing room)
 *   php spark queue:work --drainer-id=2     run as a second parallel drainer
 *
 * An in-progress job always runs to completion regardless of --max-seconds; the
 * budget only bounds how long the worker keeps picking up further jobs. Claims are
 * atomic, so several drainers (distinct --drainer-id) can run safely in parallel.
 */
class QueueWork extends BaseCommand
{
    protected $group       = 'Jobs';
    protected $name        = 'queue:work';
    protected $description = 'Process queued background jobs (dispatches by type to Config\\Queue handlers).';
    protected $usage       = 'queue:work [--max-seconds=50] [--throttle=250] [--drainer-id=1]';
    protected $options     = [
        '--max-seconds' => 'Stop claiming new jobs after this many seconds (default 50).',
        '--throttle'    => 'Milliseconds to pause between chunks so big jobs do not starve other users (default 0).',
        '--drainer-id'  => 'Identifier for this drainer; distinct IDs run in parallel, each with its own lock (default 1).',
    ];

    public function run(array $params)
    {
        // The point of the queue is to escape the web request limits.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $maxSeconds = max(5, (int) (CLI::getOption('max-seconds') ?: 50));
        $throttleMs = max(0, (int) (CLI::getOption('throttle') ?: 0));
        $drainerId  = max(1, (int) (CLI::getOption('drainer-id') ?: 1));
        $deadline   = time() + $maxSeconds;

        // One lock per drainer slot: stops the same slot from stacking across the
        // every-minute fires, while distinct slots still run in parallel.
        $lockPath   = WRITEPATH . 'queue-worker-' . $drainerId . '.lock';
        $lockHandle = fopen($lockPath, 'c');

        if ($lockHandle === false) {
            CLI::error('Could not open worker lock file: ' . $lockPath);

            return EXIT_ERROR;
        }

        if (! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            CLI::write('Another queue worker is already running. Exiting.', 'yellow');
            fclose($lockHandle);

            return EXIT_SUCCESS;
        }

        try {
            $model = new JobQueueModel();

            if (! $model->hasTable()) {
                CLI::write('The job_queue table is missing (import it from accesscardV14.sql). Exiting.', 'red');

                return EXIT_ERROR;
            }

            /** @var array<string, class-string> $handlers */
            $handlers  = config('Queue')->handlers;
            $processed = 0;

            while (true) {
                $job = $model->claimNext('d' . $drainerId);

                if ($job === null) {
                    break;
                }

                $this->runJob($model, $handlers, $job, $throttleMs);
                $processed++;

                if (time() >= $deadline) {
                    CLI::write('Time budget reached after ' . $processed . ' job(s).', 'dark_gray');
                    break;
                }
            }

            if ($processed === 0) {
                CLI::write('No queued jobs.', 'dark_gray');
            }

            return EXIT_SUCCESS;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Dispatches one claimed job to its handler and records the outcome. Never
     * throws — an unexpected handler error is retried (up to the job's
     * max_attempts) or, once exhausted, marked failed.
     *
     * @param array<string, class-string> $handlers
     * @param array<string, mixed>        $job
     */
    private function runJob(JobQueueModel $model, array $handlers, array $job, int $throttleMs = 0): void
    {
        $jobId = (int) $job['jobID'];
        $type  = (string) $job['type'];

        CLI::write('Job #' . $jobId . ' [' . $type . ']', 'cyan');

        if (! isset($handlers[$type])) {
            $model->finish($jobId, 'failed', 'No handler is registered for job type "' . $type . '".');
            CLI::error('  no handler for type: ' . $type);

            return;
        }

        try {
            $handler = new $handlers[$type]();
        } catch (Throwable $e) {
            $model->finish($jobId, 'failed', 'Handler for "' . $type . '" could not be constructed: ' . $e->getMessage());
            CLI::error('  handler construction failed: ' . $e->getMessage());

            return;
        }

        if (! $handler instanceof JobHandlerInterface) {
            $model->finish($jobId, 'failed', 'Handler for "' . $type . '" is not a JobHandlerInterface.');

            return;
        }

        $payload  = $this->decode($job['payload'] ?? null);
        $reporter = new JobReporter($model, $jobId, $throttleMs);

        try {
            $outcome = $handler->handle($payload, $job, $reporter);
        } catch (Throwable $e) {
            // Retry while attempts remain (claimNext already incremented attempts);
            // otherwise fail. Default max_attempts is 1, so one-shot jobs don't loop.
            if ((int) $job['attempts'] < (int) $job['max_attempts']) {
                $model->retry($jobId, 60, 'Retrying after error: ' . $e->getMessage());
                CLI::write('  error (will retry): ' . $e->getMessage(), 'yellow');

                return;
            }

            $model->finish($jobId, 'failed', 'The job failed: ' . $e->getMessage());
            CLI::error('  failed: ' . $e->getMessage());

            return;
        }

        $model->finish(
            $jobId,
            $outcome->status,
            $outcome->message,
            $outcome->result !== null ? json_encode($outcome->result) : null,
        );

        CLI::write('  ' . $outcome->status . ': ' . $outcome->message, $outcome->status === 'done' ? 'green' : 'yellow');
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
