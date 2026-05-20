<?php

namespace App\Models;

use CodeIgniter\Model;

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
            'memberID' => $memberId,
            'user_action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress,
        ];

        // Prefer dedicated column when present; otherwise keep UA in description.
        if ($userAgent !== null && $userAgent !== '') {
            if ($this->db->fieldExists('user_agent', $this->table)) {
                $payload['user_agent'] = $userAgent;
            } else {
                $payload['description'] = $this->appendUserAgent($description, $userAgent);
            }
        }

        return $this->insert($payload) !== false;
    }

    private function appendUserAgent(?string $description, string $userAgent): string
    {
        $base = $description ?? '';
        $suffix = 'UA: ' . $userAgent;

        return $base === '' ? $suffix : $base . ' | ' . $suffix;
    }

    public function getRecent(int $limit = 50): array
    {
        return $this->select('audit_trails.*, users.username, member.firstname, member.lastname')
            ->join('users', 'users.userID = audit_trails.userID')
            ->join('member', 'member.memberID = audit_trails.memberID', 'left')
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('userID', $userId)
            ->orderBy('dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
