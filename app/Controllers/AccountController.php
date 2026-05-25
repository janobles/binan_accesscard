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
            'memberID' => 'permit_empty|is_natural_no_zero',
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
        $memberId = $this->postedMemberId();

        if ($memberId !== null && ! $this->memberExists($memberId)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'The linked member record does not exist.');
        }

        if ($memberId === null && $this->memberLinkRequired()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please choose a linked member for this account.');
        }

        $userModel = new UserModel();

        try {
            $userId = $userModel->createAccount(
                $username,
                (string) $this->request->getPost('password'),
                $role,
                $memberId
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
        $linkedMemberName = $memberId === null ? '' : $this->memberDisplayName($memberId);
        $linkedMemberText = $linkedMemberName === '' ? '' : ' linked to ' . $linkedMemberName;

        $this->audit(
            'ACCOUNT_CREATED',
            'Created ' . $displayRole . ' account "' . $username . '" (#' . $userId . ')' . $linkedMemberText . '.',
            $memberId
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account created successfully.');
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

    private function postedMemberId(): ?int
    {
        $memberId = $this->request->getPost('memberID');

        if ($memberId === null || trim((string) $memberId) === '') {
            return null;
        }

        return (int) $memberId;
    }

    private function memberExists(int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        $db = db_connect();

        if (! $db->tableExists('member')) {
            return false;
        }

        return $db->table('member')
            ->where('memberID', $memberId)
            ->countAllResults() > 0;
    }

    private function memberLinkRequired(): bool
    {
        $db = db_connect();

        if (! $db->tableExists('users') || ! $db->fieldExists('memberID', 'users')) {
            return false;
        }

        foreach ($db->getFieldData('users') as $field) {
            if ($field->name === 'memberID') {
                return ! (bool) ($field->nullable ?? true);
            }
        }

        return false;
    }

    private function memberDisplayName(int $memberId): string
    {
        $db = db_connect();

        if (! $db->tableExists('member')) {
            return '';
        }

        $member = $db->table('member')
            ->select('firstname, middlename, lastname, suffix')
            ->where('memberID', $memberId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            return '';
        }

        return trim(implode(' ', array_filter([
            (string) ($member['firstname'] ?? ''),
            (string) ($member['middlename'] ?? ''),
            (string) ($member['lastname'] ?? ''),
            (string) ($member['suffix'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
    }
}
