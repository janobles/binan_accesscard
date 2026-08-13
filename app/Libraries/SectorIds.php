<?php

namespace App\Libraries;

/**
 * Normalizes sector IDs arriving as arrays, comma-separated text, or the JSON
 * list `member.sectorID` held before V22 moved sectors into member_sectors.
 * Rows still reach here in every one of those shapes: a form posts an array, a
 * listing selects a GROUP_CONCAT comma list, and an old export may hold JSON.
 */
class SectorIds
{
    /**
     * DECODE: stored value -> clean array of int IDs.
     *
     * Accepts a comma list ('1,2,3'), an array, or a JSON string, and returns
     * a list of unique, positive integers like [1, 2, 3]. Used for display,
     * name lookups and search.
     */
    public static function normalize(mixed $value): array
    {
        $items = self::itemsFromValue($value);
        $ids   = [];

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

    /**
     * True if the value isn't a clean list of numeric IDs (object-like JSON,
     * associative arrays, or non-numeric items). Backs the `valid_sector_array`
     * validation rule so bad sector input is rejected before saving.
     */
    public static function hasMalformedIds(mixed $value): bool
    {
        // Associative arrays and object-like JSON are not valid sector ID lists.
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

    /**
     * Turns any accepted input (array, JSON list, or comma text) into a flat list
     * of raw items for normalize()/hasMalformedIds() to inspect. Object-like values
     * yield an empty list.
     */
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

        // Prefer JSON lists; fall back to the older "[1,2,3]" / "1,2,3" text format.
        $decoded = json_decode($text, true);

        return is_array($decoded) && self::isListArray($decoded)
            ? $decoded
            : explode(',', trim($text, "[] \t\n\r\0\x0B"));
    }

    /** True if the array is a sequential (0..n) list rather than associative. */
    private static function isListArray(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    /** True if the item is a positive-integer-like value (int or digit string). */
    private static function isNumericId(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        $value = trim((string) $value);

        return $value !== '' && ctype_digit($value);
    }
}
