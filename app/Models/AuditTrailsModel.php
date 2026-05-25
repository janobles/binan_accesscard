<?php

namespace App\Models;

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
        $usernames = $this->usernameMap(array_column($rows, 'userID'));
        $memberNames = $this->memberNameMap(array_column($rows, 'memberID'));

        foreach ($rows as &$row) {
            $userId = (int) ($row['userID'] ?? 0);
            $memberId = (int) ($row['memberID'] ?? 0);
            $memberName = $memberNames[$memberId] ?? ['firstname' => '', 'lastname' => ''];

            $row['username'] = $usernames[$userId] ?? '';
            $row['firstname'] = $memberName['firstname'];
            $row['lastname'] = $memberName['lastname'];
            $row['member_name'] = $this->formatMemberName($memberName);
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
}
