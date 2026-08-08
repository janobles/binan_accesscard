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
 * too); the schedule, batch and void actions below stay Admin/Developer only.
 * A schedule binds a subsidy type (from the subsidy reference table) for the
 * whole batch. Every mutation writes an audit_trails row. Rendered in the
 * dashboard shell.
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

    /**
     * GET distribution/schedule/feed - plotted batches touching ?from=&to=, in
     * the shape FullCalendar consumes. `end` is exclusive, per its all-day
     * event contract, so a batch ending Aug 21 reports Aug 22.
     */
    public function scheduleFeed(): ResponseInterface
    {
        $from = (string) ($this->request->getGet('from') ?? date('Y-m-01'));
        $to   = (string) ($this->request->getGet('to') ?? date('Y-m-t'));

        $model  = model(DistributionBatchModel::class);
        $today  = date('Y-m-d');
        $events = [];

        foreach ($model->scheduledBetween($from, $to) as $batch) {
            $id      = (int) $batch['batch_id'];
            $filters = $model->filtersFor($id);
            $status  = 'upcoming';
            if ($batch['closed_at'] !== null) {
                $status = 'finished';
            } elseif ($batch['started_at'] !== null) {
                $status = 'running';
            }

            $events[] = [
                'id'            => $id,
                'title'         => (string) $batch['name'],
                'start'         => (string) $batch['scheduled_start'],
                'end'           => date('Y-m-d', strtotime((string) $batch['scheduled_end'] . ' +1 day')),
                'color'         => (string) $batch['color'],
                'venue'         => (string) $batch['venue'],
                'status'        => $status,
                'subsidyTypeId' => (int) $batch['subsidy_type_id'],
                'dailyStart'    => substr((string) $batch['daily_start_time'], 0, 5),
                'dailyEnd'      => substr((string) $batch['daily_end_time'], 0, 5),
                'barangayIds'   => $filters['barangays'],
                'sectorIds'     => $filters['sectors'],
                // Only a batch that has not started may be dragged or resized.
                'editable'      => $batch['started_at'] === null && (string) $batch['scheduled_start'] > $today,
            ];
        }

        return $this->response->setJSON($events);
    }

    /**
     * POST distribution/schedule/save - creates or updates a plotted batch.
     * Answers 409 with the clashing batch when the dates are taken, so the
     * calendar can offer replacing a plan or refuse outright once that batch
     * has scans against it.
     */
    public function saveSchedule(): ResponseInterface|RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }

        $model = model(DistributionBatchModel::class);
        $start = (string) $this->request->getPost('scheduled_start');
        $end   = (string) $this->request->getPost('scheduled_end');
        $id    = (int) $this->request->getPost('batch_id');

        if ($clash = $model->overlapping($start, $end, $id)) {
            $clashId = (int) $clash['batch_id'];

            return $this->response->setStatusCode(409)->setJSON([
                'error' => 'overlap',
                'clash' => [
                    'id'          => $clashId,
                    'name'        => (string) $clash['name'],
                    'venue'       => (string) $clash['venue'],
                    'start'       => (string) $clash['scheduled_start'],
                    'end'         => (string) $clash['scheduled_end'],
                    'replaceable' => ! $model->hasDistributions($clashId),
                ],
            ]);
        }

        $saved = $model->saveSchedule([
            'batch_id'         => $id,
            'name'             => (string) $this->request->getPost('name'),
            'venue'            => (string) $this->request->getPost('venue'),
            'subsidy_type_id'  => (int) $this->request->getPost('subsidy_type_id'),
            'scheduled_start'  => $start,
            'scheduled_end'    => $end,
            'daily_start_time' => (string) $this->request->getPost('daily_start_time') . ':00',
            'daily_end_time'   => (string) $this->request->getPost('daily_end_time') . ':00',
            'color'            => (string) $this->request->getPost('color'),
            'barangay_ids'     => array_map('intval', (array) $this->request->getPost('barangay_ids')),
            'sector_ids'       => array_map('intval', (array) $this->request->getPost('sector_ids')),
        ], (int) (session('user_id') ?? 0));

        if ($saved <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'error'   => 'invalid',
                'message' => 'Check the name, subsidy type and dates, then save again.',
            ]);
        }

        $this->audit(
            ($id > 0 ? 'Updated' : 'Plotted') . ' distribution schedule "' . (string) $this->request->getPost('name') . '" #' . $saved,
            0,
            'Venue: ' . (string) $this->request->getPost('venue') . '; ' . $start . ' to ' . $end
        );

        return $this->response->setJSON(['id' => $saved]);
    }

    /**
     * POST distribution/schedule/{id}/delete - removes a plotted batch. Refused
     * by the model once scans exist, because deleting then would orphan them.
     */
    public function deleteSchedule(int $id): ResponseInterface|RedirectResponse
    {
        if ($g = $this->guard()) { return $g; }

        $model = model(DistributionBatchModel::class);
        $batch = $model->find($id);

        if ($batch === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'error'   => 'not_found',
                'message' => 'This schedule no longer exists.',
            ]);
        }

        if (! $model->deleteSchedule($id)) {
            return $this->response->setStatusCode(409)->setJSON([
                'error'   => 'has_distributions',
                'message' => 'This batch already ran and families were served, so its record cannot be removed.',
            ]);
        }

        $this->audit('Removed distribution schedule "' . (string) $batch['name'] . '" #' . $id);

        return $this->response->setJSON(['ok' => true]);
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
