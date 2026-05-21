<?php

namespace App\Controllers\Concerns;

use App\Models\AuditTrailsModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\SectorModel;
use App\Models\ServiceModel;
use App\Models\UserModel;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\RedirectResponse;

trait HomeDashboardPagesTrait
{
    private function renderAdminPage(string $activePage): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if ($activePage === 'accounts' && session()->get('role') !== 'Developer') {
            return redirect()->to(site_url('admin/dashboard'))
                ->with('error', 'Developer access is required for account management.');
        }

        return view('Dashboard/admin', $this->buildAdminViewData($activePage));
    }

    private function buildAdminViewData(string $activePage): array
    {
        $layoutModel = new ViewLayoutModel();
        $isDeveloper = $this->normalizeRole((string) session()->get('role')) === 'Developer';
        $userModel = new UserModel();
        $memberModel = new MemberModel();
        $sectorModel = new SectorModel();
        $serviceModel = new ServiceModel();
        $memberServiceModel = new MemberServiceModel();
        $auditModel = new AuditTrailsModel();
        $users = $userModel->getStaffAccounts();

        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();
        $recentFamilies = $memberModel->getRecentFamilies(10);

        return [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->pageTitle($activePage),
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'accounts' => $layoutModel->navActive($activePage, 'accounts'),
                'family-entry' => $layoutModel->navActive($activePage, 'family-entry'),
                'audit-trails' => $layoutModel->navActive($activePage, 'audit-trails'),
                'sectors' => $layoutModel->navActive($activePage, 'sectors'),
                'services' => $layoutModel->navActive($activePage, 'services'),
            ],
            'adminAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'Admin')),
            'employeeAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'User')),
            'familyFormViewData' => $familyFormViewData,
            'sectors' => $this->fetchVisibleSectors($sectorModel),
            'services' => $this->fetchVisibleServices($serviceModel),
            'recentFamilies' => $recentFamilies,
            'recentAudits' => $auditModel->hasTable() ? $auditModel->getRecent(10) : [],
            'stats' => [
                'families' => $memberModel->countHeads(),
                'members' => $memberModel->countMembers(),
                'sectors' => $sectorModel->countSectors(),
                'assistance' => $memberServiceModel->countAssignments(),
            ],
            'canCreateFamily' => true,
        ];
    }

    private function fetchVisibleSectors(SectorModel $sectorModel): array
    {
        if (! $sectorModel->hasTable()) {
            return [];
        }

        $db = $sectorModel->db;
        $builder = $db->table('sector');

        if ($db->fieldExists('isactive', 'sector')) {
            $builder->where('isactive', 1);
        } elseif ($db->fieldExists('status', 'sector')) {
            $builder->where('LOWER(status) <>', 'archived');
        } elseif ($db->fieldExists('archived_at', 'sector')) {
            $builder->where('archived_at IS NULL');
        } elseif ($db->fieldExists('deleted_at', 'sector')) {
            $builder->where('deleted_at IS NULL');
        }

        return $builder
            ->orderBy('sectorID', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function fetchVisibleServices(ServiceModel $serviceModel): array
    {
        if (! $serviceModel->hasTable()) {
            return [];
        }

        $db = $serviceModel->db;
        $builder = $db->table('services');

        if ($db->fieldExists('isactive', 'services')) {
            $builder->where('isactive', 1);
        } elseif ($db->fieldExists('status', 'services')) {
            $builder->where('LOWER(status) <>', 'archived');
        } elseif ($db->fieldExists('archived_at', 'services')) {
            $builder->where('archived_at IS NULL');
        } elseif ($db->fieldExists('deleted_at', 'services')) {
            $builder->where('deleted_at IS NULL');
        }

        return $builder
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function renderEmployeePage(string $activePage): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $layoutModel = new ViewLayoutModel();
        $userId = (int) session()->get('user_id');
        $memberModel = new MemberModel();
        $auditModel = new AuditTrailsModel();
        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();

        return view('Employee/index', [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->employeePageTitle($activePage),
            'navActive' => [
                'dashboard' => $layoutModel->navActive($activePage, 'dashboard'),
                'family-entry' => $layoutModel->navActive($activePage, 'family-entry'),
                'activity' => $layoutModel->navActive($activePage, 'activity'),
            ],
            'canCreateFamily' => true,
            'familyFormViewData' => $familyFormViewData,
            'recentFamilies' => $memberModel->getRecentFamilies(10),
            'myAudits' => $auditModel->hasTable() ? $auditModel->getByUser($userId, 10) : [],
        ]);
    }
}
