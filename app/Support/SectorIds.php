<?php

namespace App\Support;

/**
 * Normalizes sector IDs stored as arrays, JSON lists, or comma-separated text.
 */
class SectorIds
{
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

    public static function toStorage(mixed $value): string
    {
        return json_encode(self::normalize($value)) ?: '[]';
    }

    public static function containsCondition(int $sectorId, string $column = 'sector_array_string'): string
    {
        // Used in query filters for the database JSON sector list column.
        return 'JSON_CONTAINS(' . $column . ", '" . $sectorId . "') = 1";
    }

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

        // Prefer JSON lists; fall back to the older "[1,2,3]" / "1,2,3" text format.
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
