<?php

namespace App\Controllers\Viewer;

use App\Controllers\BaseController;
use App\Controllers\Concerns\DashboardPartialsTrait;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles the read-only Viewer workspace (the `viewer/*` routes); the sibling
 * Admin\DashboardController and Employee\DashboardController own the editable
 * dashboards. A Viewer may look at family records, sectors and services but can
 * never add, edit, archive, restore, or delete — those endpoints live under the
 * admin/employee route groups and reject the Viewer role.
 *
 * Page rendering is delegated to DashboardPageBuilder::renderViewerPage(); this
 * controller only decides which tab to show and serves the records list fragment
 * for AJAX search/pagination (shared helpers come from DashboardPartialsTrait).
 */
class DashboardController extends BaseController
{
    use DashboardPartialsTrait;

    /** Entry point for GET `viewer`: redirects to the canonical viewer dashboard URL. */
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('viewer/dashboard'));
    }

    /**
     * GET `viewer/dashboard`. Renders the viewer shell on the read-only overview
     * tab (stats + recently added records).
     */
    public function dashboard(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderViewerPage('dashboard');
    }

    /**
     * GET `viewer/manage-records`. Renders the read-only records list page, or
     * the list fragment for AJAX search/pagination.
     */
    public function manageRecords(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderRecordListPartial();
        }

        return $this->pageBuilder()->renderViewerPage('family-manage');
    }

    /** GET `viewer/reference-data`. Read-only Sectors and Services lists, switched by ?tab=. */
    public function referenceData(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderViewerPage('reference-data');
    }

    /** Builds a page builder bound to the current request. */
    private function pageBuilder(): DashboardPageBuilder
    {
        return new DashboardPageBuilder($this->request);
    }

    /**
     * Returns the read-only records list fragment for the viewer manage-records
     * AJAX search/pagination. Renders `Family/list` with view-only flags.
     */
    private function renderRecordListPartial(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Viewer']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view(
            'Family/list',
            $this->pageBuilder()->buildViewerRecordListViewData()
        );
    }
}
