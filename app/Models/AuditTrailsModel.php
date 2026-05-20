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
        $payload = [
            'userID' => $userId,
            'memberID' => $this->memberIdValue($memberId),
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

        return $this->select('audit_trails.*, users.username, member.firstname, member.lastname')
            ->join('users', 'users.userID = audit_trails.userID')
            ->join('member', 'member.memberID = audit_trails.memberID', 'left')
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->where('userID', $userId)
            ->orderBy('dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    private function appendUserAgent(?string $description, string $userAgent): string
    {
        $base = $description ?? '';
        $suffix = 'UA: ' . $userAgent;

        return $base === '' ? $suffix : $base . ' | ' . $suffix;
    }

    private function memberIdValue(?int $memberId): ?int
    {
        if ($memberId !== null && $memberId > 0) {
            return $memberId;
        }

        if ($this->isMemberIdNullable()) {
            return null;
        }

        if (! $this->db->tableExists('member')) {
            return null;
        }

        $member = $this->db->table('member')
            ->select('memberID')
            ->orderBy('memberID', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return isset($member['memberID']) ? (int) $member['memberID'] : null;
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
}
