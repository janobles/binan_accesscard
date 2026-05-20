<?php

namespace App\Controllers\Concerns;

use App\Models\AuditTrailsModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\MemberModel;
use App\Models\MemberServiceModel;
use App\Models\SectorModel;
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
            ],
            'adminAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'Admin')),
            'employeeAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'User')),
            'familyFormViewData' => $familyFormViewData,
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
