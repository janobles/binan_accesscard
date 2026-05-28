<?php

namespace App\Models;

use App\Libraries\SectorIds;
use CodeIgniter\Model;

/**
 * Manages available assistance services and eligibility lookups.
 */
class ServiceModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['serviceID', 'category', 'name', 'description'];
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function nextServiceId(): int
    {
        if (! $this->hasTable()) {
            return 1;
        }

        $row = $this->selectMax($this->primaryKey, 'max_id')->first();

        return ((int) ($row['max_id'] ?? 0)) + 1;
    }

    public function getNameMapByIds(array $serviceIds): array
    {
        $serviceIds = $this->naturalIds($serviceIds) ?? [];

        if ($serviceIds === []) {
            return [];
        }

        $rows = $this->select('serviceID, name')
            ->whereIn('serviceID', $serviceIds)
            ->findAll();

        $map = [];

        foreach ($rows as $row) {
            $id = (int) ($row['serviceID'] ?? 0);

            if ($id < 0) {
                continue;
            }

            $map[$id] = (string) ($row['name'] ?? '');
        }

        return $map;
    }

    public function getForSectorName(string $sectorName): array
    {
        return $this->groupStart()
            ->where('category', $sectorName)
            ->orWhere('category', 'General')
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getEligibleForMember(int $memberId): array
    {
        $member = $this->db->table('member')
            ->select('sectorID')
            ->where('member.memberID', $memberId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            return [];
        }

        $sectorIds = SectorIds::normalize($member['sectorID'] ?? null);

        if ($sectorIds === []) {
            return [];
        }

        $sectorNames = array_column(
            $this->db->table('sector')
                ->select('name')
                ->whereIn('sectorID', $sectorIds)
                ->get()
                ->getResultArray(),
            'name'
        );

        if ($sectorNames === []) {
            return [];
        }

        return $this->groupStart()
            ->whereIn('category', $sectorNames)
            ->orWhere('category', 'General')
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function existsById(int $serviceId): bool
    {
        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $builder = $this->where($this->primaryKey, $serviceId);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted', null);
        }

        return $builder
            ->countAllResults() > 0;
    }

    public function idsExist(array $serviceIds): bool
    {
        $serviceIds = $this->naturalIds($serviceIds);

        if ($serviceIds === null) {
            return false;
        }

        if ($serviceIds === []) {
            return true;
        }

        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $builder = $this->whereIn($this->primaryKey, $serviceIds);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted', null);
        }

        return $builder
            ->countAllResults() === count($serviceIds);
    }

    private function naturalIds(array $serviceIds): ?array
    {
        $normalizedIds = [];

        foreach ($serviceIds as $serviceId) {
            if (is_array($serviceId)) {
                return null;
            }

            $serviceId = trim((string) $serviceId);

            if ($serviceId === '' || ! ctype_digit($serviceId)) {
                return null;
            }

            $normalizedIds[] = (int) $serviceId;
        }

        return array_values(array_unique($normalizedIds));
    }
}

