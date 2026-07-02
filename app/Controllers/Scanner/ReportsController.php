<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Scanner\AidStatsModel;
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
        $stats       = model(AidStatsModel::class);
        $summary     = $stats->receivedVsNot($from, $to);
        $byBarangay  = $stats->byBarangay($from, $to);
        $byAidType   = $stats->byAidType($from, $to);

        $role      = RoleAccess::normalizeRole((string) session()->get('role'));
        $canManage = in_array($role, ['Developer', 'Admin'], true);

        return view('Scanner/reports', [
            'activeTab'         => 'reports',
            'pageTitle'         => 'Reports',
            'username'          => session('username') ?? 'Scanner',
            'from'              => $from,
            'to'                => $to,
            'summary'           => $summary,
            'byBarangay'        => $byBarangay,
            'byAidType'         => $byAidType,
            'currentRole'       => $role,
            'canManageAccounts' => $canManage,
            'sidebarRoleClass'  => $canManage ? 'developer' : 'admin',
            'sidebarUserUrl'    => site_url('admin/dashboard'),
            'navActive'         => ['scanner-reports' => 'active'],
        ]);
    }

    /** GET scanner/reports/pdf — one-page summary (body added in Task 6). */
    public function pdf(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->response->setBody('PDF export pending.');
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
