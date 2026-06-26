<?php

namespace App\Controllers\Concerns;

use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Shared mutation helpers for the Lookups controllers (Sector, Service, Category):
 * the Developer/Admin role guard, the typed flash-redirect, and the member-less
 * audit writer. Each Lookups controller posts to a different admin path, so
 * redirectAdmin() takes the path; lookup audit rows have no affected member
 * (audit_trails.memberID is nullable). Hosting controllers extend BaseController,
 * so $this->request is available.
 */
trait LookupControllerTrait
{
    /**
     * Role guard for mutations: returns a redirect for non Developer/Admin users,
     * or null to proceed.
     */
    private function ensureAdminAccess(): ?RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        return $guard instanceof RedirectResponse ? $guard : null;
    }

    /**
     * Builds a redirect back to an admin path carrying a typed flash message
     * (e.g. 'success'/'error').
     */
    private function redirectAdmin(string $path, string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url($path))->with($type, $message);
    }

    /**
     * Write a lookup action to the audit trail. Lookup actions have no affected
     * member, so memberID is null (audit_trails.memberID is nullable).
     */
    private function audit(string $action, string $description): void
    {
        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            $auditModel->logAction(
                (int) session()->get('user_id'),
                null,
                $action,
                $description,
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (Throwable $exception) {
            log_message('error', 'Audit trail skipped: ' . $exception->getMessage());
        }
    }
}
