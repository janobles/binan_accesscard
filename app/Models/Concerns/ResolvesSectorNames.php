<?php

namespace App\Models\Concerns;

use App\Libraries\SectorIds;

/**
 * Resolves the raw JSON sectorID stored on member rows into readable names.
 * Shared by MemberModel, DashboardModel and SearchModel, which all display
 * member/family rows. Hosting classes expose $this->db (a CI Model or a plain
 * class holding a BaseConnection $db).
 */
trait ResolvesSectorNames
{
    /**
     * DECODE/display path. For each row, resolves the raw JSON sectorID string into
     * a readable 'sector_name'. See App\Libraries\SectorIds::toNames().
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

    /** Builds an [sectorID => name] map used to resolve sector names for display. */
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
