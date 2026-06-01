<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\Auth\UserModel;
use Throwable;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles developer-only staff account creation.
 *
 * Validation and redirects stay here; user persistence stays in UserModel.
 */
class AccountController extends BaseController
{
    public function create(): RedirectResponse
    {
        $guard = $this->requireDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'username' => 'required|min_length[4]|max_length[255]|is_unique[users.username]',
            'password' => 'required|min_length[8]',
            'role' => 'required|in_list[Admin,User]',
        ];
        $messages = [
            'username' => [
                'required' => 'Username is required. Example: admin_maria01 or emp_juan01.',
                'min_length' => 'Username must have at least 4 characters. Example: emp_juan01.',
                'is_unique' => 'Username must be unique. Try examples like admin_maria01, admin_roberto02, emp_ana01, or emp_juan02.',
            ],
            'password' => [
                'required' => 'Password is required.',
                'min_length' => 'Password must have at least 8 characters.',
            ],
            'role' => [
                'in_list' => 'Account type must be Admin or Employee.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $role = (string) $this->request->getPost('role');
        $username = trim((string) $this->request->getPost('username'));

        try {
            $userId = (new UserModel())->createAccount(
                $username,
                (string) $this->request->getPost('password'),
                $role
            );
        } catch (Throwable $exception) {
            log_message('error', $exception->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Account could not be created. Use a unique username such as admin_maria01 or emp_juan01.');
        }

        if ($userId === false) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Account could not be created. Use a unique username such as admin_maria01 or emp_juan01.');
        }

        $displayRole = $role === 'User' ? 'Employee' : $role;
        $this->audit('ACCOUNT_CREATED', 'Created ' . $displayRole . ' account "' . $username . '" (#' . $userId . ').');

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account created successfully.');
    }

    /**
     * Developer-only: enable/disable Admin or Employee accounts.
     */
    public function updateStatus(): RedirectResponse
    {
        $guard = $this->requireDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) $this->request->getPost('userID');
        $status = trim((string) ($this->request->getPost('status') ?? $this->request->getPost('isactive')));

        if ($userId <= 0) {
            return redirect()->back()->with('error', 'Account is required.');
        }

        if (! in_array($status, ['Enable', 'Disabled'], true)) {
            return redirect()->back()->with('error', 'Status must be Enable or Disabled.');
        }

        if ($userId === (int) session()->get('user_id')) {
            return redirect()->back()->with('error', 'You cannot change your own account status.');
        }

        $userModel = new UserModel();
        $user = $userModel->select('userID, username, role')->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $role = (string) ($user['role'] ?? '');

        if (! in_array($role, ['Admin', 'User'], true)) {
            return redirect()->back()->with('error', 'Only admin or employee accounts can be updated.');
        }

        $enabled = $status === 'Enable';

        if (! $userModel->updateAccountStatus($userId, $enabled)) {
            return redirect()->back()->with('error', 'Account status could not be updated.');
        }

        $displayRole = $role === 'User' ? 'Employee' : $role;
        $auditStatus = $enabled ? 'Enabled' : 'Disabled';

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            $auditStatus . ' ' . $displayRole . ' account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account status updated successfully.');
    }

    /**
     * Admin/Developer: disable an Employee account.
     */
    public function disableEmployee(): RedirectResponse
    {
        $guard = $this->requireAdminOrDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) $this->request->getPost('userID');

        if ($userId <= 0) {
            return redirect()->back()->with('error', 'Account is required.');
        }

        if ($userId === (int) session()->get('user_id')) {
            return redirect()->back()->with('error', 'You cannot disable your own account.');
        }

        $userModel = new UserModel();
        $user = $userModel->select('userID, username, role')->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        if ((string) ($user['role'] ?? '') !== 'User') {
            return redirect()->back()->with('error', 'Only employee accounts can be disabled from this action.');
        }

        if (! $userModel->updateAccountStatus($userId, false)) {
            return redirect()->back()->with('error', 'Employee account could not be disabled.');
        }

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            'Disabled Employee account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Employee account disabled successfully.');
    }

    private function requireDeveloper(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if ($this->normalizeRole((string) session()->get('role')) !== 'Developer') {
            return redirect()->to(site_url('/'))->with('error', 'Developer access is required.');
        }

        return null;
    }

    private function requireAdminOrDeveloper(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if (! in_array($this->normalizeRole((string) session()->get('role')), ['Developer', 'Admin'], true)) {
            return redirect()->to(site_url('/'))->with('error', 'Admin or Developer access is required.');
        }

        return null;
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

    private function audit(string $action, string $description): void
    {
        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            // Account creation is a staff action, so memberID stays null.
            (new AuditTrailsModel())->logAction(
                (int) session()->get('user_id'),
                (int) session()->get('member_id') ?: null,
                $action,
                $description,
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (Throwable $exception) {
            log_message('error', 'Audit trail skipped: ' . $exception->getMessage());
        }
    }
}
