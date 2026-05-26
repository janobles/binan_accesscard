<?php

namespace App\Controllers\Concerns;

use App\Models\AuditTrailsModel;
use App\Models\SectorModel;
use App\Models\ServicesModel;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Shared helpers for sector and service lookup management controllers.
 */
trait LookupManagementTrait
{
    private function guardLookupAccess(): ?RedirectResponse
    {
        return $this->requireRole(['Admin', 'Developer']);
    }

    private function buildLookupViewData(string $activeTab): array
    {
        $sectorModel = new SectorModel();
        $servicesModel = new ServicesModel();
        $layoutModel = new ViewLayoutModel();
        $currentRole = $this->normalizeRole((string) session()->get('role')) ?? '';
        $isDeveloper = $currentRole === 'Developer';

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
            'pageTitle' => 'Lookup Management',
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            'navActive' => [
                'dashboard' => '',
                'accounts' => '',
                'family-entry' => '',
                'family-manage' => '',
                'audit-trails' => '',
                'sectors' => '',
                'services' => '',
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
