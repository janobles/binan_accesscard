<?php

namespace App\Controllers\Families;

use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Request-context helpers shared by the Families controllers: family record
 * route bases, access guards, and modal/JSON error fragments. Relies on
 * BaseController's $this->request / $this->response.
 */
trait FamilyRequestContext
{
    /** Route base (`records`) for family record sub-actions (update, import, qr-check, ...). */
    private function currentRouteBase(): string
    {
        return 'records';
    }

    /** The Manage Records landing page for the request. */
    private function recordsUrl(): string
    {
        return site_url('records');
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
     * Records a SYSTEM_ERROR audit row for an unexpected failure during a family
     * action, so it surfaces on the audit page (visible to admins). Best-effort -
     * a failure here must never mask the original error.
     */
    private function auditSystemError(string $context, Throwable $exception): void
    {
        try {
            $auditModel = new AuditTrailsModel();

            if (! $auditModel->hasTable()) {
                return;
            }

            $auditModel->logAction(
                (int) session()->get('user_id'),
                null,
                'SYSTEM_ERROR',
                'System error during ' . $context . '.',
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString(),
                $exception->getMessage()
            );
        } catch (Throwable $ignored) {
            log_message('error', 'Audit SYSTEM_ERROR skipped: ' . $ignored->getMessage());
        }
    }

    /**
     * Access guard for family entry: allows Developer/Admin/Encoder, otherwise
     * returns a redirect. store() converts this to a 403 JSON for AJAX requests.
     */
    private function requireFamilyEntryAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if (in_array($role, ['Developer', 'Admin', 'Encoder'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to add family records.');
    }

    /**
     * Access guard for the Family Profile page (profile()). Same as
     * requireFamilyEntryAccess but also permits the Viewer role - viewers may look
     * at a record (its controls render disabled, with no Save) but never reach
     * the update/archive/restore actions, which keep the stricter
     * requireFamilyEntryAccess guard.
     */
    private function requireFamilyViewAccess(): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));

        if (in_array($role, ['Developer', 'Admin', 'Encoder', 'Viewer'], true)) {
            return null;
        }

        return redirect()->back()->with('error', 'You do not have permission to view family records.');
    }
}
