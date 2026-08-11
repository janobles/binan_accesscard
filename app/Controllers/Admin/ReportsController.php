<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Scanner\BatchScope;
use App\Models\Scanner\DistributionBatchModel;
use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The two read-only endpoints behind the dashboard's Distribution pane: a JSON
 * snapshot for its live poll, and the same figures streamed as a PDF. Both are
 * batch-scoped and carry no date filter.
 *
 * Neither re-checks the role. The pane renders for every staff role, and the
 * 'dashboard-reports' manifest key on both routes is the single place that
 * decides who reaches them; a second Admin/Developer check here is what used to
 * 404 an Encoder's poll.
 */
class ReportsController extends BaseController
{
    /** GET distribution/reports/stats - JSON snapshot for the live poll (no reload). */
    public function stats(): ResponseInterface
    {
        $batchModel = model(DistributionBatchModel::class);
        $batches    = $batchModel->allBatches();
        [$batchId, $batch] = BatchScope::resolve($batches, $batchModel->activeBatch(), (int) $this->request->getGet('batch'));
        $isOpen     = $batch !== null && ($batch['closed_at'] ?? null) === null;
        $stats      = model(SubsidyStatsModel::class);
        $snapshot   = $batchId > 0
            ? $stats->batchSnapshot($batchId, $isOpen)
            : ['coverage' => ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0], 'byBarangay' => [], 'perScanner' => [], 'timeline' => [], 'byDay' => []];

        return $this->response->setJSON([
            'coverage'   => $snapshot['coverage'],
            'barangay'   => $snapshot['byBarangay'],
            'perScanner' => $snapshot['perScanner'],
            'timeline'   => $snapshot['timeline'],
            'byDay'      => $snapshot['byDay'] ?? [],
            'updated'    => date('c'),
        ]);
    }

    /** GET distribution/reports/pdf - streams the same report as a downloadable PDF. */
    public function pdf(): ResponseInterface
    {
        $batchModel         = model(DistributionBatchModel::class);
        $batches            = $batchModel->allBatches();
        [$batchId, $batch]  = BatchScope::resolve($batches, $batchModel->activeBatch(), (int) $this->request->getGet('batch'));
        $stats              = model(SubsidyStatsModel::class);

        $bytes = (new \App\Libraries\Scanner\ReportsPdfGenerator())->generate(
            $stats->coverage($batchId),
            $stats->byBarangay($batchId),
            $batch['name'] ?? null,
            $batchId > 0 ? $stats->perScanner($batchId) : []
        );

        $name = 'subsidy-report-' . ($batchId > 0 ? 'batch' . $batchId : 'all') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($bytes);
    }
}
