<?php

namespace App\Models\Concerns;

use CodeIgniter\Database\BaseBuilder;

/**
 * Shared CRUD + management-search machinery for the lookup models
 * (SectorModel, ServiceModel, CategoryModel). They differ only in their
 * searchable columns and sort order, supplied by lookupSearchColumns() and
 * applyLookupOrder(); everything else — soft archive/restore, status-filtered
 * search/count, find/create/update, existence checks — is identical and lives
 * here. Hosting models extend CodeIgniter\Model, so $db, $table and $primaryKey
 * are available.
 */
trait LookupModelTrait
{
    /** Columns the management search box matches (first uses LIKE, rest OR LIKE). */
    abstract protected function lookupSearchColumns(): array;

    /** Applies the model-specific result ordering to a management-list builder. */
    abstract protected function applyLookupOrder(BaseBuilder $builder): void;

    /** True if the backing table exists; callers guard queries with this. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * Status-aware, keyword-filtered builder for the management list.
     * $status: active|archived|all (see RecordStatus).
     */
    private function lookupBuilder(?string $keyword, string $status): BaseBuilder
    {
        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            if ($status === RecordStatus::ARCHIVED) {
                $builder->where('dt_deleted IS NOT NULL', null, false);
            } elseif ($status !== RecordStatus::ALL) {
                $builder->where('dt_deleted IS NULL', null, false);
            }
        }

        $keyword = trim((string) $keyword);

        if ($keyword !== '') {
            $builder->groupStart();

            foreach (array_values($this->lookupSearchColumns()) as $index => $column) {
                if ($index === 0) {
                    $builder->like($column, $keyword);
                } else {
                    $builder->orLike($column, $keyword);
                }
            }

            $builder->groupEnd();
        }

        return $builder;
    }

    /**
     * One page of rows for the management list: status-filtered, keyword-matched,
     * active rows first (dt_deleted ASC) then the model's own order.
     * $status: active|archived|all.
     */
    public function searchLookup(?string $keyword, string $status = RecordStatus::ACTIVE, int $limit = 50, int $offset = 0): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->lookupBuilder($keyword, $status);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->orderBy('dt_deleted', 'ASC');
        }

        $this->applyLookupOrder($builder);

        return $builder
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->getResultArray();
    }

    /** Total rows matching the keyword/status filter (for pagination). */
    public function countLookup(?string $keyword, string $status = RecordStatus::ACTIVE): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->lookupBuilder($keyword, $status)->countAllResults();
    }

    /** Unfiltered active/archived totals for the status dropdown badges. */
    public function statusCounts(): array
    {
        return [
            'active'   => $this->countLookup(null, RecordStatus::ACTIVE),
            'archived' => $this->countLookup(null, RecordStatus::ARCHIVED),
        ];
    }

    /** Find a single row by primary key (includes archived), or null. */
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

    /** Insert a new row and return its insert ID. */
    public function create(array $data): int
    {
        $this->db->table($this->table)->insert($data);

        return (int) $this->db->insertID();
    }

    /** Update a row by primary key. */
    public function update($id = null, $row = null): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->update((array) $row);
    }

    /** Soft-archive a row by stamping dt_deleted = NOW(). */
    public function archive(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', 'NOW()', false)
            ->update();
    }

    /** Restore a soft-archived row by clearing dt_deleted. */
    public function restore(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', null)
            ->update();
    }

    /**
     * True if a row already has $value in $column (the caller handles any case/
     * whitespace normalization), optionally excluding one primary key on edit.
     */
    private function columnValueExists(string $column, string $value, ?int $excludeId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $builder = $this->db->table($this->table)
            ->where($column, $value);

        if ($excludeId !== null) {
            $builder->where($this->primaryKey . ' !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }
}
