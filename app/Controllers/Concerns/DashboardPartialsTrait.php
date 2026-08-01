<?php

namespace App\Controllers\Concerns;

use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Shared AJAX-partial helper for the dashboard controllers
 * (Admin\DashboardController, Admin\DistributionController): the "full page vs.
 * fragment" test their page actions branch on.
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

}
