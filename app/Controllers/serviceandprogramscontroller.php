<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\ServicesModel;
use CodeIgniter\HTTP\RedirectResponse;

class ServiceAndProgramsController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $keyword = trim((string) $this->request->getGet('q'));
        $searchScope = strtolower(trim((string) $this->request->getGet('search_scope'))) === 'all'
            ? 'all'
            : 'services';
        $status = strtolower(trim((string) $this->request->getGet('status'))) === 'archived'
            ? 'archived'
            : 'active';
        $servicesModel = new ServicesModel();
        $services = array_values(array_filter(
            $servicesModel->getAllIncluding(),
            static fn (array $service): bool => $status === 'archived'
                ? ! empty($service['dt_deleted'])
                : empty($service['dt_deleted'])
        ));

        if ($keyword !== '') {
            $services = array_values(array_filter($services, static function (array $service) use ($keyword): bool {
                $haystack = strtolower(
                    (string) ($service['category'] ?? '') . ' ' .
                    (string) ($service['name'] ?? '') . ' ' .
                    (string) ($service['description'] ?? '')
                );

                return str_contains($haystack, strtolower($keyword));
            }));
        }

        $viewData = [
            'keyword' => $keyword,
            'searchScope' => $searchScope,
            'status' => $status,
            'activeUrl' => site_url('admin/services?' . http_build_query(['status' => 'active'])),
            'archivedUrl' => site_url('admin/services?' . http_build_query(['status' => 'archived'])),
            'services' => $services,
        ];

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/serviceandprogram', $viewData);
        }

        return view('Admin/dashboard', [
            'activePage' => 'services',
            'pageTitle' => 'Services and Programs',
            'workspaceUrl' => site_url('admin/services'),
        ]);
    }
}
