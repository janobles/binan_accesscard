<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\SubsidyTypeModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Subsidy Types reference page (Reference Data group): list + add/archive/
 * restore/delete for the subsidy table. Admin/Developer only. Every
 * mutation writes an audit_trails row. Rendered in the admin dashboard shell.
 */
class SubsidyTypesController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** POST reference-data/subsidy-types/create - add a subsidy type. */
    public function create(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('reference-data?tab=aidtypes')->with('error', 'Subsidy type name is required.');
        }
        $id = model(SubsidyTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('reference-data?tab=aidtypes')->with('error', 'Unable to add subsidy type.');
        }
        $this->audit('Created subsidy type "' . $name . '" #' . $id);
        return redirect()->to('reference-data?tab=aidtypes')->with('success', 'Subsidy type added.');
    }

    /** POST reference-data/subsidy-types/archive/{id} - soft-archive (drops out of new-batch picks). */
    public function archive(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(SubsidyTypeModel::class)->find($id);
        if (! model(SubsidyTypeModel::class)->archive($id)) {
            return redirect()->to('reference-data?tab=aidtypes')->with('error', 'Unable to archive subsidy type.');
        }
        $this->audit('Archived subsidy type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('reference-data?tab=aidtypes')->with('success', 'Subsidy type archived.');
    }

    /** POST reference-data/subsidy-types/restore/{id} - un-archive. */
    public function restore(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(SubsidyTypeModel::class)->find($id);
        if (! model(SubsidyTypeModel::class)->restore($id)) {
            return redirect()->to('reference-data?tab=aidtypes')->with('error', 'Unable to restore subsidy type.');
        }
        $this->audit('Restored subsidy type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('reference-data?tab=aidtypes')->with('success', 'Subsidy type restored.');
    }

    /** POST reference-data/subsidy-types/delete/{id} - permanent delete, blocked while referenced. */
    public function deleteType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type   = model(SubsidyTypeModel::class)->find($id);
        $result = model(SubsidyTypeModel::class)->deleteIfUnused($id);
        if ($result > 0) {
            return redirect()->to('reference-data?tab=aidtypes')->with('error', 'This subsidy type is used by ' . $result . ' distribution(s) and cannot be deleted. Archive it instead.');
        }
        if ($result < 0) {
            return redirect()->to('reference-data?tab=aidtypes')->with('error', 'Unable to delete subsidy type.');
        }
        $this->audit('Permanently deleted subsidy type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('reference-data?tab=aidtypes')->with('success', 'Subsidy type deleted.');
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
