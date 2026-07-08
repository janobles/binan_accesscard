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
 * personal counter. Aid type is no longer chosen per-session — it comes from
 * the active distribution batch (set by an admin when the batch is opened).
 *
 * - scan():        GET  scanner/scan        -> lookup UI; empty state when no batch is open.
 * - performance(): GET  scanner/performance  -> this kiosk's own live metrics.
 * - stats():       GET  scanner/stats        -> JSON own-performance snapshot for polling.
 * - lookup():      GET  scanner/lookup/{num} -> JSON {head, members, history}.
 * - logAid():      POST scanner/log          -> insert + audit; 409 when no open batch;
 *                                               returns refreshed history + myBatchCount.
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
                ? ['aid_type_id' => (int) $activeBatch['aid_type_id'], 'name' => (string) ($activeBatch['aid_type_name'] ?? 'Aid')]
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

        $userId = (int) (session('user_id') ?? 0);
        $stats  = model(AidStatsModel::class);

        return view('Scanner/performance', [
            'pageTitle'    => 'My Performance',
            'username'     => session('username') ?? 'Scanner',
            'user'         => SessionAccount::user(),
            'accountLevelLabel' => SessionAccount::levelLabel(),
            'activeBatch'  => $active,
            'batches'      => $batches,
            'batchId'      => $batchId,
            'mine'         => $batchId > 0 ? ($stats->perScanner($batchId, $userId)[0] ?? ['families' => 0, 'handouts' => 0]) : ['families' => 0, 'handouts' => 0],
            'byAidType'    => $batchId > 0 ? $stats->byAidType($batchId) : [],
        ]);
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

        $batchId = (int) $activeBatch['batch_id'];
        $rows    = model(AidStatsModel::class)->perScanner($batchId, $userId);
        $mine    = $rows[0] ?? ['families' => 0, 'handouts' => 0];

        return $this->response->setJSON([
            'batch'    => ['id' => $batchId, 'name' => (string) $activeBatch['name'], 'aid_type' => (string) ($activeBatch['aid_type_name'] ?? '')],
            'families' => (int) ($mine['families'] ?? 0),
            'handouts' => (int) ($mine['handouts'] ?? 0),
            'updated'  => date('c'),
        ]);
    }

    public function lookup(int $controlNo): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'QR control number is not registered.']);
        }

        $members = new MemberModel();
        $head    = $members->findHead($headId);
        if ($head === null) {
            log_message('error', 'Scanner lookup: control {c} maps to missing head {h}', ['c' => $controlNo, 'h' => $headId]);
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Family record unavailable.']);
        }

        return $this->response->setJSON([
            'control_no' => $controlNo,
            'head'       => $head,
            'members'    => $members->familyMembers($headId),
            'history'    => model(AidDistributionModel::class)->historyFor($controlNo),
        ]);
    }

    public function logAid(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden.']);
        }

        $rules = [
            'control_no'  => 'required|is_natural_no_zero',
            'memberID'    => 'required|is_natural_no_zero',
            'claim_date'  => 'required|valid_date[Y-m-d]',
        ];
        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)
                ->setJSON(['errors' => $this->validator->getErrors()]);
        }

        $controlNo = (int) $this->request->getPost('control_no');
        $memberId  = (int) $this->request->getPost('memberID');

        // Guard: the claimant must belong to the family the QR maps to.
        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'QR control number is not registered.']);
        }
        $memberIds = array_column((new MemberModel())->familyMembers($headId), 'memberID');
        if (! in_array($memberId, array_map('intval', $memberIds), true)) {
            return $this->response->setStatusCode(422)->setJSON(['errors' => ['memberID' => 'Claimant is not part of this family.']]);
        }

        $activeBatch = model(DistributionBatchModel::class)->activeBatch();
        if ($activeBatch === null) {
            return $this->response->setStatusCode(409)
                ->setJSON(['errors' => ['general' => 'No active distribution batch. Ask an administrator to open one.']]);
        }

        $aidTypeId = (int) $activeBatch['aid_type_id'];
        $userId    = (int) (session('user_id') ?? 0);

        // The insert and its audit row must land together: without a shared
        // transaction, a handout could get logged with no audit trail (or an
        // audit row could survive a rolled-back handout).
        $db = db_connect();
        $db->transStart();

        $aidId = model(AidDistributionModel::class)->logAid([
            'control_no'  => $controlNo,
            'memberID'    => $memberId,
            'aid_type_id' => $aidTypeId,
            'claim_date'  => $this->request->getPost('claim_date'),
            'userID'      => $userId,
            'batch_id'    => (int) $activeBatch['batch_id'],
        ]);

        $audited = $aidId > 0 && (new AuditTrailsModel())->logAction(
            $userId,
            $memberId,
            'Logged aid distribution',
            'Control #' . $controlNo,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            'Aid type ID ' . $aidTypeId . ' on ' . $this->request->getPost('claim_date')
        );

        if (! $audited) {
            $db->transRollback();

            return $this->response->setStatusCode(500)->setJSON(['errors' => ['general' => 'Failed to log the aid distribution.']]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setStatusCode(500)->setJSON(['errors' => ['general' => 'Failed to log the aid distribution.']]);
        }

        return $this->response->setJSON([
            'ok'           => true,
            'history'      => model(AidDistributionModel::class)->historyFor($controlNo),
            'myBatchCount' => model(AidDistributionModel::class)->familiesForUserInBatch($userId, (int) $activeBatch['batch_id']),
        ]);
    }
}
