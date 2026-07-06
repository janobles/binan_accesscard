<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Maps a paper QR control number (1..100000) to a family head's memberID.
 * Pre-loaded from the encoders' Excel; this app only reads it.
 */
class QrControlModel extends Model
{
    protected $table         = 'qr_control';
    protected $primaryKey    = 'control_no';
    protected $returnType    = 'array';
    protected $allowedFields = ['control_no', 'headID'];
    protected $useTimestamps = false;
    // control_no is the paper QR number supplied by the import, not auto-generated.
    protected $useAutoIncrement = false;

    /**
     * Maps a paper QR control number to a head. Throws on a duplicate control_no
     * (the family transaction rolls back and the row is reported as failed).
     */
    public function assign(int $controlNo, int $headID): void
    {
        if ($controlNo <= 0 || $headID <= 0) {
            return;
        }

        $this->insert(['control_no' => $controlNo, 'headID' => $headID]);
    }

    /** Returns the mapped headID for a control number, or null when unmapped. */
    public function headForControl(int $controlNo): ?int
    {
        if ($controlNo <= 0) {
            return null;
        }

        $row = $this->where('control_no', $controlNo)->first();

        return $row === null ? null : (int) $row['headID'];
    }

    /** Returns the control number currently mapped to a head, or null when unmapped. */
    public function controlForHead(int $headId): ?int
    {
        if ($headId <= 0) {
            return null;
        }

        $row = $this->where('headID', $headId)->first();

        return $row === null ? null : (int) $row['control_no'];
    }

    /** True when $controlNo is already assigned to a head other than $headId. */
    public function takenByOtherHead(int $controlNo, int $headId): bool
    {
        if ($controlNo <= 0) {
            return false;
        }

        $row = $this->where('control_no', $controlNo)->first();

        return $row !== null && (int) $row['headID'] !== $headId;
    }

    /**
     * Insert or move a head's control-number mapping to $controlNo. No-op when the
     * head already maps to $controlNo. Throws when $controlNo belongs to another head.
     */
    public function upsertForHead(int $controlNo, int $headId): void
    {
        if ($controlNo <= 0 || $headId <= 0) {
            return;
        }

        if ($this->takenByOtherHead($controlNo, $headId)) {
            throw new \RuntimeException('QR Number ' . $controlNo . ' is already assigned to another family.');
        }

        $existing = $this->controlForHead($headId);
        if ($existing === $controlNo) {
            return;
        }

        // control_no is the primary key, so a "move" is delete-then-insert of the
        // head's row (there is at most one row per head).
        if ($existing !== null) {
            $this->where('headID', $headId)->delete();
        }

        $this->insert(['control_no' => $controlNo, 'headID' => $headId]);
    }
}
