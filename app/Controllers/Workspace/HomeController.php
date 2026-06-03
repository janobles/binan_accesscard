<?php

namespace App\Controllers\Workspace;

use App\Controllers\BaseController;
use App\Controllers\HomeRoleAccessTrait;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Renders the admin/developer dashboard pages and their AJAX partials.
 *
 * Page rendering is delegated to App\Libraries\DashboardPageBuilder: this
 * controller only decides WHICH page to show, the builder assembles the view data
 * and returns the rendered HTML. Authentication and session lifecycle live in
 * App\Controllers\Auth\AuthController (the controller the auth routes target);
 * the `normalizeRole` helper used by the account partial comes from
 * App\Controllers\HomeRoleAccessTrait.
 */
class HomeController extends BaseController
{
    use HomeRoleAccessTrait;

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
     * Renders `Dashboard/sectors-services/sector`.
     */
    private function renderAdminSectorsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('sectors');

        return view('Dashboard/sectors-services/sector', [
            'sectors' => $viewData['sectors'] ?? [],
            'sectorShortcodeOptions' => $viewData['sectorShortcodeOptions'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }

    /**
     * Returns the services lookup fragment for the admin services AJAX view.
     * Renders `Dashboard/sectors-services/services`.
     */
    private function renderAdminServicesPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('services');

        return view('Dashboard/sectors-services/services', [
            'services' => $viewData['services'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }

}