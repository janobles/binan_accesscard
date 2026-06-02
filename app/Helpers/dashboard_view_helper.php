<?php

use App\Support\DashboardViewData;

/**
 * Procedural view helpers that let templates turn raw controller data into their
 * expected variables via simple function calls. Each one delegates to the matching
 * App\Support\DashboardViewData method; they exist so views can call e.g.
 * `dashboard_admin_view_data($data)` instead of referencing the class directly.
 */

if (! function_exists('dashboard_admin_view_data')) {
    /** View variables for the admin shell. */
    function dashboard_admin_view_data(array $data): array
    {
        return DashboardViewData::admin($data);
    }
}

if (! function_exists('dashboard_employee_view_data')) {
    /** View variables for the employee shell. */
    function dashboard_employee_view_data(array $data): array
    {
        return DashboardViewData::employee($data);
    }
}

if (! function_exists('accounts_view_data')) {
    /** View variables for the accounts table view/partial. */
    function accounts_view_data(array $data): array
    {
        return DashboardViewData::accounts($data);
    }
}

if (! function_exists('audit_trails_view_data')) {
    /** View variables for the audit-trails view/partial. */
    function audit_trails_view_data(array $data): array
    {
        return DashboardViewData::auditTrails($data);
    }
}

if (! function_exists('family_list_view_data')) {
    /** View variables for the family records list. */
    function family_list_view_data(array $data): array
    {
        return DashboardViewData::familyList($data);
    }
}

if (! function_exists('family_details_view_data')) {
    /** View variables for the single-family detail (view/edit) screen. */
    function family_details_view_data(array $data): array
    {
        return DashboardViewData::familyDetails($data);
    }
}

if (! function_exists('sector_and_services_view_data')) {
    /** View variables for the family form's sector/service selection. */
    function sector_and_services_view_data(array $data): array
    {
        return DashboardViewData::sectorAndServices($data);
    }
}

if (! function_exists('sector_management_view_data')) {
    /** View variables for the sector management screen. */
    function sector_management_view_data(array $data): array
    {
        return DashboardViewData::sectorManagement($data);
    }
}

if (! function_exists('service_management_view_data')) {
    /** View variables for the service management screen. */
    function service_management_view_data(array $data): array
    {
        return DashboardViewData::serviceManagement($data);
    }
}
