<?php

namespace App\Models\Lookups;

use App\Libraries\SectorIds;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the `services` lookup table: admin CRUD/archival (create, update, find,
 * getAll/getAllIncluding, getByCategory, getCategories), plus per-member and
 * per-sector eligibility lookups used by the family form and search. The single
 * model for the `services` table across the admin lookup screens and family flows.
 */
class ServiceModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['serviceID', 'category', 'name', 'description'];
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;

    /** True if the `services` table exists. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
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
     * Soft-archive a service/program by stamping dt_deleted. The row is hidden,
     * never deleted.
     */
    public function archive(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', 'NOW()', false)
            ->update();
    }

    /**
     * Restore a soft-archived service/program by clearing dt_deleted.
     */
    public function restore(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', null)
            ->update();
    }

    /**
     * Fetch active (non-archived) services from the read-only view (or the base
     * table filtered by dt_deleted). Frontend: the family form's service options.
     */
    public function getAll(): array
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
     * Fetch all services, including archived, for the admin lookup management screen.
     */
    public function getAllIncluding(): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->db->table($this->table)
            ->orderBy('category', 'ASC')
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Find a single service row by ID (including archived) for the admin edit/
     * archive/restore flows. Returns null when the table or row is missing.
     */
    public function find($id = null)
    {
        if (! $this->db->tableExists($this->table)) {
            return null;
        }

        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Fetch active services in one category from the view, for category-scoped UI.
     */
    public function getByCategory(string $category): array
    {
        $builder = $this->activeBuilder();

        if ($builder === null) {
            return [];
        }

        return $builder
            ->where('category', $category)
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * List distinct active service categories for use in UI options.
     */
    public function getCategories(): array
    {
        $builder = $this->activeBuilder();

        if ($builder === null) {
            return [];
        }

        $rows = $builder
            ->distinct()
            ->select('category')
            ->orderBy('category', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn (array $row): string => (string) ($row['category'] ?? ''),
            $rows
        ));
    }

    /**
     * Create a new service record (admin lookup screen) and return its insert ID.
     * Lets the DB auto-assign serviceID; the family-form create path instead sets
     * serviceID explicitly via nextServiceId().
     */
    public function create(array $data): int
    {
        $this->db->table($this->table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * Update a service record by ID (admin lookup screen). Overrides the framework
     * update() with a builder write scoped to the services table.
     */
    public function update($id = null, $row = null): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->update((array) $row);
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
        $serviceIds = $this->naturalIds($serviceIds) ?? [];

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
     * Lists services available to a sector: those in that sector's category plus
     * any 'General' services. Used when building eligibility options.
     */
    public function getForSectorName(string $sectorName): array
    {
        return $this->groupStart()
            ->where('category', $sectorName)
            ->orWhere('category', 'General')
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Resolves the services a specific member is eligible for, based on their
     * sector(s) plus 'General' services. Frontend: drives the per-member service
     * checklist in the family form/edit views.
     */
    public function getEligibleForMember(int $memberId): array
    {
        $member = $this->db->table('member')
            ->select('sectorID')
            ->where('member.memberID', $memberId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            return [];
        }

        $sectorIds = SectorIds::normalize($member['sectorID'] ?? null);

        if ($sectorIds === []) {
            return [];
        }

        $sectorNames = array_column(
            $this->db->table('sector')
                ->select('name')
                ->whereIn('sectorID', $sectorIds)
                ->get()
                ->getResultArray(),
            'name'
        );

        if ($sectorNames === []) {
            return [];
        }

        return $this->groupStart()
            ->whereIn('category', $sectorNames)
            ->orWhere('category', 'General')
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll();
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

    /**
     * True if every given ID is an existing active service (empty list = true).
     * Used to validate a batch of selected service IDs.
     */
    public function idsExist(array $serviceIds): bool
    {
        $serviceIds = $this->naturalIds($serviceIds);

        if ($serviceIds === null) {
            return false;
        }

        if ($serviceIds === []) {
            return true;
        }

        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $builder = $this->whereIn($this->primaryKey, $serviceIds);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted', null);
        }

        return $builder
            ->countAllResults() === count($serviceIds);
    }

    /**
     * Normalizes a list of service IDs to unique non-negative ints, or null if any
     * value is non-numeric/nested — letting callers reject malformed input.
     */
    private function naturalIds(array $serviceIds): ?array
    {
        $normalizedIds = [];

        foreach ($serviceIds as $serviceId) {
            if (is_array($serviceId)) {
                return null;
            }

            $serviceId = trim((string) $serviceId);

            if ($serviceId === '' || ! ctype_digit($serviceId)) {
                return null;
            }

            $normalizedIds[] = (int) $serviceId;
        }

        return array_values(array_unique($normalizedIds));
    }
}
