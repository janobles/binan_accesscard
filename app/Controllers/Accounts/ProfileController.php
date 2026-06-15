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
 * Self-service "My Account" for logged-in Admin / Encoder / Viewer staff: edit
 * own profile details and change own password (the modal opened from the topbar
 * username). The Developer authenticates from .env and has no users row, so it
 * has no editable profile here. Account management of OTHER users lives in
 * Accounts\AccountController.
 */
class ProfileController extends BaseController
{
    /**
     * Returns the prefilled My Account modal fragment for GET `account/profile`.
     * Any logged-in non-developer; loads the session user's row and unpacks
     * full_description for the form. Frontend: the topbar username trigger loaded
     * by the dashboard modal loader.
     */
    public function myAccount(): string|RedirectResponse
    {
        $guard = $this->requireSelfEditableUser();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) session()->get('user_id');
        $account = (new UserModel())->getAccountById($userId);

        if ($account === null) {
            return '<div class="alert alert-danger mb-0">Your account could not be loaded.</div>';
        }

        $role = (string) ($account['role'] ?? '');

        return view('Accounts/my-account-modal', [
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
        $guard = $this->requireSelfEditableUser();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) session()->get('user_id');
        $userModel = new UserModel();
        $account = $userModel->getAccountById($userId);

        if ($account === null) {
            return redirect()->to(site_url('/'))->with('error', 'Your account could not be loaded.');
        }

        // Address / contact / birthday are not editable from My Account (set only at
        // account creation), so they are validated and rebuilt from the stored row.
        $rules = [
            'username' => 'required|min_length[4]|max_length[255]|is_unique[users.username,userID,' . $userId . ']',
            'last_name' => 'required|max_length[100]',
            'first_name' => 'required|max_length[100]',
            'middle_name' => 'required|max_length[100]',
            'suffix' => 'permit_empty|max_length[20]',
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

        // Self-service cannot change account level — only username/name details.
        $existingDetails = ViewFormatter::parseFullDescription((string) ($account['full_description'] ?? ''));

        if (! $userModel->updateProfile($userId, [
            'username' => $username,
            'full_description' => $this->buildFullDescription($existingDetails),
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
     * Guard: requires a logged-in, non-developer session. The Developer lives in
     * .env with no users row, so it cannot self-edit and is bounced to its
     * dashboard with an explanation.
     */
    private function requireSelfEditableUser(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if (RoleAccess::normalizeRole((string) session()->get('role')) === 'Developer') {
            return redirect()->to(site_url('admin/dashboard'))
                ->with('error', 'The developer account is managed from configuration and has no editable profile.');
        }

        return null;
    }

    /**
     * Repacks users.full_description for a My Account save: the name fields come
     * from the posted form, while address/contact/birthday are carried over from
     * the existing parsed details ($existing) since they are not editable here.
     * Produces the same LN/FN/MN/SF/ADDR/CN/BD labeled string the parser round-trips.
     */
    private function buildFullDescription(array $existing): string
    {
        $segments = [
            'LN'   => trim((string) $this->request->getPost('last_name')),
            'FN'   => trim((string) $this->request->getPost('first_name')),
            'MN'   => trim((string) $this->request->getPost('middle_name')),
            'SF'   => trim((string) $this->request->getPost('suffix')),
            'ADDR' => trim((string) ($existing['address'] ?? '')),
            'CN'   => trim((string) ($existing['contact_no'] ?? '')),
            'BD'   => trim((string) ($existing['birthday'] ?? '')),
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
