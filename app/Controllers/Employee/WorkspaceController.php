<?php

namespace App\Controllers\Employee;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Employee\WorkspaceModel;
use App\Models\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles employee workspace pages and modal partials.
 */
class WorkspaceController extends BaseController
{
    public function dashboard(): string|RedirectResponse
    {
        return $this->renderPage('dashboard');
    }

    public function familyEntry(): string|RedirectResponse
    {
        if ($this->isPartialRequest()) {
            return $this->renderFamilyPartial();
        }

        return $this->renderPage('family-entry');
    }

    public function manageRecords(): string|RedirectResponse
    {
        return $this->renderPage('family-manage');
    }

    public function activity(): string|RedirectResponse
    {
        return $this->renderPage('activity');
    }

    private function renderPage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $view = match ($activePage) {
            'family-manage' => 'manage-records',
            default => $activePage,
        };

        return view('Employee/' . $view, (new WorkspaceModel($this->request))->pageData($activePage));
    }

    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    private function renderFamilyPartial(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin', 'User']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Dashboard/familyform/familyform', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }
}
