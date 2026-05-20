<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SessionTimeout implements FilterInterface
{
    private int $timeoutSeconds = 60;

    // Enforces a 1-minute inactivity timeout for staff sessions.
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('is_logged_in')) {
            return null;
        }

        $lastActivity = session()->get('last_activity');

        if (is_int($lastActivity) && (time() - $lastActivity) > $this->timeoutSeconds) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your session has expired due to inactivity. Please login again.');
        }

        session()->set('last_activity', time());

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
