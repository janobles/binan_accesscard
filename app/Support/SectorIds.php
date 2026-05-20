<?php

namespace App\Support;

/**
 * Converts the final database's JSON-style sector list strings to/from arrays.
 */
class SectorIds
{
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
        foreach (self::itemsFromValue($value) as $item) {
            if (is_array($item)) {
                if (self::hasMalformedIds($item)) {
                    return true;
                }

                continue;
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
            return $value;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);

        return is_array($decoded)
            ? $decoded
            : explode(',', trim($text, "[] \t\n\r\0\x0B"));
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
