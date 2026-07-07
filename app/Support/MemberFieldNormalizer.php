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

    /**
     * Inverse of combineAddressBarangay(): splits a stored address back into its
     * address + barangay parts so the edit form can prefill both inputs. Matches the
     * trailing barangay against the canonical list (longest match first so
     * "Binan (Poblacion)" wins over "Poblacion").
     *
     * @return array{address: string, barangay: string}
     */
    public static function splitAddressBarangay(mixed $combined): array
    {
        $combined = trim((string) $combined);
        $barangays = FamilyProfilingFormV2::barangays();
        usort($barangays, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($barangays as $barangay) {
            $suffix = ', ' . $barangay;

            if (mb_strlen($combined) >= mb_strlen($suffix)
                && strcasecmp(mb_substr($combined, -mb_strlen($suffix)), $suffix) === 0) {
                return [
                    'address' => rtrim(mb_substr($combined, 0, mb_strlen($combined) - mb_strlen($suffix))),
                    'barangay' => $barangay,
                ];
            }

            if (strcasecmp($combined, $barangay) === 0) {
                return ['address' => '', 'barangay' => $barangay];
            }
        }

        return ['address' => $combined, 'barangay' => ''];
    }
}
