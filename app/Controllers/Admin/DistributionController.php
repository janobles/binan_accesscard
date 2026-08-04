<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Scanner\DistributionBatchModel;
use App\Models\Scanner\SubsidyDistributionModel;
use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Distribution-batch control and the all-distributions log. Who may open the
 * page is the roleNav filter's decision (Config\Navigation lists Viewer there
 * too); the batch and void actions below stay Admin/Developer only. Batch open
 * binds a subsidy type (from the subsidy reference table) for the whole batch.
 * Every mutation writes an audit_trails row. Rendered in the dashboard shell.
 */
class DistributionController extends BaseController
{
    /** Write guard for the batch/void actions, which are stricter than the page. */
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /**
     * GET distribution - batches and the distribution log share one
     * page, switched by ?tab= (batches|log).
     */
    public function distribution(): ResponseInterface|string
    {
        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderPage('distribution');
    }

    /**
     * POST distribution/void/{id} - voids a logged subsidy distribution
     * (a mistaken or duplicate scan) so it no longer counts in the reports, and
     * writes an audit trail row recording the control number, subsidy type, and
     * claim date that were voided.
     */
    public function voidDistribution(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $row = model(SubsidyDistributionModel::class)->find($id);
        if ($row === null) {
            return redirect()->to('distribution?tab=log')->with('error', 'Distribution not found.');
        }
        if (! model(SubsidyDistributionModel::class)->void($id)) {
            return redirect()->to('distribution?tab=log')->with('error', 'Unable to void distribution.');
        }
        $this->audit(
            'Voided subsidy distribution #' . $id,
            (int) ($row['memberID'] ?? 0),
            'Control #' . (string) ($row['control_no'] ?? '') . ', subsidy type ID ' . (int) ($row['subsidy_type_id'] ?? 0) . ', claim date ' . (string) ($row['claim_date'] ?? '')
        );
        // The voided row's own batch, not whatever batch is active now - a void
        // reaches back into a closed batch just as often as the open one, and
        // that closed batch's cache never expires on its own (ttl 0).
        model(SubsidyStatsModel::class)->forgetBatch((int) ($row['batch_id'] ?? 0));
        return redirect()->to('distribution?tab=log')->with('success', 'Distribution voided.');
    }

    /**
     * GET distribution/batches/preview - the eligible-family count for a
     * prospective batch, so the admin sees the roster size before committing.
     * Runs the same query the roster build uses, via EligibilityBuilder::count(),
     * so this preview and the frozen roster can never disagree.
     */
    public function previewEligibility(): ResponseInterface
    {
        if ($g = $this->guard()) { return $g; }

        $barangayIds = array_map('intval', (array) $this->request->getGet('barangay_ids'));
        $sectorIds   = array_map('intval', (array) $this->request->getGet('sector_ids'));

        return $this->response->setJSON([
            'eligible' => (new \App\Libraries\EligibilityBuilder())->count($barangayIds, $sectorIds),
        ]);
    }

    /** POST distribution/batches/open - name + subsidy type. */
    public function openBatch(): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $name      = trim((string) $this->request->getPost('name'));
        $subsidyTypeId = (int) $this->request->getPost('subsidy_type_id');
        if ($name === '') {
            return redirect()->to('distribution?tab=batches')->with('error', 'Batch name is required.');
        }
        if ($subsidyTypeId <= 0) {
            return redirect()->to('distribution?tab=batches')->with('error', 'Choose a subsidy type for this batch.');
        }
        $barangayIds = array_map('intval', (array) $this->request->getPost('barangay_ids'));
        $sectorIds   = array_map('intval', (array) $this->request->getPost('sector_ids'));

        $batchModel = model(DistributionBatchModel::class);
        $id         = $batchModel->open($name, $subsidyTypeId, (int) (session('user_id') ?? 0), $barangayIds, $sectorIds);
        if ($id <= 0) {
            $message = $batchModel->activeBatch() !== null
                ? 'A batch is already open. Close the active batch before opening a new one.'
                : 'Unable to open batch. Please try again or contact an administrator.';

            return redirect()->to('distribution?tab=batches')->with('error', $message);
        }
        $eligible = (int) ($batchModel->find($id)['eligible_count'] ?? 0);
        $this->audit(
            'Opened distribution batch "' . $name . '" #' . $id . ' (subsidy type ID ' . $subsidyTypeId . ')',
            0,
            'Eligible families: ' . $eligible
                . '; barangays: ' . ($barangayIds === [] ? 'all' : implode(',', $barangayIds))
                . '; sectors: ' . ($sectorIds === [] ? 'all' : implode(',', $sectorIds))
        );
        return redirect()->to('distribution?tab=batches')->with('success', 'Batch opened. Scanning is now live.');
    }

    /**
     * POST distribution/batches/close/{id} - closes an open distribution batch, ending
     * scanning for it and resetting the live kiosk statistics for the next
     * batch. Writes an audit trail row.
     */
    public function closeBatch(int $id): RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }
        $batch = model(DistributionBatchModel::class)->find($id);
        if (! model(DistributionBatchModel::class)->close($id)) {
            return redirect()->to('distribution?tab=batches')->with('error', 'Unable to close batch.');
        }
        $this->audit('Closed distribution batch "' . (string) ($batch['name'] ?? '') . '" #' . $id);
        return redirect()->to('distribution?tab=batches')->with('success', 'Batch closed. Statistics reset for the next batch.');
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
