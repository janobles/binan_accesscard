<?php

namespace App\Support;

/**
 * Single source of truth for cleaning/normalizing `member` field values on save.
 *
 * Both entry paths share these rules:
 *   - the manual Add/Edit Family form (App\Controllers\Families\FamilyController,
 *     whose private cleaners now delegate here), and
 *   - the Excel bulk importer (App\Libraries\FamilyExcelImporter).
 *
 * Pure functions: no DB, session, or request access - the same input always
 * produces the same stored value regardless of which path called it.
 */
class MemberFieldNormalizer
{
    /**
     * Placeholder words a worker types into a cell to mean "no data" instead of
     * leaving it blank. Matched case-insensitively and with all whitespace removed,
     * so the set is stored space-free ("no data" -> "nodata"). Treated as an empty
     * cell everywhere: never stored, and required fields still flag as missing.
     *
     * @var list<string>
     */
    private const NO_DATA_TOKENS = [
        'none', 'n/a', 'na', 'nil', 'null', 'blank', 'empty',
        'nodata', 'notapplicable', 'notavailable', 'unknown', 'unk',
    ];

    /**
     * True when a cell is a no-data placeholder (case-insensitive, spacing ignored),
     * e.g. "None", "  N/A  ", "no data", "N / A". Numbers (incl. 0) are never matched,
     * so a real income of 0 survives.
     */
    public static function isNoData(mixed $value): bool
    {
        // Lowercase + strip ALL whitespace so "No Data"/"N / A"/" NONE " normalize.
        $key = strtolower((string) preg_replace('/\s+/u', '', trim((string) $value)));

        return $key !== '' && in_array($key, self::NO_DATA_TOKENS, true);
    }

    /**
     * Returns '' when the value is a no-data placeholder, otherwise the trimmed
     * value. Apply at cell-read time so downstream blank/required checks see an
     * empty string for placeholders.
     */
    public static function blankIfNoData(mixed $value): string
    {
        $trimmed = trim((string) $value);

        return self::isNoData($trimmed) ? '' : $trimmed;
    }

    /**
     * Cleans a person-name field: keeps only letters (incl. ñ/Ñ and accents),
     * spaces and the - ' . punctuation real names use, collapses repeated
     * whitespace, then uppercases. Workers may type freely; the stored value is
     * normalized here. Used for first/middle/last names.
     */
    public static function cleanName(mixed $value): string
    {
        $value = preg_replace("/[^\\p{L}\\s.'-]/u", '', (string) $value);
        $value = trim((string) preg_replace('/\\s+/u', ' ', (string) $value));

        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Cleans an address/barangay field: address-safe allowlist of letters, digits,
     * spaces and # , . - / ' ( ) & (so house/block numbers survive), collapses
     * repeated whitespace, then uppercases. Strips odd symbols such as
     * < > | \ " : ] [.
     */
    public static function cleanAddress(mixed $value): string
    {
        $value = preg_replace("/[^\\p{L}\\p{N}\\s#,.\\-\\/'()&]/u", '', (string) $value);
        $value = trim((string) preg_replace('/\\s+/u', ' ', (string) $value));

        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Trims a value to a string, returning null when empty so optional columns
     * store NULL rather than ''. Used throughout the payload builders.
     */
    public static function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * A stored salary as the income select spells it. member.salary is
     * decimal(12,2) since V22, so it reads back "25000.00" while the option
     * values are plain integers - without this the select matches nothing,
     * renders empty, and blocks Save on a required field.
     */
    public static function salaryOptionValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $amount = (float) $value;

        return $amount === floor($amount) ? (string) (int) $amount : (string) $amount;
    }

    /**
     * Uppercased trimmed value, or null when empty. For the choice fields that
     * also accept free text (civil status, religion, education, job,
     * relationship): the dropdown values are uppercase, so an "Other" answer
     * typed by hand has to be stored the same way or the same answer reads two
     * ways depending on how it was entered.
     */
    public static function nullableUpperText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Parses a salary input into a float, stripping thousands separators, or null
     * when blank. Keeps the `salary` column numeric/nullable.
     */
    public static function moneyOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * Folds a barangay name to a comparable key: lowercase, ñ to n, a trailing
     * "(alias)" dropped, Sto./Sta. expanded to Santo/Santa, punctuation stripped,
     * whitespace collapsed. Lets "Biñan", "Sto. Tomas" and "Santo Tomas
     * (Calabuso)" all resolve to the same barangay regardless of which spelling
     * produced them - the Excel importer's barangay warning already relied on
     * this fold; member.barangayID resolution reuses it so the two paths agree.
     */
    public static function barangayKey(mixed $value): string
    {
        $key = mb_strtolower(trim((string) $value));
        $key = strtr($key, ['ñ' => 'n']);
        $key = (string) preg_replace('/\([^)]*\)/', ' ', $key);
        $key = strtr($key, ['sto.' => 'santo', 'sto ' => 'santo ', 'sta.' => 'santa', 'sta ' => 'santa ']);
        $key = (string) preg_replace('/[^a-z0-9 ]/', ' ', $key);

        return trim((string) preg_replace('/\s+/', ' ', $key));
    }
}
