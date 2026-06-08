<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Auth\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class LoginController extends BaseController
{
    public function index(): string
    {
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

        if (! in_array($role, ['Developer', 'Admin', 'User'], true)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account role is invalid. Please contact an administrator.');
        }

        session()->regenerate();
        session()->set([
            'is_logged_in' => true,
            'user_id' => (int) $user['userID'],
            'member_id' => (int) ($user['memberID'] ?? 0),
            'username' => (string) $user['username'],
            'role' => $role,
            'idle_last_activity' => time(),
        ]);

        return RoleAccess::redirectByRole($role);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('login'));
    }
}
