<?php

namespace App\Controllers\Concerns;

use App\Libraries\RoleAccess;
use App\Models\Families\FamilyFormOptionsModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Shared AJAX-partial helpers for the role-specific dashboard controllers
 * (Admin\DashboardController and Employee\DashboardController). Both expose the
 * same "full page vs. fragment" pattern and the same embedded family-form modal;
 * this trait keeps that logic in one place.
 */
trait DashboardPartialsTrait
{
    /**
     * True when the dashboard JS is fetching just a section fragment (XHR header
     * or `?partial=1`) rather than a full page navigation.
     */
    private function isPartialRequest(): bool
    {
        return $this->request->isAJAX() || (string) $this->request->getGet('partial') === '1';
    }

    /**
     * Returns the family registration form fragment for the "add family" modal,
     * guarded for the given roles. Renders `Family/form` in embedded mode. Callers
     * pass their own allowed-role set (admin: Developer/Admin; employee adds
     * Employee).
     *
     * @param list<string> $allowedRoles
     */
    private function renderFamilyFormPartial(array $allowedRoles): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole($allowedRoles);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Family/form', array_merge(
            (new FamilyFormOptionsModel())->getViewData(),
            ['canCreateFamily' => true, 'embeddedInModal' => true]
        ));
    }
}
