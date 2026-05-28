<?php

namespace App\Controllers;

use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAuditLogger;
use App\Models\Auth\UserModel;
use App\Models\FamilyFormOptionsModel;
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
            if (! $this->hasValidLoginSession()) {
                $this->clearLoginSession();

                return redirect()->to(site_url('login'))
                    ->with('error', 'Your session expired. Please login again.');
            }

            return $this->redirectByRole((string) session()->get('role'));
        }

        return view('Login/login');
    }

    public function login(): string|RedirectResponse
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->index();
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())->verifyLogin($username, $password);

        if ($user === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid username or password.');
        }

        // Show a specific message when valid credentials belong to a disabled account.
        if (($user['login_error'] ?? '') === 'disabled') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This account is disabled and cannot be used.');
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
            'member_id' => 0,
            'username' => $user['username'],
            'role' => $role,
            'idle_last_activity' => time(),
        ]);

        SessionAuditLogger::logLogin($user, $role, $this->request);

        return $this->redirectByRole($role);
    }

    public function logout(): RedirectResponse
    {
        if ($this->request->getGet('timeout') === '1') {
            SessionAuditLogger::logLogoutFromSession($this->request, true);
            $this->clearLoginSession();

            return redirect()->to(site_url('login'))
                ->with('error', 'You were logged out due to inactivity.');
        }

        SessionAuditLogger::logLogoutFromSession($this->request);
        $this->clearLoginSession();

        return redirect()->to(site_url('login'));
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

    public function adminManageRecords(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-manage');
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

    public function adminManageMembers(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-manage');
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

    public function employeeManageRecords(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderEmployeePage('family-manage');
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

        $currentRole = $this->normalizeRole((string) session()->get('role'));

        if (! in_array($currentRole, ['Developer', 'Admin'], true)) {
            return '<div class="alert alert-danger mb-0">Developer or Admin access is required for account management.</div>';
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('accounts');

        return view('Dashboard/Manage/accounts', [
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

        return view('Dashboard/familyform/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }

    private function renderAdminAuditPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('audit-trails');

        return view('Dashboard/Manage/audit-trails', [
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
            'sectorShortcodeOptions' => $viewData['sectorShortcodeOptions'] ?? [],
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

        return view('Dashboard/familyform/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }

}
