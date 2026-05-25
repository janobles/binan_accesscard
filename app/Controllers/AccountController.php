<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\Auth\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Handles developer-only staff account creation.
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

        $userModel = new UserModel();

        try {
            $userId = $userModel->createAccount(
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

        $this->audit(
            'ACCOUNT_CREATED',
            'Created ' . $displayRole . ' account "' . $username . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account created successfully.');
    }

    /**
     * Developer-only: toggle Admin/User isactive from Account Management UI.
     */
    public function updateStatus(): RedirectResponse
    {
        $guard = $this->requireDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        // Inputs come from Dashboard/accounts.php action buttons.
        $rules = [
            'userID' => 'required|is_natural_no_zero',
            'isactive' => 'required|in_list[Enable,Disabled]',
        ];
        $messages = [
            'userID' => [
                'required' => 'Account is required.',
            ],
            'isactive' => [
                'in_list' => 'Status must be Enable or Disabled.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = (int) $this->request->getPost('userID');
        $status = (string) $this->request->getPost('isactive');
        $sessionUserId = (int) session()->get('user_id');

        if ($userId === $sessionUserId) {
            return redirect()->back()->with('error', 'You cannot change your own account status.');
        }

        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $role = (string) ($user['role'] ?? '');

        if (! in_array($role, ['Admin', 'User'], true)) {
            return redirect()->back()->with('error', 'Only admin or employee accounts can be updated.');
        }

        if ($userModel->update($userId, ['isactive' => $status]) === false) {
            return redirect()->back()->with('error', 'Account status could not be updated.');
        }

        $displayRole = $role === 'User' ? 'Employee' : $role;
        $auditStatus = $status === 'Enable' ? 'Enabled' : 'Disabled';

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            $auditStatus . ' ' . $displayRole . ' account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account status updated successfully.');
    }

    /**
     * Admin-only: disable employee accounts from Account Management UI.
     */
    public function disableEmployee(): RedirectResponse
    {
        $guard = $this->requireAdmin();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        // Input comes from the admin Account Management table action.
        $rules = [
            'userID' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = (int) $this->request->getPost('userID');
        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $role = (string) ($user['role'] ?? '');

        if ($role !== 'User') {
            return redirect()->back()->with('error', 'Only employee accounts can be disabled.');
        }

        if ($userModel->update($userId, ['isactive' => 'Disabled']) === false) {
            return redirect()->back()->with('error', 'Account status could not be updated.');
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

        if (! $this->sessionUserExists()) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your session is no longer valid after the database update. Please login again.');
        }

        if ($this->normalizeRole((string) session()->get('role')) !== 'Developer') {
            return redirect()->to(site_url('/'))->with('error', 'Developer access is required.');
        }

        return null;
    }

    private function requireAdmin(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if (! $this->sessionUserExists()) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your session is no longer valid after the database update. Please login again.');
        }

        if ($this->normalizeRole((string) session()->get('role')) !== 'Admin') {
            return redirect()->to(site_url('/'))->with('error', 'Admin access is required.');
        }

        return null;
    }

    private function sessionUserExists(): bool
    {
        $userId = (int) session()->get('user_id');

        if ($userId <= 0) {
            return false;
        }

        $db = db_connect();

        if (! $db->tableExists('users')) {
            return false;
        }

        return $db->table('users')
            ->where('userID', $userId)
            ->countAllResults() > 0;
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
            $auditModel->logAction(
                (int) session()->get('user_id'),
                null,
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
