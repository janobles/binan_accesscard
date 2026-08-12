<?php

namespace App\Models\Concerns;

use App\Libraries\SectorIds;

/**
 * Turns the sector ids carried on a member row into readable names.
 * Shared by MemberModel, DashboardModel and SearchModel, which all display
 * member/family rows. Hosting classes expose $this->db (a CI Model or a plain
 * class holding a BaseConnection $db).
 *
 * The ids arrive as the `sector_ids` comma list the member listings select out
 * of member_sectors (V22 replaced the JSON column that used to hold them).
 * SectorIds::normalize() reads that list, an array, or the old JSON string, so
 * a row assembled by any path resolves the same way.
 */
trait ResolvesSectorNames
{
    /** For each row, resolves its sector ids into a readable 'sector_name'. */
    private function withSectorNames(array $rows): array
    {
        $sectorNames = $this->sectorNameMap();

        foreach ($rows as &$row) {
            $sectorValue = $row['sector_ids'] ?? $row['sector_array_string'] ?? '';
            $row['sector_ids'] = SectorIds::normalize($sectorValue);
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
