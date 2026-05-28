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
            'members' => $this->countMembers(),
            'sectors' => $this->countTable('sector'),
            'assistance' => $this->countTable('member_services'),
        ];
    }

    public function recentFamilies(int $limit = 10): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        $rows = $this->db->table('member')
            ->select('memberID, firstname, lastname, contactnumber, relationship, headID, sectorID, dt_created, dt_updated')
            ->where('memberID = headID', null, false)
            ->where('dt_deleted IS NULL', null, false)
            ->orderBy('memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    private function countFamilies(): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->db->table('member')
            ->where('memberID = headID', null, false)
            ->where('dt_deleted IS NULL', null, false)
            ->countAllResults();
    }

    private function countMembers(): int
    {
        if (! $this->db->tableExists('member')) {
            return 0;
        }

        return $this->db->table('member')
            ->where('dt_deleted IS NULL', null, false)
            ->countAllResults();
    }

    private function countTable(string $table): int
    {
        return $this->db->tableExists($table)
            ? $this->db->table($table)->countAllResults()
            : 0;
    }

    /**
     * DECODE/display path for dashboard lists. Resolves each row's raw JSON
     * sectorID string into a readable 'sector_name'. See App\Support\SectorIds::toNames().
     */
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
