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
        if (! $this->db->tableExists('view_member_dashboard')) {
            return [];
        }

        $rows = $this->db->table('view_member_dashboard')
            ->where('memberID = headID')
            ->orderBy('memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    private function countFamilies(): int
    {
        if (! $this->db->tableExists('view_member_dashboard')) {
            return 0;
        }

        return $this->db->table('view_member_dashboard')
            ->where('memberID = headID')
            ->countAllResults();
    }

    private function countTable(string $table): int
    {
        return $this->db->tableExists($table)
            ? $this->db->table($table)->countAllResults()
            : 0;
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
}
