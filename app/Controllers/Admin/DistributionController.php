<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidTypeModel;
use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin server: aid-type catalogue + distribution-batch control. Admin/Developer
 * only. Batch open binds the aid type for the whole batch. Every mutation writes
 * an audit_trails row. Rendered in the admin dashboard shell.
 */
class DistributionController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** GET admin/distribution — aid types + batches hub, rendered in the admin shell. */
    public function index(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('distribution');
    }

    public function createAidType(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->to('admin/distribution')->with('error', 'Aid type name is required.');
        }
        $id = model(AidTypeModel::class)->create($name);
        if ($id <= 0) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to add aid type.');
        }
        $this->audit('Created aid type "' . $name . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type added.');
    }

    public function archiveAidType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->archive($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to archive aid type.');
        }
        $this->audit('Archived aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type archived.');
    }

    public function restoreAidType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->restore($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to restore aid type.');
        }
        $this->audit('Restored aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type restored.');
    }

    public function deleteAidType(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $used = model(AidDistributionModel::class)->where('aid_type_id', $id)->countAllResults();
        if ($used > 0) {
            return redirect()->to('admin/distribution')
                ->with('error', 'Cannot delete: aid type is used by ' . $used . ' distribution(s). Archive it instead.');
        }
        $type = model(AidTypeModel::class)->find($id);
        if (! model(AidTypeModel::class)->delete($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to delete aid type.');
        }
        $this->audit('Deleted aid type "' . (string) ($type['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Aid type deleted.');
    }

    public function voidDistribution(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $row = model(AidDistributionModel::class)->find($id);
        if ($row === null) {
            return redirect()->to('admin/distribution')->with('error', 'Distribution not found.');
        }
        if (! model(AidDistributionModel::class)->void($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to void distribution.');
        }
        $this->audit(
            'Voided aid distribution #' . $id,
            (int) ($row['memberID'] ?? 0),
            'Control #' . (int) ($row['control_no'] ?? 0) . ', aid type ID ' . (int) ($row['aid_type_id'] ?? 0) . ', claim date ' . (string) ($row['claim_date'] ?? '')
        );
        return redirect()->to('admin/distribution')->with('success', 'Distribution voided.');
    }

    /** POST admin/batches/open — name + aid type. */
    public function openBatch(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name      = trim((string) $this->request->getPost('name'));
        $aidTypeId = (int) $this->request->getPost('aid_type_id');
        if ($name === '') {
            return redirect()->to('admin/distribution')->with('error', 'Batch name is required.');
        }
        if ($aidTypeId <= 0) {
            return redirect()->to('admin/distribution')->with('error', 'Choose an aid type for this batch.');
        }
        $id = model(DistributionBatchModel::class)->open($name, $aidTypeId, (int) (session('user_id') ?? 0));
        if ($id <= 0) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to open batch. Close the active batch first.');
        }
        $this->audit('Opened distribution batch "' . $name . '" #' . $id . ' (aid type ID ' . $aidTypeId . ')');
        return redirect()->to('admin/distribution')->with('success', 'Batch opened. Scanning is now live.');
    }

    public function closeBatch(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $batch = model(DistributionBatchModel::class)->find($id);
        if (! model(DistributionBatchModel::class)->close($id)) {
            return redirect()->to('admin/distribution')->with('error', 'Unable to close batch.');
        }
        $this->audit('Closed distribution batch "' . (string) ($batch['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/distribution')->with('success', 'Batch closed. Statistics reset for the next batch.');
    }

    private function audit(string $action, int $memberId = 0, ?string $detail = null): void
    {
        (new AuditTrailsModel())->logAction(
            (int) (session('user_id') ?? 0),
            $memberId > 0 ? $memberId : null,
            $action,
            null,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $detail
        );
    }
}
