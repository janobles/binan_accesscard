<?php

namespace App\Filters;

use App\Support\SessionAuditLogger;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\IdleTimeout;

/**
 * Logs out inactive users before protected routes continue.
 */
class IdleTimeoutFilter implements FilterInterface
{
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

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

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
