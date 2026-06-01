<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use App\Models\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles admin workspace pages and modal partials.
 */
class WorkspaceController extends BaseController
{
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('admin/dashboard'));
    }

    public function dashboard(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderAdminPage('dashboard');
    }

    public function accounts(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAccountsPartial();
        }

        return $this->pageBuilder()->renderAdminPage('accounts');
    }

    public function familyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderFamilyPartial();
        }

        return $this->pageBuilder()->renderAdminPage('family-entry');
    }

    public function manageRecords(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderAdminPage('family-manage');
    }

    public function auditTrails(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAuditPartial();
        }

        return $this->pageBuilder()->renderAdminPage('audit-trails');
    }

    public function sectors(): string|RedirectResponse
    {
        return redirect()->to(site_url('admin/sectors'));
    }

    public function services(): string|RedirectResponse
    {
        return redirect()->to(site_url('admin/services'));
    }

    private function pageBuilder(): DashboardPageBuilder
    {
        return new DashboardPageBuilder($this->request);
    }

    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    private function guardPartialAccess(): ?RedirectResponse
    {
        return RoleAccess::requireRole(['Developer', 'Admin']);
    }

    private function renderAccountsPartial(): string|RedirectResponse
    {
        $guard = $this->guardPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $currentRole = RoleAccess::normalizeRole((string) session()->get('role'));

        if (! in_array($currentRole, ['Developer', 'Admin'], true)) {
            return '<div class="alert alert-danger mb-0">Developer or Admin access is required for account management.</div>';
        }

        $viewData = $this->pageBuilder()->buildAdminViewData('accounts');

        return view('Dashboard/Manage/accounts', [
            'adminAccounts' => $viewData['adminAccounts'] ?? [],
            'employeeAccounts' => $viewData['employeeAccounts'] ?? [],
            'canCreateAccounts' => $viewData['canCreateAccounts'] ?? false,
            'currentRole' => $viewData['currentRole'] ?? '',
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
        ]);
    }

    private function renderFamilyPartial(): string|RedirectResponse
    {
        $guard = $this->guardPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Dashboard/familyform/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }

    private function renderAuditPartial(): string|RedirectResponse
    {
        $guard = $this->guardPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = $this->pageBuilder()->buildAdminViewData('audit-trails');

        return view('Dashboard/Manage/audit-trails', [
            'recentAudits' => $viewData['recentAudits'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
            'auditActionOptions' => $viewData['auditActionOptions'] ?? [],
        ]);
    }

}
