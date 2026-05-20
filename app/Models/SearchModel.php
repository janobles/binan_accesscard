<?php

namespace App\Models;

use App\Support\SectorIds;
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
        if (! $this->db->tableExists('member')) {
            return [];
        }

        $builder = $this->db->table('member')
            ->select('member.*, ' . SectorIds::sectorNameSelect(), false)
            ->where('member.headID = member.memberID');

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('member.firstname', $keyword)
                ->orLike('member.middlename', $keyword)
                ->orLike('member.lastname', $keyword)
                ->orLike('member.contactnumber', $keyword)
                ->orLike('member.relationship', $keyword)
                ->orWhere(SectorIds::sectorNameLikeCondition($keyword, 'member.sectorID', $this->db), null, false)
                ->groupEnd();
        }

        $sectorId = (int) ($filters['sectorID'] ?? 0);

        if ($sectorId > 0) {
            $builder->where(SectorIds::containsCondition($sectorId));
        }

        $this->applyDateRange($builder, 'member.dt_created', $filters);

        return $builder
            ->orderBy('member.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function staffAccounts(string $keyword = '', array $filters = [], int $limit = 100): array
    {
        if (! $this->db->tableExists('users')) {
            return [];
        }

        $builder = $this->db->table('users')
            ->select('userID, username, role, isactive')
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

        if (in_array($status, ['Enable', 'Disabled'], true)) {
            $builder->where('isactive', $status);
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

        $builder = $this->auditTrailBuilder();
        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        $this->applyAuditFilters($builder, $filters);

        return $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function auditTrailsByUser(int $userId, string $keyword = '', array $filters = [], int $limit = 50): array
    {
        if (! $this->hasAuditSearchTables()) {
            return [];
        }

        $builder = $this->auditTrailBuilder()
            ->where('audit_trails.userID', $userId);

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        $this->applyAuditFilters($builder, $filters);

        return $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
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

    private function auditTrailBuilder()
    {
        return $this->db->table('audit_trails')
            ->select('audit_trails.*, users.username, member.firstname, member.lastname')
            ->join('users', 'users.userID = audit_trails.userID')
            ->join('member', 'member.memberID = audit_trails.memberID', 'left');
    }

    private function applyAuditSearch($builder, string $keyword): void
    {
        $builder->groupStart()
            ->like('users.username', $keyword)
            ->orLike('audit_trails.user_action', $keyword)
            ->orLike('audit_trails.description', $keyword)
            ->orLike('audit_trails.ip_address', $keyword)
            ->orLike('member.firstname', $keyword)
            ->orLike('member.lastname', $keyword)
            ->groupEnd();
    }

    private function applyAuditFilters($builder, array $filters): void
    {
        $action = $this->normalizeKeyword((string) ($filters['action'] ?? ''));

        if ($action !== '') {
            $builder->where('audit_trails.user_action', $action);
        }

        $this->applyDateRange($builder, 'audit_trails.dt_created', $filters);
    }

    private function applyDateRange($builder, string $column, array $filters): void
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

    private function hasAuditSearchTables(): bool
    {
        return $this->db->tableExists('audit_trails')
            && $this->db->tableExists('users')
            && $this->db->tableExists('member');
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
