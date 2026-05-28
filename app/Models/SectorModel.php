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

        return $this->orderBy('name', 'ASC')->findAll();
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

    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $catalog = array_fill_keys(array_keys(FamilyProfilingFormV2::SECTOR_CATEGORIES), []);

        foreach ($sectorOptions as $sector) {
            $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

            if ($shortcode === '') {
                continue;
            }

            $group = array_key_exists($shortcode, FamilyProfilingFormV2::SECTOR_CATEGORIES)
                ? $shortcode
                : 'OTHER';

            $catalog[$group][] = [
                'category_label' => FamilyProfilingFormV2::SECTOR_CATEGORIES[$group] ?? 'Other Sectors',
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => (string) ($sector['shortcode'] ?? ''),
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];
        }

        return $catalog;
    }
}
