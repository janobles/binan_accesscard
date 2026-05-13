<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['category', 'name', 'description'];
    protected $useTimestamps = false;

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
            ->select('sector.name AS sector_name')
            ->join('sector', 'sector.sectorID = member.sectorID')
            ->where('member.memberID', $memberId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            return [];
        }

        return $this->getForSectorName((string) $member['sector_name']);
    }
}

