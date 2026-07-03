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
}
