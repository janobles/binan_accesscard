<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Concerns\DashboardPartialsTrait;
use App\Controllers\HomeRoleAccessTrait;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Renders the admin/developer dashboard pages and their AJAX partials (the
 * `admin/*` routes). The sibling Employee\DashboardController owns the
 * `employee/*` pages.
 *
 * Page rendering is delegated to App\Libraries\DashboardPageBuilder: this
 * controller only decides WHICH page to show, the builder assembles the view data
 * and returns the rendered HTML. Authentication and session lifecycle live in
 * App\Controllers\Auth\AuthController (the controller the auth routes target);
 * the `normalizeRole` helper used by the account partial comes from
 * App\Controllers\HomeRoleAccessTrait.
 */
class DashboardController extends BaseController
{
    use HomeRoleAccessTrait;
    use DashboardPartialsTrait;

    // ---------------------------------------------------------------------
    // Admin / Developer pages (full page loads).
    // Each maps to a route in Config\Routes and an $activePage the admin shell
    // (Views/Admin/layout.php) switches on. Routes are guarded for
    // Developer/Admin inside DashboardPageBuilder::renderAdminPage().
    // ---------------------------------------------------------------------

    /**
     * Entry point for GET `admin`: redirects to the canonical admin dashboard URL.
     */
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('admin/dashboard'));
    }

    /**
     * GET `admin/dashboard`. Delegates to DashboardPageBuilder to assemble stats
     * and render the admin shell on the "dashboard" tab. Frontend: full-page load
     * of `Admin/layout`.
     */
    public function dashboard(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('dashboard');
    }

    /**
     * GET `admin/accounts`. Renders the full accounts page, or—when the request
     * is an AJAX/partial fetch from the dashboard—just the accounts fragment.
     */
    public function accounts(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAccountsPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('accounts');
    }

    /**
     * GET `admin/family-entry`. Legacy URL; the add/edit experience now lives in
     * the Manage Records modal.
     */
    public function familyEntry(): RedirectResponse
    {
        return redirect()->to(site_url('admin/manage-records'));
    }

    /**
     * GET `admin/manage-records` (and `manage-families`). Renders the family
     * records list page, or the list fragment for AJAX search/pagination.
     */
    public function manageRecords(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderRecordListPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-manage');
    }

    /**
     * GET `admin/audit-trails`. Renders the audit log page, or the audit fragment
     * for AJAX search/filtering.
     */
    public function auditTrails(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderAuditPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('audit-trails');
    }

    /**
     * GET `admin/reference-data`. One page for the four lookup tables
     * (Sectors, Services, Categories, Aid Types), switched by ?tab=.
     * Mutations still post to the Lookups\* and AidTypes controllers.
     */
    public function referenceData(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('reference-data');
    }

    /**
     * GET `admin/cards`. Renders the QR access-card batch page in the admin
     * shell. Generation/lookup are handled by Cards\QrCardController.
     */
    public function cards(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('cards');
    }

    /**
     * GET `admin/manage-members`. Reuses the family-manage page (member-centric
     * view of the same records). Frontend: full-page load of the admin shell.
     */
    public function manageMembers(): string|RedirectResponse
    {
        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-manage');
    }

    // ---------------------------------------------------------------------
    // AJAX partial rendering.
    // The dashboard shell loads some sections (accounts, family form, audit,
    // sectors, services) into a modal/panel via fetch. When ?partial=1 or an
    // XHR header is present we return just the inner view fragment instead of
    // the whole page. Front-end loader: assets/js/dashboard/*-modal.js.
    // isPartialRequest() comes from DashboardPartialsTrait.
    // ---------------------------------------------------------------------

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
     * `Admin/accounts` with data from DashboardPageBuilder.
     */
    private function renderAccountsPartial(): string|RedirectResponse
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

        return view('Admin/accounts', [
            'adminAccounts' => $viewData['adminAccounts'] ?? [],
            'employeeAccounts' => $viewData['employeeAccounts'] ?? [],
            'viewerAccounts' => $viewData['viewerAccounts'] ?? [],
            'scannerAccounts' => $viewData['scannerAccounts'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
            'canCreateAccounts' => $viewData['canCreateAccounts'] ?? false,
            'canEditAccounts' => $viewData['canEditAccounts'] ?? false,
            'currentRole' => $viewData['currentRole'] ?? '',
        ]);
    }

    /**
     * Returns the family records list fragment (table rows) for the admin
     * manage-records AJAX search/pagination. Renders
     * `Family/list` with data from DashboardPageBuilder.
     */
    private function renderRecordListPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view(
            'Family/list',
            (new DashboardPageBuilder($this->request))->buildAdminRecordListViewData()
        );
    }

    /**
     * Returns the audit-trail list fragment for the admin audit AJAX
     * search/filter. Renders `Admin/audit-trails`.
     */
    private function renderAuditPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('audit-trails');

        return view('Admin/audit-trails', [
            'recentAudits' => $viewData['recentAudits'] ?? [],
            'searchTerm' => $viewData['searchTerm'] ?? '',
            'searchFilters' => $viewData['searchFilters'] ?? [],
            'auditActionOptions' => $viewData['auditActionOptions'] ?? [],
            'auditListData' => $viewData['auditListData'] ?? [],
        ]);
    }

}
