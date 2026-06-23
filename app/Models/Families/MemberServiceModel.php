<?php

namespace App\Models\Families;

use CodeIgniter\Model;

/**
 * Links members to services or assistance programs.
 */
class MemberServiceModel extends Model
{
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

    /** True if the `member_services` link table exists. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /** Total member↔service links, used for dashboard stats. */
    public function countAssignments(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

    /**
     * Links one member to one service (inserts a row in `member_services`).
     * Kept for the future Bootstrap family form rebuild; returns the new link ID
     * or false.
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
     * Returns the latest service assignments with member and service names joined
     * in, newest first. Frontend: the dashboard/activity "recent assignments" list.
     */
    public function recentAssignments(int $limit = 25): array
    {
        $rows = $this->select('member_services.*')
            ->orderBy('member_services.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->withNames($rows);
    }

    /**
     * Enriches assignment rows with `service_name`, `firstname`, and `lastname`
     * by looking up the related services and members in batch (avoids N+1 queries).
     */
    private function withNames(array $rows): array
    {
        $serviceNames = $this->serviceNameMap(array_column($rows, 'serviceID'));
        $memberNames = $this->memberNameMap(array_column($rows, 'memberID'));

        foreach ($rows as &$row) {
            $serviceId = (int) ($row['serviceID'] ?? -1);
            $memberId = (int) ($row['memberID'] ?? 0);
            $memberName = $memberNames[$memberId] ?? ['firstname' => '', 'lastname' => ''];

            $row['service_name'] = $serviceNames[$serviceId] ?? '';
            $row['firstname'] = $memberName['firstname'];
            $row['lastname'] = $memberName['lastname'];
        }

        return $rows;
    }

    /** Batch [serviceID => name] lookup used by withNames(). */
    private function serviceNameMap(array $serviceIds): array
    {
        $serviceIds = $this->uniqueIds($serviceIds);

        if ($serviceIds === [] || ! $this->db->tableExists('services')) {
            return [];
        }

        $services = $this->db->table('services')
            ->select('serviceID, name')
            ->whereIn('serviceID', $serviceIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($services as $service) {
            $map[(int) $service['serviceID']] = (string) $service['name'];
        }

        return $map;
    }

    /** Batch [memberID => {firstname, lastname}] lookup used by withNames(). */
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

    /** Normalizes an ID list to unique positive ints (members must be > 0). */
    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /** Normalizes an ID list to unique ints (service IDs may include 0). */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids
        )));
    }

    /**
     * Returns a [memberID => [serviceID, ...]] map for the given members, used to
     * pre-check the assigned services when rendering a family for edit.
     */
    public function getServiceIdsByMemberIds(array $memberIds): array
    {
        $memberIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $memberIds), static fn (int $id): bool => $id > 0));

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
        $memberIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $memberIds), static fn (int $id): bool => $id > 0));

        if ($memberIds === []) {
            return true;
        }

        return $this->whereIn('memberID', $memberIds)->delete() !== false;
    }
}
