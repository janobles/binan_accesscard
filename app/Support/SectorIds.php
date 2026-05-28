<?php

namespace App\Support;

/**
 * Converts a member's sector list between the stored JSON string and a PHP
 * array of integer sector IDs.
 *
 * The `member`.`sectorID` column holds a JSON array as text, e.g. '[1,2,3]',
 * where each number is a `sector`.`sectorID`. This class is the single place
 * that does json_encode (array -> string, on save) and json_decode
 * (string -> array, on read) for that column.
 *
 * Where it connects:
 *   - App\Models\MemberModel::normalizeSectorIdStorage()  -> toStorage()
 *       on beforeInsert/beforeUpdate (encodes the array before it is saved).
 *   - App\Models\MemberModel::withSectorNames() and
 *     App\Models\DashboardModel::withSectorNames()        -> normalize()/toNames()
 *       (decodes the string and turns IDs into sector names for display).
 *   - App\Models\MemberModel::familySearchBuilder()       -> containsCondition()
 *       (builds the JSON_CONTAINS(...) clause used to search members by sector).
 *   - App\Validation\SectorRules ('valid_sector_array')   -> normalize()/
 *       hasMalformedIds() (validates the submitted value before it is saved).
 */
class SectorIds
{
    /**
     * DECODE: stored value -> clean array of int IDs.
     *
     * Accepts the JSON string from the DB (e.g. '[1,2,3]') or an
     * already-decoded array, and returns a list of unique, positive integers
     * like [1, 2, 3]. Used for display, name lookups and search.
     */
    public static function normalize(mixed $value): array
    {
        $items = self::itemsFromValue($value);
        $ids = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $ids = array_merge($ids, self::normalize($item));
                continue;
            }

            if (! self::isNumericId($item)) {
                continue;
            }

            $id = (int) trim((string) $item);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function hasMalformedIds(mixed $value): bool
    {
        if (is_array($value) && ! self::isListArray($value)) {
            return true;
        }

        if (is_string($value)) {
            $text = trim($value);

            if ($text !== '' && $text[0] === '{') {
                return true;
            }

            $decoded = json_decode($text, true);

            if (is_array($decoded) && ! self::isListArray($decoded)) {
                return true;
            }
        }

        foreach (self::itemsFromValue($value) as $item) {
            if (is_array($item)) {
                return true;
            }

            if (! self::isNumericId($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ENCODE: array -> storage string.
     *
     * Normalizes to a clean list of unique positive IDs, then json_encode()s
     * it to '[1,2,3]' for the `member`.`sectorID` column. Falls back to '[]'
     * if encoding fails. Called on save by MemberModel::normalizeSectorIdStorage().
     */
    public static function toStorage(mixed $value): string
    {
        return json_encode(self::normalize($value)) ?: '[]';
    }

    /**
     * Builds the SQL used to search members by sector:
     * JSON_CONTAINS(<column>, '<id>') = 1. This works directly on the JSON
     * array string in the column (no decoding needed in PHP).
     * Used by MemberModel::familySearchBuilder().
     */
    public static function containsCondition(int $sectorId, string $column = 'sector_array_string'): string
    {
        return 'JSON_CONTAINS(' . $column . ", '" . $sectorId . "') = 1";
    }

    /**
     * Decodes the stored IDs and maps each to its human-readable sector name
     * using a [sectorID => name] map built from the `sector` table, returning
     * a comma-separated string. Lets lists show sector names instead of raw IDs.
     */
    public static function toNames(mixed $value, array $sectorNames): string
    {
        $names = [];

        foreach (self::normalize($value) as $sectorId) {
            if (isset($sectorNames[$sectorId])) {
                $names[] = $sectorNames[$sectorId];
            }
        }

        return implode(', ', $names);
    }

    private static function itemsFromValue(mixed $value): array
    {
        if (is_array($value)) {
            return self::isListArray($value) ? $value : [];
        }

        $text = trim((string) $value);

        if ($text === '') {
            return [];
        }

        if ($text[0] === '{') {
            return [];
        }

        // Core decode step: try json_decode() first (handles '[1,2,3]'). If the
        // value is not a valid JSON list, fall back to splitting a bare
        // '1,2,3' string so legacy/hand-entered values still work.
        $decoded = json_decode($text, true);

        return is_array($decoded) && self::isListArray($decoded)
            ? $decoded
            : explode(',', trim($text, "[] \t\n\r\0\x0B"));
    }

    private static function isListArray(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private static function isNumericId(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value);
    }
}
