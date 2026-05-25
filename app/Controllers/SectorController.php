<?php

namespace App\Controllers;

use App\Libraries\RecordArchiver;
use App\Libraries\RoleAccess;
use App\Models\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;

class SectorController extends BaseController
{
    public function create(): RedirectResponse
    {
        return $this->saveSector();
    }

    public function update(int $sectorId): RedirectResponse
    {
        return $this->saveSector($sectorId);
    }

    public function archive(int $sectorId): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if (! RecordArchiver::archive('sector', 'sectorID', $sectorId)) {
            return redirect()->to(site_url('admin/sectors'))->with('error', 'Unable to delete sector.');
        }

        return redirect()->to(site_url('admin/sectors'))->with('success', 'Sector deleted successfully.');
    }

    private function saveSector(?int $sectorId = null): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return redirect()->to(site_url('admin/sectors'))->with('error', 'Sector table is not available.');
        }

        $data = [
            'shortcode' => strtoupper(trim((string) $this->request->getPost('shortcode'))),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['shortcode'] === '' || $data['name'] === '') {
            return redirect()->to(site_url('admin/sectors'))->with('error', 'Shortcode and name are required.');
        }

        $isUpdate = $sectorId !== null;
        $isUpdate ? $model->update($sectorId, $data) : $model->insert($data);
        $message = $isUpdate ? 'Sector updated successfully.' : 'Sector added successfully.';

        return redirect()->to(site_url('admin/sectors'))->with('success', $message);
    }
}
