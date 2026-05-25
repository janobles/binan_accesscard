<?php

use App\Support\DashboardViewData;

if (! function_exists('family_form_view_data')) {
    function family_form_view_data(array $data = []): array
    {
        return DashboardViewData::familyForm($data);
    }
}
