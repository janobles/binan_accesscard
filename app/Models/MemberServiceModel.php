<?php

namespace App\Models;

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

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function countAssignments(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

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

    public function recentAssignments(int $limit = 25): array
    {
        $rows = $this->select('member_services.*')
            ->orderBy('member_services.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->withNames($rows);
    }

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

    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids
        )));
    }

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

            if ($memberId <= 0 || $serviceId <= 0) {
                continue;
            }

            $map[$memberId] ??= [];
            $map[$memberId][] = $serviceId;
        }

        return $map;
    }

    public function deleteByMemberIds(array $memberIds): bool
    {
        $memberIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $memberIds), static fn (int $id): bool => $id > 0));

        if ($memberIds === []) {
            return true;
        }

        return $this->whereIn('memberID', $memberIds)->delete() !== false;
    }
}

