<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Libraries\ActiveSessionRegistry;
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
        // Drop any half-finished "logged in elsewhere" confirmation the user
        // navigated away from, so it can't be resumed later.
        session()->remove('pending_login');

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
            SessionAuditLogger::logFailedLogin($username, 'invalid username or password', $this->request);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid username or password.');
        }

        if (($user['login_error'] ?? '') === 'disabled') {
            SessionAuditLogger::logFailedLogin($username, 'account disabled', $this->request);

            return redirect()->back()
                ->withInput()
                ->with('error', 'This account is disabled and cannot be used.');
        }

        $role = RoleAccess::normalizeRole((string) ($user['role'] ?? ''));

        if ($role === null) {
            SessionAuditLogger::logFailedLogin($username, 'invalid account role', $this->request);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account role is invalid. Please contact an administrator.');
        }

        $pending = [
            'user_id'   => (int) $user['userID'],
            'member_id' => (int) ($user['memberID'] ?? 0),
            'username'  => (string) $user['username'],
            'role'      => $role,
        ];

        // Single-session guard: if this account already holds a fresh session on
        // another browser/device, ask the user before displacing it (requirement:
        // "validate same user login"). Confirming routes to confirmLogin().
        if ($this->activeOtherSession($pending['user_id'], $pending['username'])) {
            session()->set('pending_login', $pending + ['created_at' => time()]);

            return view('Auth/session_conflict');
        }

        return $this->establishSession($pending);
    }

    /**
     * Completes a login the user confirmed from the "already signed in elsewhere"
     * prompt (POST `login/confirm`). Reads the server-side pending_login stashed by
     * login(), re-checks it is fresh, then establishes the session — which overwrites
     * the account's active-session token so the previous instance is logged out on
     * its next request (App\Filters\SingleSessionFilter). A `cancel` field, or an
     * expired/missing pending_login, aborts back to the login screen.
     */
    public function confirmLogin(): RedirectResponse
    {
        $pending = session()->get('pending_login');
        session()->remove('pending_login');

        if ($this->request->getPost('cancel') !== null) {
            return redirect()->to(site_url('login'))
                ->with('error', 'Login cancelled. The other session is still active.');
        }

        // Guard against a stale/forged confirmation: pending_login is only ever set
        // after a successful credential + role check, and expires quickly.
        if (! is_array($pending) || (time() - (int) ($pending['created_at'] ?? 0)) > 120) {
            return redirect()->to(site_url('login'))
                ->with('error', 'Your confirmation expired. Please login again.');
        }

        return $this->establishSession([
            'user_id'   => (int) ($pending['user_id'] ?? 0),
            'member_id' => (int) ($pending['member_id'] ?? 0),
            'username'  => (string) ($pending['username'] ?? ''),
            'role'      => (string) ($pending['role'] ?? ''),
        ]);
    }

    /**
     * Establishes the authenticated session for a verified account: regenerates the
     * session id, sets the auth keys plus a fresh single-session auth token, registers
     * that token as the account's sole active session (evicting any prior one), writes
     * the login audit row, and redirects to the role dashboard. Shared by login() and
     * confirmLogin().
     */
    private function establishSession(array $data): RedirectResponse
    {
        $token = bin2hex(random_bytes(16));

        session()->regenerate();
        session()->set([
            'is_logged_in'       => true,
            'user_id'            => $data['user_id'],
            'member_id'          => $data['member_id'],
            'username'           => $data['username'],
            'role'               => $data['role'],
            'auth_token'         => $token,
            'idle_last_activity' => time(),
        ]);

        ActiveSessionRegistry::put(
            ActiveSessionRegistry::identityKey($data['user_id'], $data['username']),
            $token,
            $data['username'],
            $this->request
        );

        SessionAuditLogger::logLogin(
            ['userID' => $data['user_id'], 'username' => $data['username']],
            $data['role'],
            $this->request
        );

        return RoleAccess::redirectByRole($data['role']);
    }

    /**
     * True when the account already holds a DIFFERENT, still-active session than the
     * one making this request. Used by login() to decide whether to prompt before
     * displacing another login. A registered session counts as "active" only while it
     * is within the idle-timeout window; older ones are treated as gone (the other
     * session has effectively lapsed).
     */
    private function activeOtherSession(int $userId, string $username): bool
    {
        $record = ActiveSessionRegistry::get(
            ActiveSessionRegistry::identityKey($userId, $username)
        );

        if ($record === null) {
            return false;
        }

        $token = (string) ($record['token'] ?? '');

        if ($token === '' || $token === (string) session()->get('auth_token')) {
            return false;
        }

        $idleSeconds = (new IdleTimeout())->seconds;

        return (time() - (int) ($record['updated_at'] ?? 0)) < $idleSeconds;
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
        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if ($role === null) {
            return false;
        }

        // The developer logs in from .env (no users row), so the row existence
        // check does not apply to it.
        if ($role !== 'Developer' && ! RoleAccess::sessionUserExists()) {
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
        // Release this account's single-session slot so an immediate re-login on
        // another browser is not falsely flagged as a concurrent session.
        ActiveSessionRegistry::forget(ActiveSessionRegistry::identityKey(
            (int) session()->get('user_id'),
            (string) session()->get('username')
        ));

        session()->remove([
            'is_logged_in',
            'user_id',
            'member_id',
            'username',
            'role',
            'auth_token',
            'idle_last_activity',
            'pending_login',
        ]);

        session()->regenerate(true);
    }
}
