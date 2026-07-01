<?php

namespace App\Models\Lookups;

use App\Models\Concerns\ModelQueryHelpers;
use App\Models\Concerns\LookupModelTrait;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the `services` lookup table: admin CRUD/archival (create, update, find,
 * getActive) plus the per-batch eligibility lookups used by the family form and
 * search. Shared CRUD and management-search behaviour lives in LookupModelTrait.
 * The single model for the `services` table across the admin lookup screens and
 * family flows.
 */
class ServiceModel extends Model
{
    use LookupModelTrait;
    use ModelQueryHelpers;

    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['serviceID', 'category', 'name', 'description'];
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;

    /** Columns the Services management search box matches. */
    protected function lookupSearchColumns(): array
    {
        return ['name', 'category', 'description'];
    }

    /** Services management list order: Name first, then category/id for stability. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $this->applyNameFirstOrder($builder, ['category', $this->primaryKey]);
    }

    /**
     * Returns the next service ID (max + 1). serviceID is not auto-increment here,
     * so create flows assign it explicitly.
     */
    public function nextServiceId(): int
    {
        if (! $this->hasTable()) {
            return 1;
        }

        $row = $this->selectMax($this->primaryKey, 'max_id')->first();

        return ((int) ($row['max_id'] ?? 0)) + 1;
    }

    /**
     * Fetch active (non-archived) services from the read-only view (or the base
     * table filtered by dt_deleted). Frontend: the family form's service options.
     */
    public function getActive(): array
    {
        $builder = $this->activeBuilder();

        if ($builder === null) {
            return [];
        }

        return $builder
            ->orderBy('category', 'ASC')
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Fetch specific services by ID, including archived ones. Used by the family edit
     * form to keep showing services a member already has even after they were archived.
     *
     * @param list<int> $ids
     */
    public function getByIdsIncludingArchived(array $ids): array
    {
        $ids = $this->positiveUniqueIds($ids);

        if ($ids === [] || ! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->db->table($this->table)
            ->whereIn($this->primaryKey, $ids)
            ->orderBy('category', 'ASC')
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Returns a query builder scoped to active (non-archived) services: prefers the
     * `view_services_active` DB view, else filters dt_deleted on the base table.
     * Returns null if neither exists. Shared by the active-read methods above.
     */
    private function activeBuilder(): ?BaseBuilder
    {
        if ($this->db->tableExists('view_services_active')) {
            return $this->db->table('view_services_active');
        }

        if (! $this->db->tableExists($this->table)) {
            return null;
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder;
    }

    /**
     * Returns a [serviceID => name] map for the given IDs. Used by SearchModel to
     * label a member's assigned services in search results.
     */
    public function getNameMapByIds(array $serviceIds): array
    {
        $serviceIds = $this->naturalUniqueIds($serviceIds) ?? [];

        if ($serviceIds === []) {
            return [];
        }

        $rows = $this->select('serviceID, name')
            ->whereIn('serviceID', $serviceIds)
            ->findAll();

        $map = [];

        foreach ($rows as $row) {
            $id = (int) ($row['serviceID'] ?? 0);

            if ($id < 0) {
                continue;
            }

            $map[$id] = (string) ($row['name'] ?? '');
        }

        return $map;
    }

    /**
     * True if an active (non-archived) service with this ID exists. Used by
     * FamilyController::store() to validate selected services before linking them.
     */
    public function existsById(int $serviceId): bool
    {
        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $builder = $this->where($this->primaryKey, $serviceId);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted', null);
        }

        return $builder
            ->countAllResults() > 0;
    }
}
