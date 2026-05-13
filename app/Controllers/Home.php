<?php

namespace App\Controllers;

use App\Models\UserModel;
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
        $guard = $this->requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Dashboard/admin');
    }

    public function employee(): string|RedirectResponse
    {
        $guard = $this->requireRole(['Developer', 'Admin', 'Employee']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Employee/index');
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
        if ($role === 'Employee') {
            return redirect()->to(site_url('employee/workspace'));
        }

        return redirect()->to(site_url('admin'));
    }
}
