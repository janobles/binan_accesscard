<?php

namespace App\Models;

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

    public function families(string $keyword = '', int $limit = 25): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        $builder = $this->db->table('member')
            ->select('member.*, sector.name AS sector_name')
            ->join('sector', 'sector.sectorID = member.sectorID', 'left')
            ->where('member.headID = member.memberID');

        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('member.firstname', $keyword)
                ->orLike('member.middlename', $keyword)
                ->orLike('member.lastname', $keyword)
                ->orLike('member.contactnumber', $keyword)
                ->orLike('member.relationship', $keyword)
                ->orLike('sector.name', $keyword)
                ->groupEnd();
        }

        return $builder
            ->orderBy('member.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function staffAccounts(string $keyword = '', int $limit = 100): array
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

        return $builder
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function auditTrails(string $keyword = '', int $limit = 50): array
    {
        if (! $this->hasAuditSearchTables()) {
            return [];
        }

        $builder = $this->auditTrailBuilder();
        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        return $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function auditTrailsByUser(int $userId, string $keyword = '', int $limit = 50): array
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

        return $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
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
}
