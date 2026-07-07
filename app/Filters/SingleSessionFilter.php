<?php

namespace App\Filters;

use App\Libraries\ActiveSessionRegistry;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAuditLogger;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Enforces one active session per account. Registered AFTER IdleTimeoutFilter on the
 * protected routes (so an already-expired session is cleared first): if this
 * session's auth token no longer matches the account's registered active token —
 * because the user confirmed a login elsewhere — it audits and clears this session,
 * then returns a 401 JSON for AJAX or redirects normal requests to login.
 */
class SingleSessionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            return null;
        }

        $token = (string) $session->get('auth_token');

        // Only sessions established through our login flow carry an auth_token; a
        // logged-in session without one predates this guard (or is a test/manual
        // session), so there is nothing to enforce until it next logs in.
        if ($token === '') {
            return null;
        }

        $identity = ActiveSessionRegistry::identityKey(
            (int) $session->get('user_id'),
            (string) $session->get('username')
        );
        $record = ActiveSessionRegistry::get($identity);

        // We are the account's active session (or the registry entry was pruned/lost
        // while our session is still valid): (re)register and refresh the heartbeat.
        if ($record === null) {
            ActiveSessionRegistry::put($identity, $token, (string) $session->get('username'), $request);

            return null;
        }

        if (($record['token'] ?? '') === $token) {
            ActiveSessionRegistry::touch($identity, $token);

            return null;
        }

        // A newer login elsewhere took over this account: this session is superseded.
        SessionAuditLogger::logLogoutFromSession($request);
        RoleAccess::forgetLoginSession();

        $message = 'You were logged out because your account was signed in on another device.';

        // For the keep-alive/AJAX poll, hand the frontend an explicit redirect to the
        // login page (with the reason flashed) so session-timeout.js does NOT fall
        // through to its default logout?timeout=1 path — that would wrongly show the
        // "due to inactivity" message for a displaced-login logout.
        if ($request->isAJAX()) {
            session()->setFlashdata('error', $message);

            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'   => 'error',
                    'message'  => $message,
                    'redirect' => site_url('/'),
                ]);
        }

        return redirect()->to(site_url('/'))->with('error', $message);
    }

    /** No post-response work; required by FilterInterface. */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
