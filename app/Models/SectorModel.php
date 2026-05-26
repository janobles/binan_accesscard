<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Manages the citizen sectors used for categorizing members.
 */
class SectorModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';
    protected $allowedFields = ['shortcode', 'name', 'description'];
    protected $useTimestamps = false;

    /**
     * Fetch active sectors from the read-only view used by lookups and forms.
     */
    public function getAll(): array
    {
        if (! $this->db->tableExists('view_sector_active')) {
            return [];
        }

        return $this->db->table('view_sector_active')
            ->orderBy('sectorID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Fetch all sectors, including archived records, for admin management.
     */
    public function getAllIncluding(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->orderBy('dt_deleted', 'ASC');
        }

        return $builder->orderBy('sectorID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Find a single sector row by ID for edit forms.
     */
    public function find($id = null)
    {
        if (! $this->hasTable()) {
            return null;
        }

        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Create a new sector record and return its insert ID.
     */
    public function create(array $data): int
    {
        $this->db->table($this->table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * Update a sector record by ID.
     */
    public function update($id = null, $row = null): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->update((array) $row);
    }

    /**
     * Soft-archive a sector by setting dt_deleted.
     */
    public function archive(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', 'NOW()', false)
            ->update();
    }

    /**
     * Restore a soft-archived sector by clearing dt_deleted.
     */
    public function restore(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', null)
            ->update();
    }

    /**
     * Check for an existing shortcode, excluding one ID when editing.
     */
    public function shortcodeExists(string $shortcode, ?int $excludeId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $builder = $this->db->table($this->table)
            ->where('shortcode', $shortcode);

        if ($excludeId !== null) {
            $builder->where($this->primaryKey . ' !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function countSectors(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

    public function getSectorOptions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        if ($this->db->tableExists('view_sector_active')) {
            return $this->db->table('view_sector_active')
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        }

        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $catalog = [
            'PWD' => [],
            'SP' => [],
            'OSCA' => [],
        ];

        foreach ($sectorOptions as $sector) {
            $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

            if ($shortcode === '') {
                continue;
            }

            $group = null;

            if (str_starts_with($shortcode, 'PWD')) {
                $group = 'PWD';
            } elseif (str_starts_with($shortcode, 'SP')) {
                $group = 'SP';
            } elseif (str_starts_with($shortcode, 'OSCA')) {
                $group = 'OSCA';
            }

            if ($group === null) {
                continue;
            }

            $catalog[$group][] = [
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => (string) ($sector['shortcode'] ?? ''),
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];
        }

        return $catalog;
    }
}
