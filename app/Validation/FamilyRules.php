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
}
