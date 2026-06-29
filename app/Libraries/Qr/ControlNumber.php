<?php

namespace App\Libraries\Qr;

/**
 * Bijection between a family head's memberID and its printed control number.
 *
 * With $controlNumberWidth = 1, the control number is the head's bare memberID
 * with no leading zeros (e.g. memberID 42 → "42"). format()/parse() are a true
 * bijection for all positive memberIDs up to PHP_INT_MAX — there is no width-based
 * ceiling. Because memberID is the unique primary key of the `member` table, one
 * head maps to exactly one control number and vice-versa.
 *
 * format() / parse() are pure string<->int transforms. Whether a parsed id is
 * actually a *head* (headID == memberID) is the caller's check, via MemberModel.
 */
final class ControlNumber
{
    private static function width(): int
    {
        return config('QrCardSettings')->controlNumberWidth;
    }

    public static function format(int $memberID): string
    {
        return str_pad((string) $memberID, self::width(), '0', STR_PAD_LEFT);
    }

    /**
     * Returns the memberID encoded by $control, or null when $control is not a
     * positive all-digits string. Accepts any length — there is no width ceiling,
     * so format()/parse() are bijective for all positive memberIDs.
     */
    public static function parse(string $control): ?int
    {
        if ($control === '' || ! ctype_digit($control)) {
            return null;
        }

        $memberID = (int) $control;

        return $memberID > 0 ? $memberID : null;
    }
}
