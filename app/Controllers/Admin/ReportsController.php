<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Scanner\AidStatsModel;
use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin overall aid-distribution reports: combined totals + per-kiosk table,
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

    /** [batchId, batch|null] resolved against known batches, defaulting to active/latest. */
    private function resolveBatch(array $batches, ?array $active): array
    {
        $batchId = (int) $this->request->getGet('batch');
        if ($batchId <= 0) {
            $batchId = $active !== null ? (int) $active['batch_id'] : (int) ($batches[0]['batch_id'] ?? 0);
        }
        foreach ($batches as $b) {
            if ((int) $b['batch_id'] === $batchId) {
                return [$batchId, $b];
            }
        }
        return [0, null];
    }

    /** GET admin/reports — combined totals + per-kiosk table, rendered in the admin shell. */
    public function index(): ResponseInterface|string
    {
        if ($g = $this->guard()) { return $g; }

        return (new \App\Libraries\DashboardPageBuilder($this->request))->renderAdminPage('reports');
    }

    /** GET admin/reports/pdf — streams the same report as a downloadable PDF. */
    public function pdf(): ResponseInterface
    {
        if ($g = $this->guard()) { return $g; }

        $batchModel        = model(DistributionBatchModel::class);
        $batches           = $batchModel->allBatches();
        [$batchId, $batch] = $this->resolveBatch($batches, $batchModel->activeBatch());
        $scope             = $batchId > 0 ? $batchId : null;
        $stats             = model(AidStatsModel::class);

        $bytes = (new \App\Libraries\Scanner\ReportsPdfGenerator())->generate(
            $stats->receivedVsNot($scope),
            $stats->byBarangay($scope),
            $stats->byAidType($scope),
            null,
            null,
            $batchId > 0 ? $stats->perScanner($batchId) : [],
            $batch['name'] ?? null
        );

        $name = 'aid-report-' . ($batchId > 0 ? 'batch' . $batchId : 'all') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($bytes);
    }
}
