<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

class ServiceController extends BaseController
{
    public function create(): RedirectResponse
    {
        return $this->saveService();
    }

    public function update(int $serviceId): RedirectResponse
    {
        return $this->saveService($serviceId);
    }

    public function archive(int $serviceId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/services', 'error', 'Services table is not available.');
        }

        if ($this->serviceIsUsed($serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'This service or program is assigned to one or more records and cannot be archived. Reassign them first.');
        }

        if (! $model->archive($serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'Unable to archive service.');
        }

        return $this->redirectAdmin('admin/services', 'success', 'Service or program archived successfully.');
    }

    public function delete(int $serviceId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/services', 'error', 'Services table is not available.');
        }

        if ($this->serviceIsUsed($serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'This service or program is already used by one or more records and cannot be deleted.');
        }

        if (! $model->delete($serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'Unable to delete service.');
        }

        return $this->redirectAdmin('admin/services', 'success', 'Service or program deleted successfully.');
    }

    private function saveService(?int $serviceId = null): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/services', 'error', 'Services table is not available.');
        }

        $category = trim((string) $this->request->getPost('category'));

        if ($category === '__other__') {
            $category = trim((string) $this->request->getPost('category_other'));
        }

        $data = [
            'category' => $category,
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['category'] === '' || $data['name'] === '') {
            return $this->redirectAdmin('admin/services', 'error', 'Category and name are required.');
        }

        $isUpdate = $serviceId !== null;

        if ($isUpdate) {
            $model->update($serviceId, $data);
        } else {
            $data['serviceID'] = $model->nextServiceId();
            $model->insert($data);
        }

        $message = $isUpdate ? 'Service updated successfully.' : 'Service added successfully.';

        return $this->redirectAdmin('admin/services', 'success', $message);
    }

    private function serviceIsUsed(int $serviceId): bool
    {
        $db = db_connect();

        if (! $db->tableExists('member_services')) {
            return false;
        }

        return $db->table('member_services')
            ->where('serviceID', $serviceId)
            ->countAllResults() > 0;
    }

    private function ensureAdminAccess(): ?RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        return $guard instanceof RedirectResponse ? $guard : null;
    }

    private function redirectAdmin(string $path, string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url($path))->with($type, $message);
    }
}
