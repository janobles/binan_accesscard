<?php

namespace App\Controllers\Concerns;

use App\Models\Audit\AuditTrailsModel;
use App\Libraries\RoleAccess;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServicesModel;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Shared helpers for sector and service lookup management controllers.
 */
trait LookupManagementTrait
{
    /**
     * Role guard shared by Admin\SectorController and Admin\ServicesController:
     * returns a redirect for non Admin/Developer users, or null to proceed.
     */
    private function guardLookupAccess(): ?RedirectResponse
    {
        return RoleAccess::requireRole(['Admin', 'Developer']);
    }

    /**
     * Assembles all data the `admin/lookups/index` view needs: active vs archived
     * sectors and services (grouped by category), per-row member assignment counts,
     * nav highlighting, and the idle-timeout value. $activeTab toggles the page
     * between the Sectors and Services tabs. Frontend: feeds the lookups view/JS.
     */
    private function buildLookupViewData(string $activeTab): array
    {
        $sectorModel = new SectorModel();
        $servicesModel = new ServicesModel();
        $layoutModel = new ViewLayoutModel();
        $currentRole = RoleAccess::normalizeRole((string) session()->get('role')) ?? '';
        $isDeveloper = $currentRole === 'Developer';
        $isServicesTab = $activeTab === 'services';

        $sectors = $sectorModel->getAllIncluding();
        $services = $servicesModel->getAllIncluding();

        $activeSectors = [];
        $archivedSectors = [];
        $sectorAssignmentCounts = [];

        foreach ($sectors as $sector) {
            $sectorId = (int) ($sector['sectorID'] ?? 0);

            if (empty($sector['dt_deleted'])) {
                $activeSectors[] = $sector;
                $sectorAssignmentCounts[$sectorId] = $this->countActiveMembersForSector($sectorId);

                continue;
            }

            $archivedSectors[] = $sector;
        }

        $serviceGroups = [];
        $serviceAssignmentCounts = [];

        foreach ($services as $service) {
            $category = trim((string) ($service['category'] ?? ''));
            $category = $category !== '' ? $category : 'Other';
            $serviceGroups[$category] ??= ['active' => [], 'archived' => []];

            if (empty($service['dt_deleted'])) {
                $serviceGroups[$category]['active'][] = $service;
                $serviceAssignmentCounts[(int) ($service['serviceID'] ?? 0)] = $this->countActiveMembersForService((int) ($service['serviceID'] ?? 0));
            } else {
                $serviceGroups[$category]['archived'][] = $service;
            }
        }

        ksort($serviceGroups);

        return [
            'user' => session()->get(),
            'pageTitle' => $isServicesTab ? 'Services and Programs Management' : 'Sector Management',
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            'navActive' => [
                'dashboard' => '',
                'accounts' => '',
                'family-entry' => '',
                'family-manage' => '',
                'audit-trails' => '',
                'sectors' => $isServicesTab ? '' : 'active',
                'services' => $isServicesTab ? 'active' : '',
                'lookups' => 'active',
            ],
            'activeTab' => $activeTab,
            'activeSectors' => $activeSectors,
            'archivedSectors' => $archivedSectors,
            'sectorAssignmentCounts' => $sectorAssignmentCounts,
            'serviceGroups' => $serviceGroups,
            'serviceAssignmentCounts' => $serviceAssignmentCounts,
            'serviceCategories' => array_keys($serviceGroups),
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
        ];
    }

    /**
     * Counts non-deleted `member` rows assigned to a sector (sectorID is a JSON
     * array, matched with JSON_CONTAINS). Used to show usage counts and to block
     * archiving sectors that are still in use.
     */
    private function countActiveMembersForSector(int $sectorId): int
    {
        if ($sectorId <= 0) {
            return 0;
        }

        $db = db_connect();

        if (! $db->tableExists('member')) {
            return 0;
        }

        return (int) $db->table('member')
            ->where('dt_deleted', null)
            ->where("JSON_CONTAINS(sectorID, '" . (int) $sectorId . "')", null, false)
            ->countAllResults();
    }

    /**
     * Counts non-deleted members linked to a service via `member_services`. Used
     * for usage counts and to block archiving services that are still assigned.
     */
    private function countActiveMembersForService(int $serviceId): int
    {
        if ($serviceId < 0) {
            return 0;
        }

        $db = db_connect();

        if (! $db->tableExists('member_services') || ! $db->tableExists('member')) {
            return 0;
        }

        return (int) $db->table('member_services ms')
            ->join('member m', 'ms.memberID = m.memberID')
            ->where('ms.serviceID', $serviceId)
            ->where('m.dt_deleted', null)
            ->countAllResults();
    }

    /**
     * Records a lookup-management event (sector/service create/update/archive/
     * restore) to audit_trails with no affected member. No-ops when the table is
     * missing or no user is in session. No frontend connection.
     */
    private function logLookupAction(string $action, string $description): void
    {
        $auditModel = new AuditTrailsModel();
        $userId = (int) session()->get('user_id');

        if (! $auditModel->hasTable() || $userId <= 0) {
            return;
        }

        $auditModel->logAction(
            $userId,
            null,
            $action,
            $description,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );
    }
}
