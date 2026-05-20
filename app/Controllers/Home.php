<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\FamilyFormOptionsModel;
use App\Models\SearchModel;
use App\Models\UserModel;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Handles authentication and role-based page routing.
 *
 * Controllers coordinate request/session flow while models fetch the data.
 */
class Home extends BaseController
{
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
        // Session data drives role-based redirects for operators (staff accounts).
        session()->set([
            'is_logged_in' => true,
            'user_id'      => (int) $user['userID'],
            'member_id'    => (int) ($user['memberID'] ?? 0),
            'username'     => $user['username'],
            'role'         => $role,
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
        return $this->renderAdminPage('accounts');
    }

    public function adminFamilyEntry(): string|RedirectResponse
    {
        return $this->renderAdminPage('family-entry');
    }

    public function adminAuditTrails(): string|RedirectResponse
    {
        return $this->renderAdminPage('audit-trails');
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

        $layoutModel = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $isDeveloper = $this->normalizeRole((string) session()->get('role')) === 'Developer';
        $users = $isDeveloper && $activePage === 'accounts'
            ? $searchModel->staffAccounts($searchTerm)
            : [];

        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();
        $recentFamilies = $activePage === 'dashboard' && $searchTerm !== ''
            ? $searchModel->families($searchTerm, 25)
            : $dashboardModel->recentFamilies(10);
        $recentAudits = $activePage === 'audit-trails'
            ? $searchModel->auditTrails($searchTerm, 50)
            : (new AuditTrailsModel())->getRecent(10);

        return view('Dashboard/admin', [
            'user' => session()->get(),
            'activePage' => $activePage,
            'pageTitle' => $layoutModel->pageTitle($activePage),
            'modeLabel' => $layoutModel->adminModeLabel($isDeveloper),
            'canManageAccounts' => $isDeveloper,
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
            'recentAudits' => $recentAudits,
            'searchTerm' => $searchTerm,
            'stats' => $dashboardModel->stats(),
            'canCreateFamily' => true,
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
        ]);
    }

    private function renderEmployeePage(string $activePage): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $layoutModel = new ViewLayoutModel();
        $dashboardModel = new DashboardModel();
        $searchModel = new SearchModel();
        $searchTerm = trim((string) $this->request->getGet('q'));
        $userId = (int) session()->get('user_id');
        $familyFormViewData = (new FamilyFormOptionsModel())->getViewData();
        $recentFamilies = $activePage === 'dashboard' && $searchTerm !== ''
            ? $searchModel->families($searchTerm, 25)
            : $dashboardModel->recentFamilies(10);
        $myAudits = $activePage === 'activity'
            ? $searchModel->auditTrailsByUser($userId, $searchTerm, 50)
            : (new AuditTrailsModel())->getByUser($userId, 10);

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
            'recentFamilies' => $recentFamilies,
            'myAudits' => $myAudits,
            'searchTerm' => $searchTerm,
            'idleTimeoutSeconds' => (new IdleTimeout())->seconds,
        ]);
    }

    private function requireRole(array $allowedRoles): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $currentRole = $this->normalizeRole((string) session()->get('role'));
        $normalizedAllowedRoles = array_values(array_filter(array_map(
            fn (string $role): ?string => $this->normalizeRole($role),
            $allowedRoles
        )));

        if ($currentRole === null) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your account role is invalid. Please login again or contact an administrator.');
        }

        if (! in_array($currentRole, $normalizedAllowedRoles, true)) {
            return $this->redirectByRole($currentRole)
                ->with('error', 'You do not have access to that page.');
        }

        return null;
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        $normalizedRole = $this->normalizeRole($role);

        if ($normalizedRole === 'User') {
            return redirect()->to(site_url('employee/workspace'));
        }

        if ($normalizedRole === 'Admin' || $normalizedRole === 'Developer') {
            return redirect()->to(site_url('admin'));
        }

        session()->destroy();

        return redirect()->to(site_url('/'))
            ->with('error', 'Your account role is invalid. Please contact an administrator.');
    }

    private function normalizeRole(string $role): ?string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'developer' => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'employee' => 'User',
            default => null,
        };
    }

    private function clearLoginSession(): void
    {
        session()->remove([
            'is_logged_in',
            'user_id',
            'member_id',
            'username',
            'role',
            'idle_last_activity',
        ]);
    }
}
