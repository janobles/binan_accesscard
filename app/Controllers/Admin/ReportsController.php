<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\Scanner\BatchScope;
use App\Models\Scanner\DistributionBatchModel;
use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin overall subsidy-distribution reports: combined totals + per-kiosk table,
 * batch-scoped (no date filter). PDF export. Admin/Developer only. The index
 * page is assembled by DashboardPageBuilder and rendered in the admin shell
 * (mirrors Admin\DistributionController); pdf() streams bytes directly.
 */
class ReportsController extends BaseController
{
    private function guard(): ?RedirectResponse
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        return $g instanceof RedirectResponse ? $g : null;
    }

    /** GET distribution/reports/stats - JSON snapshot for the live poll (no reload). */
    public function stats(): ResponseInterface
    {
        $g = RoleAccess::requireRole(['Admin', 'Developer']);
        if ($g instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $batchModel = model(DistributionBatchModel::class);
        $batches    = $batchModel->allBatches();
        [$batchId, $batch] = BatchScope::resolve($batches, $batchModel->activeBatch(), (int) $this->request->getGet('batch'));
        $isOpen     = $batch !== null && ($batch['closed_at'] ?? null) === null;
        $stats      = model(SubsidyStatsModel::class);
        $snapshot   = $batchId > 0
            ? $stats->batchSnapshot($batchId, $isOpen)
            : ['coverage' => ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0], 'byBarangay' => [], 'perScanner' => [], 'timeline' => []];

        return $this->response->setJSON([
            'coverage'   => $snapshot['coverage'],
            'barangay'   => $snapshot['byBarangay'],
            'perScanner' => $snapshot['perScanner'],
            'timeline'   => $snapshot['timeline'],
            'updated'    => date('c'),
        ]);
    }

    /** GET distribution/reports/pdf - streams the same report as a downloadable PDF. */
    public function pdf(): ResponseInterface
    {
        if ($g = $this->guard()) { return $g; }

        $batchModel         = model(DistributionBatchModel::class);
        $batches            = $batchModel->allBatches();
        [$batchId, $batch]  = BatchScope::resolve($batches, $batchModel->activeBatch(), (int) $this->request->getGet('batch'));
        $stats              = model(SubsidyStatsModel::class);

        $bytes = (new \App\Libraries\Scanner\ReportsPdfGenerator())->generate(
            $stats->coverage($batchId),
            $stats->byBarangay($batchId),
            $stats->remaining($batchId),
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
