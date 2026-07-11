<?php

namespace App\Controllers\Accounts;

use App\Controllers\BaseController;
use App\Libraries\ViewFormatter;
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
     * Returns the create-account modal fragment. Admin/Developer only.
     */
    public function createForm(): string|RedirectResponse
    {
        $guard = $this->requireAdminOrDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Accounts/account-form-modal', [
            'mode' => 'create',
        ]);
    }

    /**
     * Creates a staff account from POST `developer/accounts`. Developer-only;
     * validates the username/password/role, delegates persistence to
     * UserModel::createAccount, writes an audit row, then redirects to
     * `admin/accounts` with a flash message. Frontend: the account-creation
     * form on the admin accounts page.
     */
    public function create(): RedirectResponse
    {
        // Admins now have the same create privilege as the Developer: they can add
        // administrator, encoder, and viewer accounts.
        $guard = $this->requireAdminOrDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'username' => 'required|min_length[4]|max_length[255]|is_unique[users.username]',
            'password' => 'required|min_length[8]',
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
            'role' => 'required|in_list[administrator,encoder,viewer,scanner]',
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
            'birthday' => [
                'valid_date' => 'Birthday must be a valid date.',
            ],
            'role' => [
                'in_list' => 'Account level must be Administrator, Encoder, Viewer, or Scanner.',
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
     * Returns the prefilled edit-account modal fragment for GET `accounts/edit/{id}`.
     * Admin/Developer only. Loads the target account, unpacks full_description into
     * form fields, and renders the shared account form modal. The Developer (no DB
     * row) and unknown roles cannot be edited. Frontend: the Edit button in the
     * admin Account Management list (loaded by the dashboard modal loader).
     */
    public function editForm(int $userId): string|RedirectResponse
    {
        $guard = $this->requireAdminOrDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $account = (new UserModel())->getAccountById($userId);

        if ($account === null) {
            return '<div class="alert alert-danger mb-0">Account not found.</div>';
        }

        if (! in_array((string) ($account['role'] ?? ''), ['administrator', 'encoder', 'viewer', 'scanner'], true)) {
            return '<div class="alert alert-danger mb-0">This account cannot be edited.</div>';
        }

        $currentUserId = (int) session()->get('user_id');

        return view('Accounts/account-form-modal', [
            'mode'    => 'edit',
            'account' => $account,
            'details' => ViewFormatter::parseFullDescription((string) ($account['full_description'] ?? '')),
            'isSelf'  => $userId === $currentUserId,
            // An admin may manage another administrator's login (username, account
            // level, password) but not their personal details; the developer and
            // editing your own row are unrestricted.
            'personalLocked' => $this->isPersonalLocked((string) ($account['role'] ?? ''), $userId === $currentUserId),
        ]);
    }

    /**
     * Saves edits to an existing account from POST `accounts/update`. Admin/Developer
     * only. Validates username (unique except itself), the personal fields, and the
     * role; rebuilds full_description and persists via UserModel::updateProfile.
     * Blocks editing the Developer and blocks changing your own account level (to
     * avoid self-lockout). Audits ACCOUNT_UPDATED. Frontend: the edit-account modal.
     */
    public function update(): RedirectResponse
    {
        $guard = $this->requireAdminOrDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) $this->request->getPost('userID');

        if ($userId <= 0) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'Account is required.');
        }

        $userModel = new UserModel();
        $account = $userModel->getAccountById($userId);

        if ($account === null) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'Account not found.');
        }

        $currentRole = (string) ($account['role'] ?? '');

        if (! in_array($currentRole, ['administrator', 'encoder', 'viewer', 'scanner'], true)) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'This account cannot be edited.');
        }

        $isSelf = $userId === (int) session()->get('user_id');
        // When an admin edits another administrator, the personal details are locked
        // (only username/account level/password may change), so those fields are not
        // posted/validated and the existing full_description is kept as-is.
        $personalLocked = $this->isPersonalLocked($currentRole, $isSelf);

        $rules = [
            'username' => 'required|min_length[4]|max_length[255]|is_unique[users.username,userID,' . $userId . ']',
            'role' => 'required|in_list[administrator,encoder,viewer,scanner]',
        ];
        $messages = [
            'username' => [
                'is_unique' => 'Username is already taken. Choose a different one.',
            ],
            'role' => [
                'in_list' => 'Account level must be Administrator, Encoder, Viewer, or Scanner.',
            ],
        ];

        if (! $personalLocked) {
            $rules += [
                'last_name' => 'required|max_length[100]',
                'first_name' => 'required|max_length[100]',
                'middle_name' => 'required|max_length[100]',
                'suffix' => 'permit_empty|max_length[20]',
                'address' => 'required|max_length[255]',
                'contact_no' => 'required|max_length[50]',
                'birthday' => 'required|valid_date[Y-m-d]',
            ];
            $messages['birthday'] = [
                'valid_date' => 'Birthday must be a valid date.',
            ];
        }

        if (! $this->validate($rules, $messages)) {
            return redirect()->to(site_url('admin/accounts'))
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $newRole = (string) $this->request->getPost('role');

        // Changing your own account level could lock you out of your own dashboard.
        if ($isSelf && $newRole !== $currentRole) {
            return redirect()->to(site_url('admin/accounts'))
                ->with('error', 'You cannot change your own account level.');
        }

        $username = trim((string) $this->request->getPost('username'));
        $fields = [
            'username' => $username,
            'account_level' => $newRole,
            // A locked editor cannot touch personal details, so keep the stored value.
            'full_description' => $personalLocked
                ? (string) ($account['full_description'] ?? '')
                : $this->buildFullDescription(),
        ];

        try {
            $saved = $userModel->updateProfile($userId, $fields);
        } catch (Throwable $exception) {
            $this->auditSystemError('account update', $exception);

            return redirect()->to(site_url('admin/accounts'))->with('error', 'Account could not be updated due to a system error.');
        }

        if (! $saved) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'Account could not be updated.');
        }

        // Keep the topbar/session in sync if an admin renamed their own account.
        if ($isSelf) {
            session()->set('username', $username);
        }

        $displayRole = $this->normalizeRole($newRole) ?? $newRole;

        // A privilege change is security-relevant, so it gets its own action type and
        // records the old→new role; plain edits stay ACCOUNT_UPDATED.
        if ($newRole !== $currentRole) {
            $oldDisplayRole = $this->normalizeRole($currentRole) ?? $currentRole;
            $this->audit(
                'ACCOUNT_ROLE_CHANGED',
                'Changed account level of "' . $username . '" (#' . $userId . ') to ' . $displayRole . '.',
                'Role changed from ' . $oldDisplayRole . ' to ' . $displayRole
            );
        } else {
            $this->audit('ACCOUNT_UPDATED', 'Updated ' . $displayRole . ' account "' . $username . '" (#' . $userId . ').');
        }

        return redirect()->to(site_url('admin/accounts'))->with('success', 'Account updated successfully.');
    }

    /**
     * Generates a fresh random password for an account from POST
     * `accounts/reset-password` (admin/developer "forgot password" recovery). Hashes
     * and stores it, audits ACCOUNT_PASSWORD_RESET, and flashes the plaintext once so
     * the staffer can hand it to the user, who then changes it in My Account. Self
     * resets are pushed to My Account instead. Frontend: the Reset button in the
     * admin Account Management list.
     */
    public function resetPassword(): RedirectResponse
    {
        $guard = $this->requireAdminOrDeveloper();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $userId = (int) $this->request->getPost('userID');

        if ($userId <= 0) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'Account is required.');
        }

        if ($userId === (int) session()->get('user_id')) {
            return redirect()->to(site_url('admin/accounts'))
                ->with('error', 'Use My Account to change your own password.');
        }

        $userModel = new UserModel();
        $account = $userModel->getAccountById($userId);

        if ($account === null) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'Account not found.');
        }

        if (! in_array((string) ($account['role'] ?? ''), ['administrator', 'encoder', 'viewer', 'scanner'], true)) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'This account password cannot be reset.');
        }

        $newPassword = $userModel->generateRandomPassword();

        if (! $userModel->updatePassword($userId, $newPassword)) {
            return redirect()->to(site_url('admin/accounts'))->with('error', 'Password could not be reset.');
        }

        $username = (string) ($account['username'] ?? '');
        $this->audit('ACCOUNT_PASSWORD_RESET', 'Reset password for account "' . $username . '" (#' . $userId . ').');

        return redirect()->to(site_url('admin/accounts'))
            ->with('reset_password', [
                'username' => $username,
                'password' => $newPassword,
            ]);
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

        if (! in_array($role, ['administrator', 'encoder', 'viewer', 'scanner'], true)) {
            return redirect()->back()->with('error', 'Only admin, employee, viewer, or scanner accounts can be updated.');
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

        if (! in_array((string) ($user['role'] ?? ''), ['encoder', 'viewer'], true)) {
            return redirect()->back()->with('error', 'Only employee or viewer accounts can be disabled from this action.');
        }

        $displayRole = $this->normalizeRole((string) ($user['role'] ?? '')) ?? (string) ($user['role'] ?? '');

        if (! $userModel->updateAccountStatus($userId, false)) {
            return redirect()->back()->with('error', $displayRole . ' account could not be disabled.');
        }

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            'Disabled ' . $displayRole . ' account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', $displayRole . ' account disabled successfully.');
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

        if (! in_array((string) ($user['role'] ?? ''), ['encoder', 'viewer'], true)) {
            return redirect()->back()->with('error', 'Only employee or viewer accounts can be enabled from this action.');
        }

        $displayRole = $this->normalizeRole((string) ($user['role'] ?? '')) ?? (string) ($user['role'] ?? '');

        if (! $userModel->updateAccountStatus($userId, true)) {
            return redirect()->back()->with('error', $displayRole . ' account could not be enabled.');
        }

        $this->audit(
            'ACCOUNT_STATUS_UPDATED',
            'Enabled ' . $displayRole . ' account "' . (string) ($user['username'] ?? '') . '" (#' . $userId . ').'
        );

        return redirect()->to(site_url('admin/accounts'))->with('success', $displayRole . ' account enabled successfully.');
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
     * Whether the current editor may NOT change the target account's personal
     * details (name, suffix, birthday, contact no, address). True only when an
     * administrator edits another administrator: an admin can still manage that
     * account's username, account level, and password, but not its personal info.
     * The developer is unrestricted, and editing your own row is never locked.
     *
     * @param string $targetRole the target account's raw account_level enum value
     */
    private function isPersonalLocked(string $targetRole, bool $isSelf): bool
    {
        if ($isSelf) {
            return false;
        }

        return $this->normalizeRole((string) session()->get('role')) === 'Admin'
            && $this->normalizeRole($targetRole) === 'Admin';
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
            'scanner' => 'Scanner',
            default => null,
        };
    }

    /**
     * Writes an account-management event to audit_trails (staff action, so
     * memberID stays null). Silently skips if the table is missing and never
     * lets an audit failure break the account action. No frontend connection.
     */
    private function audit(string $action, string $description, ?string $detail = null): void
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
                $this->request->getUserAgent()->getAgentString(),
                $detail
            );
        } catch (Throwable $exception) {
            log_message('error', 'Audit trail skipped: ' . $exception->getMessage());
        }
    }

    /**
     * Records a SYSTEM_ERROR audit row for an unexpected failure during an account
     * action, so operators see it on the audit page (visible to admins). Best-effort:
     * a failure here must never mask the original error, hence the nested try/catch.
     */
    private function auditSystemError(string $context, Throwable $exception): void
    {
        try {
            $this->audit(
                'SYSTEM_ERROR',
                'System error during ' . $context . '.',
                $exception->getMessage()
            );
        } catch (Throwable $ignored) {
            // Swallow — the caller already handles the originating error.
        }
    }
}
