<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\Auth\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class AccountManagementController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = $this->accountViewData();

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/accountmanagement', $viewData);
        }

        return view('Admin/dashboard', [
            'activePage' => 'accounts',
            'pageTitle' => 'Account Management',
            'workspaceUrl' => site_url('admin/accounts'),
        ]);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $role = (string) $this->request->getPost('role');
        $allowedRoles = ['Admin', 'User'];
        $errors = [];

        if ($username === '') {
            $errors[] = 'Username is required.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (! in_array($role, $allowedRoles, true)) {
            $errors[] = 'Account role is invalid.';
        }

        $userModel = new UserModel();

        if ($username !== '' && $userModel->where('username', $username)->first() !== null) {
            $errors[] = 'Username already exists.';
        }

        if ($errors !== []) {
            return $this->accountCreateResponse(false, implode(' ', $errors));
        }

        $created = $userModel->createAccount($username, $password, $role);

        if ($created === false) {
            return $this->accountCreateResponse(false, 'Account could not be created.');
        }

        return $this->accountCreateResponse(true, $role . ' account created successfully.');
    }

    private function accountCreateResponse(bool $success, string $message): RedirectResponse|ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => $success,
                'message' => $message,
                'html' => view('Admin/accountmanagement', $this->accountViewData()),
            ])->setStatusCode($success ? 201 : 422);
        }

        $redirect = redirect()->to(site_url('admin/accounts'));

        return $success
            ? $redirect->with('success', $message)
            : $redirect->withInput()->with('error', $message);
    }

    private function accountViewData(): array
    {
        $accounts = (new UserModel())->getStaffAccounts();

        return [
            'adminAccounts' => array_values(array_filter(
                $accounts,
                static fn (array $account): bool => (string) ($account['role'] ?? '') === 'Admin'
            )),
            'employeeAccounts' => array_values(array_filter(
                $accounts,
                static fn (array $account): bool => (string) ($account['role'] ?? '') === 'User'
            )),
        ];
    }
}
