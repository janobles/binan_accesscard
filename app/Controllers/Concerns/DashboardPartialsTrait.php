<?php

namespace App\Controllers\Concerns;

<<<<<<< HEAD
/**
 * Shared AJAX-partial helpers for the role-specific dashboard controllers
 * (Admin\DashboardController and Employee\DashboardController).
=======
use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Shared AJAX-partial helpers for the role-specific dashboard controllers
 * (Admin\DashboardController and Employee\DashboardController). Both expose the
 * same "full page vs. fragment" pattern.
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a
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
<<<<<<< HEAD
=======

>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a
}
