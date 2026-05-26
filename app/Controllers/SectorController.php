<?php

namespace App\Controllers;

use App\Controllers\Concerns\AdminCrudSupportTrait;
use App\Controllers\Concerns\HomeRoleAccessTrait;
use App\Models\SectorModel;
use App\Support\SectorIds;
use CodeIgniter\HTTP\RedirectResponse;

class SectorController extends BaseController
{
    use AdminCrudSupportTrait;
    use HomeRoleAccessTrait;

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
        return $this->delete($sectorId);
    }

    public function delete(int $sectorId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Sector table is not available.');
        }

        if ($this->sectorIsUsed($sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'This sector is already used by one or more records and cannot be deleted.');
        }

        if (! $model->delete($sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Unable to delete sector.');
        }

        return $this->redirectAdmin('admin/sectors', 'success', 'Sector deleted successfully.');
    }

    private function saveSector(?int $sectorId = null): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Sector table is not available.');
        }

        $shortcode = trim((string) $this->request->getPost('shortcode'));

        if ($shortcode === '__other__') {
            $shortcode = trim((string) $this->request->getPost('shortcode_other'));
        }

        $data = [
            'shortcode' => strtoupper($shortcode),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['shortcode'] === '' || $data['name'] === '') {
            return $this->redirectAdmin('admin/sectors', 'error', 'Shortcode and name are required.');
        }

        $isUpdate = $sectorId !== null;
        $isUpdate ? $model->update($sectorId, $data) : $model->insert($data);
        $message = $isUpdate ? 'Sector updated successfully.' : 'Sector added successfully.';

        return $this->redirectAdmin('admin/sectors', 'success', $message);
    }

    private function sectorIsUsed(int $sectorId): bool
    {
        $db = db_connect();

        if (! $db->tableExists('member')) {
            return false;
        }

        return $db->table('member')
            ->where(SectorIds::containsCondition($sectorId, 'sectorID'), null, false)
            ->countAllResults() > 0;
    }
}
