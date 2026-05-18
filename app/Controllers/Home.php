<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\AuditTrailsModel;
use App\Models\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

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

        session()->regenerate();
        session()->set([
            'is_logged_in' => true,
            'user_id'      => (int) $user['userID'],
            'username'     => $user['username'],
            'role'         => $user['role'],
            'member_id'    => (int) $user['memberID'],
        ]);

        return $this->redirectByRole((string) $user['role']);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('/'));
    }

    public function admin(): string|RedirectResponse
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

        $db = db_connect();
        $users = (new UserModel())
            ->select('userID, username, role, isactive')
            ->whereIn('role', ['Admin', 'User'])
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();

        $formOptions = (new FamilyFormOptionsModel())->getOptions();
        $recentFamilies = $this->recentFamilies($db);

        return view('Dashboard/admin', [
            'user' => session()->get(),
            'activePage' => $activePage,
            'adminAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'Admin')),
            'employeeAccounts' => array_values(array_filter($users, static fn ($account) => $account['role'] === 'User')),
            'formOptions' => $formOptions,
            'recentFamilies' => $recentFamilies,
            'recentAudits' => $db->tableExists('audit_trails') ? (new AuditTrailsModel())->getRecent(10) : [],
            'stats' => [
                'families' => $db->tableExists('member') ? $db->table('member')->where('headID = memberID')->countAllResults() : 0,
                'members' => $db->tableExists('member') ? $db->table('member')->countAllResults() : 0,
                'sectors' => $db->tableExists('sector') ? $db->table('sector')->countAllResults() : 0,
                'assistance' => $db->tableExists('member_services') ? $db->table('member_services')->countAllResults() : 0,
            ],
            'canCreateFamily' => true,
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

    private function renderEmployeePage(string $activePage): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) session()->get('user_id');
        $db = db_connect();
        return view('Employee/index', [
            'user' => session()->get(),
            'activePage' => $activePage,
            'canCreateFamily' => true,
            'formOptions' => (new FamilyFormOptionsModel())->getOptions(),
            'recentFamilies' => $this->recentFamilies($db),
            'myAudits' => $db->tableExists('audit_trails') ? (new AuditTrailsModel())->getByUser($userId, 10) : [],
        ]);
    }

    private function requireRole(array $allowedRoles): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if (! in_array(session()->get('role'), $allowedRoles, true)) {
            return $this->redirectByRole((string) session()->get('role'))
                ->with('error', 'You do not have access to that page.');
        }

        return null;
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        if ($role === 'User') {
            return redirect()->to(site_url('employee/workspace'));
        }

        return redirect()->to(site_url('admin'));
    }

    private function recentFamilies($db): array
    {
        if (! $db->tableExists('member')) {
            return [];
        }

        return $db->table('member')
            ->select('member.*, sector.name AS sector_name')
            ->join('sector', 'sector.sectorID = member.sectorID', 'left')
            ->where('member.headID = member.memberID')
            ->orderBy('member.dt_created', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }
}
