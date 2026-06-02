<?php

namespace App\Controllers\Workspace;

use App\Controllers\BaseController;
use App\Controllers\HomeRoleAccessTrait;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAuditLogger;
use App\Models\Auth\UserModel;
use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles authentication, session lifetime, and role-based dashboard routing.
 *
 * Page rendering is delegated to App\Libraries\DashboardPageBuilder: this
 * controller only decides WHICH page/role to show, the builder assembles the
 * view data and returns the rendered HTML. Auth/session helpers
 * (hasValidLoginSession, clearLoginSession, normalizeRole, redirectByRole)
 * come from App\Controllers\HomeRoleAccessTrait.
 */
class Home extends BaseController
{
    use HomeRoleAccessTrait;

    // ---------------------------------------------------------------------
    // Authentication & session lifecycle
    // ---------------------------------------------------------------------

    // NOTE: index/login/logout/keepAlive mirror Auth\AuthController, which is the
    // controller the routes actually target for `/`, `login`, `logout`, and
    // `session/keep-alive`. They are kept here as the role-routing reference.

    /**
     * Shows the login view or, for an already-authenticated user, redirects to
     * their role dashboard. Frontend: renders the `Auth/login` view.
     */
    public function index(): string|RedirectResponse
    {
        if (session()->get('is_logged_in')) {
            if (! $this->hasValidLoginSession()) {
                $this->clearLoginSession();

                return redirect()->to(site_url('login'))
                    ->with('error', 'Your session expired. Please login again.');
            }

            return $this->redirectByRole((string) session()->get('role'));
        }

        return view('Auth/login');
    }

    /**
     * Authenticates the login form POST: verifies credentials via UserModel,
     * sets the session, logs the login to audit_trails, and redirects by role.
     * Frontend: consumes `username`/`password` from the login form.
     */
    public function login(): string|RedirectResponse
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->index();
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())->verifyLogin($username, $password);

        if ($user === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid username or password.');
        }

        // Show a specific message when valid credentials belong to a disabled account.
        if (($user['login_error'] ?? '') === 'disabled') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This account is disabled and cannot be used.');
        }

        $role = $this->normalizeRole((string) ($user['role'] ?? ''));

        if ($role === null) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account role is invalid. Please contact an administrator.');
        }

        session()->regenerate();
        session()->set([
            'is_logged_in' => true,
            'user_id' => (int) $user['userID'],
            'member_id' => 0,
            'username' => $user['username'],
            'role' => $role,
            'idle_last_activity' => time(),
        ]);

        SessionAuditLogger::logLogin($user, $role, $this->request);

        return $this->redirectByRole($role);
    }

    /**
     * Logs the logout to audit_trails, clears the session, and redirects to the
     * login page. `?timeout=1` (from the idle JS) shows an inactivity message.
     */
    public function logout(): RedirectResponse
    {
        if ($this->request->getGet('timeout') === '1') {
            SessionAuditLogger::logLogoutFromSession($this->request, true);
            $this->clearLoginSession();

            return redirect()->to(site_url('login'))
                ->with('error', 'You were logged out due to inactivity.');
        }

        SessionAuditLogger::logLogoutFromSession($this->request);
        $this->clearLoginSession();

        return redirect()->to(site_url('login'));
    }

    /**
     * Session heartbeat polled by the dashboard keep-alive JS. Refreshes the idle
     * timer and returns JSON `{status: ok}`, or 401 `{status: expired}`.
     */
    public function keepAlive()
    {
        if (! session()->get('is_logged_in')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['status' => 'expired']);
        }

        session()->set('idle_last_activity', time());

        return $this->response->setJSON(['status' => 'ok']);
    }

    // ---------------------------------------------------------------------
    // Admin / Developer pages (full page loads).
    // Each maps to a route in Config\Routes and an $activePage the admin shell
    // (Views/Dashboard/Manage/admin.php) switches on. Routes are guarded for
    // Developer/Admin inside DashboardPageBuilder::renderAdminPage().
    // ---------------------------------------------------------------------

    /**
     * Entry point for GET `admin`: redirects to the canonical admin dashboard URL.
     */
    public function admin(): RedirectResponse
    {
        return redirect()->to(site_url('admin/dashboard'));
    }

    /**
     * GET `admin/dashboard`. Delegates to DashboardPageBuilder to assemble stats
     * and render the admin shell on the "dashboard" tab. Frontend: full-page load
     * of `Dashboard/Manage/admin`.
     */
    public function adminDashboard(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('dashboard');
    }

    /**
     * GET `admin/accounts`. Renders the full accounts page, or—when the request
     * is an AJAX/partial fetch from the dashboard—just the accounts fragment.
     */
    public function adminAccounts(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminAccountsPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('accounts');
    }

    /**
     * GET `admin/family-entry`. Shows the family registration form page, or its
     * modal partial when fetched via AJAX from the dashboard.
     */
    public function adminFamilyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminFamilyPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-entry');
    }

    /**
     * GET `admin/manage-records` (and `manage-families`). Renders the family
     * records list page, or the list fragment for AJAX search/pagination.
     */
    public function adminManageRecords(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminRecordListPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-manage');
    }

    /**
     * GET `admin/audit-trails`. Renders the audit log page, or the audit fragment
     * for AJAX search/filtering.
     */
    public function adminAuditTrails(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminAuditPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('audit-trails');
    }

    /**
     * GET `admin/sectors`. Renders the sector lookup page, or the sector fragment
     * for AJAX. Mutations are posted to Lookups\SectorController.
     */
    public function adminSectors(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminSectorsPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('sectors');
    }

    /**
     * GET `admin/services`. Renders the service lookup page, or the service
     * fragment for AJAX. Mutations are posted to Lookups\ServiceController.
     */
    public function adminServices(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAdminServicesPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('services');
    }

    /**
     * GET `admin/manage-members`. Reuses the family-manage page (member-centric
     * view of the same records). Frontend: full-page load of the admin shell.
     */
    public function adminManageMembers(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-manage');
    }

    // Employee (`employee/*`) pages were moved to
    // App\Controllers\Employee\WorkspaceController. This controller now covers
    // auth/session and the admin/developer pages only.

    // ---------------------------------------------------------------------
    // AJAX partial rendering.
    // The dashboard shells load some sections (accounts, family form, audit,
    // sectors, services) into a modal/panel via fetch. When ?partial=1 or an
    // XHR header is present we return just the inner view fragment instead of
    // the whole page. Front-end loader: assets/js/dashboard/*-modal.js.
    // ---------------------------------------------------------------------

    /**
     * True when the dashboard JS is fetching just a section fragment (XHR header
     * or `?partial=1`) rather than a full page navigation.
     */
    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    /**
     * Role guard for admin partial fetches; returns a RedirectResponse to block
     * non Developer/Admin users, otherwise null to continue.
     */
    private function guardAdminPartialAccess(): ?RedirectResponse
    {
        return RoleAccess::requireRole(['Developer', 'Admin']);
    }

    /**
     * Returns just the accounts table fragment for the dashboard's AJAX loader
     * (assets/js/dashboard/*-modal.js). Guarded for Developer/Admin; renders
     * `Dashboard/Manage/accounts` with data from DashboardPageBuilder.
     */
    private function renderAdminAccountsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $currentRole = $this->normalizeRole((string) session()->get('role'));

        if (! in_array($currentRole, ['Developer', 'Admin'], true)) {
            return '<div class="alert alert-danger mb-0">Developer or Admin access is required for account management.</div>';
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('accounts');

        return view('Dashboard/Manage/accounts', [
            'adminAccounts' => $viewData['adminAccounts'] ?? [],
            'employeeAccounts' => $viewData['employeeAccounts'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
            'canCreateAccounts' => $viewData['canCreateAccounts'] ?? false,
            'currentRole' => $viewData['currentRole'] ?? '',
        ]);
    }

    /**
     * Returns the family registration form fragment for the admin "add family"
     * modal. Pulls dropdown options from FamilyFormOptionsModel and renders
     * `Dashboard/familyform/familyform` in embedded mode.
     */
    private function renderAdminFamilyPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Dashboard/familyform/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }

    /**
     * Returns the family records list fragment (table rows) for the admin
     * manage-records AJAX search/pagination. Renders
     * `Dashboard/familyform/family-list` with data from DashboardPageBuilder.
     */
    private function renderAdminRecordListPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view(
            'Dashboard/familyform/family-list',
            (new DashboardPageBuilder($this->request))->buildAdminRecordListViewData()
        );
    }

    /**
     * Returns the audit-trail list fragment for the admin audit AJAX
     * search/filter. Renders `Dashboard/Manage/audit-trails`.
     */
    private function renderAdminAuditPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('audit-trails');

        return view('Dashboard/Manage/audit-trails', [
            'recentAudits' => $viewData['recentAudits'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
            'auditActionOptions' => $viewData['auditActionOptions'] ?? [],
        ]);
    }

    /**
     * Returns the sectors lookup fragment for the admin sectors AJAX view.
     * Renders `Dashboard/Sectors and Services/sector`.
     */
    private function renderAdminSectorsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('sectors');

        return view('Dashboard/Sectors and Services/sector', [
            'sectors' => $viewData['sectors'] ?? [],
            'sectorShortcodeOptions' => $viewData['sectorShortcodeOptions'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }

    /**
     * Returns the services lookup fragment for the admin services AJAX view.
     * Renders `Dashboard/Sectors and Services/services`.
     */
    private function renderAdminServicesPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('services');

        return view('Dashboard/Sectors and Services/services', [
            'services' => $viewData['services'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }

}