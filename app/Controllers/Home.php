<?php

namespace App\Controllers;

use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use App\Models\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles authentication, session lifetime, and role-based dashboard routing.
 */
class Home extends BaseController
{
    public function admin(): RedirectResponse
    {
        return redirect()->to(site_url('admin/dashboard'));
    }

    public function adminDashboard(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('dashboard');
    }

    public function adminAccounts(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminAccountsPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('accounts');
    }

    public function adminFamilyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminFamilyPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-entry');
    }

    public function adminAuditTrails(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminAuditPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('audit-trails');
    }

    public function adminSectors(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminSectorsPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('sectors');
    }

    public function adminServices(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminServicesPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('services');
    }

    public function employee(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderEmployeePage('dashboard');
    }

    public function employeeFamilyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderEmployeeFamilyPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderEmployeePage('family-entry');
    }

    public function employeeActivity(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderEmployeePage('activity');
    }

    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    private function guardAdminPartialAccess(): ?RedirectResponse
    {
        return RoleAccess::requireRole(['Developer', 'Admin']);
    }

    private function renderAdminAccountsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if (RoleAccess::normalizeRole((string) session()->get('role')) !== 'Developer') {
            return '<div class="alert alert-danger mb-0">Developer access is required for account management.</div>';
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('accounts');

        return view('Dashboard/accounts', [
            'adminAccounts' => $viewData['adminAccounts'] ?? [],
            'employeeAccounts' => $viewData['employeeAccounts'] ?? [],
            'linkableMembers' => $viewData['linkableMembers'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
        ]);
    }

    private function renderAdminFamilyPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Dashboard/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true]
        ));
    }

    private function renderAdminAuditPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('audit-trails');

        return view('Dashboard/audit-trails', [
            'recentAudits' => $viewData['recentAudits'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
            'auditActionOptions' => $viewData['auditActionOptions'] ?? [],
        ]);
    }

    private function renderAdminSectorsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('sectors');

        return view('Dashboard/Sectors and Services/sector', [
            'sectors' => $viewData['sectors'] ?? [],
        ]);
    }

    private function renderAdminServicesPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('services');

        return view('Dashboard/Sectors and Services/services', [
            'services' => $viewData['services'] ?? [],
        ]);
    }

    private function renderEmployeeFamilyPartial(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Dashboard/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true]
        ));
    }

}
