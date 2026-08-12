<?php

namespace App\Models\Concerns;

use App\Libraries\Qr\ControlNumber;
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
     * - sector names are resolved to sector IDs and matched through member_sectors
     * - optional service matches are used by deep search
     * - an exact QR match takes precedence and includes only that family
     */
    private function applyMemberKeyword(
        BaseBuilder $builder,
        string $keyword,
        string $prefix,
        array $likeColumns,
        array $serviceMemberIds = [],
        array $qrHeadIds = []
    ): void {
        if ($qrHeadIds !== []) {
            $builder->whereIn($prefix . 'headID', $qrHeadIds);

            return;
        }

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

        $keywordSectorIds = $this->sectorIdsForKeyword($keyword);

        if ($keywordSectorIds !== []) {
            $condition = $this->sectorMembershipCondition($keywordSectorIds, $prefix . 'memberID');

            if ($hasCondition) {
                $builder->orWhere($condition, null, false);
            } else {
                $builder->where($condition, null, false);
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

    /** Resolves an exact, digits-only QR number to its family head. */
    private function headIdsForQrKeyword(string $keyword): array
    {
        $controlNo = ControlNumber::parse(trim($keyword));

        if ($controlNo === null || ! $this->db->tableExists('qr_control')) {
            return [];
        }

        return array_map(
            static fn (array $row): int => (int) $row['headID'],
            $this->db->table('qr_control')
                ->select('headID')
                ->where('control_no', $controlNo)
                ->get()
                ->getResultArray()
        );
    }

    /**
     * Adds the shared sector filter for member queries. $memberIdColumn is the
     * member id in the caller's query ('member.memberID', 'm.memberID', or a
     * bare 'memberID'), which is what the junction is matched against.
     */
    private function applySectorIdFilter(BaseBuilder $builder, mixed $value, string $memberIdColumn): void
    {
        $sectorIds = $this->normalizeFilterList($value);

        if ($sectorIds === []) {
            return;
        }

        $builder->where($this->sectorMembershipCondition($sectorIds, $memberIdColumn), null, false);
    }

    /**
     * "member is in any of these sectors", as a subquery against member_sectors.
     * A member holds one row per sector there, so a join would multiply the
     * result rows; IN (SELECT ...) keeps one row per member, which is what
     * every caller here counts on.
     *
     * Ids are cast to int before they reach the SQL string, so nothing a caller
     * passes can be interpolated as anything but a number.
     */
    private function sectorMembershipCondition(array $sectorIds, string $memberIdColumn): string
    {
        $ids = implode(',', array_map('intval', $this->positiveUniqueIds($sectorIds)));

        if ($ids === '') {
            return '1 = 0';
        }

        return $this->qualifiedMemberColumn($memberIdColumn) . ' IN (SELECT memberID FROM '
            . $this->db->prefixTable('member_sectors') . ' WHERE sectorID IN (' . $ids . '))';
    }

    /**
     * Table-qualified column for raw SQL. The query builder adds DBPrefix to a
     * table name it is given, but a hand-written fragment is passed through
     * untouched, so "member.memberID" has to become "db_member.memberID" here or
     * it resolves to nothing on a prefixed connection (the test database is one).
     * An alias like "m." is left alone: an alias is never prefixed.
     */
    private function qualifiedMemberColumn(string $column): string
    {
        return str_starts_with($column, 'member.')
            ? $this->db->prefixTable('member') . substr($column, strlen('member'))
            : $column;
    }

    /**
     * Adds the shared barangay filter, matching member.barangayID against the
     * selected barangay names. $memberIdColumn is only used to qualify the
     * barangayID column in the caller's query ('member.', 'm.', or bare).
     *
     * Filtering used to run against the barangay text on the end of the address
     * and so answered by spelling: "Sto. Tomas" and "SANTO TOMAS" were
     * different barangays. V22 took that text out of the address; the id is now
     * the only source, and names resolve through the same fold the importer and
     * the form use.
     */
    private function applyBarangayFilter(BaseBuilder $builder, mixed $value, string $memberIdColumn): void
    {
        $barangays = $this->normalizeFilterList($value, false);

        if ($barangays === [] || ! $this->db->tableExists('barangay')) {
            return;
        }

        $idsByKey = (new \App\Models\Lookups\BarangayModel())->idByNormalizedName();
        $ids      = [];

        foreach ($barangays as $barangay) {
            $key = \App\Support\MemberFieldNormalizer::barangayKey((string) $barangay);

            if (isset($idsByKey[$key])) {
                $ids[] = $idsByKey[$key];
            }
        }

        $column = str_contains($memberIdColumn, '.')
            ? substr($memberIdColumn, 0, strrpos($memberIdColumn, '.') + 1) . 'barangayID'
            : 'barangayID';

        // A name matching no barangay row must return nothing rather than
        // everything, or an unknown filter value would silently widen the list.
        $builder->whereIn($column, $ids === [] ? [0] : array_unique($ids));
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
