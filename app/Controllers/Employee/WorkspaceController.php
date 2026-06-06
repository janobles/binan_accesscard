<?php

namespace App\Controllers\Employee;

use App\Controllers\BaseController;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles the employee workspace pages (the `employee/*` routes), mirroring how
 * the Admin controllers own the admin-only screens. Page rendering is delegated
 * to App\Libraries\DashboardPageBuilder::renderEmployeePage(); this controller
 * only decides which tab to show and serves the AJAX fragments.
 *
 * The Employee role is stored in the DB as the legacy enum value 'User' but is
 * referred to as 'Employee' throughout the app (see RoleAccess::normalizeRole).
 */
class WorkspaceController extends BaseController
{
    /**
     * GET `employee/workspace`. Renders the employee shell on the dashboard tab.
     * Frontend: full-page load of `Views/Employee/layout`.
     */
    public function dashboard(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderEmployeePage('dashboard');
    }

    /**
     * GET `employee/family-entry`. Shows the employee family registration form,
     * or its modal partial for AJAX fetches.
     */
    public function familyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderFamilyPartial();
        }

        return $this->pageBuilder()->renderEmployeePage('family-entry');
    }

    /**
     * GET `employee/manage-records` (and `manage-families`). Renders the employee
     * records list page, or the list fragment for AJAX search/pagination.
     */
    public function manageRecords(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderRecordListPartial();
        }

        return $this->pageBuilder()->renderEmployeePage('family-manage');
    }

    /**
     * GET `employee/activity`. Renders the employee's recent-activity tab.
     */
    public function activity(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderEmployeePage('activity');
    }

    /** Builds a page builder bound to the current request. */
    private function pageBuilder(): DashboardPageBuilder
    {
        return new DashboardPageBuilder($this->request);
    }

    /**
     * True when the dashboard JS is fetching just a section fragment (XHR header
     * or `?partial=1`) rather than a full page navigation.
     */
    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    /**
     * Returns the family registration form fragment for the employee "add family"
     * modal. Allows Developer/Admin/Employee; renders
     * `Family/form` in embedded mode.
     */
    private function renderFamilyPartial(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'Employee']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Family/form', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }

    /**
     * Returns the records list fragment for employee manage-records AJAX
     * search/pagination. Renders `Family/list`.
     */
    private function renderRecordListPartial(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'Employee']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view(
            'Family/list',
            $this->pageBuilder()->buildEmployeeRecordListViewData()
        );
    }
}
