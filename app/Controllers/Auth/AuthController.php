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
    /**
     * Landing action for GET `/` (and GET `login`). If a valid session already
     * exists, it routes the user to their role's dashboard; otherwise it renders
     * the login screen. Frontend: returns the `Auth/login` view.
     */
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

    /**
     * Processes the login form POSTed to `login`. Validates the username/password
     * via UserModel::verifyLogin, rejects invalid/disabled/invalid-role accounts
     * with a flash error (redisplayed on the login view), then establishes the
     * session, writes a login audit row, and redirects to the role dashboard.
     * Frontend: receives `username`/`password` from `Auth/login`'s form.
     */
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

    /**
     * Ends the session for GET `logout` (the dashboard's logout link). Writes a
     * logout audit row, clears the session, and redirects to the login page.
     * The `?timeout=1` variant is triggered by the idle-timeout JS and shows an
     * "inactivity" message instead.
     */
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

    /**
     * Heartbeat endpoint for GET `session/keep-alive`, polled by the dashboard's
     * keep-alive JS. Refreshes the idle timer and returns JSON `{status: ok}`,
     * or 401 `{status: expired}` so the frontend can redirect to login.
     */
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

    /**
     * Guard used by index(): confirms the session points at a real user, has a
     * recognized role, and has not exceeded the IdleTimeout window. No frontend
     * connection — internal session validation only.
     */
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

    /**
     * Removes all auth-related session keys and regenerates the session ID to
     * prevent fixation. Called on logout and on expired-session redirects.
     */
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
