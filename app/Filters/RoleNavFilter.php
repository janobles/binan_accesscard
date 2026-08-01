<?php

namespace App\Filters;

use App\Libraries\RoleAccess;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Navigation;

/**
 * Gates a page on the navigation manifest. The route declares its page key
 * ('roleNav:records') and this filter answers whether the session's role has an
 * entry for it. A role without an entry gets a 404 rather than a redirect, so the
 * response does not confirm that a page it may not use exists.
 */
class RoleNavFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments the page key, as declared on the route
     */
    public function before(RequestInterface $request, $arguments = null): ResponseInterface|RedirectResponse|null
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('login'))->with('error', 'Please login first.');
        }

        if (! RoleAccess::sessionUserExists()) {
            RoleAccess::forgetLoginSession(true);

            return redirect()->to(site_url('login'))
                ->with('error', 'Your session is no longer valid. Please login again.');
        }

        $role = RoleAccess::normalizeRole((string) session()->get('role'));
        $key  = (string) ($arguments[0] ?? '');

        if ($role === null) {
            RoleAccess::forgetLoginSession(true);

            return redirect()->to(site_url('login'))
                ->with('error', 'Your account role is invalid. Please contact an administrator.');
        }

        if (! in_array($role, Navigation::pageRoles($key), true)) {
            return service('response')->setStatusCode(404);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
