<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table = 'services';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'description', 'sector_id'];
    protected $useTimestamps = true;

    public function getForSector(int $sectorId): array
    {
        return $this->where('sector_id', $sectorId)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
