<?php

use App\Support\FamilyFormViewData;

if (! function_exists('family_form_view_data')) {
    /**
     * View helper that prepares all family-form variables (create/edit aware) by
     * delegating to App\Support\FamilyFormViewData::prepare(). Called by the family
     * form view so the template can extract ready-to-use variables.
     */
    function family_form_view_data(array $data): array
    {
        return FamilyFormViewData::prepare($data);
    }
}
