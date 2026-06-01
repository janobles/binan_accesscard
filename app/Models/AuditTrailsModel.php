<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Records and retrieves audit trail entries for staff actions.
 */
class AuditTrailsModel extends Model
{
    protected $table = 'audit_trails';
    protected $primaryKey = 'auditID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_action',
        'description',
        'ip_address',
        'user_agent',
        'userID',
        'memberID',
    ];
    protected $useTimestamps = false;

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function logAction(
        int $userId,
        ?int $memberId,
        string $action,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        $memberId = $this->memberIdValue($memberId);

        if ($memberId === null && ! $this->isMemberIdNullable()) {
            log_message('error', 'Audit trail skipped: audit_trails.memberID is required but no affected member was supplied.');

            return false;
        }

        $payload = [
            'userID' => $userId,
            'memberID' => $memberId,
            'user_action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress,
        ];

        if ($userAgent !== null && $userAgent !== '') {
            if ($this->db->fieldExists('user_agent', $this->table)) {
                $payload['user_agent'] = $userAgent;
            } else {
                $payload['description'] = $this->appendUserAgent($description, $userAgent);
            }
        }

        return $this->insert($payload) !== false;
    }

    public function getRecent(int $limit = 50): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->select('audit_trails.*')
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->withNames($rows);
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->where('userID', $userId)
            ->orderBy('dt_created', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->withNames($rows);
    }

    public function auditTrails(string $keyword = '', array $filters = [], int $limit = 50): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->auditTrailBuilder();
        $this->applySearchAndFilters($builder, $keyword, $filters);

        $rows = $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit(max(1, $limit))
            ->get()
            ->getResultArray();

        return $this->withNames($rows);
    }

    public function auditTrailsByUser(int $userId, string $keyword = '', array $filters = [], int $limit = 50): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->auditTrailBuilder()
            ->where('audit_trails.userID', $userId);

        $this->applySearchAndFilters($builder, $keyword, $filters);

        $rows = $builder
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit(max(1, $limit))
            ->get()
            ->getResultArray();

        return $this->withNames($rows);
    }

    public function auditActions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return array_column(
            $this->db->table($this->table)
                ->select('TRIM(user_action) AS user_action', false)
                ->where('user_action IS NOT NULL')
                ->where("TRIM(user_action) != ''", null, false)
                ->groupBy('TRIM(user_action)')
                ->orderBy('TRIM(user_action)', 'ASC', false)
                ->get()
                ->getResultArray(),
            'user_action'
        );
    }

    private function auditTrailBuilder(): BaseBuilder
    {
        return $this->db->table($this->table)
            ->select('audit_trails.*');
    }

    private function applySearchAndFilters(BaseBuilder $builder, string $keyword, array $filters): void
    {
        $keyword = trim($keyword);

        if ($keyword !== '') {
            $this->applyAuditSearch($builder, $keyword);
        }

        $action = trim((string) ($filters['action'] ?? ''));

        if ($action !== '') {
            $builder->where('TRIM(audit_trails.user_action) = ' . $this->db->escape($action), null, false);
        }

        $this->applyDateRange($builder, $filters);
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

    private function applyDateRange(BaseBuilder $builder, array $filters): void
    {
        $date = $this->normalizeDate((string) ($filters['date'] ?? ''));

        if ($date !== '') {
            $builder
                ->where('audit_trails.dt_created >=', $date . ' 00:00:00')
                ->where('audit_trails.dt_created <=', $date . ' 23:59:59');

            return;
        }

        $dateFrom = $this->normalizeDate((string) ($filters['date_from'] ?? ''));
        $dateTo = $this->normalizeDate((string) ($filters['date_to'] ?? ''));

        if ($dateFrom !== '') {
            $builder->where('audit_trails.dt_created >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== '') {
            $builder->where('audit_trails.dt_created <=', $dateTo . ' 23:59:59');
        }
    }

    private function appendUserAgent(?string $description, string $userAgent): string
    {
        $base = $description ?? '';
        $suffix = 'UA: ' . $userAgent;

        return $base === '' ? $suffix : $base . ' | ' . $suffix;
    }

    private function memberIdValue(?int $memberId): ?int
    {
        if ($memberId !== null && $memberId > 0 && $this->memberExists($memberId)) {
            return $memberId;
        }

        return null;
    }

    private function isMemberIdNullable(): bool
    {
        foreach ($this->db->getFieldData($this->table) as $field) {
            if ($field->name === 'memberID') {
                return (bool) ($field->nullable ?? false);
            }
        }

        return true;
    }

    private function withNames(array $rows): array
    {
        $users = $this->userMap(array_column($rows, 'userID'));
        $memberNames = $this->memberNameMap(array_column($rows, 'memberID'));

        foreach ($rows as &$row) {
            $userId = (int) ($row['userID'] ?? 0);
            $memberId = (int) ($row['memberID'] ?? 0);
            $memberName = $memberNames[$memberId] ?? ['firstname' => '', 'lastname' => ''];
            $user = $users[$userId] ?? ['username' => '', 'role' => ''];

            $row['username'] = $user['username'];
            $row['user_role'] = $user['role'];
            $row['firstname'] = $memberName['firstname'];
            $row['lastname'] = $memberName['lastname'];
            $row['member_name'] = $this->formatMemberName($memberName);
        }

        return $rows;
    }

    private function userMap(array $userIds): array
    {
        $userIds = $this->positiveUniqueIds($userIds);

        if ($userIds === [] || ! $this->db->tableExists('users')) {
            return [];
        }

        $users = $this->db->table('users')
            ->select('userID, username, role')
            ->whereIn('userID', $userIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($users as $user) {
            $map[(int) $user['userID']] = [
                'username' => (string) $user['username'],
                'role' => (string) ($user['role'] ?? ''),
            ];
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

    private function memberExists(int $memberId): bool
    {
        if (! $this->db->tableExists('member')) {
            return false;
        }

        return $this->db->table('member')
            ->where('memberID', $memberId)
            ->countAllResults() > 0;
    }

    private function formatMemberName(array $memberName): string
    {
        return trim(implode(' ', array_filter([
            (string) ($memberName['firstname'] ?? ''),
            (string) ($memberName['lastname'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
    }

    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
    }
}
