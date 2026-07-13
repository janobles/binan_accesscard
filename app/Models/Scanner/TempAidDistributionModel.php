<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/** Temporary QR-only handout log used while family encoding is incomplete. */
class TempAidDistributionModel extends Model
{
    protected $table         = 'temp_aid_distribution';
    protected $primaryKey    = 'temp_aidID';
    protected $returnType    = 'array';
    protected $allowedFields = ['control_no', 'aid_type_id', 'claim_date', 'batch_id'];
    protected $useTimestamps = false;

    /** Returns the existing scan for this QR and batch, or null. */
    public function inBatch(int $controlNo, int $batchId): ?array
    {
        if ($controlNo <= 0 || $batchId <= 0) {
            return null;
        }

        try {
            $row = $this->where('control_no', $controlNo)
                ->where('batch_id', $batchId)
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Inserts a temporary distribution and returns its id. */
    public function logAid(array $data): int
    {
        if ((int) ($data['control_no'] ?? 0) <= 0
            || (int) ($data['aid_type_id'] ?? 0) <= 0
            || (int) ($data['batch_id'] ?? 0) <= 0
            || empty($data['claim_date'])
        ) {
            return 0;
        }

        try {
            $inserted = $this->insert([
                'control_no'  => (int) $data['control_no'],
                'aid_type_id' => (int) $data['aid_type_id'],
                'claim_date'  => $data['claim_date'],
                'batch_id'    => (int) $data['batch_id'],
            ]);

            return $inserted === false ? 0 : (int) $this->getInsertID();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Number of QR codes recorded in one batch. */
    public function countInBatch(int $batchId): int
    {
        return $batchId > 0 ? $this->where('batch_id', $batchId)->countAllResults() : 0;
    }
}
