<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAccount;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidStatsModel;
use App\Models\Scanner\DistributionBatchModel;
use App\Models\Scanner\QrControlModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner module: resolve a paper QR control number to a family, show its aid
 * history, and log a new aid distribution. Scanner/Admin/Developer only.
 *
 * The kiosk (scan + performance) renders in the kiosk shell
 * (Scanner/kiosk-layout): no sidebar/topbar, one slim header with the live
 * personal counter. The aid type comes from the active distribution batch
 * (set by an admin when the batch is opened).
 *
 * - scan():        GET  scanner/scan        -> one-action scan UI; empty state when no batch is open.
 * - performance(): GET  scanner/performance  -> this kiosk's own live metrics.
 * - stats():       GET  scanner/stats        -> JSON own-performance snapshot for polling.
 * - logAid():      POST scanner/log          -> the whole scan in one call: resolve family,
 *                                               per-batch duplicate guard, insert + audit;
 *                                               409 when no open batch.
 */
class ScanController extends BaseController
{
    /** GET scanner/scan — kiosk lookup UI. Aid type comes from the active batch. */
    public function scan(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        $userId      = (int) (session('user_id') ?? 0);

        return view('Scanner/scan', [
            'pageTitle'    => 'Scan',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $activeBatch,
            'aidType'      => $activeBatch !== null
                ? [
                    'aid_type_id' => (int) $activeBatch['aid_type_id'],
                    'name'        => (string) ($activeBatch['aid_type_name'] ?? 'Aid'),
                ]
                : null,
            'myBatchCount' => $activeBatch !== null
                ? model(AidDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id'])
                : 0,
        ]);
    }

    /** GET scanner/performance — this kiosk's own live metrics. */
    public function performance(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $batchModel = model(DistributionBatchModel::class);
        $active     = $batchModel->activeBatch();
        $batches    = $batchModel->allBatches();
        $batchId    = (int) $this->request->getGet('batch');
        if ($batchId <= 0) {
            $batchId = $active !== null ? (int) $active['batch_id'] : (int) ($batches[0]['batch_id'] ?? 0);
        }

        $userId    = (int) (session('user_id') ?? 0);
        $batchRow  = null;
        foreach ($batches as $b) {
            if ((int) $b['batch_id'] === $batchId) {
                $batchRow = $b;
                break;
            }
        }
        $snapshot = $this->kioskSnapshot($batchId, $userId, $batchRow);

        return view('Scanner/performance', array_merge([
            'pageTitle'    => 'My Performance',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $active,
            'batches'      => $batches,
            'batchId'      => $batchId,
        ], $snapshot));
    }

    /**
     * One kiosk's live figures for a batch: totals, a time-bucketed throughput
     * timeline, and derived pace (families/hour, busiest window). Shared by the
     * performance page and the polling endpoint so both stay in sync.
     *
     * @return array{mine:array{families:int,handouts:int},timeline:list<array{label:string,families:int,handouts:int}>,pace:array{perHour:int,busiest:string}}
     */
    private function kioskSnapshot(int $batchId, int $userId, ?array $batchRow): array
    {
        $stats    = model(AidStatsModel::class);
        $mineRow  = $batchId > 0 ? ($stats->perScanner($batchId, $userId)[0] ?? null) : null;
        $mine     = ['families' => (int) ($mineRow['families'] ?? 0), 'handouts' => (int) ($mineRow['handouts'] ?? 0)];
        $timeline = $batchId > 0 ? $stats->timelineForUserInBatch($batchId, $userId) : [];

        // Pace uses the batch's active span (open batch → now, else its close time).
        $perHour = 0;
        if ($mine['families'] > 0 && $batchRow !== null && ! empty($batchRow['started_at'])) {
            $start   = strtotime((string) $batchRow['started_at']);
            $end     = ! empty($batchRow['closed_at']) ? strtotime((string) $batchRow['closed_at']) : time();
            $hours   = max(0.05, ($end - $start) / 3600);
            $perHour = (int) round($mine['families'] / $hours);
        }

        $busiest = '';
        $peak    = -1;
        foreach ($timeline as $bucket) {
            if ($bucket['families'] > $peak) {
                $peak    = $bucket['families'];
                $busiest = $bucket['label'];
            }
        }

        return [
            'mine'     => $mine,
            'timeline' => $timeline,
            'pace'     => ['perHour' => $perHour, 'busiest' => $busiest],
        ];
    }

    /** GET scanner/stats — JSON own-performance snapshot for kiosk polling. */
    public function stats(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        $userId      = (int) (session('user_id') ?? 0);
        if ($activeBatch === null) {
            return $this->response->setJSON(['batch' => null, 'families' => 0, 'handouts' => 0]);
        }

        $batchId  = (int) $activeBatch['batch_id'];
        $snapshot = $this->kioskSnapshot($batchId, $userId, $activeBatch);

        return $this->response->setJSON([
            'batch'    => [
                'id'       => $batchId,
                'name'     => (string) $activeBatch['name'],
                'aid_type' => (string) ($activeBatch['aid_type_name'] ?? ''),
            ],
            'families' => $snapshot['mine']['families'],
            'handouts' => $snapshot['mine']['handouts'],
            'timeline' => $snapshot['timeline'],
            'pace'     => $snapshot['pace'],
            'updated'  => date('c'),
        ]);
    }

    /**
     * POST scanner/log — the whole scan in one action. Resolves the control
     * number to a family, refuses when the family was already logged in the
     * open batch (Duplicate Entry), otherwise inserts a distribution for the
     * family HEAD dated today and audits it. The response always carries the
     * family panel data so the kiosk renders in one round trip.
     */
    public function logAid(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        if (! $this->validate(['control_no' => 'required|is_natural_no_zero'])) {
            return $this->response->setStatusCode(422)
                ->setJSON(['errors' => $this->validator->getErrors()]);
        }
        $controlNo = (int) $this->request->getPost('control_no');

        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'QR control number is not registered.']);
        }
        $members = new MemberModel();
        $head    = $members->findHead($headId);
        if ($head === null) {
            log_message('error', 'Scanner log: control {c} maps to missing head {h}', ['c' => $controlNo, 'h' => $headId]);
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Family record unavailable.']);
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return $this->response->setStatusCode(409)
                ->setJSON(['error' => 'No active distribution batch. Ask an administrator to open one.']);
        }
        $batchId = (int) $activeBatch['batch_id'];
        $userId  = (int) (session('user_id') ?? 0);

        $familyPayload = [
            'control_no' => $controlNo,
            'head'       => $head,
            'members'    => $members->familyMembers($headId),
        ];

        // Sector/category/service badges per member for the family panel.
        $memberRows = $familyPayload['members'];
        $badges     = $members->referenceBadges(array_map(static fn (array $m): int => (int) $m['memberID'], $memberRows));
        $familyPayload['head']['badges'] = $badges[(int) $head['memberID']] ?? [];
        foreach ($memberRows as $i => $m) {
            $memberRows[$i]['badges'] = $badges[(int) $m['memberID']] ?? [];
        }
        $familyPayload['members'] = $memberRows;

        // One handout per family per batch: a repeat scan reports the original
        // entry instead of logging again. The check is server-side so a stale
        // kiosk page can never double-log.
        $existing = model(AidDistributionModel::class)->inBatch($controlNo, $batchId);
        if ($existing !== null) {
            return $this->response->setJSON($familyPayload + [
                'ok'           => true,
                'logged'       => false,
                'duplicate'    => $existing,
                'history'      => model(AidDistributionModel::class)->historyFor($controlNo),
                'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, $batchId),
            ]);
        }

        $aidTypeId = (int) $activeBatch['aid_type_id'];
        $claimDate = date('Y-m-d');

        // The insert and its audit row must land together: without a shared
        // transaction, a handout could get logged with no audit trail (or an
        // audit row could survive a rolled-back handout).
        $db = db_connect();
        $db->transStart();

        $aidId = model(AidDistributionModel::class)->logAid([
            'control_no'  => $controlNo,
            'memberID'    => (int) $head['memberID'],
            'aid_type_id' => $aidTypeId,
            'claim_date'  => $claimDate,
            'userID'      => $userId,
            'batch_id'    => $batchId,
        ]);

        $audited = $aidId > 0 && (new AuditTrailsModel())->logAction(
            $userId,
            (int) $head['memberID'],
            'Logged aid distribution',
            'Control #' . $controlNo,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            'Aid type ID ' . $aidTypeId . ' on ' . $claimDate
        );

        if (! $audited) {
            $db->transRollback();

            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the aid distribution.']);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the aid distribution.']);
        }

        return $this->response->setJSON($familyPayload + [
            'ok'           => true,
            'logged'       => true,
            'duplicate'    => null,
            'history'      => model(AidDistributionModel::class)->historyFor($controlNo),
            'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, $batchId),
        ]);
    }
}
