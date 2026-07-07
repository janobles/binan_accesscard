<?php

namespace App\Libraries;

/**
 * Shared presentation formatting and normalization for view templates.
 */
/**
 * Static presentation helpers called from the view templates (and mirrored by the
 * closures DashboardPageBuilder passes in) to format and normalize display data.
 * Pure functions with no DB or session access.
 */
class ViewFormatter
{
    /** True if a search term or any filter value is set (drives "filters active" UI). */
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

    /** True if a value has non-whitespace text. */
    public static function hasText(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    /** Formats any date/time value as Y-m-d for display ('' if unparseable). */
    public static function formatDate(mixed $value): string
    {
        return self::formatTimestamp($value, 'Y-m-d', '');
    }

    /** Formats any date/time value as 12-hour time for display ('' if unparseable). */
    public static function formatTime(mixed $value): string
    {
        return self::formatTimestamp($value, 'h:i A', '');
    }

    /** Interprets an isactive value (enum/numeric/string) as a boolean for display. */
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

    /** Renders an isactive value as the "Enable"/"Disabled" badge text. */
    public static function formatStatus(mixed $value): string
    {
        return self::isActiveStatus($value) ? 'Enable' : 'Disabled';
    }

    /**
     * Unpacks the labeled `users.full_description` string back into form fields —
     * the inverse of AccountController::buildFullDescription. Returns every key
     * (last_name/first_name/middle_name/suffix/address/contact_no/birthday) with
     * '' for any segment that was absent, so edit/My-Account forms can prefill
     * reliably. Unknown labels are ignored.
     */
    public static function parseFullDescription(string $packed): array
    {
        $fields = [
            'last_name'   => '',
            'first_name'  => '',
            'middle_name' => '',
            'suffix'      => '',
            'address'     => '',
            'contact_no'  => '',
            'birthday'    => '',
        ];

        $labelMap = [
            'LN'   => 'last_name',
            'FN'   => 'first_name',
            'MN'   => 'middle_name',
            'SF'   => 'suffix',
            'ADDR' => 'address',
            'CN'   => 'contact_no',
            'BD'   => 'birthday',
        ];

        foreach (explode(';', $packed) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || strpos($segment, ':') === false) {
                continue;
            }

            [$label, $value] = explode(':', $segment, 2);
            $label = strtoupper(trim($label));

            if (isset($labelMap[$label])) {
                $fields[$labelMap[$label]] = trim($value);
            }
        }

        return $fields;
    }

    /**
     * Middle name → initials with periods, one per whitespace-separated word,
     * for compact name display: "Torres" → "T.", "Dela Cruz" → "D. C.", "" → "".
     */
    public static function middleInitial(string $middle): string
    {
        $parts = [];

        foreach (preg_split('/\s+/', trim($middle)) ?: [] as $word) {
            if ($word !== '') {
                $parts[] = mb_strtoupper(mb_substr($word, 0, 1), 'UTF-8') . '.';
            }
        }

        return implode(' ', $parts);
    }

    /** Splits an array or comma string into a trimmed, non-empty list of strings. */
    public static function splitList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', array_map('strval', $items))));
    }

    /** Coerces a value into a list of ints (optionally dropping non-numeric items). */
    public static function integerList(mixed $value, bool $numericOnly = false): array
    {
        $items = (array) $value;

        if ($numericOnly) {
            $items = array_filter($items, 'is_numeric');
        }

        return array_values(array_map('intval', $items));
    }

    /**
     * Given the grouped sector catalog and the IDs a member has, returns which
     * category keys are selected — used to pre-expand the right sector groups in
     * the family form.
     */
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

    /** Category keys in the catalog that actually contain sectors (for rendering tabs). */
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

    /** Distinct service category list (seeded with defaults) for category dropdowns. */
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

    /**
     * Returns the member-row sectors as a single "Sectors" group. Sectors are flat
     * classifications after the Phase A restructure (SC, PWD, SP, B, LGBT, OFW, IP,
     * IDP, PDL, OTHER), so there is no per-category grouping. The grouped shape is
     * kept for callers that render [{label, sectors}]. $categoryLabels is accepted
     * for signature stability but no longer used. Empty input yields [].
     */
    public static function memberSectorGroups(array $sectorOptions, array $categoryLabels = []): array
    {
        $sectors = [];

        foreach ($sectorOptions as $sector) {
            $shortcode = trim((string) ($sector['shortcode'] ?? ''));
            $name = trim((string) ($sector['name'] ?? ''));

            if ($shortcode === '' && $name === '') {
                continue;
            }

            $sectors[] = $sector;
        }

        return $sectors === [] ? [] : [['label' => 'Sectors', 'sectors' => $sectors]];
    }

    /** Compact, safe string form of any value for debug output in views. */
    public static function debugArgument(mixed $value): string
    {
        return match (true) {
            is_object($value) => 'Object(' . $value::class . ')',
            is_array($value) => $value !== [] ? '[...]' : '[]',
            $value === null => 'null',
            default => var_export($value, true),
        };
    }

    /** Shared date/time formatter: parses a value and formats it, or returns $fallback. */
    private static function formatTimestamp(mixed $value, string $format, string $fallback): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? $fallback : date($format, $timestamp);
    }
}
