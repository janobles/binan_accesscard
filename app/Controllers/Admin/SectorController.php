<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Concerns\HomeRoleAccessTrait;
use App\Controllers\Concerns\LookupManagementTrait;
use App\Models\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles sector lookup management for Admin and Developer roles.
 */
class SectorController extends BaseController
{
    use HomeRoleAccessTrait;
    use LookupManagementTrait;

    public function index(): string|RedirectResponse
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('admin/lookups/index', $this->buildLookupViewData('sectors'));
    }

    public function store()
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'shortcode' => 'required|max_length[20]|regex_match[/^[A-Z0-9]+$/]',
            'name' => 'required|max_length[255]',
            'description' => 'required',
        ];

        if (! $this->validate($rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'errors' => $this->validator->getErrors(),
                    'csrf' => csrf_hash(),
                ]);
        }

        $model = new SectorModel();
        $shortcode = strtoupper(trim((string) $this->request->getPost('shortcode')));

        if ($model->shortcodeExists($shortcode)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'errors' => ['shortcode' => 'Shortcode already exists.'],
                    'csrf' => csrf_hash(),
                ]);
        }

        $data = [
            'shortcode' => $shortcode,
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        $id = $model->create($data);

        $this->logLookupAction(
            'SECTOR_CREATE',
            'Sector ' . $shortcode . ' (' . $data['name'] . ') created.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Sector created successfully.',
            'sector' => array_merge(['sectorID' => $id], $data),
            'csrf' => csrf_hash(),
        ]);
    }

    public function update(int $id)
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'shortcode' => 'required|max_length[20]|regex_match[/^[A-Z0-9]+$/]',
            'name' => 'required|max_length[255]',
            'description' => 'required',
        ];

        if (! $this->validate($rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'errors' => $this->validator->getErrors(),
                    'csrf' => csrf_hash(),
                ]);
        }

        $model = new SectorModel();
        $shortcode = strtoupper(trim((string) $this->request->getPost('shortcode')));

        if ($model->shortcodeExists($shortcode, $id)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'errors' => ['shortcode' => 'Shortcode already exists.'],
                    'csrf' => csrf_hash(),
                ]);
        }

        $data = [
            'shortcode' => $shortcode,
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if (! $model->update($id, $data)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unable to update sector.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $this->logLookupAction(
            'SECTOR_UPDATE',
            'Sector ' . $shortcode . ' (' . $data['name'] . ') updated.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Sector updated successfully.',
            'sector' => array_merge(['sectorID' => $id], $data),
            'csrf' => csrf_hash(),
        ]);
    }

    public function archive(int $id)
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();
        $sector = $model->find($id);

        if ($sector === null || ! empty($sector['dt_deleted'])) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => 'Sector not found or already archived.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $memberCount = $this->countActiveMembersForSector($id);

        if ($memberCount > 0) {
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    'success' => false,
                    'message' => 'Cannot archive: ' . $memberCount . ' member record(s) are assigned to this sector. Reassign them first.',
                    'csrf' => csrf_hash(),
                ]);
        }

        if (! $model->archive($id)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unable to archive sector.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $this->logLookupAction(
            'SECTOR_ARCHIVE',
            'Sector ' . ($sector['shortcode'] ?? '') . ' (' . ($sector['name'] ?? '') . ') archived.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Sector archived successfully.',
            'csrf' => csrf_hash(),
        ]);
    }

    public function restore(int $id)
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();
        $sector = $model->find($id);

        if ($sector === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => 'Sector not found.',
                    'csrf' => csrf_hash(),
                ]);
        }

        if (empty($sector['dt_deleted'])) {
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    'success' => false,
                    'message' => 'Sector is already active.',
                    'csrf' => csrf_hash(),
                ]);
        }

        if (! $model->restore($id)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unable to restore sector.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $this->logLookupAction(
            'SECTOR_RESTORE',
            'Sector ' . ($sector['shortcode'] ?? '') . ' (' . ($sector['name'] ?? '') . ') restored.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Sector restored successfully.',
            'csrf' => csrf_hash(),
        ]);
    }
}
