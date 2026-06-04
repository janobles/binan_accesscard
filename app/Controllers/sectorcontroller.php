<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;

class SectorController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $keyword = trim((string) $this->request->getGet('q'));
        $status = strtolower(trim((string) $this->request->getGet('status'))) === 'archived'
            ? 'archived'
            : 'active';
        $sectorModel = new SectorModel();
        $sectors = $sectorModel->getByArchiveStatus($status === 'archived');

        if ($keyword !== '') {
            $sectors = array_values(array_filter($sectors, static function (array $sector) use ($keyword): bool {
                $haystack = strtolower(
                    (string) ($sector['shortcode'] ?? '') . ' ' .
                    (string) ($sector['name'] ?? '') . ' ' .
                    (string) ($sector['description'] ?? '')
                );

                return str_contains($haystack, strtolower($keyword));
            }));
        }

        $viewData = [
            'keyword' => $keyword,
            'status' => $status,
            'activeUrl' => site_url('admin/sectors?' . http_build_query(['status' => 'active'])),
            'archivedUrl' => site_url('admin/sectors?' . http_build_query(['status' => 'archived'])),
            'sectors' => $sectors,
        ];

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/sector', $viewData);
        }

        return view('Admin/dashboard', [
            'activePage' => 'sectors',
            'pageTitle' => 'Sector Management',
            'workspaceUrl' => site_url('admin/sectors'),
        ]);
    }
}
