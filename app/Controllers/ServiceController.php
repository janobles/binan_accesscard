<?php

namespace App\Controllers;

use App\Controllers\Concerns\AdminCrudSupportTrait;
use App\Controllers\Concerns\HomeRoleAccessTrait;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

class ServiceController extends BaseController
{
    use AdminCrudSupportTrait;
    use HomeRoleAccessTrait;

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

        if (! $this->archiveRecord('services', 'serviceID', $serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'Unable to delete service.');
        }

        return $this->redirectAdmin('admin/services', 'success', 'Service deleted successfully.');
    }

    private function saveService(?int $serviceId = null): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/services', 'error', 'Services table is not available.');
        }

        $data = [
            'category' => trim((string) $this->request->getPost('category')),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['category'] === '' || $data['name'] === '') {
            return $this->redirectAdmin('admin/services', 'error', 'Category and name are required.');
        }

        $isUpdate = $serviceId !== null;
        $isUpdate ? $model->update($serviceId, $data) : $model->insert($data);
        $message = $isUpdate ? 'Service updated successfully.' : 'Service added successfully.';

        return $this->redirectAdmin('admin/services', 'success', $message);
    }
}
