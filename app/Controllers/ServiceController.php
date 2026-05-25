<?php

namespace App\Controllers;

use App\Libraries\RecordArchiver;
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
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if (! RecordArchiver::archive('services', 'serviceID', $serviceId)) {
            return redirect()->to(site_url('admin/services'))->with('error', 'Unable to delete service.');
        }

        return redirect()->to(site_url('admin/services'))->with('success', 'Service deleted successfully.');
    }

    private function saveService(?int $serviceId = null): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();

        if (! $model->hasTable()) {
            return redirect()->to(site_url('admin/services'))->with('error', 'Services table is not available.');
        }

        $data = [
            'category' => trim((string) $this->request->getPost('category')),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['category'] === '' || $data['name'] === '') {
            return redirect()->to(site_url('admin/services'))->with('error', 'Category and name are required.');
        }

        $isUpdate = $serviceId !== null;
        $isUpdate ? $model->update($serviceId, $data) : $model->insert($data);
        $message = $isUpdate ? 'Service updated successfully.' : 'Service added successfully.';

        return redirect()->to(site_url('admin/services'))->with('success', $message);
    }
}
