<?php

namespace App\Models\Lookups;

use App\Models\Concerns\LookupModelTrait;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the service categories table (`category`). After the Phase A restructure
 * a category groups SERVICES by a short alpha code (SC, PWD, SP, B, FA, SWPS, EDA)
 * and a display name; services link here by the category NAME stored in
 * `services.category`. Every category is fully managed from the "Manage Categories"
 * admin page (rename, archive, restore). Shared CRUD and management-search behaviour
 * lives in LookupModelTrait.
 */
class CategoryModel extends Model
{
    use LookupModelTrait;

    protected $table = 'category';
    protected $primaryKey = 'categoryID';
    protected $returnType = 'array';
    protected $allowedFields = ['code', 'name'];
    protected $useTimestamps = false;

    /** Columns the Manage Categories search box matches. */
    protected function lookupSearchColumns(): array
    {
        return ['code', 'name'];
    }

    /** Manage Categories list order: by code. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $builder->orderBy('code', 'ASC');
    }

    /**
     * All categories including archived, for the management table, ordered by code.
     */
    public function getAllIncluding(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->db->table($this->table)
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Active (non-archived) categories for dropdowns and the family-form
     * grouping, ordered alphabetically by name.
     */
    public function getActive(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Find a single category row by its code (uppercased), or null. */
    public function findByCode(string $code): ?array
    {
        if (! $this->hasTable()) {
            return null;
        }

        return $this->db->table($this->table)
            ->where('code', strtoupper(trim($code)))
            ->get()
            ->getRowArray();
    }

    /** Check for an existing code (uppercased), excluding one ID when editing. */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        return $this->columnValueExists('code', strtoupper(trim($code)), $excludeId);
    }

    /**
     * True if any active category matches this code OR name (case-insensitive),
     * optionally excluding one categoryID. Used to keep sectors and service
     * categories disjoint: a new sector may not duplicate a standalone category.
     */
    public function activeCodeOrNameExists(string $code, string $name, ?int $excludeId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $code = strtoupper(trim($code));
        $name = strtolower(trim($name));

        if ($code === '' && $name === '') {
            return false;
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        if ($excludeId !== null) {
            $builder->where($this->primaryKey . ' !=', $excludeId);
        }

        $builder->groupStart();

        if ($code !== '') {
            $builder->where('UPPER(code)', $code);
        }

        if ($name !== '') {
            $builder->orWhere('LOWER(name)', $name);
        }

        $builder->groupEnd();

        return $builder->countAllResults() > 0;
    }

    /**
     * True if any active service uses this category (by its name). Guards
     * archiving/deleting a service category still in use. Categories are matched to
     * services by the category NAME stored in `services.category`.
     */
    public function isUsedByServices(string $name): bool
    {
        $name = trim($name);

        if ($name === '' || ! $this->db->tableExists('services')) {
            return false;
        }

        $builder = $this->db->table('services')->where('category', $name);

        if ($this->db->fieldExists('dt_deleted', 'services')) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->countAllResults() > 0;
    }
}
