<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Aid-distribution log: one row per handout against a QR control number.
 * historyFor() drives the per-QR chronological history panel.
 */
class AidDistributionModel extends Model
{
    protected $table         = 'aid_distribution';
    protected $primaryKey    = 'aidID';
    protected $returnType    = 'array';
    protected $allowedFields = ['control_no', 'memberID', 'subsidy_type_id', 'claim_date', 'userID', 'batch_id'];
    protected $useTimestamps = false;

    /** Inserts one distribution row and returns its aidID. */
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

    /** True when at least one aid claim has been recorded under this control number. */
    public function hasClaims(int $controlNo): bool
    {
        if ($controlNo <= 0) {
            return false;
        }

        return $this->where('control_no', $controlNo)->countAllResults() > 0;
    }

    /**
     * Chronological (newest-first) subsidy history for a control number, with
     * the scan timestamp, batch name, and subsidy type name resolved via
     * joins. Capped at the 10 most recent claims - drives the scan kiosk's
     * History tab.
     */
    public function historyFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . ' aid_distribution.dt_created, aid_distribution.subsidy_type_id,'
                    . " subsidy.name AS aid_type,"
                    . " distribution_batch.name AS batch_name,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
                ->join('subsidy', 'subsidy.subsidy_type_id = aid_distribution.subsidy_type_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->join('distribution_batch', 'distribution_batch.batch_id = aid_distribution.batch_id', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
                ->limit(10)
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Every audit-log entry for one control number, newest first, joined to
     * batch name and subsidy type name, optionally keyword/batch/subsidy-type
     * filtered. Not paginated - one QR control number's scan instances are a
     * small, bounded set. Powers the scan kiosk's "View all" page Audit Logs
     * tab: scan control number 1010 and this returns only 1010's scanned
     * instances; scan a different control number and it returns that one's.
     */
    public function historyAllFor(int $controlNo, string $keyword, int $batchId, int $aidTypeId): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            $builder = $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . ' aid_distribution.dt_created, aid_distribution.subsidy_type_id, aid_distribution.batch_id,'
                    . " subsidy.name AS aid_type,"
                    . " distribution_batch.name AS batch_name,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
                ->join('subsidy', 'subsidy.subsidy_type_id = aid_distribution.subsidy_type_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->join('distribution_batch', 'distribution_batch.batch_id = aid_distribution.batch_id', 'left')
                ->where('aid_distribution.control_no', $controlNo);

            if ($batchId > 0) {
                $builder->where('aid_distribution.batch_id', $batchId);
            }
            if ($aidTypeId > 0) {
                $builder->where('aid_distribution.subsidy_type_id', $aidTypeId);
            }
            if ($keyword !== '') {
                $builder->groupStart()
                    ->like('subsidy.name', $keyword)
                    ->orLike('distribution_batch.name', $keyword)
                    ->groupEnd();
            }

            return $builder
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Distinct batches this family has been logged in, with a claim count
     * each - drives the "View all" page's Batch filter.
     *
     * @return list<array{batch_id:int,name:string,n:int}>
     */
    public function batchCountsFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            $rows = $this->select('aid_distribution.batch_id, distribution_batch.name, COUNT(*) AS n')
                ->join('distribution_batch', 'distribution_batch.batch_id = aid_distribution.batch_id', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->groupBy('aid_distribution.batch_id, distribution_batch.name')
                ->orderBy('distribution_batch.name', 'ASC')
                ->findAll();

            return array_map(static fn (array $r): array => [
                'batch_id' => (int) $r['batch_id'],
                'name'     => (string) ($r['name'] ?? 'Unknown batch'),
                'n'        => (int) $r['n'],
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Distinct subsidy types this family has received, with a claim count
     * each - drives the "View all" page's Subsidy Type filter.
     *
     * @return list<array{subsidy_type_id:int,name:string,n:int}>
     */
    public function aidTypeCountsFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            $rows = $this->select('aid_distribution.subsidy_type_id, subsidy.name, COUNT(*) AS n')
                ->join('subsidy', 'subsidy.subsidy_type_id = aid_distribution.subsidy_type_id', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->groupBy('aid_distribution.subsidy_type_id, subsidy.name')
                ->orderBy('subsidy.name', 'ASC')
                ->findAll();

            return array_map(static fn (array $r): array => [
                'subsidy_type_id' => (int) $r['subsidy_type_id'],
                'name'            => (string) ($r['name'] ?? 'Subsidy'),
                'n'               => (int) $r['n'],
            ], $rows);
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
            return $this->select('aid_distribution.aidID, aid_distribution.control_no, aid_distribution.claim_date,'
                    . " subsidy.name AS aid_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant,"
                    . " TRIM(CONCAT(head.firstname, ' ', head.lastname)) AS head,"
                    . " COALESCE(users.username, '') AS scanned_by")
                ->join('subsidy', 'subsidy.subsidy_type_id = aid_distribution.subsidy_type_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->join('qr_control', 'qr_control.control_no = aid_distribution.control_no', 'left')
                ->join('member head', 'head.memberID = qr_control.headID', 'left')
                ->join('users', 'users.userID = aid_distribution.userID', 'left')
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
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
            $row = $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . ' aid_distribution.dt_created,'
                    . " COALESCE(users.username, '') AS scanned_by")
                ->join('users', 'users.userID = aid_distribution.userID', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->where('aid_distribution.batch_id', $batchId)
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
    public function void(int $aidId): bool
    {
        if ($aidId <= 0) {
            return false;
        }

        return $this->delete($aidId) !== false;
    }
}
