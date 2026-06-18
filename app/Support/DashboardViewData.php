<?php

namespace App\Support;

/**
 * Prepares dashboard view variables before templates render markup.
 */
/**
 * Normalizes raw controller data into the exact variables each dashboard view/
 * partial expects (with safe defaults). Called from the dashboard view helper just
 * before templates render, so views never deal with missing keys.
 */
class DashboardViewData
{
    /** Prepares variables for the admin shell (`Admin/layout`). */
    public static function admin(array $data): array
    {
        $user = self::arrayValue($data['user'] ?? []);
        $username = $user['username'] ?? 'Admin';
        $activePage = (string) ($data['activePage'] ?? 'dashboard');
        $pageTitle = (string) ($data['pageTitle'] ?? 'Dashboard');
        $modeLabel = (string) ($data['modeLabel'] ?? 'Admin Console');
        $canManageAccounts = (bool) ($data['canManageAccounts'] ?? false);
        $navActive = self::arrayValue($data['navActive'] ?? []);
        $stats = array_merge(self::defaultStats(), self::arrayValue($data['stats'] ?? []));
        $recentFamilies = self::arrayValue($data['recentFamilies'] ?? []);
        $recentAudits = self::arrayValue($data['recentAudits'] ?? []);
        $adminAccounts = self::arrayValue($data['adminAccounts'] ?? []);
        $employeeAccounts = self::arrayValue($data['employeeAccounts'] ?? []);
        $familyFormViewData = self::arrayValue($data['familyFormViewData'] ?? []);
        $sectorShortcodeOptions = self::stringList($data['sectorShortcodeOptions'] ?? []);
        $searchTerm = (string) ($data['searchTerm'] ?? '');
        $searchFilters = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $auditListData = self::arrayValue($data['auditListData'] ?? []);
        $sectorOptions = self::arrayValue($familyFormViewData['sectorOptions'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $canCreateFamily = (bool) ($data['canCreateFamily'] ?? false);
        $idleTimeoutSeconds = (int) ($data['idleTimeoutSeconds'] ?? 900);
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'activePage',
            'adminAccounts',
            'auditActionOptions',
            'auditListData',
            'canCreateFamily',
            'canManageAccounts',
            'employeeAccounts',
            'familyFormViewData',
            'formatDate',
            'formatTime',
            'hasSearchFilters',
            'idleTimeoutSeconds',
            'modeLabel',
            'navActive',
            'pageTitle',
            'recentAudits',
            'recentFamilies',
            'searchFilters',
            'searchTerm',
            'sectorShortcodeOptions',
            'sectorOptions',
            'stats',
            'user',
            'username'
        );
    }

    /** Prepares variables for the employee shell (`Employee/layout`). */
    public static function employee(array $data): array
    {
        $user = self::arrayValue($data['user'] ?? []);
        $username = $user['username'] ?? 'Employee';
        $activePage = (string) ($data['activePage'] ?? 'dashboard');
        $pageTitle = (string) ($data['pageTitle'] ?? ($activePage === 'dashboard' ? 'Workspace' : ucwords(str_replace('-', ' ', $activePage))));
        $navActive = self::arrayValue($data['navActive'] ?? []);
        $stats = array_merge(self::defaultStats(), self::arrayValue($data['stats'] ?? []));
        $recentFamilies = self::arrayValue($data['recentFamilies'] ?? []);
        $myAudits = self::arrayValue($data['myAudits'] ?? []);
        $familyFormViewData = self::arrayValue($data['familyFormViewData'] ?? []);
        $searchTerm = (string) ($data['searchTerm'] ?? '');
        $searchFilters = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $auditListData = self::arrayValue($data['auditListData'] ?? []);
        $sectorOptions = self::arrayValue($familyFormViewData['sectorOptions'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $canCreateFamily = (bool) ($data['canCreateFamily'] ?? false);
        $idleTimeoutSeconds = (int) ($data['idleTimeoutSeconds'] ?? 900);
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'activePage',
            'auditActionOptions',
            'auditListData',
            'canCreateFamily',
            'familyFormViewData',
            'formatDate',
            'formatTime',
            'hasSearchFilters',
            'idleTimeoutSeconds',
            'myAudits',
            'navActive',
            'pageTitle',
            'recentFamilies',
            'searchFilters',
            'searchTerm',
            'sectorOptions',
            'stats',
            'user',
            'username'
        );
    }

    /** Prepares variables for the accounts table view/partial. */
    public static function accounts(array $data): array
    {
        $adminAccounts = self::arrayValue($data['adminAccounts'] ?? []);
        $employeeAccounts = self::arrayValue($data['employeeAccounts'] ?? []);
        $searchTerm = (string) ($data['searchTerm'] ?? '');
        $searchFilters = self::arrayValue($data['searchFilters'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'adminAccounts',
            'employeeAccounts',
            'formatDate',
            'formatTime',
            'hasSearchFilters',
            'searchFilters',
            'searchTerm'
        );
    }

    /** Prepares variables for the audit-trails view/partial. */
    public static function auditTrails(array $data): array
    {
        $recentAudits = self::arrayValue($data['recentAudits'] ?? []);
        $searchTerm = (string) ($data['searchTerm'] ?? '');
        $searchFilters = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $auditPage = max(1, (int) ($data['auditPage'] ?? 1));
        $auditPerPage = max(1, (int) ($data['auditPerPage'] ?? 50));
        $auditTotal = max(0, (int) ($data['auditTotal'] ?? count($recentAudits)));
        $auditTotalPages = max(1, (int) ($data['auditTotalPages'] ?? (int) ceil($auditTotal / $auditPerPage)));
        $auditFromRecord = max(0, (int) ($data['auditFromRecord'] ?? ($auditTotal === 0 ? 0 : (($auditPage - 1) * $auditPerPage) + 1)));
        $auditToRecord = max(0, (int) ($data['auditToRecord'] ?? min($auditTotal, $auditPage * $auditPerPage)));
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'auditActionOptions',
            'auditFromRecord',
            'auditPage',
            'auditPerPage',
            'auditToRecord',
            'auditTotal',
            'auditTotalPages',
            'formatDate',
            'formatTime',
            'hasSearchFilters',
            'recentAudits',
            'searchFilters',
            'searchTerm'
        );
    }

    /** Prepares variables for the family records list view/partial. */
    public static function familyList(array $data): array
    {
        $families = self::arrayValue($data['families'] ?? []);
        $keyword = (string) ($data['keyword'] ?? '');
        $routeBase = (string) ($data['routeBase'] ?? 'admin/manage-family');
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'families',
            'formatDate',
            'formatTime',
            'keyword',
            'routeBase'
        );
    }

    /** Prepares variables for the single-family detail (view/edit) view. */
    public static function familyDetails(array $data): array
    {
        $head = self::arrayValue($data['head'] ?? []);
        $members = self::arrayValue($data['members'] ?? []);
        $serviceMap = self::arrayValue($data['serviceMap'] ?? []);
        $serviceNameMap = self::arrayValue($data['serviceNameMap'] ?? []);

        return compact(
            'head',
            'members',
            'serviceMap',
            'serviceNameMap'
        );
    }

    /** Prepares the combined sector/service selection data for the family form. */
    public static function sectorAndServices(array $data): array
    {
        $serviceGroups = self::arrayValue($data['serviceGroups'] ?? []);
        $sectorGroups = self::arrayValue($data['sectorGroups'] ?? []);
        $selectedSectorIds = self::integerList($data['selectedSectorIds'] ?? []);
        $selectedServiceIds = self::integerList($data['selectedServiceIds'] ?? []);

        return compact(
            'sectorGroups',
            'selectedSectorIds',
            'selectedServiceIds',
            'serviceGroups'
        );
    }

    /** Prepares variables for the sector management view (paginated list + search/status). */
    public static function sectorManagement(array $data): array
    {
        $bundle = (array) ($data['sectorListData'] ?? []);
        $sectors = self::arrayValue($bundle['rows'] ?? $data['sectors'] ?? []);
        $sectorShortcodeOptions = self::stringList($data['sectorShortcodeOptions'] ?? []);
        $canRestore = (bool) ($data['canRestore'] ?? false);

        return array_merge(
            compact('sectorShortcodeOptions', 'sectors', 'canRestore'),
            self::lookupListVars($bundle, 'admin/sectors')
        );
    }

    /** Prepares variables for the service management view (paginated list + search/status). */
    public static function serviceManagement(array $data): array
    {
        $bundle = (array) ($data['serviceListData'] ?? []);
        $services = self::arrayValue($bundle['rows'] ?? $data['services'] ?? []);
        $canRestore = (bool) ($data['canRestore'] ?? false);

        return array_merge(
            compact('services', 'canRestore'),
            self::lookupListVars($bundle, 'admin/services')
        );
    }

    /** Prepares variables for the manage-categories view (paginated list + search/status). */
    public static function categoryManagement(array $data): array
    {
        $bundle = (array) ($data['categoryListData'] ?? []);
        $categories = self::arrayValue($bundle['rows'] ?? $data['categories'] ?? []);
        $canRestore = (bool) ($data['canRestore'] ?? false);

        return array_merge(
            compact('categories', 'canRestore'),
            self::lookupListVars($bundle, 'admin/categories')
        );
    }

    /**
     * Normalizes the paginated lookup-list bundle (from DashboardPageBuilder::
     * buildLookupListData) into the view vars shared by all three lookup pages:
     * status, keyword, pagination markers and the active/archived count badges.
     */
    private static function lookupListVars(array $bundle, string $defaultRoute): array
    {
        $status = (string) ($bundle['status'] ?? 'active');
        $status = in_array($status, ['active', 'archived', 'all'], true) ? $status : 'active';

        return [
            'status'        => $status,
            'keyword'       => (string) ($bundle['keyword'] ?? ''),
            'page'          => max(1, (int) ($bundle['page'] ?? 1)),
            'perPage'       => max(1, (int) ($bundle['perPage'] ?? 50)),
            'perPageOptions'=> array_values(array_map('intval', (array) ($bundle['perPageOptions'] ?? []))) ?: [10, 25, 50, 100],
            'totalPages'    => max(1, (int) ($bundle['totalPages'] ?? 1)),
            'totalRows'     => max(0, (int) ($bundle['totalRows'] ?? 0)),
            'fromRecord'    => max(0, (int) ($bundle['fromRecord'] ?? 0)),
            'toRecord'      => max(0, (int) ($bundle['toRecord'] ?? 0)),
            'activeCount'   => max(0, (int) ($bundle['activeCount'] ?? 0)),
            'archivedCount' => max(0, (int) ($bundle['archivedCount'] ?? 0)),
            'listRoute'     => (string) ($bundle['listRoute'] ?? $defaultRoute),
        ];
    }

    /** Zeroed stats array used as a default before real counts are merged in. */
    private static function defaultStats(): array
    {
        return [
            'families' => 0,
            'members' => 0,
            'sectors' => 0,
            'assistance' => 0,
        ];
    }

    /** True if a search term or any filter is set (toggles "filters active" UI). */
    private static function hasSearchFilters(string $searchTerm, array $searchFilters): bool
    {
        if ($searchTerm !== '') {
            return true;
        }

        foreach ($searchFilters as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /** Returns a Y-m-d date-formatting closure passed to views as $formatDate. */
    private static function formatDateCallback(): callable
    {
        return static function (mixed $value): string {
            $timestamp = strtotime((string) $value);

            return $timestamp === false ? '' : date('Y-m-d', $timestamp);
        };
    }

    /** Returns a 12-hour time-formatting closure passed to views as $formatTime. */
    private static function formatTimeCallback(): callable
    {
        return static function (mixed $value): string {
            $timestamp = strtotime((string) $value);

            return $timestamp === false ? '' : date('h:i A', $timestamp);
        };
    }

    /** Coerces a value into a list of ints. */
    private static function integerList(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $value
        ));
    }

    /** Coerces a value into a list of strings. */
    private static function stringList(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            (array) $value
        ));
    }

    /** Returns the value if it's an array, else an empty array (safe-default guard). */
    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
