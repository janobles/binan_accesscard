<?php

namespace App\Controllers;

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

        if (! (new SectorModel())->archiveSector($sectorId)) {
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

        $data = $model->sectorData((array) $this->request->getPost());

        if (! $model->isValidSectorData($data)) {
            return redirect()->to(site_url('admin/sectors'))->with('error', 'Shortcode and name are required.');
        }

        $isUpdate = $sectorId !== null;

        if (! $model->saveSectorRecord($data, $sectorId)) {
            return redirect()->to(site_url('admin/sectors'))->with('error', 'Unable to save sector.');
        }

        $message = $isUpdate ? 'Sector updated successfully.' : 'Sector added successfully.';

        return redirect()->to(site_url('admin/sectors'))->with('success', $message);
    }
}
