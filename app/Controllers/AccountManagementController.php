<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;

class AccountManagementController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/accountmanagement');
        }

        return view('Admin/dashboard', [
            'activePage' => 'accounts',
            'pageTitle' => 'Account Management',
            'workspaceUrl' => site_url('admin/accounts'),
        ]);
    }
}
