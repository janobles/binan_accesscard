<?php

namespace App\Models\Scanner;

use CodeIgniter\Model;

/**
 * Aid-type reference lookup (Financial/Rice/Grocery, admin-editable) backing
 * the admin/aidtypes page and the batch-open modal. Isolated from the
 * `services` table: subsidy types are their own concept, not services/programs.
 */
class AidTypeModel extends Model
{
    protected $table         = 'subsidy';
    protected $primaryKey    = 'subsidy_type_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'dt_deleted'];
    protected $useTimestamps = false;

    /** Non-archived subsidy types, ordered by name, for the dropdown. */
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

    /** Insert a new subsidy type; returns the new id (0 on failure or a blank name). */
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

    /**
     * Delete a subsidy type only when no distribution references it, checking and
     * deleting in one transaction to close the check-then-delete race. Returns 0
     * when the row was deleted, -1 when the delete failed, and the referencing
     * distribution count when it is still in use.
     */
    public function deleteIfUnused(int $id): int
    {
        try {
            $this->db->transStart();

            $used = $this->db->table('aid_distribution')
                ->where('subsidy_type_id', $id)
                ->countAllResults();
            if ($used > 0) {
                $this->db->transComplete();

                return $used;
            }

            $ok = $this->delete($id) !== false;
            $this->db->transComplete();

            return ($this->db->transStatus() && $ok) ? 0 : -1;
        } catch (\Throwable $e) {
            return -1;
        }
    }
}
