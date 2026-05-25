<?php

namespace App\Models;

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

    public function getSectorOptions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function getShortcodeOptions(): array
    {
        $fallback = [
            'PWD1',
            'PWD2',
            'PWD3',
            'PWD4',
            'PWD5',
            'SP1',
            'SP2',
            'OSCA1',
            'OSCA2',
            'OSCA3',
            'OSCA4',
            'OSCA5',
            'OSCA6',
            'OSCA7',
        ];

        if (! $this->hasTable()) {
            return $fallback;
        }

        $field = $this->db
            ->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'shortcode'")
            ->getRowArray();
        $type = (string) ($field['Type'] ?? '');

        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches) !== false && $matches[1] !== []) {
            return array_map(static fn (string $value): string => stripcslashes($value), $matches[1]);
        }

        return $fallback;
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
