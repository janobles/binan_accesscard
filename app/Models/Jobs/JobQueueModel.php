<?php

namespace App\Models\Jobs;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Persistence for the generic background job queue (`job_queue`).
 *
 * Any long/heavy task can be enqueued here instead of running inside a web
 * request: the request writes a `pending` row (a `type` + a JSON `payload`) and
 * returns immediately; a scheduled worker (`php spark queue:work`, fired every
 * minute by scripts/queue-worker.ps1) claims it and dispatches to the handler
 * registered for that type in Config\Queue. The family Excel import is the first
 * such job type (App\Jobs\FamilyImportJob); add more by registering a handler.
 *
 * Status lifecycle: pending → processing → done | partial | failed.
 *
 * No CI4 migrations exist in this project (schema ships as accesscardV1.4.sql),
 * so ensureTable() creates the table on demand; sql/job_queue.sql holds the
 * canonical DDL for the schema dump.
 */
class JobQueueModel
{
    private BaseConnection $db;

    /** A `processing` row untouched this long is treated as a crashed run and is
     *  re-claimed (handlers resume from their checkpoint). */
    public const STALE_MINUTES = 15;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function hasTable(): bool
    {
        return $this->db->tableExists('job_queue');
    }

    /** Creates `job_queue` if missing. Idempotent. */
    public function ensureTable(): void
    {
        if ($this->hasTable()) {
            return;
        }

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `job_queue` (
                `jobID` INT NOT NULL AUTO_INCREMENT,
                `type` VARCHAR(64) NOT NULL,
                `payload` LONGTEXT NULL,
                `status` ENUM(\'pending\',\'processing\',\'done\',\'partial\',\'failed\') NOT NULL DEFAULT \'pending\',
                `progress_total` INT NOT NULL DEFAULT 0,
                `progress_done` INT NOT NULL DEFAULT 0,
                `checkpoint` INT NOT NULL DEFAULT 0,
                `result_json` LONGTEXT NULL,
                `message` VARCHAR(500) NULL,
                `userID` INT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `attempts` INT NOT NULL DEFAULT 0,
                `max_attempts` INT NOT NULL DEFAULT 1,
                `available_at` DATETIME NULL,
                `locked_at` DATETIME NULL,
                `locked_by` VARCHAR(64) NULL,
                `dt_created` DATETIME NOT NULL,
                `dt_started` DATETIME NULL,
                `dt_finished` DATETIME NULL,
                PRIMARY KEY (`jobID`),
                KEY `idx_claim` (`status`, `available_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Enqueues a job and returns its jobID.
     *
     * @param string               $type       handler key registered in Config\Queue::$handlers
     * @param array<string, mixed> $payload    handler-specific input (JSON-encoded)
     */
    public function enqueue(string $type, array $payload, int $userId = 0, ?string $ip = null, ?string $userAgent = null, int $maxAttempts = 1): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('job_queue')->insert([
            'type'         => mb_substr($type, 0, 64),
            'payload'      => json_encode($payload),
            'status'       => 'pending',
            'userID'       => $userId > 0 ? $userId : null,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'max_attempts' => max(1, $maxAttempts),
            'available_at' => $now,
            'dt_created'   => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function find(int $jobId): ?array
    {
        $row = $this->db->table('job_queue')->where('jobID', $jobId)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Atomically claims the oldest runnable job (a due `pending`, or a stale
     * `processing` to resume after a crash) and returns it, or null when nothing is
     * claimable. The claim is a single UPDATE stamped with a unique token, so it is
     * safe for several drainers to run at once — only one can flip a given row. Each
     * claim is short (a single family transaction), keeping row locks off other users.
     *
     * @param string $worker label of the claiming drainer (for locked_by/debugging)
     */
    public function claimNext(string $worker = 'worker'): ?array
    {
        $now         = date('Y-m-d H:i:s');
        $staleBefore = date('Y-m-d H:i:s', time() - self::STALE_MINUTES * 60);
        $token       = $worker . '-' . bin2hex(random_bytes(6)) . '-' . getmypid();

        // Single-statement claim. The inner SELECT is wrapped in a derived table so
        // MySQL allows referencing job_queue in the UPDATE's own subquery.
        $this->db->query(
            'UPDATE `job_queue`
                SET `status` = ?, `locked_at` = ?, `locked_by` = ?, `attempts` = `attempts` + 1,
                    `dt_started` = COALESCE(`dt_started`, ?)
              WHERE `jobID` = (
                  SELECT `jobID` FROM (
                      SELECT `jobID` FROM `job_queue`
                       WHERE (`status` = ? AND (`available_at` IS NULL OR `available_at` <= ?))
                          OR (`status` = ? AND `locked_at` < ?)
                       ORDER BY `jobID` ASC
                       LIMIT 1
                  ) AS pick
              )',
            ['processing', $now, $token, $now, 'pending', $now, 'processing', $staleBefore]
        );

        if ($this->db->affectedRows() < 1) {
            return null;
        }

        $row = $this->db->table('job_queue')
            ->where('locked_by', $token)
            ->where('status', 'processing')
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /** Handler-side: declare the unit total so the UI has a denominator. */
    public function setTotal(int $jobId, int $total): void
    {
        $this->db->table('job_queue')->where('jobID', $jobId)->update([
            'progress_total' => $total,
            'locked_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /** Handler-side: persist mid-job progress + checkpoint (and optional result JSON). */
    public function progress(int $jobId, int $done, int $checkpoint, ?string $resultJson = null): void
    {
        $data = [
            'progress_done' => $done,
            'checkpoint'    => $checkpoint,
            'locked_at'     => date('Y-m-d H:i:s'),
        ];

        if ($resultJson !== null) {
            $data['result_json'] = $resultJson;
        }

        $this->db->table('job_queue')->where('jobID', $jobId)->update($data);
    }

    /** Marks a job terminal (done|partial|failed) with a summary + optional result. */
    public function finish(int $jobId, string $status, string $message, ?string $resultJson = null): void
    {
        $data = [
            'status'      => $status,
            'message'     => mb_substr($message, 0, 500),
            'locked_at'   => null,
            'dt_finished' => date('Y-m-d H:i:s'),
        ];

        if ($resultJson !== null) {
            $data['result_json'] = $resultJson;
        }

        $this->db->table('job_queue')->where('jobID', $jobId)->update($data);
    }

    /** Requeues a job for a later retry after a recoverable failure. */
    public function retry(int $jobId, int $delaySeconds, string $message): void
    {
        $this->db->table('job_queue')->where('jobID', $jobId)->update([
            'status'       => 'pending',
            'message'      => mb_substr($message, 0, 500),
            'locked_at'    => null,
            'available_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
        ]);
    }
}
