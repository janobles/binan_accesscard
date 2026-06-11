<?php

namespace App\Libraries;

/**
 * File-backed store for custom sector category display names — no database
 * involved. Maps a shortcode prefix to a friendly name, e.g. ['TEST' => 'Test
 * Families'], persisted as JSON under writable/sector_categories.json.
 *
 * Official categories (SC, PWD, …) keep their fixed names in
 * FamilyProfilingFormV2 and are never stored here; this only names the custom
 * prefixes created via the sector modal's "Other (custom)" option. Reads
 * degrade to an empty map when the file is missing or unreadable, so callers
 * transparently fall back to the bare prefix.
 */
class SectorCategoryStore
{
    /** Absolute path to the JSON file that persists the prefix => name map. */
    private static function path(): string
    {
        return WRITEPATH . 'sector_categories.json';
    }

    /** [PREFIX => name] for every named custom category (uppercased, trimmed). */
    public static function all(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach ($decoded as $prefix => $name) {
            $prefix = strtoupper(trim((string) $prefix));
            $name = trim((string) $name);

            if ($prefix !== '' && $name !== '') {
                $map[$prefix] = $name;
            }
        }

        return $map;
    }

    /**
     * Upserts the display name for a prefix. Returns false on blank input or a
     * write failure.
     */
    public static function save(string $prefix, string $name): bool
    {
        $prefix = strtoupper(trim($prefix));
        $name = trim($name);

        if ($prefix === '' || $name === '') {
            return false;
        }

        $map = self::all();
        $map[$prefix] = $name;

        return self::write($map);
    }

    /** Removes a custom category name; the prefix reverts to its bare code. */
    public static function delete(string $prefix): bool
    {
        $prefix = strtoupper(trim($prefix));
        $map = self::all();

        if (! isset($map[$prefix])) {
            return true;
        }

        unset($map[$prefix]);

        return self::write($map);
    }

    /** Serialises the map back to disk (sorted for stable, readable output). */
    private static function write(array $map): bool
    {
        ksort($map);

        $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return file_put_contents(self::path(), $json) !== false;
    }
}
