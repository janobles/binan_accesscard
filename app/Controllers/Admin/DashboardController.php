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
 * App\Controllers\HomeRoleAccessTrait. The shared AJAX-partial helpers come from
 * App\Controllers\Concerns\DashboardPartialsTrait.
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
     * GET `admin/family-entry`. Shows the family registration form page, or its
     * modal partial when fetched via AJAX from the dashboard.
     */
    public function familyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderFamilyFormPartial(['Developer', 'Admin']);
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('family-entry');
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
     * GET `admin/sectors`. Renders the sector lookup page, or the sector fragment
     * for AJAX. Mutations are posted to Lookups\SectorController.
     */
    public function sectors(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderSectorsPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('sectors');
    }

    /**
     * GET `admin/services`. Renders the service lookup page, or the service
     * fragment for AJAX. Mutations are posted to Lookups\ServiceController.
     */
    public function services(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderServicesPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('services');
    }

    /**
     * GET `admin/categories`. Renders the sector-category lookup page, or the
     * category fragment for AJAX. Mutations are posted to Lookups\CategoryController.
     */
    public function categories(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderCategoriesPartial();
        }

        return (new DashboardPageBuilder($this->request))->renderAdminPage('categories');
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
    // isPartialRequest() and renderFamilyFormPartial() come from
    // DashboardPartialsTrait.
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

    /**
     * Returns the sectors lookup fragment for the admin sectors AJAX view.
     * Renders `Lookups/sectors`.
     */
    private function renderSectorsPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('sectors');

        return view('Lookups/sectors', [
            'sectors' => $viewData['sectors'] ?? [],
            'sectorShortcodeOptions' => $viewData['sectorShortcodeOptions'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }

    /**
     * Returns the services lookup fragment for the admin services AJAX view.
     * Renders `Lookups/services`.
     */
    private function renderServicesPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('services');

        return view('Lookups/services', [
            'services' => $viewData['services'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }

    /**
     * Returns the categories lookup fragment for the admin categories AJAX view.
     * Renders `Lookups/categories`.
     */
    private function renderCategoriesPartial(): string|RedirectResponse
    {
        $guard = $this->guardAdminPartialAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $viewData = (new DashboardPageBuilder($this->request))->buildAdminViewData('categories');

        return view('Lookups/categories', [
            'categories' => $viewData['categories'] ?? [],
            'lookupStatus' => $viewData['lookupStatus'] ?? 'active',
            'canRestore' => $viewData['canRestoreLookups'] ?? false,
        ]);
    }
}
