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

    /** Active + archived, active first then alphabetical, for the management table. */
    public function all(): array
    {
        try {
            return $this->orderBy('dt_deleted IS NULL', 'DESC', false)
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Insert a new aid type; returns the new id (0 on failure or a blank name). */
    public function create(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        if ($this->insert(['name' => $name, 'dt_deleted' => null]) === false) {
            return 0;
        }

        return (int) $this->getInsertID();
    }

    /** Soft-archive: stamp dt_deleted so it drops out of active(). */
    public function archive(int $id): bool
    {
        return $this->update($id, ['dt_deleted' => date('Y-m-d H:i:s')]) !== false;
    }

    /** Un-archive: clear dt_deleted. */
    public function restore(int $id): bool
    {
        return $this->update($id, ['dt_deleted' => null]) !== false;
    }
}
