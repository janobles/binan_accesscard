<?php

namespace App\Support;

use CodeIgniter\Database\BaseConnection;

/**
 * Converts the final database's bracketed sector list strings to/from arrays.
 */
class SectorIds
{
    public static function normalize(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim((string) $value);

            if ($text === '') {
                return [];
            }

            $decoded = json_decode($text, true);
            $items = is_array($decoded)
                ? $decoded
                : explode(',', trim($text, "[] \t\n\r\0\x0B"));
        }

        $ids = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $ids = array_merge($ids, self::normalize($item));
                continue;
            }

            $id = (int) trim((string) $item);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function toStorage(mixed $value): string
    {
        return '[' . implode(',', self::normalize($value)) . ']';
    }

    public static function sqlListExpression(string $column = 'member.sectorID'): string
    {
        return "REPLACE(REPLACE($column, '[', ''), ']', '')";
    }

    public static function containsCondition(int $sectorId, string $column = 'member.sectorID'): string
    {
        return 'FIND_IN_SET(' . $sectorId . ', ' . self::sqlListExpression($column) . ') > 0';
    }

    public static function sectorNameSelect(string $memberAlias = 'member', string $alias = 'sector_name'): string
    {
        $sectorList = self::sqlListExpression($memberAlias . '.sectorID');

        return "(SELECT GROUP_CONCAT(sector_lookup.name ORDER BY sector_lookup.name SEPARATOR ', ') "
            . 'FROM sector sector_lookup '
            . "WHERE FIND_IN_SET(sector_lookup.sectorID, $sectorList) > 0) AS $alias";
    }

    public static function sectorShortcodeSelect(string $memberAlias = 'member', string $alias = 'sector_shortcode'): string
    {
        $sectorList = self::sqlListExpression($memberAlias . '.sectorID');

        return "(SELECT GROUP_CONCAT(sector_lookup.shortcode ORDER BY sector_lookup.shortcode SEPARATOR ', ') "
            . 'FROM sector sector_lookup '
            . "WHERE FIND_IN_SET(sector_lookup.sectorID, $sectorList) > 0) AS $alias";
    }

    public static function sectorNameLikeCondition(
        string $keyword,
        string $column = 'member.sectorID',
        ?BaseConnection $db = null
    ): string
    {
        $db ??= db_connect();
        $escapedKeyword = $db->escapeLikeString($keyword);

        return 'EXISTS (SELECT 1 FROM sector sector_search '
            . 'WHERE FIND_IN_SET(sector_search.sectorID, ' . self::sqlListExpression($column) . ') > 0 '
            . "AND sector_search.name LIKE '%$escapedKeyword%' ESCAPE '!')";
    }
}
