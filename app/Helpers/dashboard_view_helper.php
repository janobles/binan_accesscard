<?php

use App\Support\DashboardViewData;

/**
 * Procedural view helpers that let templates turn raw controller data into their
 * expected variables via simple function calls. Each one delegates to the matching
 * App\Support\DashboardViewData method; they exist so views can call e.g.
 * `dashboard_admin_view_data($data)` instead of referencing the class directly.
 */

if (! function_exists('dashboard_admin_view_data')) {
    /**
     * Builds the Admin shell bundle: page chrome, the dashboard search panel,
     * and the accounts/audit lists the sidebar links jump to.
     *
     * @return array{
     *     activePage: string,
     *     adminAccounts: list<array<string, mixed>>,
     *     auditActionOptions: list<string>,
     *     auditListData: array<string, mixed>,
     *     canCreateFamily: bool,
     *     canManageAccounts: bool,
     *     employeeAccounts: list<array<string, mixed>>,
     *     formatDate: callable,
     *     formatTime: callable,
     *     hasSearchFilters: bool,
     *     idleTimeoutSeconds: int,
     *     modeLabel: string,
     *     navActive: array<string, string>,
     *     pageTitle: string,
     *     recentAudits: list<array<string, mixed>>,
     *     recentFamilies: list<array<string, mixed>>,
     *     searchFilters: array<string, mixed>,
     *     searchTerm: string,
     *     sectorShortcodeOptions: list<string>,
     *     sectorOptions: list<array<string, mixed>>,
     *     stats: array<string, int>,
     *     user: array<string, mixed>,
     *     username: string
     * }
     */
    function dashboard_admin_view_data(array $data): array
    {
        return DashboardViewData::admin($data);
    }
}

if (! function_exists('dashboard_employee_view_data')) {
    /**
     * Builds the Employee shell bundle: the same page-chrome and search-panel
     * variables as the admin shell, scoped to the signed-in employee's own
     * audit rows (myAudits) instead of the account lists an employee can't manage.
     *
     * @return array{
     *     activePage: string,
     *     auditActionOptions: list<string>,
     *     auditListData: array<string, mixed>,
     *     canCreateFamily: bool,
     *     formatDate: callable,
     *     formatTime: callable,
     *     hasSearchFilters: bool,
     *     idleTimeoutSeconds: int,
     *     myAudits: list<array<string, mixed>>,
     *     navActive: array<string, string>,
     *     pageTitle: string,
     *     recentFamilies: list<array<string, mixed>>,
     *     searchFilters: array<string, mixed>,
     *     searchTerm: string,
     *     sectorOptions: list<array<string, mixed>>,
     *     stats: array<string, int>,
     *     user: array<string, mixed>,
     *     username: string
     * }
     */
    function dashboard_employee_view_data(array $data): array
    {
        return DashboardViewData::employee($data);
    }
}

if (! function_exists('accounts_view_data')) {
    /**
     * Builds the accounts table view/partial bundle: admin and employee
     * account lists split apart, plus the search panel state.
     *
     * @return array{
     *     adminAccounts: list<array<string, mixed>>,
     *     employeeAccounts: list<array<string, mixed>>,
     *     formatDate: callable,
     *     formatTime: callable,
     *     hasSearchFilters: bool,
     *     searchFilters: array<string, mixed>,
     *     searchTerm: string
     * }
     */
    function accounts_view_data(array $data): array
    {
        return DashboardViewData::accounts($data);
    }
}

if (! function_exists('audit_trails_view_data')) {
    /**
     * Builds the audit-trails view/partial bundle: the current page of rows
     * plus the pagination counters the table footer renders.
     *
     * @return array{
     *     auditActionOptions: list<string>,
     *     auditFromRecord: int,
     *     auditPage: int,
     *     auditPerPage: int,
     *     auditToRecord: int,
     *     auditTotal: int,
     *     auditTotalPages: int,
     *     formatDate: callable,
     *     formatTime: callable,
     *     hasSearchFilters: bool,
     *     recentAudits: list<array<string, mixed>>,
     *     searchFilters: array<string, mixed>,
     *     searchTerm: string
     * }
     */
    function audit_trails_view_data(array $data): array
    {
        return DashboardViewData::auditTrails($data);
    }
}

if (! function_exists('family_list_view_data')) {
    /**
     * Builds the family records list bundle.
     *
     * @return array{
     *     families: list<array<string, mixed>>,
     *     formatDate: callable,
     *     formatTime: callable,
     *     keyword: string,
     *     routeBase: string
     * }
     */
    function family_list_view_data(array $data): array
    {
        return DashboardViewData::familyList($data);
    }
}

if (! function_exists('family_details_view_data')) {
    /**
     * Builds the single-family detail (view/edit) bundle: the head row, the
     * rest of the members, and the service ID/name maps used to label them.
     * No current view calls this helper (family detail rendering goes through
     * FamilyRecordPresenter instead); the shape below is only what
     * DashboardViewData::familyDetails() itself guarantees.
     *
     * @return array{
     *     head: array<string, mixed>,
     *     members: list<array<string, mixed>>,
     *     serviceMap: array<string, mixed>,
     *     serviceNameMap: array<string, mixed>
     * }
     */
    function family_details_view_data(array $data): array
    {
        return DashboardViewData::familyDetails($data);
    }
}

if (! function_exists('sector_and_services_view_data')) {
    /**
     * Builds the family form's sector/service selection bundle: the grouped
     * options plus which ones are already selected. No current view calls
     * this helper; the shape below is only what
     * DashboardViewData::sectorAndServices() itself guarantees.
     *
     * @return array{
     *     sectorGroups: array<string, mixed>,
     *     selectedSectorIds: list<int>,
     *     selectedServiceIds: list<int>,
     *     serviceGroups: array<string, mixed>
     * }
     */
    function sector_and_services_view_data(array $data): array
    {
        return DashboardViewData::sectorAndServices($data);
    }
}

if (! function_exists('sector_management_view_data')) {
    /**
     * Builds the Sectors page bundle: the paged list plus the data the
     * Add-Sector modal needs for its inline duplicate check. Counts cover
     * the whole table, not the current page.
     *
     * @return array{
     *     sectors: list<array<string, mixed>>,
     *     sectorShortcodeOptions: list<string>,
     *     canRestore: bool,
     *     existingShortcodes: list<string>,
     *     status: string,
     *     keyword: string,
     *     page: int,
     *     perPage: int,
     *     perPageOptions: list<int>,
     *     totalPages: int,
     *     totalRows: int,
     *     fromRecord: int,
     *     toRecord: int,
     *     activeCount: int,
     *     archivedCount: int,
     *     listRoute: string
     * }
     */
    function sector_management_view_data(array $data): array
    {
        return DashboardViewData::sectorManagement($data);
    }
}

if (! function_exists('service_management_view_data')) {
    /**
     * Builds the Services page bundle: the paged list, the Add-Program modal's
     * category dropdown (union of active sectors, managed categories, and any
     * category string already used on a service), and a suggested next service
     * code per category. Counts cover the whole table, not the current page.
     *
     * @return array{
     *     services: list<array<string, mixed>>,
     *     canRestore: bool,
     *     serviceCategoryOptions: list<string>,
     *     serviceNextCodeMap: array<string, string>,
     *     existingShortcodes: list<string>,
     *     status: string,
     *     keyword: string,
     *     page: int,
     *     perPage: int,
     *     perPageOptions: list<int>,
     *     totalPages: int,
     *     totalRows: int,
     *     fromRecord: int,
     *     toRecord: int,
     *     activeCount: int,
     *     archivedCount: int,
     *     listRoute: string
     * }
     */
    function service_management_view_data(array $data): array
    {
        return DashboardViewData::serviceManagement($data);
    }
}

if (! function_exists('category_management_view_data')) {
    /**
     * Builds the Categories page bundle: the paged list plus every existing
     * code (including archived rows) for the Add-Category modal's duplicate
     * check. Counts cover the whole table, not the current page.
     *
     * @return array{
     *     categories: list<array<string, mixed>>,
     *     canRestore: bool,
     *     existingCodes: list<string>,
     *     status: string,
     *     keyword: string,
     *     page: int,
     *     perPage: int,
     *     perPageOptions: list<int>,
     *     totalPages: int,
     *     totalRows: int,
     *     fromRecord: int,
     *     toRecord: int,
     *     activeCount: int,
     *     archivedCount: int,
     *     listRoute: string
     * }
     */
    function category_management_view_data(array $data): array
    {
        return DashboardViewData::categoryManagement($data);
    }
}
