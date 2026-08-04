<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Distribution batches: one row per giving event (e.g. one day of handouts).
 * At most one batch may be open (closed_at IS NULL) at a time; open() enforces
 * that invariant. Closing a batch is the manual "reset" - the next batch's
 * statistics start from zero. All methods keep the scanner module's no-DB
 * test posture: safe empty shapes on any DB error.
 */
class DistributionBatchModel extends Model
{
    protected $table         = 'distribution_batch';
    protected $primaryKey    = 'batch_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'subsidy_type_id', 'closed_at', 'created_by', 'eligible_count'];
    protected $useTimestamps = false;

    /** The single open batch, or null when none (or on DB error). */
    public function activeBatch(): ?array
    {
        try {
            $row = $this->select('distribution_batch.*, subsidy.name AS subsidy_type_name')
                ->join('subsidy', 'subsidy.subsidy_type_id = distribution_batch.subsidy_type_id', 'left')
                ->where('distribution_batch.closed_at', null)
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Opens a batch and freezes its eligibility roster in one transaction.
     * Refuses when the name is blank, the subsidy type is missing, or a batch is
     * already open. Empty filter arrays mean citywide and all sectors.
     *
     * @param list<int> $barangayIds
     * @param list<int> $sectorIds
     */
    public function open(string $name, int $subsidyTypeId, int $userId, array $barangayIds = [], array $sectorIds = []): int
    {
        $name = trim($name);
        if ($name === '' || $subsidyTypeId <= 0 || $this->activeBatch() !== null) {
            return 0;
        }

        try {
            $this->db->transStart();

            if ($this->insert([
                'name'            => $name,
                'subsidy_type_id' => $subsidyTypeId,
                'created_by'      => $userId > 0 ? $userId : null,
            ]) === false) {
                $this->db->transComplete();

                return 0;
            }

            $batchId = (int) $this->getInsertID();

            foreach ($barangayIds as $id) {
                $this->db->table('batch_barangay')
                    ->insert(['batch_id' => $batchId, 'barangayID' => (int) $id]);
            }
            foreach ($sectorIds as $id) {
                $this->db->table('batch_sector')
                    ->insert(['batch_id' => $batchId, 'sectorID' => (int) $id]);
            }

            $eligible = (new \App\Libraries\EligibilityBuilder($this->db))
                ->materialize($batchId, $barangayIds, $sectorIds);
            $this->update($batchId, ['eligible_count' => $eligible]);

            $this->db->transComplete();

            return $this->db->transStatus() === false ? 0 : $batchId;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * A batch's stored filters. Empty arrays mean the batch was citywide or
     * covered all sectors.
     *
     * @return array{barangays:list<int>,sectors:list<int>}
     */
    public function filtersFor(int $batchId): array
    {
        $empty = ['barangays' => [], 'sectors' => []];
        if ($batchId <= 0) {
            return $empty;
        }

        try {
            $brgy = $this->db->table('batch_barangay')
                ->select('barangayID')->where('batch_id', $batchId)->get()->getResultArray();
            $sect = $this->db->table('batch_sector')
                ->select('sectorID')->where('batch_id', $batchId)->get()->getResultArray();

            return [
                'barangays' => array_map(static fn ($r) => (int) $r['barangayID'], $brgy),
                'sectors'   => array_map(static fn ($r) => (int) $r['sectorID'], $sect),
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * Re-runs the roster for a batch whose profiling data changed after it
     * opened. Deliberately explicit: the roster never moves on its own, because
     * a closed batch's coverage is a printed figure that must not drift. Returns
     * the new eligible count.
     */
    public function rebuildRoster(int $batchId): int
    {
        if ($batchId <= 0) {
            return 0;
        }

        $filters  = $this->filtersFor($batchId);
        $eligible = (new \App\Libraries\EligibilityBuilder($this->db))
            ->materialize($batchId, $filters['barangays'], $filters['sectors']);

        try {
            $this->update($batchId, ['eligible_count' => $eligible]);
        } catch (\Throwable $e) {
            return 0;
        }

        return $eligible;
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
            return $this->select('distribution_batch.*, subsidy.name AS subsidy_type_name')
                ->join('subsidy', 'subsidy.subsidy_type_id = distribution_batch.subsidy_type_id', 'left')
                ->orderBy('distribution_batch.batch_id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
