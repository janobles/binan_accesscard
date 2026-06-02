<?php

namespace App\Libraries;

use App\Libraries\RoleAccess;
use App\Models\ViewLayoutModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;

class DashboardPageBuilder
{
    public function __construct(private IncomingRequest $request) {}

    public function renderAdminPage(string $activePage): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $currentRole = RoleAccess::normalizeRole((string) session()->get('role'));

        if ($activePage === 'accounts' && ! in_array($currentRole, ['Developer', 'Admin'], true)) {
            return redirect()->to(site_url('admin/dashboard'))
            ->with('error', 'Developer or Admin access is required for account management.');
        }

        $viewData = [
            'activePage' => $activePage,
            'pageTitle' => (new ViewLayoutModel())->pageTitle($activePage),
        ];

        return view('Admin/dashboard', $viewData);
    }

}
