<?php

namespace App\Controllers;

use App\Models\AuditTrailsModel;
use App\Models\UserModel;
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

    public function updateStatus(): RedirectResponse
    {
        $guard = $this->requireDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'userID' => 'required|is_natural_no_zero',
            'status' => 'required|in_list[Enable,Disabled]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = (int) $this->request->getPost('userID');
        $enabled = (string) $this->request->getPost('status') === 'Enable';
        $userModel = new UserModel();
        $account = $userModel->find($userId);

        if ($account === null || ! in_array((string) ($account['role'] ?? ''), ['Admin', 'User'], true)) {
            return redirect()->back()->with('error', 'Account could not be found.');
        }

        if (! $userModel->updateAccountStatus($userId, $enabled)) {
            return redirect()->back()->with('error', 'Account status could not be updated.');
        }

        $displayRole = (string) ($account['role'] ?? '') === 'User' ? 'Employee' : (string) ($account['role'] ?? '');
        $statusLabel = $enabled ? 'enabled' : 'disabled';

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            ucfirst($statusLabel) . ' ' . $displayRole . ' account "' . (string) ($account['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))
            ->with('success', 'Account ' . $statusLabel . ' successfully.');
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

    private function audit(string $action, string $description, ?int $memberId = null): void
    {
        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            $auditModel->logAction(
                (int) session()->get('user_id'),
                $memberId,
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
