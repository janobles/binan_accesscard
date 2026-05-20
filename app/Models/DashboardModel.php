<?php

namespace App\Models;

use App\Support\SectorIds;
use CodeIgniter\Database\BaseConnection;

/**
 * Provides dashboard summary data for controller pages.
 *
 * This model keeps reporting queries out of the UI views and controllers.
 */
class DashboardModel
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function stats(): array
    {
        return [
            'families' => $this->countFamilies(),
            'members' => $this->countTable('member'),
            'sectors' => $this->countTable('sector'),
            'assistance' => $this->countTable('member_services'),
        ];
    }

    public function recentFamilies(int $limit = 10): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        return $this->db->table('member')
            ->select('member.*, ' . SectorIds::sectorNameSelect(), false)
            ->where('member.headID = member.memberID')
            ->orderBy('member.dt_created', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function countFamilies(): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->db->table('member')
            ->where('headID = memberID')
            ->countAllResults();
    }

    private function countTable(string $table): int
    {
        return $this->db->tableExists($table)
            ? $this->db->table($table)->countAllResults()
            : 0;
    }
}
