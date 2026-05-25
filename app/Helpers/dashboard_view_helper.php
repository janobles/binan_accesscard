<?php

use App\Support\DashboardViewData;

if (! function_exists('admin_dashboard_view_data')) {
    function admin_dashboard_view_data(array $data = []): array
    {
        return DashboardViewData::admin($data);
    }
}

if (! function_exists('employee_dashboard_view_data')) {
    function employee_dashboard_view_data(array $data = []): array
    {
        return DashboardViewData::employee($data);
    }
}

if (! function_exists('accounts_view_data')) {
    function accounts_view_data(array $data = []): array
    {
        return DashboardViewData::accounts($data);
    }
}

if (! function_exists('audit_trails_view_data')) {
    function audit_trails_view_data(array $data = []): array
    {
        return DashboardViewData::auditTrails($data);
    }
}

if (! function_exists('family_list_view_data')) {
    function family_list_view_data(array $data = []): array
    {
        return DashboardViewData::familyList($data);
    }
}

if (! function_exists('family_details_view_data')) {
    function family_details_view_data(array $data = []): array
    {
        return DashboardViewData::familyDetails($data);
    }
}

if (! function_exists('sector_and_services_view_data')) {
    function sector_and_services_view_data(array $data = []): array
    {
        return DashboardViewData::sectorAndServices($data);
    }
}

if (! function_exists('sector_management_view_data')) {
    function sector_management_view_data(array $data = []): array
    {
        return DashboardViewData::sectorManagement($data);
    }
}

if (! function_exists('service_management_view_data')) {
    function service_management_view_data(array $data = []): array
    {
        return DashboardViewData::serviceManagement($data);
    }
}
