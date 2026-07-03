<?php

namespace App\Models\Families;

use App\Models\Concerns\NormalizesIds;
use CodeIgniter\Model;

/**
 * Links members to services or assistance programs.
 */
class MemberServiceModel extends Model
{
    use NormalizesIds;

    protected $table = 'member_services';
    protected $primaryKey = 'ID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'serviceID',
        'memberID',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'memberID' => 'required|is_natural_no_zero',
        'serviceID' => 'required|is_natural',
    ];

    /**
     * Links one member to one service (inserts a row in `member_services`).
     * Called by FamilyController::store() for each selected service; returns the
     * new link ID or false.
     */
    public function assignService(int $memberId, int $serviceId): int|false
    {
        if (! $this->insert([
            'memberID' => $memberId,
            'serviceID' => $serviceId,
        ])) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    /**
     * Returns a [memberID => [serviceID, ...]] map for the given members, used to
     * pre-check the assigned services when rendering a family for edit.
     */
    public function getServiceIdsByMemberIds(array $memberIds): array
    {
        $memberIds = $this->positiveUniqueIds($memberIds);

        if ($memberIds === []) {
            return [];
        }

        $rows = $this->select('memberID, serviceID')
            ->whereIn('memberID', $memberIds)
            ->findAll();

        $map = [];

        foreach ($rows as $row) {
            $memberId = (int) ($row['memberID'] ?? 0);
            $serviceId = (int) ($row['serviceID'] ?? 0);

            if ($memberId <= 0 || $serviceId < 0) {
                continue;
            }

            $map[$memberId] ??= [];
            $map[$memberId][] = $serviceId;
        }

        return $map;
    }

    /**
     * Removes all service links for the given members. Used during a family edit
     * to clear old assignments before re-inserting the submitted selection.
     */
    public function deleteByMemberIds(array $memberIds): bool
    {
        $memberIds = $this->positiveUniqueIds($memberIds);

        if ($memberIds === []) {
            return true;
        }

        return $this->whereIn('memberID', $memberIds)->delete() !== false;
    }
}
