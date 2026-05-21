<?php

namespace App\Models;

use App\Support\SectorIds;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

/**
 * Centralizes search queries used by records, accounts, and audit trail pages.
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
        $builder = $this->db->table('view_member_dashboard')
            ->where('memberID = headID');

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('firstname', $keyword)
                ->orLike('middlename', $keyword)
                ->orLike('lastname', $keyword)
                ->orLike('contactnumber', $keyword)
                ->orLike('relationship', $keyword)
                ->orLike('head_firstname', $keyword)
                ->orLike('head_lastname', $keyword)
                ->orLike('sector_array_string', $keyword);

            foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
                $builder->orWhere(SectorIds::containsCondition($sectorId), null, false);
            }

            $builder->groupEnd();
        }

        $sectorId = (int) ($filters['sectorID'] ?? 0);

        if ($sectorId > 0) {
            $builder->where(SectorIds::containsCondition($sectorId), null, false);
        }

        $this->applyDateRange($builder, 'dt_created', $filters);

        $rows = $builder
            ->orderBy('memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
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

        return $builder
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function auditTrails(string $keyword = '', array $filters = [], int $limit = 50): array
    {
        if (! $this->hasAuditSearchTables()) {
            return [];
        }

        $limit = max(1, $limit);
        $builder = $this->auditTrailBuilder();
        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        $this->applyAuditFilters($builder, $filters);

        $rows = $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withAuditNames($rows);
    }

    public function auditTrailsByUser(int $userId, string $keyword = '', array $filters = [], int $limit = 50): array
    {
        if (! $this->hasAuditSearchTables()) {
            return [];
        }

        $limit = max(1, $limit);
        $builder = $this->auditTrailBuilder()
            ->where('audit_trails.userID', $userId);

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        $this->applyAuditFilters($builder, $filters);

        $rows = $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withAuditNames($rows);
    }

    public function auditActions(): array
    {
        if (! $this->db->tableExists('audit_trails')) {
            return [];
        }

        return array_column(
            $this->db->table('audit_trails')
                ->select('user_action')
                ->where('user_action IS NOT NULL')
                ->groupBy('user_action')
                ->orderBy('user_action', 'ASC')
                ->get()
                ->getResultArray(),
            'user_action'
        );
    }

    private function auditTrailBuilder(): BaseBuilder
    {
        return $this->db->table('audit_trails')
            ->select('audit_trails.*');
    }

    private function applyAuditSearch(BaseBuilder $builder, string $keyword): void
    {
        $builder->groupStart()
            ->like('audit_trails.user_action', $keyword)
            ->orLike('audit_trails.description', $keyword)
            ->orLike('audit_trails.ip_address', $keyword);

        $userIds = $this->userIdsForKeyword($keyword);

        if ($userIds !== []) {
            $builder->orWhereIn('audit_trails.userID', $userIds);
        }

        $memberIds = $this->memberIdsForKeyword($keyword);

        if ($memberIds !== []) {
            $builder->orWhereIn('audit_trails.memberID', $memberIds);
        }

        $builder->groupEnd();
    }

    private function applyAuditFilters(BaseBuilder $builder, array $filters): void
    {
        $action = $this->normalizeKeyword((string) ($filters['action'] ?? ''));

        if ($action !== '') {
            $builder->where('audit_trails.user_action', $action);
        }

        $this->applyDateRange($builder, 'audit_trails.dt_created', $filters);
    }

    private function applyDateRange(BaseBuilder $builder, string $column, array $filters): void
    {
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
        return $this->db->tableExists('view_member_dashboard')
            && $this->db->tableExists('sector');
    }

    private function hasAuditSearchTables(): bool
    {
        return $this->db->tableExists('audit_trails')
            && $this->db->tableExists('users')
            && $this->db->tableExists('member');
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

    private function withAuditNames(array $rows): array
    {
        $usernames = $this->usernameMap(array_column($rows, 'userID'));
        $memberNames = $this->memberNameMap(array_column($rows, 'memberID'));

        foreach ($rows as &$row) {
            $userId = (int) ($row['userID'] ?? 0);
            $memberId = (int) ($row['memberID'] ?? 0);
            $memberName = $memberNames[$memberId] ?? ['firstname' => '', 'lastname' => ''];

            $row['username'] = $usernames[$userId] ?? '';
            $row['firstname'] = $memberName['firstname'];
            $row['lastname'] = $memberName['lastname'];
        }

        return $rows;
    }

    private function usernameMap(array $userIds): array
    {
        $userIds = $this->positiveUniqueIds($userIds);

        if ($userIds === [] || ! $this->db->tableExists('users')) {
            return [];
        }

        $users = $this->db->table('users')
            ->select('userID, username')
            ->whereIn('userID', $userIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($users as $user) {
            $map[(int) $user['userID']] = (string) $user['username'];
        }

        return $map;
    }

    private function memberNameMap(array $memberIds): array
    {
        $memberIds = $this->positiveUniqueIds($memberIds);

        if ($memberIds === [] || ! $this->db->tableExists('member')) {
            return [];
        }

        $members = $this->db->table('member')
            ->select('memberID, firstname, lastname')
            ->whereIn('memberID', $memberIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($members as $member) {
            $map[(int) $member['memberID']] = [
                'firstname' => (string) $member['firstname'],
                'lastname' => (string) $member['lastname'],
            ];
        }

        return $map;
    }

    private function userIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('users')) {
            return [];
        }

        return array_map(
            static fn (array $user): int => (int) $user['userID'],
            $this->db->table('users')
                ->select('userID')
                ->like('username', $keyword)
                ->get()
                ->getResultArray()
        );
    }

    private function memberIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        return array_map(
            static fn (array $member): int => (int) $member['memberID'],
            $this->db->table('member')
                ->select('memberID')
                ->groupStart()
                ->like('firstname', $keyword)
                ->orLike('lastname', $keyword)
                ->groupEnd()
                ->get()
                ->getResultArray()
        );
    }

    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
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
