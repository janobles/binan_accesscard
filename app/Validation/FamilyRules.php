<?php

namespace App\Validation;

use DateTimeImmutable;

/** Custom validation rules for family profile fields. */
class FamilyRules
{
    /** Accepts a valid Y-m-d date only when it is today or earlier. */
    public function not_future_date(mixed $value, ?string &$error = null): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        if ($date === false || $date->format('Y-m-d') !== (string) $value) {
            $error = 'The {field} field must contain a valid date.';

            return false;
        }

        if ($date > new DateTimeImmutable('today')) {
            $error = 'The {field} field cannot be a future date.';

            return false;
        }

        return true;
    }

    /** Rejects values made entirely of numbers, with optional whitespace. */
    public function not_numeric_only(mixed $value, ?string &$error = null): bool
    {
        $compact = preg_replace('/\s+/u', '', trim((string) $value));

        if ($compact !== '' && preg_match('/^\p{N}+$/u', $compact) === 1) {
            $error = 'The {field} field cannot contain numbers only.';

            return false;
        }

        return true;
    }
}
