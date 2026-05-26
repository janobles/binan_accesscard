<?php

namespace App\Support;

/**
 * Prepares dashboard view variables before templates render markup.
 */
class DashboardViewData
{
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
        $searchTerm = (string) ($data['searchTerm'] ?? '');
        $searchFilters = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
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
            'sectorOptions',
            'stats',
            'user',
            'username'
        );
    }

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
        $sectorOptions = self::arrayValue($familyFormViewData['sectorOptions'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $canCreateFamily = (bool) ($data['canCreateFamily'] ?? false);
        $idleTimeoutSeconds = (int) ($data['idleTimeoutSeconds'] ?? 900);
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'activePage',
            'auditActionOptions',
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

    public static function auditTrails(array $data): array
    {
        $recentAudits = self::arrayValue($data['recentAudits'] ?? []);
        $searchTerm = (string) ($data['searchTerm'] ?? '');
        $searchFilters = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $formatDate = self::formatDateCallback();
        $formatTime = self::formatTimeCallback();

        return compact(
            'auditActionOptions',
            'formatDate',
            'formatTime',
            'hasSearchFilters',
            'recentAudits',
            'searchFilters',
            'searchTerm'
        );
    }

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

    public static function sectorManagement(array $data): array
    {
        $sectors = self::arrayValue($data['sectors'] ?? []);

        return compact('sectors');
    }

    public static function serviceManagement(array $data): array
    {
        $services = self::arrayValue($data['services'] ?? []);

        return compact('services');
    }

    private static function defaultStats(): array
    {
        return [
            'families' => 0,
            'members' => 0,
            'sectors' => 0,
            'assistance' => 0,
        ];
    }

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

    private static function formatDateCallback(): callable
    {
        return static function (mixed $value): string {
            $timestamp = strtotime((string) $value);

            return $timestamp === false ? '' : date('Y-m-d', $timestamp);
        };
    }

    private static function formatTimeCallback(): callable
    {
        return static function (mixed $value): string {
            $timestamp = strtotime((string) $value);

            return $timestamp === false ? '' : date('h:i A', $timestamp);
        };
    }

    private static function integerList(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $value
        ));
    }

    private static function stringList(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            (array) $value
        ));
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
