<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Aid Types reference page (Reference Data group): list + add/archive/
 * restore/delete for the aid_type table. Admin/Developer only. Every
 * mutation writes an audit_trails row. Rendered in the admin dashboard shell.
 */
class AidTypesController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** GET admin/aidtypes — aid-type management page. */
    public function index(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('aidtypes');
    }

    /** POST admin/aidtypes/create — add an aid type. */
    public function create(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('admin/aidtypes')->with('error', 'Aid type name is required.');
        }
        $id = model(AidTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to add aid type.');
        }
        $this->audit('Created aid type "' . $name . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type added.');
    }

    /** POST admin/aidtypes/archive/{id} — soft-archive (drops out of new-batch picks). */
    public function archive(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->archive($id)) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to archive aid type.');
        }
        $this->audit('Archived aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type archived.');
    }

    /** POST admin/aidtypes/restore/{id} — un-archive. */
    public function restore(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->restore($id)) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to restore aid type.');
        }
        $this->audit('Restored aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type restored.');
    }

    /** POST admin/aidtypes/delete/{id} — permanent delete, blocked while referenced. */
    public function deleteType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type   = model(AidTypeModel::class)->find($id);
        $result = model(AidTypeModel::class)->deleteIfUnused($id);
        if ($result > 0) {
            return redirect()->to('admin/aidtypes')->with('error', 'This aid type is used by ' . $result . ' distribution(s) and cannot be deleted. Archive it instead.');
        }
        if ($result < 0) {
            return redirect()->to('admin/aidtypes')->with('error', 'Unable to delete aid type.');
        }
        $this->audit('Permanently deleted aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/aidtypes')->with('success', 'Aid type deleted.');
    }

    private function audit(string $action): void
    {
        (new AuditTrailsModel())->logAction(
            (int) (session('user_id') ?? 0),
            null,
            $action,
            null,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            null
        );
    }
}
