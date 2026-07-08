<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAccount;
use App\Models\Scanner\AidStatsModel;
use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner Reports tab: read-only aid-distribution statistics with chart.js
 * visualizations and a one-page PDF summary export. No mutations, no audit.
 * Scanner/Admin/Developer only, guarded per action.
 */
class ReportsController extends BaseController
{
    /** GET scanner/reports — the statistics dashboard. */
    public function index(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        [$from, $to] = $this->normalizeDates();

        $batches           = model(DistributionBatchModel::class)->allBatches();
        [$batchId, $batch] = $this->resolveBatch($batches);
        // Batch scope and date scope are alternative filters: a chosen batch
        // wins and the date window is cleared to keep the label truthful.
        if ($batch !== null) {
            [$from, $to] = [null, null];
        }

        $role          = RoleAccess::normalizeRole((string) session()->get('role'));
        $isScannerRole = $role === 'Scanner';
        $canManage     = in_array($role, ['Developer', 'Admin'], true);

        $stats      = model(AidStatsModel::class);
        $scope      = $batchId > 0 ? $batchId : null;
        $summary    = $stats->receivedVsNot($from, $to, $scope);
        $byBarangay = $stats->byBarangay($from, $to, $scope);
        $byAidType  = $stats->byAidType($from, $to, $scope);
        $perScanner = $batchId > 0
            ? $stats->perScanner($batchId, $isScannerRole ? (int) (session('user_id') ?? 0) : null)
            : [];

        return view('Scanner/reports', [
            'activeTab'         => 'reports',
            'pageTitle'         => 'Reports',
            'username'          => session('username') ?? 'Scanner',
            'user'              => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'from'              => $from,
            'to'                => $to,
            'summary'           => $summary,
            'byBarangay'        => $byBarangay,
            'byAidType'         => $byAidType,
            'batches'           => $batches,
            'batchId'           => $batchId > 0 ? $batchId : null,
            'batchName'         => $batch['name'] ?? null,
            'perScanner'        => $perScanner,
            'isScannerRole'     => $isScannerRole,
            'currentRole'       => $role,
            'canManageAccounts' => $canManage,
            'sidebarRoleClass'  => strtolower($role),
            'sidebarUserUrl'    => site_url('admin/dashboard'),
            'navActive'         => ['scanner-reports' => 'active'],
        ]);
    }

    /** GET scanner/reports/pdf — one-page summary of the current date window. */
    public function pdf(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        [$from, $to] = $this->normalizeDates();
        $stats       = model(AidStatsModel::class);

        $bytes = (new \App\Libraries\Scanner\ReportsPdfGenerator())->generate(
            $stats->receivedVsNot($from, $to),
            $stats->byBarangay($from, $to),
            $stats->byAidType($from, $to),
            $from,
            $to
        );

        $name = 'aid-report-' . ($from ?: 'start') . '_' . ($to ?: 'today') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($bytes);
    }

    /**
     * Resolves the ?batch query param against the known batches.
     *
     * @param array $batches rows from DistributionBatchModel::allBatches()
     * @return array{0:int,1:?array} [batchId (0 = none), batch row or null]
     */
    private function resolveBatch(array $batches): array
    {
        $batchId = (int) $this->request->getGet('batch');
        foreach ($batches as $b) {
            if ((int) $b['batch_id'] === $batchId) {
                return [$batchId, $b];
            }
        }

        return [0, null];
    }

    /**
     * Reads from/to query params, keeps only valid YYYY-MM-DD values, and swaps
     * them when from is later than to. Either side may be null (open-ended).
     *
     * @return array{0:?string,1:?string}
     */
    private function normalizeDates(): array
    {
        $clean = static function (?string $v): ?string {
            $v = trim((string) $v);
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
            return ($d !== false && $d->format('Y-m-d') === $v) ? $v : null;
        };

        $from = $clean($this->request->getGet('from'));
        $to   = $clean($this->request->getGet('to'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
