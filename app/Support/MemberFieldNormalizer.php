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
 * Pure functions: no DB, session, or request access — the same input always
 * produces the same stored value regardless of which path called it.
 */
class MemberFieldNormalizer
{
    /**
     * Cleans a person-name field: keeps only letters (incl. ñ/Ñ and accents),
     * spaces and the - ' . punctuation real names use, collapses repeated
     * whitespace, then applies Title Case. Workers may type freely; the stored
     * value is normalized here. Used for first/middle/last names.
     */
    public static function cleanName(mixed $value): string
    {
        $value = preg_replace("/[^\\p{L}\\s.'-]/u", '', (string) $value);
        $value = trim((string) preg_replace('/\\s+/u', ' ', (string) $value));

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Cleans an address/barangay field: address-safe allowlist of letters, digits,
     * spaces and # , . - / ' ( ) & (so house/block numbers survive), collapses
     * repeated whitespace, then applies Title Case. Strips odd symbols such as
     * < > | \ " : ] [.
     */
    public static function cleanAddress(mixed $value): string
    {
        $value = preg_replace("/[^\\p{L}\\p{N}\\s#,.\\-\\/'()&]/u", '', (string) $value);
        $value = trim((string) preg_replace('/\\s+/u', ' ', (string) $value));

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
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
     * Parses a salary input into a float, stripping thousands separators, or null
     * when blank. Keeps the `Salary` column numeric/nullable.
     */
    public static function moneyOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * Combines the separate Address and Barangay inputs into the single
     * `member.address` column ("address, barangay"). The schema has no barangay
     * column; barangay is kept only as a form/sheet field for entry.
     */
    public static function combineAddressBarangay(mixed $address, mixed $barangay): ?string
    {
        $address = self::cleanAddress($address);
        $barangay = self::cleanAddress($barangay);
        $combined = trim($address . ($address !== '' && $barangay !== '' ? ', ' : '') . $barangay);

        return $combined === '' ? null : $combined;
    }
}
