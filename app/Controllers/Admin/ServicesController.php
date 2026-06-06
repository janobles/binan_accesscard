<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Concerns\LookupManagementTrait;
use App\Models\Lookups\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles service lookup management for Admin and Developer roles.
 */
class ServicesController extends BaseController
{
    use LookupManagementTrait;

    /**
     * GET `admin/lookups/services`: renders the services management screen
     * (`Admin/lookups/index` view). The store/update/archive/restore actions
     * below are called by assets/js/lookups.js and return JSON, not redirects.
     */
    public function index(): string|RedirectResponse
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Admin/lookups/index', $this->buildLookupViewData('services'));
    }

    /**
     * POST `admin/lookups/services/store`: validates and creates a service via
     * ServiceModel. Returns JSON (with a fresh CSRF hash) for lookups.js; 422 on
     * validation errors. Audits a SERVICE_CREATE on success.
     */
    public function store()
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'category' => 'required|max_length[50]',
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

        $model = new ServiceModel();
        $data = [
            'category' => trim((string) $this->request->getPost('category')),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        $id = $model->create($data);

        $this->logLookupAction(
            'SERVICE_CREATE',
            'Service ' . $data['name'] . ' (' . $data['category'] . ') created.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Service created successfully.',
            'service' => array_merge(['serviceID' => $id], $data),
            'csrf' => csrf_hash(),
        ]);
    }

    /**
     * POST `admin/lookups/services/update/{id}`: validates and updates a service.
     * Returns JSON for lookups.js; audits a SERVICE_UPDATE on success.
     */
    public function update(int $id)
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $rules = [
            'category' => 'required|max_length[50]',
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

        $model = new ServiceModel();
        $data = [
            'category' => trim((string) $this->request->getPost('category')),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if (! $model->update($id, $data)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unable to update service.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $this->logLookupAction(
            'SERVICE_UPDATE',
            'Service ' . $data['name'] . ' (' . $data['category'] . ') updated.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Service updated successfully.',
            'service' => array_merge(['serviceID' => $id], $data),
            'csrf' => csrf_hash(),
        ]);
    }

    /**
     * POST `admin/lookups/services/archive/{id}`: soft-archives a service. Returns
     * 404 if missing/already archived, 409 if members are still assigned to it,
     * else archives and audits SERVICE_ARCHIVE. JSON response for lookups.js.
     */
    public function archive(int $id)
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();
        $service = $model->find($id);

        if ($service === null || ! empty($service['dt_deleted'])) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => 'Service not found or already archived.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $memberCount = $this->countActiveMembersForService($id);

        if ($memberCount > 0) {
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    'success' => false,
                    'message' => 'Cannot archive: ' . $memberCount . ' member record(s) are assigned to this service. Reassign them first.',
                    'csrf' => csrf_hash(),
                ]);
        }

        if (! $model->archive($id)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unable to archive service.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $this->logLookupAction(
            'SERVICE_ARCHIVE',
            'Service ' . ($service['name'] ?? '') . ' (' . ($service['category'] ?? '') . ') archived.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Service archived successfully.',
            'csrf' => csrf_hash(),
        ]);
    }

    /**
     * POST `admin/lookups/services/restore/{id}`: un-archives a service. Returns
     * 404 if missing, 409 if already active, else restores and audits
     * SERVICE_RESTORE. JSON response for lookups.js.
     */
    public function restore(int $id)
    {
        $guard = $this->guardLookupAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();
        $service = $model->find($id);

        if ($service === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => 'Service not found.',
                    'csrf' => csrf_hash(),
                ]);
        }

        if (empty($service['dt_deleted'])) {
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    'success' => false,
                    'message' => 'Service is already active.',
                    'csrf' => csrf_hash(),
                ]);
        }

        if (! $model->restore($id)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unable to restore service.',
                    'csrf' => csrf_hash(),
                ]);
        }

        $this->logLookupAction(
            'SERVICE_RESTORE',
            'Service ' . ($service['name'] ?? '') . ' (' . ($service['category'] ?? '') . ') restored.'
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Service restored successfully.',
            'csrf' => csrf_hash(),
        ]);
    }
}
