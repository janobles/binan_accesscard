<?php

namespace App\Controllers\Families;

use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Request-context helpers shared by the Families controllers: admin/employee
 * route detection, access guards, and modal/JSON error fragments. Relies on
 * BaseController's $this->request / $this->response.
 */
trait FamilyRequestContext
{
    /** True when the current request is under the `employee/` route group. */
    private function isEmployeeContext(): bool
    {
        // uri_string() returns the path relative to baseURL (e.g. "employee/manage-family/
        // update/5"). Using the URI's getPath() here would include the subfolder the app
        // is installed in (e.g. "/binan_accesscard/employee/..."), so the str_starts_with
        // check would fail and an encoder's save would redirect to the admin-only
        // manage-records page ("You do not have access to that page.").
        return str_starts_with(uri_string(), 'employee/');
    }

    /** Route base (`admin/manage-family` or `employee/manage-family`) for the request. */
    private function currentRouteBase(): string
    {
        return $this->isEmployeeContext() ? 'employee/manage-family' : 'admin/manage-family';
    }

    /**
     * For a partial (modal) request whose access guard failed, returns an inline
     * alert fragment so the modal shows the reason; otherwise returns the redirect.
     */
    private function partialGuard(RedirectResponse $guard, string $message): string|RedirectResponse
    {
        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return '<div class="alert alert-danger mb-0">' . esc($message) . '</div>';
        }

        return $guard;
    }

    /** Inline alert fragment shown in the modal when a family record can't be found. */
    private function recordMissing(): string
    {
        return '<div class="alert alert-warning mb-0">That family record could not be found. It may have been removed.</div>';
    }

    /**
     * JSON error body (with a fresh CSRF hash) used by the AJAX update responses.
     * Optional $code adds a machine-readable tag (e.g. 'FORM_TRUNCATED') the
     * frontend can branch on instead of matching the human message text.
     */
    private function jsonError(string $message, int $statusCode, ?string $code = null)
    {
        $body = [
            'status' => 'error',
            'message' => $message,
            'csrf' => csrf_hash(),
        ];

        if ($code !== null) {
            $body['code'] = $code;
        }

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON($body);
    }

    /**
     * Access guard for family entry: allows Developer/Admin/User, otherwise
     * returns a redirect. store() converts this to a 403 JSON for AJAX requests.
     */
    private function requireFamilyEntryAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if (in_array($role, ['Developer', 'Admin', 'Employee'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to add family records.');
    }

    /**
     * Access guard for the READ-ONLY family detail fragment (viewFamily). Same as
     * requireFamilyEntryAccess but also permits the Viewer role — viewers may look
     * at a record but never reach the edit/update/archive/restore actions, which
     * keep the stricter requireFamilyEntryAccess guard.
     */
    private function requireFamilyViewAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if (in_array($role, ['Developer', 'Admin', 'Employee', 'Viewer'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to view family records.');
    }
}
