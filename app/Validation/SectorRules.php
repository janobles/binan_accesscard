<?php

namespace App\Validation;

use App\Support\SectorIds;

class SectorRules
{
    public function valid_sector_array(mixed $value, ?string &$error = null): bool
    {
        if (SectorIds::hasMalformedIds($value)) {
            $error = 'The {field} field must contain valid sector IDs.';

            return false;
        }

        if (SectorIds::normalize($value) === []) {
            $error = 'The {field} field must contain at least one sector ID.';

            return false;
        }

        if (strlen(SectorIds::toStorage($value)) > 255) {
            $error = 'The {field} field contains too many sector IDs.';

            return false;
        }

        return true;
    }
}
