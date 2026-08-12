<?php

namespace App\Validation;

use App\Libraries\SectorIds;

/**
 * Custom validation rules for sector input, registered in Config\Validation.
 */
class SectorRules
{
    /**
     * The `valid_sector_array` rule used by FamilyController: passes only when
     * the submitted sectors are a well-formed, non-empty list of IDs. Sets
     * $error with a field-specific message on failure. Frontend: guards the
     * sector multi-select on the family form.
     *
     * There is no length ceiling any more: the ids used to be packed into a
     * varchar(255), and V22 moved them to one row per sector in member_sectors.
     */
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

        return true;
    }
}
