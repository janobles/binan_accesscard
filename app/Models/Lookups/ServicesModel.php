<?php

namespace App\Models\Lookups;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages services lookups using the active view and base services table.
 */
class ServicesModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['category', 'name', 'description'];
    protected $useTimestamps = false;

    /**
     * Fetch active services from the read-only view.
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
     * Fetch all services, including archived, for admin management.
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
     * Find a single service row by ID for edit forms.
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
     * Fetch active services by category from the view.
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
     * List distinct active categories for use in UI options.
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
     * Create a new service record and return its insert ID.
     */
    public function create(array $data): int
    {
        $this->db->table($this->table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * Update a service record by ID.
     */
    public function update($id = null, $row = null): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->update((array) $row);
    }

    /**
     * Soft-archive a service by setting dt_deleted.
     */
    public function archive(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', 'NOW()', false)
            ->update();
    }

    /**
     * Restore a soft-archived service by clearing dt_deleted.
     */
    public function restore(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', null)
            ->update();
    }

    /**
     * Returns a query builder scoped to active (non-archived) services: prefers
     * the `view_services_active` DB view, else filters dt_deleted on the base
     * table. Returns null if neither exists. Shared by the read methods above.
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
}
