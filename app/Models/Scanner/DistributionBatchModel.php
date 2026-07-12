<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Distribution batches: one row per giving event (e.g. one day of handouts).
 * At most one batch may be open (closed_at IS NULL) at a time; open() enforces
 * that invariant. Closing a batch is the manual "reset" — the next batch's
 * statistics start from zero. All methods keep the scanner module's no-DB
 * test posture: safe empty shapes on any DB error.
 */
class DistributionBatchModel extends Model
{
    protected $table         = 'distribution_batch';
    protected $primaryKey    = 'batch_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'service_id', 'closed_at', 'created_by'];
    protected $useTimestamps = false;

    /** The single open batch, or null when none (or on DB error). */
    public function activeBatch(): ?array
    {
        try {
            $row = $this->select('distribution_batch.*, services.name AS service_name,'
                    . ' services.shortcode AS service_code,'
                    . ' services.category AS category_name,'
                    . ' category.code AS category_code')
                ->join('services', 'services.serviceID = distribution_batch.service_id', 'left')
                ->join('category', 'category.name = services.category', 'left')
                ->where('distribution_batch.closed_at', null)
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Opens a batch; refuses when name blank, service missing, or a batch is open. */
    public function open(string $name, int $serviceId, int $userId): int
    {
        $name = trim($name);
        if ($name === '' || $serviceId <= 0 || $this->activeBatch() !== null) {
            return 0;
        }

        try {
            if ($this->insert([
                'name'       => $name,
                'service_id' => $serviceId,
                'created_by' => $userId > 0 ? $userId : null,
            ]) === false) {
                return 0;
            }

            return (int) $this->getInsertID();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Closes (resets) an open batch by stamping closed_at. */
    public function close(int $batchId): bool
    {
        if ($batchId <= 0) {
            return false;
        }

        try {
            $row = $this->find($batchId);
            if (! is_array($row) || $row['closed_at'] !== null) {
                return false;
            }

            return $this->update($batchId, ['closed_at' => date('Y-m-d H:i:s')]) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Every batch, newest first, for the manage table and reports selector. */
    public function allBatches(): array
    {
        try {
            return $this->select('distribution_batch.*, services.name AS service_name,'
                    . ' services.shortcode AS service_code,'
                    . ' services.category AS category_name,'
                    . ' category.code AS category_code')
                ->join('services', 'services.serviceID = distribution_batch.service_id', 'left')
                ->join('category', 'category.name = services.category', 'left')
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
