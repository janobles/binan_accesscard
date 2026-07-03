<?php

namespace App\Controllers\Lookups;

use App\Controllers\BaseController;
use App\Controllers\Concerns\LookupControllerTrait;
use App\Libraries\RoleAccess;
use App\Models\Lookups\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles the write/mutation actions for the `services` lookup table, posted from
 * the admin services page. Listing is done by Admin\DashboardController::services; every
 * action here is Developer/Admin-only and redirects back to `admin/services` with
 * a flash message.
 */
class ServiceController extends BaseController
{
    use LookupControllerTrait;

    /**
     * POST `admin/services/create`: add a new service/program. Delegates to
     * saveService(). Frontend: the "Add service" modal form.
     */
    public function create(): RedirectResponse
    {
        return $this->saveService();
    }

    /**
     * POST `admin/services/update/{id}`: edit a service/program. Delegates to
     * saveService() with the id. Frontend: the "Edit service" modal form.
     */
    public function update(int $serviceId): RedirectResponse
    {
        return $this->saveService($serviceId);
    }

    /**
     * POST `admin/services/archive/{id}`: soft-archive a service. Allowed even when
     * the service is still assigned to members: archiving only retires it from new
     * selections; existing records keep the service (the family edit form preserves
     * archived-but-assigned services). Permanent delete is still guarded. Audits the action.
     */
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

        $service = $model->find($serviceId);

        if (! $model->archive($serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'Unable to archive service.');
        }

        $this->audit('SERVICE_ARCHIVE', 'Archived ' . $this->serviceLabel($service, $serviceId) . '.');

        return $this->redirectAdmin('admin/services', 'success', 'Service or program archived successfully.');
    }

    /**
     * POST `admin/services/restore/{id}`: un-archive a service/program and audit
     * it. Frontend: the "restore" control on the archived services view.
     */
    public function restore(int $serviceId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new ServiceModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/services?status=archived', 'error', 'Services table is not available.');
        }

        $service = $model->find($serviceId);

        if (! $model->restore($serviceId)) {
            return $this->redirectAdmin('admin/services?status=archived', 'error', 'Unable to restore service.');
        }

        $this->audit('SERVICE_RESTORE', 'Restored ' . $this->serviceLabel($service, $serviceId) . '.');

        return $this->redirectAdmin('admin/services', 'success', 'Service or program restored successfully.');
    }

    /**
     * POST `admin/services/delete/{id}`: permanently delete a service/program.
     * Blocked if it is in use by any member; audits the hard delete.
     */
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

        $service = $model->find($serviceId);

        if (! $model->delete($serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'Unable to delete service.');
        }

        $this->audit('SERVICE_DELETE', 'Permanently deleted ' . $this->serviceLabel($service, $serviceId) . '.');

        return $this->redirectAdmin('admin/services', 'success', 'Service or program deleted successfully.');
    }

    /**
     * Shared create/update logic for services. Resolves the category (including
     * the "__other__" custom option), validates required fields, then either
     * updates the row or inserts a new one with the next service ID, and audits.
     * $serviceId null = create, otherwise update.
     */
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

        $shortcode = strtoupper(trim((string) $this->request->getPost('shortcode')));

        $data = [
            'shortcode' => $shortcode,
            'category' => $category,
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['category'] === '' || $data['name'] === '' || $data['shortcode'] === '') {
            return $this->redirectAdmin('admin/services', 'error', 'Code, category and name are required.');
        }

        // The code is the unique key the Excel import uses, so it must not clash.
        if ($model->shortcodeExists($data['shortcode'], $serviceId)) {
            return $this->redirectAdmin('admin/services', 'error', 'The code "' . $data['shortcode'] . '" is already used by another service.');
        }

        $isUpdate = $serviceId !== null;

        if ($isUpdate) {
            $model->update($serviceId, $model->dataForCurrentSchema($data));
        } else {
            $data['serviceID'] = $model->nextServiceId();
            $model->insert($model->dataForCurrentSchema($data));
            $serviceId = (int) $data['serviceID'];
        }

        $this->audit(
            $isUpdate ? 'SERVICE_UPDATE' : 'SERVICE_CREATE',
            ($isUpdate ? 'Updated' : 'Created') . ' service/program ' . $data['name'] . ' (' . $data['category'] . ') #' . $serviceId . '.'
        );

        $message = $isUpdate ? 'Service updated successfully.' : 'Service added successfully.';

        return $this->redirectAdmin('admin/services', 'success', $message);
    }

    /**
     * True if any `member_services` row links to this service ID. Guards
     * archive/delete so in-use services cannot be removed.
     */
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

    /**
     * Human-readable service label for audit descriptions, e.g. "Aid (General) #12".
     */
    private function serviceLabel(?array $service, int $serviceId): string
    {
        $name = trim((string) ($service['name'] ?? ''));
        $category = trim((string) ($service['category'] ?? ''));
        $code = trim((string) ($service['shortcode'] ?? ''));
        $label = trim(($code !== '' ? $code . ' ' : '') . $name . ($category !== '' ? ' (' . $category . ')' : ''));

        return ($label === '' ? 'service/program' : 'service/program ' . $label) . ' #' . $serviceId;
    }
}
