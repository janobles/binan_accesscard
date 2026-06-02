<?php

namespace App\Filters;

use App\Libraries\SessionAuditLogger;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\IdleTimeout;

/**
 * Logs out inactive users before protected routes continue.
 */
class IdleTimeoutFilter implements FilterInterface
{
    /**
     * Runs before the protected routes listed in Config\Filters (admin/*, employee/*,
     * etc.). If the logged-in user has been idle past IdleTimeout, it audits and
     * clears the session, then returns a 401 JSON for AJAX or a redirect to login
     * for normal requests; otherwise it refreshes the idle timer and continues.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            return null;
        }

        $timeoutSeconds = (new IdleTimeout())->seconds;
        $lastActivity = (int) ($session->get('idle_last_activity') ?? time());

        if ((time() - $lastActivity) >= $timeoutSeconds) {
            SessionAuditLogger::logLogoutFromSession($request, true);
            $this->clearLoginSession();

            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'You were logged out due to inactivity.',
                    ]);
            }

            return redirect()->to(site_url('/'))
                ->with('error', 'You were logged out due to inactivity.');
        }

        $session->set('idle_last_activity', time());

        return null;
    }

    /** No post-response work; required by FilterInterface. */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /** Removes auth session keys when a request is rejected for inactivity. */
    private function clearLoginSession(): void
    {
        session()->remove([
            'is_logged_in',
            'user_id',
            'member_id',
            'username',
            'role',
            'idle_last_activity',
        ]);
    }
}
