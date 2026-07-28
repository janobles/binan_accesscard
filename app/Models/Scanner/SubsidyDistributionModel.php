<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Subsidy-distribution log: one row per handout against a QR control number.
 * historyFor() drives the per-QR chronological history panel.
 */
class SubsidyDistributionModel extends Model
{
    protected $table         = 'subsidy_distribution';
    protected $primaryKey    = 'distribution_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['control_no', 'memberID', 'subsidy_type_id', 'claim_date', 'userID', 'batch_id'];
    protected $useTimestamps = false;

    /** Inserts one distribution row and returns its distribution_id. */
    public function logAid(array $data): int
    {
        // Guard against a malformed handout: control number, claimant, and
        // subsidy type must all be positive ids and a claim date must be present.
        if ((int) ($data['control_no'] ?? 0) <= 0
            || (int) ($data['memberID'] ?? 0) <= 0
            || (int) ($data['subsidy_type_id'] ?? 0) <= 0
            || empty($data['claim_date'])
        ) {
            return 0;
        }

        $this->insert([
            'control_no'  => (int) $data['control_no'],
            'memberID'    => (int) $data['memberID'],
            'subsidy_type_id' => (int) $data['subsidy_type_id'],
            'claim_date'  => $data['claim_date'],
            'userID'      => isset($data['userID']) && (int) $data['userID'] > 0 ? (int) $data['userID'] : null,
            'batch_id'    => isset($data['batch_id']) && (int) $data['batch_id'] > 0 ? (int) $data['batch_id'] : null,
        ]);

        return (int) $this->getInsertID();
    }

    /** True when at least one subsidy claim has been recorded under this control number. */
    public function hasClaims(int $controlNo): bool
    {
        if ($controlNo <= 0) {
            return false;
        }

        return $this->where('control_no', $controlNo)->countAllResults() > 0;
    }

    /**
     * Chronological (newest-first) subsidy history for a control number, with the
     * subsidy type name and the claimant's full name resolved via joins.
     */
    public function historyFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            return $this->select('subsidy_distribution.distribution_id, subsidy_distribution.claim_date,'
                    . ' subsidy_distribution.subsidy_type_id,'
                    . " subsidy.name AS subsidy_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
                ->join('subsidy', 'subsidy.subsidy_type_id = subsidy_distribution.subsidy_type_id', 'left')
                ->join('member', 'member.memberID = subsidy_distribution.memberID', 'left')
                ->where('subsidy_distribution.control_no', $controlNo)
                ->orderBy('subsidy_distribution.claim_date', 'DESC')
                ->orderBy('subsidy_distribution.distribution_id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Every distribution, newest first, with subsidy type name, claimant name,
     * family-head name, and the scanning user's username resolved via joins.
     * Drives the all-distributions table.
     */
    public function allDistributions(): array
    {
        try {
            return $this->select('subsidy_distribution.distribution_id, subsidy_distribution.control_no, subsidy_distribution.claim_date,'
                    . " subsidy.name AS subsidy_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant,"
                    . " TRIM(CONCAT(head.firstname, ' ', head.lastname)) AS head,"
                    . " COALESCE(users.username, '') AS scanned_by")
                ->join('subsidy', 'subsidy.subsidy_type_id = subsidy_distribution.subsidy_type_id', 'left')
                ->join('member', 'member.memberID = subsidy_distribution.memberID', 'left')
                ->join('qr_control', 'qr_control.control_no = subsidy_distribution.control_no', 'left')
                ->join('member head', 'head.memberID = qr_control.headID', 'left')
                ->join('users', 'users.userID = subsidy_distribution.userID', 'left')
                ->orderBy('subsidy_distribution.claim_date', 'DESC')
                ->orderBy('subsidy_distribution.distribution_id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The existing distribution row for this family in this batch, or null.
     * One family may only be logged once per batch; this is the probe the
     * scan endpoint uses to report "Duplicate Entry".
     */
    public function inBatch(int $controlNo, int $batchId): ?array
    {
        if ($controlNo <= 0 || $batchId <= 0) {
            return null;
        }

        try {
            $row = $this->select('subsidy_distribution.distribution_id, subsidy_distribution.claim_date,'
                    . ' subsidy_distribution.dt_created,'
                    . " COALESCE(users.username, '') AS scanned_by")
                ->join('users', 'users.userID = subsidy_distribution.userID', 'left')
                ->where('subsidy_distribution.control_no', $controlNo)
                ->where('subsidy_distribution.batch_id', $batchId)
                ->first();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Distinct families (control numbers) this user has served within a batch. */
    public function familiesForUserInBatch(int $userId, int $batchId): int
    {
        if ($userId <= 0 || $batchId <= 0) {
            return 0;
        }

        try {
            return (int) ($this->builder()
                ->select('COUNT(DISTINCT control_no) AS n')
                ->where('userID', $userId)
                ->where('batch_id', $batchId)
                ->get()->getRowArray()['n'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Hard-delete one distribution (void a wrong entry). Audited by the caller. */
    public function void(int $distributionId): bool
    {
        if ($distributionId <= 0) {
            return false;
        }

        return $this->delete($distributionId) !== false;
    }
}
