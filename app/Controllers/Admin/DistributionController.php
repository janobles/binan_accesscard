<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin server: distribution-batch control and the all-distributions log.
 * Admin/Developer only. Batch open binds a service (from the services
 * reference table) for the whole batch. Every mutation writes an
 * audit_trails row. Rendered in the admin dashboard shell.
 */
class DistributionController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** GET admin/batches — batch control page (create modal, open/close). */
    public function batches(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('batches');
    }

    /** GET admin/distributions — every handout, searchable log. */
    public function distributions(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('distributions');
    }

    public function voidDistribution(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $row = model(AidDistributionModel::class)->find($id);
        if ($row === null) {
            return redirect()->to('admin/distributions')->with('error', 'Distribution not found.');
        }
        if (! model(AidDistributionModel::class)->void($id)) {
            return redirect()->to('admin/distributions')->with('error', 'Unable to void distribution.');
        }
        $this->audit(
            'Voided aid distribution #' . $id,
            (int) ($row['memberID'] ?? 0),
            'Control #' . (string) ($row['control_no'] ?? '') . ', service ID ' . (int) ($row['service_id'] ?? 0) . ', claim date ' . (string) ($row['claim_date'] ?? '')
        );
        return redirect()->to('admin/distributions')->with('success', 'Distribution voided.');
    }

    /** POST admin/batches/open — name + service. */
    public function openBatch(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name      = trim((string) $this->request->getPost('name'));
        $serviceId = (int) $this->request->getPost('service_id');
        if ($name === '') {
            return redirect()->to('admin/batches')->with('error', 'Batch name is required.');
        }
        if ($serviceId <= 0) {
            return redirect()->to('admin/batches')->with('error', 'Choose a service for this batch.');
        }
        $batchModel = model(DistributionBatchModel::class);
        $id         = $batchModel->open($name, $serviceId, (int) (session('user_id') ?? 0));
        if ($id <= 0) {
            $message = $batchModel->activeBatch() !== null
                ? 'A batch is already open. Close the active batch before opening a new one.'
                : 'Unable to open batch. Please try again or contact an administrator.';

            return redirect()->to('admin/batches')->with('error', $message);
        }
        $this->audit('Opened distribution batch "' . $name . '" #' . $id . ' (service ID ' . $serviceId . ')');
        return redirect()->to('admin/batches')->with('success', 'Batch opened. Scanning is now live.');
    }

    public function closeBatch(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $batch = model(DistributionBatchModel::class)->find($id);
        if (! model(DistributionBatchModel::class)->close($id)) {
            return redirect()->to('admin/batches')->with('error', 'Unable to close batch.');
        }
        $this->audit('Closed distribution batch "' . (string) ($batch['name'] ?? '') . '" #' . $id);
        return redirect()->to('admin/batches')->with('success', 'Batch closed. Statistics reset for the next batch.');
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
