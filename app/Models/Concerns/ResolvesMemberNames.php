<?php

namespace App\Models\Concerns;

/**
 * Batched member-name lookup + display formatting, shared by the models that
 * label rows with member names (AuditTrailsModel, SearchModel). Hosting classes
 * must also use NormalizesIds — memberNameMap() calls positiveUniqueIds().
 */
trait ResolvesMemberNames
{
    /** Batch [memberID => {firstname, lastname}] lookup. */
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

    /** Joins first/last name into a single display string. */
    private function formatMemberName(array $memberName): string
    {
        return trim(implode(' ', array_filter([
            (string) ($memberName['firstname'] ?? ''),
            (string) ($memberName['lastname'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
    }
}
