<?php

namespace App\Models\Scanner;

use App\Models\Concerns\LookupModelTrait;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Subsidy-type reference lookup (Financial/Rice/Grocery, admin-editable) backing
 * the reference-data/subsidy-types page and the batch-open modal. Isolated from the
 * `services` table: subsidy types are their own concept, not services/programs.
 *
 * LookupModelTrait supplies the Subsidy Types management-table search/
 * pagination (searchLookup/countLookup/statusCounts), matching Sectors/
 * Services/Categories. This model's own create()/archive()/restore() (single-
 * field signatures already wired to the subsidy-types controller) take
 * precedence over the trait's generic versions.
 */
class SubsidyTypeModel extends Model
{
    use LookupModelTrait;

    protected $table         = 'subsidy';
    protected $primaryKey    = 'subsidy_type_id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'dt_deleted'];
    protected $useTimestamps = false;

    /** Columns the management search box matches. */
    protected function lookupSearchColumns(): array
    {
        return ['name'];
    }

    /** Alphabetical, matching all()/active(). */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $builder->orderBy('name', 'ASC');
    }

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

        try {
            if ($this->insert(['name' => $name, 'dt_deleted' => null]) === false) {
                return 0;
            }

            return (int) $this->getInsertID();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Soft-archive: stamp dt_deleted so it drops out of active(). */
    public function archive(int $id): bool
    {
        try {
            return $this->update($id, ['dt_deleted' => date('Y-m-d H:i:s')]) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Un-archive: clear dt_deleted. */
    public function restore(int $id): bool
    {
        try {
            return $this->update($id, ['dt_deleted' => null]) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete an aid type only when no distribution references it, checking and
     * deleting in one transaction to close the check-then-delete race.
     *
     * @return integer 0 = deleted, -1 = delete failed, >0 = still referenced (count)
     */
    public function deleteIfUnused(int $id): int
    {
        try {
            $this->db->transStart();

            $used = $this->db->table('subsidy_distribution')
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
            // transStart() opened a transaction that transComplete() never reached.
            // Without this the connection keeps it open for the rest of the request.
            $this->db->transRollback();

            return -1;
        }
    }
}
