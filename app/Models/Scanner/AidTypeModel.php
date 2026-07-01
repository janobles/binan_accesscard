<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Aid-type lookup for the scan log dropdown. Isolated from the `services`
 * table per the scanner-module boundary. CRUD is a later spec; this reads only.
 */
class AidTypeModel extends Model
{
    protected $table         = 'aid_type';
    protected $primaryKey    = 'aid_type_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'dt_deleted'];
    protected $useTimestamps = false;

    /** Non-archived aid types, ordered by name, for the dropdown. */
    public function active(): array
    {
        try {
            return $this->where('dt_deleted', null)
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
