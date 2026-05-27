<?php

namespace App\Models;

use App\Libraries\RecordArchiver;
use CodeIgniter\Model;

/**
 * Manages the citizen sectors used for categorizing members.
 */
class SectorModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';
    protected $allowedFields = ['shortcode', 'name', 'description'];
    protected $useTimestamps = false;

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function countSectors(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

    public function saveSectorRecord(array $data, ?int $sectorId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $sectorData = $this->sectorData($data);

        if (! $this->isValidSectorData($sectorData)) {
            return false;
        }

        if ($sectorId === null) {
            return (bool) $this->insert($sectorData);
        }

        return (bool) $this->update($sectorId, $sectorData);
    }

    public function archiveSector(int $sectorId): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        return RecordArchiver::archive($this->table, $this->primaryKey, $sectorId);
    }

    public function sectorData(array $data): array
    {
        return [
            'shortcode' => strtoupper(trim((string) ($data['shortcode'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
        ];
    }

    public function isValidSectorData(array $data): bool
    {
        return trim((string) ($data['shortcode'] ?? '')) !== ''
            && trim((string) ($data['name'] ?? '')) !== '';
    }

    public function getSectorOptions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $catalog = [
            'PWD' => [],
            'SP' => [],
            'OSCA' => [],
        ];

        foreach ($sectorOptions as $sector) {
            $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

            if ($shortcode === '') {
                continue;
            }

            $group = null;

            if (str_starts_with($shortcode, 'PWD')) {
                $group = 'PWD';
            } elseif (str_starts_with($shortcode, 'SP')) {
                $group = 'SP';
            } elseif (str_starts_with($shortcode, 'OSCA')) {
                $group = 'OSCA';
            }

            if ($group === null) {
                continue;
            }

            $catalog[$group][] = [
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => (string) ($sector['shortcode'] ?? ''),
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];
        }

        return $catalog;
    }
}
