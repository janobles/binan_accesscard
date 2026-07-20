<?php

namespace App\Controllers\Employee;

use App\Controllers\BaseController;
use App\Controllers\Concerns\DashboardPartialsTrait;
use App\Libraries\DashboardPageBuilder;
use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles the employee workspace pages (the `employee/*` routes); the sibling
 * Admin\DashboardController owns the admin-only `admin/*` pages. Page rendering is
 * delegated to App\Libraries\DashboardPageBuilder::renderEmployeePage(); this
 * controller only decides which tab to show and serves the AJAX fragments.
 *
 * The Employee role is stored in the DB as the legacy enum value 'User' but is
 * referred to as 'Employee' throughout the app (see RoleAccess::normalizeRole).
 */
class DashboardController extends BaseController
{
    use DashboardPartialsTrait;

    /**
     * GET `employee/workspace`. Renders the employee shell on the dashboard tab.
     * Frontend: full-page load of `Views/Employee/layout`.
     */
    public function dashboard(): string|RedirectResponse
    {
        return $this->pageBuilder()->renderEmployeePage('dashboard');
    }

    /**
     * GET `employee/manage-records`. Renders the employee records list page, or
     * the list fragment for AJAX search/pagination.
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
