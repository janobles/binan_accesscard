<?php

namespace App\Models;

use App\Support\SectorIds;
use CodeIgniter\Model;

/**
 * Manages available assistance services and eligibility lookups.
 */
class ServiceModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['category', 'name', 'description'];
    protected $useTimestamps = false;

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
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

        return $this->where($this->primaryKey, $serviceId)->countAllResults() > 0;
    }
}

