<?php

namespace App\Models;

use App\Support\FamilyProfilingFormV2;
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

        return $this->orderBy('shortcode', 'ASC')->findAll();
    }

    public function getShortcodeOptions(): array
    {
        $fallback = array_values(array_filter(
            array_keys(FamilyProfilingFormV2::SECTOR_CATEGORIES),
            static fn (string $shortcode): bool => $shortcode !== 'OTHER'
        ));

        if (! $this->hasTable()) {
            return $fallback;
        }

        $rows = $this->select('shortcode')
            ->where('shortcode !=', '')
            ->orderBy('shortcode', 'ASC')
            ->findAll();
        $shortcodes = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['shortcode'] ?? ''))),
            $rows
        ))));

        if ($shortcodes !== []) {
            return array_values(array_unique(array_merge($shortcodes, $fallback)));
        }

        $field = $this->db
            ->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'shortcode'")
            ->getRowArray();
        $type = (string) ($field['Type'] ?? '');

        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches) !== false && $matches[1] !== []) {
            $enumValues = array_map(static fn (string $value): string => stripcslashes($value), $matches[1]);

            return array_values(array_unique(array_merge($fallback, $enumValues)));
        }

        return $fallback;
    }

    public function saveSectorRecord(array $data, ?int $sectorId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $data['shortcode'] = $this->effectiveShortcode($data, $sectorId);

        if ($sectorId !== null) {
            return $this->update($sectorId, $data) !== false;
        }

        return $this->insert($data) !== false;
    }

    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $catalog = array_fill_keys(array_keys(FamilyProfilingFormV2::SECTOR_CATEGORIES), []);
        $catalog['OSCA'] = [];

        foreach ($sectorOptions as $sector) {
            $shortcode = $this->effectiveShortcode($sector);

            if ($shortcode === '') {
                continue;
            }

            if (str_starts_with($shortcode, 'PWD')) {
                $group = 'PWD';
            } elseif (str_starts_with($shortcode, 'SP')) {
                $group = 'SP';
            } elseif (str_starts_with($shortcode, 'SC') || str_starts_with($shortcode, 'OSCA') || str_starts_with($shortcode, 'OSWA')) {
                $group = 'SC';
            } elseif (str_starts_with($shortcode, 'B')) {
                $group = 'B';
            } else {
                $group = array_key_exists($shortcode, FamilyProfilingFormV2::SECTOR_CATEGORIES)
                    ? $shortcode
                    : 'OTHER';
            }

            $catalog[$group][] = [
                'category_label' => FamilyProfilingFormV2::SECTOR_CATEGORIES[$group] ?? 'Other Sectors',
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => $shortcode,
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];
        }

        return $catalog;
    }

    private function effectiveShortcode(array $sector, ?int $sectorId = null): string
    {
        $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

        if ($shortcode !== '') {
            return $shortcode;
        }

        if ($sectorId !== null) {
            $existing = $this->find($sectorId);
            $existingShortcode = strtoupper(trim((string) ($existing['shortcode'] ?? '')));

            if ($existingShortcode !== '') {
                return $existingShortcode;
            }
        }

        $name = strtoupper(trim((string) ($sector['name'] ?? '')));

        if (str_contains($name, 'REGISTERED OSCA')) {
            return 'SC1';
        }

        return '';
    }
}
