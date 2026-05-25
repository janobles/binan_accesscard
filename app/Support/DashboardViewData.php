<?php

namespace App\Support;

/**
 * Prepares view variables for the admin and employee dashboard layouts.
 */
class DashboardViewData
{
    public static function admin(array $data): array
    {
        $user               = self::arrayValue($data['user'] ?? []);
        $username           = $user['username'] ?? 'Admin';
        $activePage         = (string) ($data['activePage'] ?? 'dashboard');
        $pageTitle          = (string) ($data['pageTitle'] ?? 'Dashboard');
        $modeLabel          = (string) ($data['modeLabel'] ?? 'Admin Console');
        $canManageAccounts  = (bool) ($data['canManageAccounts'] ?? false);
        $navActive          = self::arrayValue($data['navActive'] ?? []);
        $stats              = array_merge(self::defaultStats(), self::arrayValue($data['stats'] ?? []));
        $recentFamilies     = self::arrayValue($data['recentFamilies'] ?? []);
        $recentAudits       = self::arrayValue($data['recentAudits'] ?? []);
        $adminAccounts      = self::arrayValue($data['adminAccounts'] ?? []);
        $employeeAccounts   = self::arrayValue($data['employeeAccounts'] ?? []);
        $linkableMembers    = self::arrayValue($data['linkableMembers'] ?? []);
        $familyFormViewData = self::arrayValue($data['familyFormViewData'] ?? []);
        $searchTerm         = (string) ($data['searchTerm'] ?? '');
        $searchFilters      = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $sectorOptions      = self::arrayValue($familyFormViewData['sectorOptions'] ?? []);
        $selectedFilterDate = (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? '');
        $hasSearchFilters   = self::hasSearchFilters($searchTerm, $searchFilters);
        $canCreateFamily    = (bool) ($data['canCreateFamily'] ?? false);
        $idleTimeoutSeconds = (int) ($data['idleTimeoutSeconds'] ?? 900);
        $formatDate         = self::formatDateCallback();
        $formatTime         = self::formatTimeCallback();
        $formatAuditMember  = self::formatAuditMemberCallback();
        $formatAuditUser    = self::formatAuditUserCallback();

        return compact(
            'activePage',
            'adminAccounts',
            'auditActionOptions',
            'canCreateFamily',
            'canManageAccounts',
            'employeeAccounts',
            'familyFormViewData',
            'formatAuditMember',
            'formatAuditUser',
            'linkableMembers',
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
            'selectedFilterDate',
            'sectorOptions',
            'stats',
            'user',
            'username'
        );
    }

    public static function employee(array $data): array
    {
        $user               = self::arrayValue($data['user'] ?? []);
        $username           = $user['username'] ?? 'Employee';
        $activePage         = (string) ($data['activePage'] ?? 'dashboard');
        $pageTitle          = (string) ($data['pageTitle'] ?? ($activePage === 'dashboard' ? 'Workspace' : ucwords(str_replace('-', ' ', $activePage))));
        $navActive          = self::arrayValue($data['navActive'] ?? []);
        $stats              = array_merge(self::defaultStats(), self::arrayValue($data['stats'] ?? []));
        $recentFamilies     = self::arrayValue($data['recentFamilies'] ?? []);
        $myAudits           = self::arrayValue($data['myAudits'] ?? []);
        $familyFormViewData = self::arrayValue($data['familyFormViewData'] ?? []);
        $searchTerm         = (string) ($data['searchTerm'] ?? '');
        $searchFilters      = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $sectorOptions      = self::arrayValue($familyFormViewData['sectorOptions'] ?? []);
        $selectedFilterDate = (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? '');
        $hasSearchFilters   = self::hasSearchFilters($searchTerm, $searchFilters);
        $canCreateFamily    = (bool) ($data['canCreateFamily'] ?? false);
        $idleTimeoutSeconds = (int) ($data['idleTimeoutSeconds'] ?? 900);
        $formatDate         = self::formatDateCallback();
        $formatTime         = self::formatTimeCallback();
        $formatAuditMember  = self::formatAuditMemberCallback();

        return compact(
            'activePage',
            'auditActionOptions',
            'canCreateFamily',
            'familyFormViewData',
            'formatAuditMember',
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
            'selectedFilterDate',
            'sectorOptions',
            'stats',
            'user',
            'username'
        );
    }

    public static function accounts(array $data): array
    {
        $adminAccounts    = self::arrayValue($data['adminAccounts'] ?? []);
        $employeeAccounts = self::arrayValue($data['employeeAccounts'] ?? []);
        $linkableMembers  = self::arrayValue($data['linkableMembers'] ?? []);
        $searchTerm       = (string) ($data['searchTerm'] ?? '');
        $searchFilters    = self::arrayValue($data['searchFilters'] ?? []);
        $hasSearchFilters = self::hasSearchFilters($searchTerm, $searchFilters);
        $formatDate       = self::formatDateCallback();
        $formatTime       = self::formatTimeCallback();
        $formatMemberName = self::formatMemberNameCallback();

        return compact(
            'adminAccounts',
            'employeeAccounts',
            'formatDate',
            'formatMemberName',
            'formatTime',
            'hasSearchFilters',
            'linkableMembers',
            'searchFilters',
            'searchTerm'
        );
    }

    public static function auditTrails(array $data): array
    {
        $recentAudits       = self::arrayValue($data['recentAudits'] ?? []);
        $searchTerm         = (string) ($data['searchTerm'] ?? '');
        $searchFilters      = self::arrayValue($data['searchFilters'] ?? []);
        $auditActionOptions = self::arrayValue($data['auditActionOptions'] ?? []);
        $hasSearchFilters   = self::hasSearchFilters($searchTerm, $searchFilters);
        $selectedFilterDate = (string) ($searchFilters['date'] ?? $searchFilters['date_from'] ?? '');
        $formatDate         = self::formatDateCallback();
        $formatTime         = self::formatTimeCallback();
        $formatAuditMember  = self::formatAuditMemberCallback();
        $formatAuditUser    = self::formatAuditUserCallback();

        return compact(
            'auditActionOptions',
            'formatAuditMember',
            'formatAuditUser',
            'formatDate',
            'formatTime',
            'hasSearchFilters',
            'recentAudits',
            'searchFilters',
            'searchTerm',
            'selectedFilterDate'
        );
    }

    public static function familyList(array $data): array
    {
        $families           = self::arrayValue($data['families'] ?? []);
        $keyword            = (string) ($data['keyword'] ?? '');
        $routeBase          = (string) ($data['routeBase'] ?? 'admin/manage-family');
        $status             = (string) ($data['status'] ?? 'active') === 'archived' ? 'archived' : 'active';
        $canRestoreArchived = (bool) ($data['canRestoreArchived'] ?? false);
        $page               = max(1, (int) ($data['page'] ?? 1));
        $perPage            = max(1, (int) ($data['perPage'] ?? 50));
        $totalFamilies      = max(0, (int) ($data['totalFamilies'] ?? count($families)));
        $totalPages         = max(1, (int) ($data['totalPages'] ?? 1));
        $fromRecord         = $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $toRecord           = min($totalFamilies, $page * $perPage);
        $requestPath        = trim((string) service('request')->getUri()->getPath(), '/');
        $isEmployeeList     = (string) session()->get('role') === 'User'
            || str_starts_with($routeBase, 'employee/')
            || str_starts_with($requestPath, 'employee/')
            || str_contains('/' . $requestPath, '/employee/');
        $formatDate         = self::formatDateCallback();
        $formatTime         = self::formatTimeCallback();
        $listUrl            = static function (string $targetStatus, int $targetPage = 1) use ($routeBase, $keyword): string {
            $params = ['page' => $targetPage];

            if ($targetStatus === 'archived') {
                $params['status'] = 'archived';
            }

            if (trim($keyword) !== '') {
                $params['q'] = $keyword;
            }

            return site_url($routeBase . '/list?' . http_build_query($params));
        };

        return compact(
            'canRestoreArchived',
            'families',
            'formatDate',
            'formatTime',
            'fromRecord',
            'isEmployeeList',
            'keyword',
            'listUrl',
            'page',
            'perPage',
            'routeBase',
            'status',
            'toRecord',
            'totalFamilies',
            'totalPages'
        );
    }

    public static function familyDetails(array $data): array
    {
        $head           = self::arrayValue($data['head'] ?? []);
        $members        = self::arrayValue($data['members'] ?? []);
        $serviceMap     = self::arrayValue($data['serviceMap'] ?? []);
        $serviceNameMap = self::arrayValue($data['serviceNameMap'] ?? []);
        $fullName       = self::fullNameCallback();
        $display        = self::displayCallback();
        $formatDate     = self::detailDateCallback();
        $formatTime     = self::detailTimeCallback();
        $serviceNames   = self::serviceNamesCallback($serviceMap, $serviceNameMap);
        $detailItem     = self::detailItemCallback($display);
        $renderServices = self::renderServicesCallback();

        return compact(
            'detailItem',
            'display',
            'formatDate',
            'formatTime',
            'fullName',
            'head',
            'members',
            'renderServices',
            'serviceMap',
            'serviceNameMap',
            'serviceNames'
        );
    }

    public static function sectorAndServices(array $data): array
    {
        $servicesByCategory       = self::arrayValue($data['servicesByCategory'] ?? []);
        $sectorCatalog            = self::arrayValue($data['sectorCatalog'] ?? []);
        $selectedSectorIds        = self::integerList($data['selectedSectorIds'] ?? []);
        $selectedSectorCategories = self::stringList($data['selectedSectorCategories'] ?? []);
        $selectedServiceIds       = self::integerList($data['selectedServiceIds'] ?? []);
        $sectorCatalogJson        = json_encode($sectorCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}';

        return compact(
            'sectorCatalog',
            'sectorCatalogJson',
            'selectedSectorCategories',
            'selectedSectorIds',
            'selectedServiceIds',
            'servicesByCategory'
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

    public static function familyForm(array $data): array
    {
        $formOptions              = array_merge(self::defaultFormOptions(), self::arrayValue($data['formOptions'] ?? []));
        $sectorOptions            = $data['sectorOptions'] ?? ($formOptions['sectors'] ?? []);
        $sectorCatalog            = self::arrayValue($data['sectorCatalog'] ?? []);
        $sexOptions               = $data['sexOptions'] ?? ($formOptions['sexes'] ?? []);
        $suffixOptions            = $data['suffixOptions'] ?? ($formOptions['suffixes'] ?? []);
        $civilOptions             = $data['civilOptions'] ?? ($formOptions['civil_statuses'] ?? []);
        $relationshipOptions      = $data['relationshipOptions'] ?? ($formOptions['relationships'] ?? []);
        $educationOptions         = $data['educationOptions'] ?? ($formOptions['education_levels'] ?? []);
        $incomeOptions            = $data['incomeOptions'] ?? ($formOptions['income_ranges'] ?? []);
        $servicesByCategory       = $data['servicesByCategory'] ?? ($formOptions['services_by_category'] ?? []);
        $familyHeads              = $data['familyHeads'] ?? ($formOptions['family_heads'] ?? []);
        $formAction               = $data['formAction'] ?? site_url('families');
        $submitButtonLabel        = $data['submitButtonLabel'] ?? 'Save Family Data';
        $familyRecord             = self::arrayValue($data['familyRecord'] ?? []);
        $existingMembers          = self::arrayValue($data['existingMembers'] ?? []);
        $headServiceIds           = self::integerList($data['headServiceIds'] ?? ($familyRecord['service_ids'] ?? []));
        $isEditMode               = $familyRecord !== [];
        $selectedSectorIds        = SectorIds::normalize($familyRecord['sectorID'] ?? null);
        $selectedSectorCategories = self::selectedSectorCategories($sectorCatalog, $selectedSectorIds);
        $initialFamilyData        = [
            'selectedSectorIds'        => $selectedSectorIds,
            'selectedSectorCategories' => $selectedSectorCategories,
            'headServiceIds'           => $headServiceIds,
            'existingMembers'          => $existingMembers,
        ];
        $fieldViewData            = compact(
            'civilOptions',
            'educationOptions',
            'familyRecord',
            'incomeOptions',
            'relationshipOptions',
            'sectorOptions',
            'servicesByCategory',
            'sexOptions',
            'suffixOptions'
        );

        return compact(
            'civilOptions',
            'educationOptions',
            'existingMembers',
            'familyHeads',
            'familyRecord',
            'fieldViewData',
            'formAction',
            'formOptions',
            'headServiceIds',
            'incomeOptions',
            'initialFamilyData',
            'isEditMode',
            'relationshipOptions',
            'sectorCatalog',
            'sectorOptions',
            'selectedSectorCategories',
            'selectedSectorIds',
            'servicesByCategory',
            'sexOptions',
            'submitButtonLabel',
            'suffixOptions'
        );
    }

    public static function familyFormPartial(array $familyFormViewData, bool $canCreateFamily): array
    {
        $familyFormViewData                    = self::arrayValue($familyFormViewData);
        $familyFormViewData['canCreateFamily'] = $canCreateFamily;

        return $familyFormViewData;
    }

    private static function defaultStats(): array
    {
        return [
            'families'   => 0,
            'members'    => 0,
            'sectors'    => 0,
            'assistance' => 0,
        ];
    }

    private static function defaultFormOptions(): array
    {
        return [
            'sectors'              => [],
            'sexes'                => [],
            'suffixes'             => [],
            'civil_statuses'       => [],
            'relationships'        => [],
            'education_levels'     => [],
            'income_ranges'        => [],
            'services_by_category' => [],
            'family_heads'         => [],
        ];
    }

    private static function selectedSectorCategories(array $sectorCatalog, array $selectedSectorIds): array
    {
        $selectedCategories = [];

        foreach ($sectorCatalog as $categoryKey => $sectorRows) {
            foreach ((array) $sectorRows as $sectorRow) {
                if (in_array((int) ($sectorRow['sectorID'] ?? 0), $selectedSectorIds, true)) {
                    $selectedCategories[] = (string) $categoryKey;
                    break;
                }
            }
        }

        return array_values(array_unique($selectedCategories));
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

    private static function formatAuditMemberCallback(): callable
    {
        return static function (array $audit): string {
            $memberName = trim((string) ($audit['member_name'] ?? ''));

            if ($memberName === '') {
                $memberName = trim((string) ($audit['firstname'] ?? '') . ' ' . (string) ($audit['lastname'] ?? ''));
            }

            return $memberName === '' ? '-' : $memberName;
        };
    }

    private static function formatAuditUserCallback(): callable
    {
        return static function (array $audit): string {
            $username = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
            $role     = trim((string) ($audit['user_role'] ?? ''));

            if ($role === 'User') {
                $role = 'Employee';
            }

            return $role === '' ? $username : $username . ' (' . $role . ')';
        };
    }

    private static function formatMemberNameCallback(): callable
    {
        return static function (array $member): string {
            $memberName = trim((string) ($member['member_name'] ?? ''));

            if ($memberName !== '') {
                return $memberName;
            }

            $memberName = trim(implode(' ', array_filter([
                (string) ($member['firstname'] ?? ''),
                (string) ($member['middlename'] ?? ''),
                (string) ($member['lastname'] ?? ''),
                (string) ($member['suffix'] ?? ''),
            ], static fn (string $value): bool => trim($value) !== '')));

            return $memberName === '' ? '-' : $memberName;
        };
    }

    private static function fullNameCallback(): callable
    {
        return static function (array $person): string {
            $name = trim((string) (
                ($person['firstname'] ?? '') . ' '
                . ($person['middlename'] ?? '') . ' '
                . ($person['lastname'] ?? '') . ' '
                . ($person['suffix'] ?? '')
            ));

            return $name !== '' ? $name : '-';
        };
    }

    private static function displayCallback(): callable
    {
        return static fn (mixed $value): string => trim((string) $value) !== '' ? (string) $value : '-';
    }

    private static function detailDateCallback(): callable
    {
        return static function (mixed $value): string {
            $timestamp = strtotime((string) $value);

            return $timestamp === false ? '-' : date('Y-m-d', $timestamp);
        };
    }

    private static function detailTimeCallback(): callable
    {
        return static function (mixed $value): string {
            $timestamp = strtotime((string) $value);

            return $timestamp === false ? '-' : date('h:i A', $timestamp);
        };
    }

    private static function serviceNamesCallback(array $serviceMap, array $serviceNameMap): callable
    {
        return static function (array $person) use ($serviceMap, $serviceNameMap): array {
            $memberId = (int) ($person['memberID'] ?? 0);
            $names    = [];

            foreach (($serviceMap[$memberId] ?? []) as $serviceId) {
                $serviceId = (int) $serviceId;
                $names[]   = (string) ($serviceNameMap[$serviceId] ?? ('Service #' . $serviceId));
            }

            return $names;
        };
    }

    private static function detailItemCallback(callable $display): callable
    {
        return static function (string $label, mixed $value) use ($display): string {
            return '<div class="family-detail-item"><span>' . esc($label) . '</span><strong>' . esc($display($value)) . '</strong></div>';
        };
    }

    private static function renderServicesCallback(): callable
    {
        return static function (array $names): string {
            if ($names === []) {
                return '<span class="text-muted">No services availed.</span>';
            }

            $items = array_map(
                static fn (string $name): string => '<span class="family-service-chip">' . esc($name) . '</span>',
                $names
            );

            return implode('', $items);
        };
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
}
