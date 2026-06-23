<?php

namespace App\Controllers\Concerns;

/**
 * Shared AJAX-partial helpers for the role-specific dashboard controllers
 * (Admin\DashboardController and Employee\DashboardController).
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
