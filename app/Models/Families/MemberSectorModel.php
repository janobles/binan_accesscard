<?php

namespace App\Models\Families;

use App\Models\Concerns\NormalizesIds;
use CodeIgniter\Model;

/**
 * Links members to the sectors they belong to (senior citizen, PWD, solo
 * parent and the rest).
 *
 * Replaces the `member.sectorID` JSON list V22 dropped. Same shape and same
 * call sites as MemberServiceModel: FamilyController and the Excel importer
 * clear a member's rows and re-insert the submitted selection, everything else
 * reads the map.
 *
 * The junction has a composite primary key (memberID, sectorID) rather than a
 * surrogate id, so a member can hold a sector only once and the DB is what
 * enforces it.
 */
class MemberSectorModel extends Model
{
    use NormalizesIds;

    protected $table         = 'member_sectors';
    protected $primaryKey    = 'memberID';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'memberID',
        'sectorID',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'memberID' => 'required|is_natural_no_zero',
        'sectorID' => 'required|is_natural_no_zero',
    ];

    /** Links one member to one sector. Returns false when the row already exists. */
    public function assignSector(int $memberId, int $sectorId): bool
    {
        return $this->insert([
            'memberID' => $memberId,
            'sectorID' => $sectorId,
        ]) !== false;
    }

    /**
     * Replaces one member's sectors with the given list, in a single pair of
     * statements. The empty list is a valid selection (a member in no sector),
     * so it clears the rows rather than skipping the write.
     */
    public function replaceForMember(int $memberId, array $sectorIds): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        $this->where('memberID', $memberId)->delete();

        $sectorIds = $this->positiveUniqueIds($sectorIds);

        if ($sectorIds === []) {
            return true;
        }

        return $this->db->table($this->table)->insertBatch(array_map(
            static fn (int $sectorId): array => ['memberID' => $memberId, 'sectorID' => $sectorId],
            $sectorIds
        )) !== false;
    }

    /**
     * [memberID => [sectorID, ...]] for the given members, for pre-checking the
     * sector boxes when rendering a family and for labelling rows in a list.
     */
    public function getSectorIdsByMemberIds(array $memberIds): array
    {
        $memberIds = $this->positiveUniqueIds($memberIds);

        if ($memberIds === []) {
            return [];
        }

        $rows = $this->select('memberID, sectorID')
            ->whereIn('memberID', $memberIds)
            ->orderBy('sectorID', 'ASC')
            ->findAll();

        $map = [];

        foreach ($rows as $row) {
            $memberId = (int) ($row['memberID'] ?? 0);
            $sectorId = (int) ($row['sectorID'] ?? 0);

            if ($memberId <= 0 || $sectorId <= 0) {
                continue;
            }

            $map[$memberId] ??= [];
            $map[$memberId][] = $sectorId;
        }

        return $map;
    }

    /** The sector ids held by one member. */
    public function sectorIdsForMember(int $memberId): array
    {
        return $this->getSectorIdsByMemberIds([$memberId])[$memberId] ?? [];
    }

    /** Removes all sector links for the given members. */
    public function deleteByMemberIds(array $memberIds): bool
    {
        $memberIds = $this->positiveUniqueIds($memberIds);

        if ($memberIds === []) {
            return true;
        }

        return $this->whereIn('memberID', $memberIds)->delete() !== false;
    }

    /** True when any member still holds this sector. Backs the lookup archive guard. */
    public function sectorInUse(int $sectorId): bool
    {
        return $this->where('sectorID', $sectorId)->countAllResults() > 0;
    }
}
