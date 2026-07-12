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
    protected $allowedFields = ['control_no', 'memberID', 'service_id', 'claim_date', 'userID', 'batch_id'];
    protected $useTimestamps = false;

    /** Inserts one distribution row and returns its aidID. */
    public function logAid(array $data): int
    {
        // Guard against a malformed handout: control number, claimant, and
        // service must all be positive ids and a claim date must be present.
        if ((int) ($data['control_no'] ?? 0) <= 0
            || (int) ($data['memberID'] ?? 0) <= 0
            || (int) ($data['service_id'] ?? 0) <= 0
            || empty($data['claim_date'])
        ) {
            return 0;
        }

        $this->insert([
            'control_no'  => (int) $data['control_no'],
            'memberID'    => (int) $data['memberID'],
            'service_id'  => (int) $data['service_id'],
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
     * Chronological (newest-first) aid history for a control number, with the
     * service name/shortcode and the claimant's full name resolved via joins.
     */
    public function historyFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . ' aid_distribution.service_id,'
                    . " services.name AS service, services.shortcode AS service_code,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
                ->join('services', 'services.serviceID = aid_distribution.service_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Every distribution, newest first, with service name/shortcode, claimant
     * name, family-head name, and the scanning user's username resolved via
     * joins. Drives the all-distributions table.
     */
    public function allDistributions(): array
    {
        try {
            return $this->select('aid_distribution.aidID, aid_distribution.control_no, aid_distribution.claim_date,'
                    . " services.name AS service, services.shortcode AS service_code,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant,"
                    . " TRIM(CONCAT(head.firstname, ' ', head.lastname)) AS head,"
                    . " COALESCE(users.username, '') AS scanned_by")
                ->join('services', 'services.serviceID = aid_distribution.service_id', 'left')
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
