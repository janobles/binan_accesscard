<?php

namespace App\Libraries\Qr;

/**
 * Bijection between a family head's memberID and its printed control number.
 *
 * The control number is the head's memberID zero-padded to the configured width
 * (QrCardSettings::$controlNumberWidth). Because memberID is the unique primary
 * key of the `member` table, one head maps to exactly one control number and
 * vice-versa — there is no separate stored code and no way to mint a duplicate.
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
     * positive all-digits string within the configured width.
     */
    public static function parse(string $control): ?int
    {
        if ($control === '' || ! ctype_digit($control)) {
            return null;
        }

        if (strlen($control) > self::width()) {
            return null;
        }

        $memberID = (int) $control;

        return $memberID > 0 ? $memberID : null;
    }
}
