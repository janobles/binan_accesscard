<?php

namespace App\Controllers\Accounts;

use App\Controllers\BaseController;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Auth\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Handles developer-only staff account creation.
 *
 * Validation and redirects stay here; user persistence stays in UserModel.
 */
class AccountController extends BaseController
{
    /**
     * Creates a staff account from POST `developer/accounts`. Developer-only;
     * validates the username/password/role, delegates persistence to
     * UserModel::createAccount, writes an audit row, then redirects to
     * `admin/accounts` with a flash message. Frontend: the account-creation
     * form on the admin accounts page.
     */
    public function create(): RedirectResponse
    {
        $guard = $this->requireDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'username' => 'required|min_length[4]|max_length[255]|is_unique[users.username]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            // Personal details are packed into the single users.full_description
            // column (see buildFullDescription). Suffix is optional.
            'last_name' => 'required|max_length[100]',
            'first_name' => 'required|max_length[100]',
            'middle_name' => 'required|max_length[100]',
            'suffix' => 'permit_empty|max_length[20]',
            'address' => 'required|max_length[255]',
            'contact_no' => 'required|max_length[50]',
            'birthday' => 'required|valid_date[Y-m-d]',
            // The form posts the DB enum value for account_level directly.
            'role' => 'required|in_list[administrator,encoder,viewer]',
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
            'password_confirm' => [
                'required' => 'Confirm password is required.',
                'matches' => 'Confirm password must match the password.',
            ],
            'birthday' => [
                'valid_date' => 'Birthday must be a valid date.',
            ],
            'role' => [
                'in_list' => 'Account level must be Administrator, Encoder, or Viewer.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $role = (string) $this->request->getPost('role');
        $username = trim((string) $this->request->getPost('username'));
        $fullDescription = $this->buildFullDescription();

        try {
            $userId = (new UserModel())->createAccount(
                $username,
                (string) $this->request->getPost('password'),
                $role,
                $fullDescription
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

        $displayRole = $this->normalizeRole($role) ?? $role;
        $this->audit('ACCOUNT_CREATED', 'Created ' . $displayRole . ' account "' . $username . '" (#' . $userId . ').');

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account created successfully.');
    }

    /**
     * Developer-only: enable/disable Admin or Employee accounts via POST
     * `developer/accounts/status`. Blocks self-changes, verifies the target is an
     * Admin/User account, updates status through UserModel, and audits the change.
     * Frontend: the enable/disable controls on the admin accounts page.
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
        $user = $userModel->select('userID, username, account_level AS role')->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $role = (string) ($user['role'] ?? '');

        if (! in_array($role, ['administrator', 'encoder'], true)) {
            return redirect()->back()->with('error', 'Only admin or employee accounts can be updated.');
        }

        $enabled = $status === 'Enable';

        if (! $userModel->updateAccountStatus($userId, $enabled)) {
            return redirect()->back()->with('error', 'Account status could not be updated.');
        }

        $displayRole = $this->normalizeRole($role) ?? $role;
        $auditStatus = $enabled ? 'Enabled' : 'Disabled';

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            $auditStatus . ' ' . $displayRole . ' account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account status updated successfully.');
    }

    /**
     * Admin/Developer: disable an Employee account via POST `admin/accounts/disable`.
     * Only `User`-role accounts may be disabled here; self-disable is blocked.
     * Audits the change and redirects to `admin/accounts`. Frontend: the
     * "disable" button in the admin Account Management list.
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
        $user = $userModel->select('userID, username, account_level AS role')->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        if ((string) ($user['role'] ?? '') !== 'encoder') {
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

    /**
     * Admin/Developer: enable an Employee account via POST `admin/accounts/enable`.
     * Only `User`-role accounts may be enabled here; self-enable is blocked.
     * Audits the change and redirects to `admin/accounts`. Frontend: the
     * "enable" button in the admin Account Management list.
     */
    public function enableEmployee(): RedirectResponse
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
            return redirect()->back()->with('error', 'You cannot enable your own account.');
        }

        $userModel = new UserModel();
        $user = $userModel->select('userID, username, account_level AS role')->find($userId);

        if ($user === null) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        if ((string) ($user['role'] ?? '') !== 'encoder') {
            return redirect()->back()->with('error', 'Only employee accounts can be enabled from this action.');
        }

        if (! $userModel->updateAccountStatus($userId, true)) {
            return redirect()->back()->with('error', 'Employee account could not be enabled.');
        }

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            'Enabled Employee account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Employee account enabled successfully.');
    }

    /**
     * Access guard: returns a redirect (to login or with an error) unless the
     * current session is a logged-in Developer; null means allowed to proceed.
     */
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

    /**
     * Access guard for actions open to Admins and Developers; returns a redirect
     * to block anyone else, or null to allow the action.
     */
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

    /**
     * Packs the create-account form's personal fields into the single
     * users.full_description column as a labeled `LABEL:value; ...` string
     * (LN/FN/MN/SF/ADDR/CN/BD). Empty segments (e.g. a missing suffix) are omitted
     * so they can be split back out reliably later. No frontend connection.
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
     * Normalizes a raw account-level string to the app's canonical 'Developer'/
     * 'Admin'/'Employee'/'Viewer' (or null) so guards can compare the session role
     * reliably. The DB enum values 'administrator'/'encoder'/'viewer' map to
     * 'Admin'/'Employee'/'Viewer'; legacy 'User'/'Admin' are still accepted.
     */
    private function normalizeRole(string $role): ?string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'developer' => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'encoder', 'employee' => 'Employee',
            'viewer' => 'Viewer',
            default => null,
        };
    }

    /**
     * Writes an account-management event to audit_trails (staff action, so
     * memberID stays null). Silently skips if the table is missing and never
     * lets an audit failure break the account action. No frontend connection.
     */
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
