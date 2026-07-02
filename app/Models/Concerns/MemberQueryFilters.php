<?php

namespace App\Models\Concerns;

use App\Libraries\SectorIds;
use CodeIgniter\Database\BaseBuilder;

/**
 * Shared member search/filter helpers used by MemberModel and SearchModel.
 *
 * Hosting classes must expose $this->db and use NormalizesIds.
 */
trait MemberQueryFilters
{
    /**
     * Adds the shared member keyword search:
     * - every name token must match a name column
     * - the whole keyword may also match contact/relationship/extra fields
     * - sector names are resolved to sector IDs and matched inside sectorID JSON
     * - optional service matches are used by deep search
     */
    private function applyMemberKeyword(
        BaseBuilder $builder,
        string $keyword,
        string $prefix,
        array $likeColumns,
        string $sectorColumn,
        array $serviceMemberIds = []
    ): void {
        $tokens = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [$keyword];
        $nameColumns = $this->existingMemberColumns(['firstname', 'middlename', 'lastname', 'suffix']);

        $builder->groupStart();

        if ($nameColumns !== []) {
            $builder->groupStart();

            foreach ($tokens as $token) {
                $builder->groupStart();

                foreach ($nameColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->like($prefix . $column, $token);
                    } else {
                        $builder->orLike($prefix . $column, $token);
                    }
                }

                $builder->groupEnd();
            }

            $builder->groupEnd();
            $builder->orGroupStart();
        } else {
            $builder->groupStart();
        }

        $hasCondition = false;

        foreach (array_values(array_unique(array_merge(['contactnumber', 'relationship'], $likeColumns))) as $field) {
            if ($this->memberFieldExistsForQuery($field)) {
                $this->addKeywordLike($builder, $prefix . $field, $keyword, $hasCondition);
            }
        }

        foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
            if ($hasCondition) {
                $builder->orWhere(SectorIds::containsCondition($sectorId, $sectorColumn), null, false);
            } else {
                $builder->where(SectorIds::containsCondition($sectorId, $sectorColumn), null, false);
                $hasCondition = true;
            }
        }

        if ($serviceMemberIds !== []) {
            if ($hasCondition) {
                $builder->orWhereIn($prefix . 'memberID', $serviceMemberIds);
            } else {
                $builder->whereIn($prefix . 'memberID', $serviceMemberIds);
                $hasCondition = true;
            }
        }

        if (! $hasCondition) {
            $builder->where('1 = 0', null, false);
        }

        $builder->groupEnd();
        $builder->groupEnd();
    }

    /** Adds the shared sectorID filter for member queries. */
    private function applySectorIdFilter(BaseBuilder $builder, mixed $value, string $sectorColumn): void
    {
        $sectorIds = $this->normalizeFilterList($value);

        if ($sectorIds === []) {
            return;
        }

        $builder->groupStart();

        foreach ($sectorIds as $index => $sectorId) {
            if ($index === 0) {
                $builder->where(SectorIds::containsCondition((int) $sectorId, $sectorColumn), null, false);
                continue;
            }

            $builder->orWhere(SectorIds::containsCondition((int) $sectorId, $sectorColumn), null, false);
        }

        $builder->groupEnd();
    }

    /**
     * Adds the shared barangay filter. Newer flows store barangay at the end of
     * address, but this also supports a real barangay column if a schema has one.
     */
    private function applyBarangayFilter(
        BaseBuilder $builder,
        mixed $value,
        string $addressColumn,
        ?string $barangayColumn = null
    ): void {
        $barangays = $this->normalizeFilterList($value, false);

        if ($barangays === []) {
            return;
        }

        $hasAddressColumn = $this->memberFieldExistsForQuery($this->unqualifiedMemberColumn($addressColumn));
        $hasBarangayColumn = $barangayColumn !== null
            && $this->memberFieldExistsForQuery($this->unqualifiedMemberColumn($barangayColumn));

        if (! $hasAddressColumn && ! $hasBarangayColumn) {
            return;
        }

        $builder->groupStart();

        foreach ($barangays as $index => $barangay) {
            $open = $index === 0 ? 'groupStart' : 'orGroupStart';
            $builder->{$open}();
            $hasCondition = false;

            if ($hasBarangayColumn) {
                $builder->where($barangayColumn, $barangay);
                $hasCondition = true;
            }

            if ($hasAddressColumn) {
                if ($hasCondition) {
                    $builder->orWhere($addressColumn, $barangay);
                } else {
                    $builder->where($addressColumn, $barangay);
                    $hasCondition = true;
                }

                $builder->orLike($addressColumn, ', ' . $barangay, 'before');
            }

            $builder->groupEnd();
        }

        $builder->groupEnd();
    }

    /** Applies a whole-day or date-range filter to a date/datetime column. */
    private function applyDateRange(BaseBuilder $builder, string $column, array $filters): void
    {
        $date = $this->normalizeDate((string) ($filters['date'] ?? ''));

        if ($date !== '') {
            $builder
                ->where($column . ' >=', $date . ' 00:00:00')
                ->where($column . ' <=', $date . ' 23:59:59');

            return;
        }

        $dateFrom = $this->normalizeDate((string) ($filters['date_from'] ?? ''));
        $dateTo = $this->normalizeDate((string) ($filters['date_to'] ?? ''));

        if ($dateFrom !== '') {
            $builder->where($column . ' >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== '') {
            $builder->where($column . ' <=', $dateTo . ' 23:59:59');
        }
    }

    /** Finds sector IDs whose name/description match a search keyword. */
    private function sectorIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('sector')) {
            return [];
        }

        return array_map(
            static fn (array $sector): int => (int) $sector['sectorID'],
            $this->db->table('sector')
                ->select('sectorID')
                ->like('name', $keyword)
                ->orLike('description', $keyword)
                ->get()
                ->getResultArray()
        );
    }

    /** Returns the date only if it is a valid Y-m-d value. */
    private function normalizeDate(string $date): string
    {
        $date = trim($date);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
    }

    /** Adds a LIKE or OR LIKE depending on whether the group already has a condition. */
    private function addKeywordLike(BaseBuilder $builder, string $column, string $keyword, bool &$hasCondition): void
    {
        if ($hasCondition) {
            $builder->orLike($column, $keyword);
        } else {
            $builder->like($column, $keyword);
            $hasCondition = true;
        }
    }

    /** Keeps member keyword search tolerant of schema differences. */
    private function existingMemberColumns(array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column): bool => $this->memberFieldExistsForQuery($column)
        ));
    }

    private function memberFieldExistsForQuery(string $field): bool
    {
        return $this->db->fieldExists($field, 'member');
    }

    private function unqualifiedMemberColumn(string $column): string
    {
        $column = str_replace('`', '', trim($column));
        $parts = explode('.', $column);

        return (string) end($parts);
    }
}
