<?php

namespace App\Libraries;

/**
 * Shared presentation formatting and normalization for view templates.
 */
class ViewFormatter
{
    public static function hasSearchFilters(string $searchTerm, array $filters): bool
    {
        if ($searchTerm !== '') {
            return true;
        }

        foreach ($filters as $value) {
            if (self::hasText($value)) {
                return true;
            }
        }

        return false;
    }

    public static function hasText(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    public static function formatDate(mixed $value): string
    {
        return self::formatTimestamp($value, 'Y-m-d', '');
    }

    public static function formatTime(mixed $value): string
    {
        return self::formatTimestamp($value, 'h:i A', '');
    }

    public static function formatAuditMember(array $audit): string
    {
        $memberName = trim((string) ($audit['member_name'] ?? ''));

        if ($memberName === '') {
            $memberName = trim((string) ($audit['firstname'] ?? '') . ' ' . (string) ($audit['lastname'] ?? ''));
        }

        return $memberName === '' ? '-' : $memberName;
    }

    public static function formatAuditUser(array $audit): string
    {
        $username = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
        $role = trim((string) ($audit['user_role'] ?? ''));

        if ($role === 'User') {
            $role = 'Employee';
        }

        return $role === '' ? $username : $username . ' (' . $role . ')';
    }

    public static function isActiveStatus(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), [
            'enable',
            'enabled',
            'active',
            '1',
            'true',
            'yes',
            'on',
        ], true);
    }

    public static function formatStatus(mixed $value): string
    {
        return self::isActiveStatus($value) ? 'Enable' : 'Disabled';
    }

    public static function splitList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', array_map('strval', $items))));
    }

    public static function integerList(mixed $value, bool $numericOnly = false): array
    {
        $items = (array) $value;

        if ($numericOnly) {
            $items = array_filter($items, 'is_numeric');
        }

        return array_values(array_map('intval', $items));
    }

    public static function stringList(mixed $value, bool $nonEmptyOnly = false): array
    {
        $items = array_values(array_map('strval', (array) $value));

        return $nonEmptyOnly ? array_values(array_filter($items, [self::class, 'hasText'])) : $items;
    }

    public static function selectedSectorCategories(array $sectorCatalog, array $selectedSectorIds): array
    {
        $categories = [];

        foreach ($sectorCatalog as $key => $rows) {
            foreach ((array) $rows as $row) {
                if (in_array((int) ($row['sectorID'] ?? 0), $selectedSectorIds, true)) {
                    $categories[] = (string) $key;
                    break;
                }
            }
        }

        return array_values(array_unique($categories));
    }

    public static function sectorCategoryKeys(array $sectorCatalog): array
    {
        $keys = [];

        foreach ($sectorCatalog as $key => $rows) {
            if ((array) $rows !== []) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    public static function sectorShortcodeOptions(array $options): array
    {
        return array_values(array_filter($options, static fn (string $shortcode): bool => $shortcode !== 'OTHER'));
    }

    public static function serviceCategoryOptions(array $services, array $defaults = []): array
    {
        $categories = $defaults;

        foreach ($services as $service) {
            $category = trim((string) ($service['category'] ?? ''));

            if ($category !== '') {
                $categories[] = $category;
            }
        }

        return array_values(array_unique($categories));
    }

    public static function memberSectorGroups(array $sectorOptions): array
    {
        $groups = [
            'SC' => ['label' => 'SC', 'sectors' => []],
            'PWD' => ['label' => 'PWD', 'sectors' => []],
            'SP' => ['label' => 'SP', 'sectors' => []],
            'B' => ['label' => 'B', 'sectors' => []],
            'OTHER' => ['label' => 'Others', 'sectors' => []],
        ];

        foreach ($sectorOptions as $sector) {
            $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));
            $groupKey = match (true) {
                str_starts_with($shortcode, 'PWD') => 'PWD',
                str_starts_with($shortcode, 'SC'),
                str_starts_with($shortcode, 'OSCA'),
                str_starts_with($shortcode, 'OSWA') => 'SC',
                str_starts_with($shortcode, 'SP') => 'SP',
                str_starts_with($shortcode, 'B') => 'B',
                default => 'OTHER',
            };
            $groups[$groupKey]['sectors'][] = $sector;
        }

        foreach ($groups as $key => $group) {
            if (($group['sectors'] ?? []) === []) {
                unset($groups[$key]);
            }
        }

        return $groups;
    }

    public static function debugArgument(mixed $value): string
    {
        return match (true) {
            is_object($value) => 'Object(' . $value::class . ')',
            is_array($value) => $value !== [] ? '[...]' : '[]',
            $value === null => 'null',
            default => var_export($value, true),
        };
    }

    private static function formatTimestamp(mixed $value, string $format, string $fallback): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? $fallback : date($format, $timestamp);
    }
}
