<?php

use App\Support\FamilyFormViewData;

if (! function_exists('family_form_view_data')) {
    function family_form_view_data(array $data = []): array
    {
        return FamilyFormViewData::prepare($data);
    }
}
