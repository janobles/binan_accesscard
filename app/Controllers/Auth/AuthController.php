<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Auth\UserModel;
use App\Libraries\SessionAuditLogger;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Handles login, logout, session keep-alive, and role-based redirects.
 */
class AuthController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (session()->get('is_logged_in')) {
            if (! $this->hasValidLoginSession()) {
                $this->clearLoginSession();

                return redirect()->to(site_url('login'))
                    ->with('error', 'Your session expired. Please login again.');
            }

            return RoleAccess::redirectByRole((string) session()->get('role'));
        }

        return view('Auth/login');
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

        if (($user['login_error'] ?? '') === 'disabled') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This account is disabled and cannot be used.');
        }

        $role = RoleAccess::normalizeRole((string) ($user['role'] ?? ''));

        if ($role === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account role is invalid. Please contact an administrator.');
        }

        session()->regenerate();
        session()->set([
            'is_logged_in'       => true,
            'user_id'            => (int) $user['userID'],
            'member_id'          => (int) ($user['memberID'] ?? 0),
            'username'           => $user['username'],
            'role'               => $role,
            'idle_last_activity' => time(),
        ]);

        SessionAuditLogger::logLogin($user, $role, $this->request);

        return RoleAccess::redirectByRole($role);
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

    private function hasValidLoginSession(): bool
    {
        if (! RoleAccess::sessionUserExists()) {
            return false;
        }

        if (RoleAccess::normalizeRole((string) session()->get('role')) === null) {
            return false;
        }

        $lastActivity = (int) (session()->get('idle_last_activity') ?? time());

        return (time() - $lastActivity) < (new IdleTimeout())->seconds;
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

        session()->regenerate(true);
    }
}
