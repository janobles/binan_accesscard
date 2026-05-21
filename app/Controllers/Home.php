<?php

namespace App\Controllers;

use App\Controllers\Concerns\HomeDashboardPagesTrait;
use App\Controllers\Concerns\HomeRoleAccessTrait;
use App\Models\FamilyFormOptionsModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles authentication, session lifetime, and role-based dashboard routing.
 */
class Home extends BaseController
{
    use HomeRoleAccessTrait;
    use HomeDashboardPagesTrait;

    public function index(): string|RedirectResponse
    {
        if (session()->get('is_logged_in')) {
            return $this->redirectByRole((string) session()->get('role'));
        }

        return view('Login/login');
    }

    public function login(): RedirectResponse
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())->verifyLogin($username, $password);

        if ($user === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid username or password.');
        }

        $role = $this->normalizeRole((string) ($user['role'] ?? ''));

        if ($role === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account role is invalid. Please contact an administrator.');
        }

        session()->regenerate();
        session()->set([
            'is_logged_in' => true,
            'user_id' => (int) $user['userID'],
            'member_id' => (int) ($user['memberID'] ?? 0),
            'username' => $user['username'],
            'role' => $role,
            'idle_last_activity' => time(),
        ]);

        return $this->redirectByRole($role);
    }

    public function logout(): RedirectResponse
    {
        if ($this->request->getGet('timeout') === '1') {
            $this->clearLoginSession();

            return redirect()->to(site_url('/'))
                ->with('error', 'You were logged out due to inactivity.');
        }

        session()->destroy();

        return redirect()->to(site_url('/'));
    }

    public function keepAlive()
    {
        if (! session()->get('is_logged_in')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['status' => 'expired']);
        }

        session()->set('idle_last_activity', time());

        return $this->response->setJSON(['status' => 'ok']);
    }

    public function admin(): RedirectResponse
    {
        return redirect()->to(site_url('admin/dashboard'));
    }

    public function adminDashboard(): string|RedirectResponse
    {
        return $this->renderAdminPage('dashboard');
    }

    public function adminAccounts(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminAccountsPartial();
        }

        return $this->renderAdminPage('accounts');
    }

    public function adminFamilyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminFamilyPartial();
        }

        return $this->renderAdminPage('family-entry');
    }

    public function adminAuditTrails(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminAuditPartial();
        }

        return $this->renderAdminPage('audit-trails');
    }

    public function employee(): string|RedirectResponse
    {
        return $this->renderEmployeePage('dashboard');
    }

    public function employeeFamilyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderEmployeeFamilyPartial();
        }

        return $this->renderEmployeePage('family-entry');
    }

    public function employeeActivity(): string|RedirectResponse
    {
        return $this->renderEmployeePage('activity');
    }

    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    private function guardAdminPartialAccess(): ?RedirectResponse
    {
        return $this->requireRole(['Developer', 'Admin']);
    }

    private function renderAdminAccountsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if ($this->normalizeRole((string) session()->get('role')) !== 'Developer') {
            return '<div class="alert alert-danger mb-0">Developer access is required for account management.</div>';
        }

        $viewData = $this->buildAdminViewData('accounts');

        return view('Dashboard/accounts', [
            'adminAccounts' => $viewData['adminAccounts'] ?? [],
            'employeeAccounts' => $viewData['employeeAccounts'] ?? [],
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

        $viewData = $this->buildAdminViewData('audit-trails');

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

        $viewData = $this->buildAdminViewData('sectors');

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

        $viewData = $this->buildAdminViewData('services');

        return view('Dashboard/Sectors and Services/services', [
            'services' => $viewData['services'] ?? [],
        ]);
    }

    public function employee(): string|RedirectResponse
    {
        return $this->renderEmployeePage('dashboard');
    }

    public function employeeFamilyEntry(): string|RedirectResponse
    {
        return $this->renderEmployeePage('family-entry');
    }

    public function employeeActivity(): string|RedirectResponse
    {
        return $this->renderEmployeePage('activity');
    }
}
