<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SessionAccount;
use App\Models\Scanner\AidStatsModel;
use App\Models\Scanner\DistributionBatchModel;
use App\Models\Scanner\TempAidDistributionModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner module: record paper QR control numbers for an active distribution
 * batch. Scanner/Admin/Developer only.
 *
 * The kiosk (scan + performance) renders in the kiosk shell
 * (Scanner/kiosk-layout): no sidebar/topbar, one slim header with the live
 * personal counter. The aid type comes from the active distribution batch
 * (set by an admin when the batch is opened).
 *
 * - scan():        GET  scanner/scan        -> one-action scan UI; empty state when no batch is open.
 * - performance(): GET  scanner/performance  -> this kiosk's own live metrics.
 * - stats():       GET  scanner/stats        -> JSON own-performance snapshot for polling.
 * - logAid():      POST scanner/log          -> validate QR, reject a batch duplicate,
 *                                               and insert a temporary distribution;
 *                                               409 when no open batch.
 * - voidScan():    POST scanner/void         -> delete the displayed QR from the active batch.
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
                ? model(TempAidDistributionModel::class)->countInBatch((int) $activeBatch['batch_id'])
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
     * POST scanner/log — records a QR number without requiring encoded family
     * data. A QR can be recorded once per active batch.
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

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return $this->response->setStatusCode(409)
                ->setJSON(['error' => 'No active distribution batch. Ask an administrator to open one.']);
        }
        $batchId  = (int) $activeBatch['batch_id'];
        $tempLog  = model(TempAidDistributionModel::class);
        $payload  = [
            'control_no'    => $controlNo,
            'qr_code_image' => (new \App\Libraries\Qr\QrImageGenerator())->dataUri(
                config('QrCardSettings')->qrUrlPrefix . \App\Libraries\Qr\ControlNumber::format($controlNo)
            ),
            'aid_type_name' => (string) ($activeBatch['aid_type_name'] ?? 'Aid'),
        ];

        $existing = $tempLog->inBatch($controlNo, $batchId);
        if ($existing !== null) {
            return $this->response->setJSON($payload + [
                'ok'           => true,
                'logged'       => false,
                'duplicate'    => $existing,
                'myBatchCount' => $tempLog->countInBatch($batchId),
            ]);
        }

        $aidTypeId = (int) $activeBatch['aid_type_id'];
        $claimDate = date('Y-m-d');
        $aidId      = $tempLog->logAid([
            'control_no'  => $controlNo,
            'aid_type_id' => $aidTypeId,
            'claim_date'  => $claimDate,
            'batch_id'    => $batchId,
        ]);

        if ($aidId <= 0) {
            $existing = $tempLog->inBatch($controlNo, $batchId);
            if ($existing !== null) {
                return $this->response->setJSON($payload + [
                    'ok'           => true,
                    'logged'       => false,
                    'duplicate'    => $existing,
                    'myBatchCount' => $tempLog->countInBatch($batchId),
                ]);
            }

            return $this->response->setStatusCode(500)->setJSON(['error' => 'Failed to log the aid distribution.']);
        }

        return $this->response->setJSON($payload + [
            'ok'           => true,
            'logged'       => true,
            'duplicate'    => null,
            'myBatchCount' => $tempLog->countInBatch($batchId),
        ]);
    }

    /** POST scanner/void — removes one mistaken temporary scan. */
    public function voidScan(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        if (! $this->validate(['control_no' => 'required|is_natural_no_zero'])) {
            return $this->response->setStatusCode(422)
                ->setJSON(['errors' => $this->validator->getErrors()]);
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return $this->response->setStatusCode(409)
                ->setJSON(['error' => 'No active distribution batch.']);
        }

        $controlNo = (int) $this->request->getPost('control_no');
        $batchId   = (int) $activeBatch['batch_id'];
        $tempLog   = model(TempAidDistributionModel::class);
        if (! $tempLog->voidInBatch($controlNo, $batchId)) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Scan record not found in the active batch.']);
        }

        return $this->response->setJSON([
            'ok'           => true,
            'control_no'   => $controlNo,
            'myBatchCount' => $tempLog->countInBatch($batchId),
        ]);
    }
}
