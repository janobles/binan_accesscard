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
    protected $allowedFields = ['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID'];
    protected $useTimestamps = false;

    /** Inserts one distribution row and returns its aidID. */
    public function logAid(array $data): int
    {
        $this->insert([
            'control_no'  => (int) $data['control_no'],
            'memberID'    => (int) $data['memberID'],
            'aid_type_id' => (int) $data['aid_type_id'],
            'claim_date'  => $data['claim_date'],
            'userID'      => isset($data['userID']) && (int) $data['userID'] > 0 ? (int) $data['userID'] : null,
        ]);

        return (int) $this->getInsertID();
    }

    /**
     * Chronological (newest-first) aid history for a control number, with the
     * aid-type name and the claimant's full name resolved via joins.
     */
    public function historyFor(int $controlNo): array
    {
        if ($controlNo <= 0) {
            return [];
        }

        try {
            return $this->select('aid_distribution.aidID, aid_distribution.claim_date,'
                    . " aid_type.name AS aid_type,"
                    . " TRIM(CONCAT(member.firstname, ' ', member.lastname)) AS claimant")
                ->join('aid_type', 'aid_type.aid_type_id = aid_distribution.aid_type_id', 'left')
                ->join('member', 'member.memberID = aid_distribution.memberID', 'left')
                ->where('aid_distribution.control_no', $controlNo)
                ->orderBy('aid_distribution.claim_date', 'DESC')
                ->orderBy('aid_distribution.aidID', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
