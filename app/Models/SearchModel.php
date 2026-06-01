<?php

namespace App\Models;

use App\Libraries\SectorIds;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Centralizes search queries used by records and account pages.
 */
class SearchModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function families(string $keyword = '', array $filters = [], int $limit = 25): array
    {
        if (! $this->hasFamilySearchTables()) {
            return [];
        }

        $limit = max(1, $limit);
        $builder = $this->db->table('member')
            ->select('memberID, firstname, middlename, lastname, contactnumber, relationship, headID, sectorID, dt_created, dt_updated')
            ->where('memberID = headID', null, false)
            ->where('dt_deleted IS NULL', null, false);

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('firstname', $keyword)
                ->orLike('middlename', $keyword)
                ->orLike('lastname', $keyword)
                ->orLike('contactnumber', $keyword)
                ->orLike('relationship', $keyword)
                ->orLike('sectorID', $keyword);

            foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
                $builder->orWhere(SectorIds::containsCondition($sectorId, 'sectorID'), null, false);
            }

            $builder->groupEnd();
        }

        $sectorId = (int) ($filters['sectorID'] ?? 0);

        if ($sectorId > 0) {
            $builder->where(SectorIds::containsCondition($sectorId, 'sectorID'), null, false);
        }

        $this->applyDateRange($builder, 'dt_created', $filters);

        $rows = $builder
            ->orderBy('memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    /**
     * SECOND ("search the whole database") bar of the Manage Records tab.
     *
     * Unlike families() this is NOT limited to family heads -- it searches every
     * member (heads AND non-head family members). The keyword also matches sector
     * names and service/program names, so a person can be found by the sector or
     * assistance they are tied to. Each row carries its head ("belongs to") name,
     * resolved sector names, and resolved service names.
     *
     * Called from App\Libraries\DashboardPageBuilder::buildMemberListData() and
     * Employee\WorkspaceModel::recordListData() when the deep search box (deep_q) is used.
     */
    public function allMembers(string $keyword = '', array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $rows = $this->allMembersBuilder($keyword, $filters)
            ->orderBy('m.lastname', 'ASC')
            ->orderBy('m.firstname', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return $this->withServiceNames($this->withSectorNames($rows));
    }

    /**
     * Row count for the deep member search (drives the deep-search pagination).
     */
    public function countAllMembers(string $keyword = '', array $filters = []): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->allMembersBuilder($keyword, $filters)->countAllResults();
    }

    /**
     * Shared query for allMembers()/countAllMembers(): every member (incl. non-heads),
     * left-joined to its head, with keyword across member fields + sector + service,
     * plus the Manage Records filters (sectorID, date, active/archived status).
     */
    private function allMembersBuilder(string $keyword, array $filters): BaseBuilder
    {
        $builder = $this->db->table('member m')
            ->select('m.memberID, m.firstname, m.middlename, m.lastname, m.contactnumber, m.relationship, m.headID, m.sectorID, m.dt_created, h.firstname AS head_firstname, h.lastname AS head_lastname')
            ->join('member h', 'h.memberID = m.headID', 'left');

        $status = strtolower(trim((string) ($filters['status'] ?? '')));

        if ($status === 'archived') {
            $builder->where('m.dt_deleted IS NOT NULL', null, false);
        } else {
            $builder->where('m.dt_deleted IS NULL', null, false);
        }

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('m.firstname', $keyword)
                ->orLike('m.middlename', $keyword)
                ->orLike('m.lastname', $keyword)
                ->orLike('m.contactnumber', $keyword)
                ->orLike('m.relationship', $keyword);

            foreach (['address', 'religion', 'job'] as $field) {
                if ($this->db->fieldExists($field, 'member')) {
                    $builder->orLike('m.' . $field, $keyword);
                }
            }

            // Match by sector name -> sector IDs -> JSON array contains the ID.
            foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
                $builder->orWhere(SectorIds::containsCondition($sectorId, 'm.sectorID'), null, false);
            }

            // Match by service/program name -> the members assigned that service.
            $serviceMemberIds = $this->memberIdsForServiceKeyword($keyword);

            if ($serviceMemberIds !== []) {
                $builder->orWhereIn('m.memberID', $serviceMemberIds);
            }

            $builder->groupEnd();
        }

        $sectorId = (int) ($filters['sectorID'] ?? 0);

        if ($sectorId > 0) {
            $builder->where(SectorIds::containsCondition($sectorId, 'm.sectorID'), null, false);
        }

        $this->applyDateRange($builder, 'm.dt_created', $filters);

        return $builder;
    }

    public function staffAccounts(string $keyword = '', array $filters = [], int $limit = 100): array
    {
        if (! $this->db->tableExists('users')) {
            return [];
        }

        $limit = max(1, $limit);
        $builder = $this->db->table('users')
            ->select('userID, username, role, isactive, dt_created')
            ->whereIn('role', ['Admin', 'User']);

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('username', $keyword)
                ->orLike('role', $keyword)
                ->orLike('isactive', $keyword)
                ->groupEnd();
        }

        $role = $this->normalizeKeyword((string) ($filters['role'] ?? ''));

        if (in_array($role, ['Admin', 'User'], true)) {
            $builder->where('role', $role);
        }

        $status = $this->normalizeKeyword((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $this->applyActiveStatusFilter($builder, $status);
        }

        $rows = $builder
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $rows;
    }

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

    private function applyActiveStatusFilter(BaseBuilder $builder, string $status): void
    {
        $normalized = strtolower($status);

        if (in_array($normalized, ['enable', 'enabled', 'active', '1', 'true', 'yes', 'on'], true)) {
            $builder->groupStart()
                ->where('isactive', 'Enable')
                ->orWhere('isactive', 'Enabled')
                ->orWhere('isactive', 1)
                ->orWhere('isactive', '1')
                ->groupEnd();

            return;
        }

        if (in_array($normalized, ['disable', 'disabled', 'inactive', '0', 'false', 'no', 'off'], true)) {
            $builder->groupStart()
                ->where('isactive', 'Disabled')
                ->orWhere('isactive', 0)
                ->orWhere('isactive', '0')
                ->groupEnd();
        }
    }

    private function hasFamilySearchTables(): bool
    {
        return $this->db->tableExists('member')
            && $this->db->tableExists('sector');
    }

    private function withSectorNames(array $rows): array
    {
        $sectorNames = $this->sectorNameMap();

        foreach ($rows as &$row) {
            $sectorValue = $row['sector_array_string'] ?? $row['sectorID'] ?? '[]';
            $row['sectorID'] = $sectorValue;
            $row['sector_name'] = SectorIds::toNames($sectorValue, $sectorNames);
        }

        return $rows;
    }

    private function sectorNameMap(): array
    {
        if (! $this->db->tableExists('sector')) {
            return [];
        }

        $sectors = $this->db->table('sector')
            ->select('sectorID, name')
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($sectors as $sector) {
            $map[(int) $sector['sectorID']] = (string) $sector['name'];
        }

        return $map;
    }

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

    // Service/program IDs whose name or category matches the keyword (for deep search).
    private function serviceIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('services')) {
            return [];
        }

        return array_map(
            static fn (array $service): int => (int) $service['serviceID'],
            $this->db->table('services')
                ->select('serviceID')
                ->groupStart()
                    ->like('name', $keyword)
                    ->orLike('category', $keyword)
                ->groupEnd()
                ->get()
                ->getResultArray()
        );
    }

    // Member IDs assigned any service matching the keyword (services -> member_services).
    private function memberIdsForServiceKeyword(string $keyword): array
    {
        $serviceIds = $this->serviceIdsForKeyword($keyword);

        if ($serviceIds === [] || ! $this->db->tableExists('member_services')) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['memberID'],
            $this->db->table('member_services')
                ->select('memberID')
                ->whereIn('serviceID', $serviceIds)
                ->get()
                ->getResultArray()
        )));
    }

    // Adds a comma-separated 'service_name' to each deep-search row, reusing the
    // member_services junction (MemberServiceModel) and the services name map (ServiceModel).
    private function withServiceNames(array $rows): array
    {
        if ($rows === [] || ! $this->db->tableExists('member_services')) {
            return $rows;
        }

        $serviceMap = (new \App\Models\MemberServiceModel())
            ->getServiceIdsByMemberIds(array_column($rows, 'memberID'));

        $allServiceIds = [];

        foreach ($serviceMap as $ids) {
            foreach ((array) $ids as $id) {
                $allServiceIds[] = (int) $id;
            }
        }

        $serviceNameMap = (new \App\Models\ServiceModel())->getNameMapByIds($allServiceIds);

        foreach ($rows as &$row) {
            $memberId = (int) ($row['memberID'] ?? 0);
            $names = [];

            foreach ((array) ($serviceMap[$memberId] ?? []) as $serviceId) {
                $serviceId = (int) $serviceId;

                if (isset($serviceNameMap[$serviceId])) {
                    $names[] = $serviceNameMap[$serviceId];
                }
            }

            $row['service_name'] = implode(', ', $names);
        }

        return $rows;
    }

    private function normalizeKeyword(string $keyword): string
    {
        return trim($keyword);
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
    }
}
