<?php

namespace App\Controllers\Accounts;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\ViewFormatter;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Auth\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Self-service "My Account" for the logged-in user (the modal opened from the
 * topbar username): edit own personal details and change own password. Admin /
 * Developer / Admin / Encoder / Viewer / Scanner accounts all save through
 * UserModel. Account management of OTHER users lives in AccountController.
 */
class ProfileController extends BaseController
{
    /**
     * Returns the prefilled My Account modal fragment for GET `account/profile`.
     * Loads the logged-in user's row and unpacks full_description for the form.
     * Frontend: the topbar username trigger loaded by the dashboard modal loader.
     */
    public function myAccount(): string|RedirectResponse
    {
        $guard = $this->requireLoggedIn();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) session()->get('user_id');
        $account = (new UserModel())->getAccountById($userId);

        if ($account === null) {
            return '<div class="alert alert-danger mb-0">Your account could not be loaded.</div>';
        }

        $role = (string) ($account['role'] ?? '');

        return view('Accounts/account-form-modal', [
            'mode'      => 'self',
            'account'   => $account,
            'details'   => ViewFormatter::parseFullDescription((string) ($account['full_description'] ?? '')),
            'roleLabel' => RoleAccess::normalizeRole($role) ?? $role,
        ]);
    }

    /**
     * Saves the My Account modal from POST `account/profile/update`. Validates the
     * username (unique except self) and personal fields; if a new password is
     * supplied, requires the correct current password and a matching confirmation.
     * Refreshes the session username and audits PROFILE_UPDATED / PASSWORD_CHANGED.
     */
    public function update(): RedirectResponse
    {
        $guard = $this->requireLoggedIn();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) session()->get('user_id');
        $userModel = new UserModel();
        $account = $userModel->getAccountById($userId);

        if ($account === null) {
            return redirect()->to(site_url('/'))->with('error', 'Your account could not be loaded.');
        }

        $rules = [
            'username' => 'required|min_length[4]|max_length[255]|is_unique[users.username,userID,' . $userId . ']',
            'last_name' => 'required|max_length[100]',
            'first_name' => 'required|max_length[100]',
            'middle_name' => 'required|max_length[100]',
            'suffix' => 'permit_empty|max_length[20]',
            'address' => 'required|max_length[255]',
            'contact_no' => 'required|max_length[50]',
            'birthday' => 'required|valid_date[Y-m-d]',
        ];
        $messages = [
            'username' => [
                'is_unique' => 'That username is already taken. Choose a different one.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Password change is optional; validate it fully before writing anything.
        $newPassword = (string) $this->request->getPost('new_password');
        $changingPassword = $newPassword !== '';

        if ($changingPassword) {
            $currentPassword = (string) $this->request->getPost('current_password');
            $confirmPassword = (string) $this->request->getPost('confirm_password');

            if (strlen($newPassword) < 8) {
                return redirect()->back()->with('error', 'New password must have at least 8 characters.');
            }

            if ($newPassword !== $confirmPassword) {
                return redirect()->back()->with('error', 'New password and confirmation do not match.');
            }

            if (! $userModel->verifyUserPassword($userId, $currentPassword)) {
                return redirect()->back()->with('error', 'Your current password is incorrect.');
            }
        }

        $username = trim((string) $this->request->getPost('username'));

        // Self-service cannot change account level.
        if (! $userModel->updateProfile($userId, [
            'username' => $username,
            'full_description' => $this->buildFullDescription(),
        ])) {
            return redirect()->back()->with('error', 'Your profile could not be saved.');
        }

        if ($changingPassword && ! $userModel->updatePassword($userId, $newPassword)) {
            return redirect()->back()->with('error', 'Your password could not be changed.');
        }

        session()->set('username', $username);

        $this->audit('PROFILE_UPDATED', 'Updated own profile "' . $username . '" (#' . $userId . ').');

        if ($changingPassword) {
            $this->audit('PASSWORD_CHANGED', 'Changed own password "' . $username . '" (#' . $userId . ').');
        }

        return redirect()->back()->with('success', 'Your account was updated successfully.');
    }

    /**
     * Guard: requires a logged-in session before loading the account row.
     */
    private function requireLoggedIn(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        return null;
    }

    /**
     * Repacks users.full_description from the shared account form.
     */
    private function buildFullDescription(): string
    {
        $segments = [
            'LN'   => trim((string) $this->request->getPost('last_name')),
            'FN'   => trim((string) $this->request->getPost('first_name')),
            'MN'   => trim((string) $this->request->getPost('middle_name')),
            'SF'   => trim((string) $this->request->getPost('suffix')),
            'ADDR' => trim((string) $this->request->getPost('address')),
            'CN'   => trim((string) $this->request->getPost('contact_no')),
            'BD'   => trim((string) $this->request->getPost('birthday')),
        ];

        $parts = [];

        foreach ($segments as $label => $value) {
            if ($value !== '') {
                $parts[] = $label . ':' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Writes a self-service profile event to audit_trails. Silently skips if the
     * table is missing and never lets an audit failure break the save.
     */
    private function audit(string $action, string $description): void
    {
        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            $auditModel->logAction(
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
