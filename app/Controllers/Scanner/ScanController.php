<?php

namespace App\Controllers\Scanner;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Scanner\AidDistributionModel;
use App\Models\Scanner\AidTypeModel;
use App\Models\Scanner\QrControlModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Scanner module: resolve a paper QR control number to a family, show its aid
 * history, and log a new aid distribution. Scanner/Admin/Developer only.
 *
 * - scan():   GET  scanner/scan          -> the mobile-first scan page.
 * - lookup(): GET  scanner/lookup/{num}  -> JSON {head, members, history}.
 * - logAid(): POST scanner/log           -> insert + audit, returns refreshed history.
 */
class ScanController extends BaseController
{
    public function scan(): ResponseInterface|string
    {
        $guard = RoleAccess::requireRole(['Scanner', 'Admin', 'Developer']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('Scanner/scan', [
            'aidTypes' => model(AidTypeModel::class)->active(),
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
            'aid_type_id' => 'required|is_natural_no_zero',
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

        $userId = (int) (session('user_id') ?? 0);
        model(AidDistributionModel::class)->logAid([
            'control_no'  => $controlNo,
            'memberID'    => $memberId,
            'aid_type_id' => (int) $this->request->getPost('aid_type_id'),
            'claim_date'  => $this->request->getPost('claim_date'),
            'userID'      => $userId,
        ]);

        (new AuditTrailsModel())->logAction(
            $userId,
            $memberId,
            'Logged aid distribution',
            'Control #' . $controlNo,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            'Aid type ID ' . (int) $this->request->getPost('aid_type_id') . ' on ' . $this->request->getPost('claim_date')
        );

        return $this->response->setJSON([
            'ok'      => true,
            'history' => model(AidDistributionModel::class)->historyFor($controlNo),
        ]);
    }
}
