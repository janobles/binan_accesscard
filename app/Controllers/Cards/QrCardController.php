<?php

namespace App\Controllers\Cards;

use App\Controllers\BaseController;
use App\Libraries\Qr\ControlNumber;
use App\Libraries\Qr\QrCardPdfGenerator;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
use App\Models\Scanner\QrControlModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * QR access cards for family heads. Decides which heads to render and streams
 * the result; the PDF/ZIP is built by App\Libraries\Qr\QrCardPdfGenerator.
 *
 * - batch():  POST admin/cards/generate  -> PDF or ZIP of cards for a filter.
 * - card():   GET  admin/cards/card/{id} -> single-head card (reprint).
 * - lookup(): GET  admin/cards/lookup/{control} -> redirect to the head record.
 *
 * The control number is the head's paper QR number, stored in qr_control (the
 * same source the scanner reads), so a printed card always resolves on scan.
 * Heads without a qr_control mapping are excluded from generation. Batch
 * generation writes ONE audit row.
 */
class QrCardController extends BaseController
{
    public function batch(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $filter = [];
        if (($barangay = trim((string) $this->request->getPost('barangay'))) !== '') {
            $filter['barangay'] = $barangay;
        }
        if (($sectorID = (int) $this->request->getPost('sectorID')) > 0) {
            $filter['sectorID'] = $sectorID;
        }

        $heads    = model(MemberModel::class)->headsForCards($filter);
        $settings = config('QrCardSettings');

        if ($heads === []) {
            return $this->withFreshCsrf($this->response->setStatusCode(400)
                ->setJSON(['error' => 'No heads of family match the selected filter.']));
        }

        if (count($heads) > $settings->maxQuantity) {
            return $this->withFreshCsrf($this->response->setStatusCode(400)
                ->setJSON(['error' => 'The selection covers ' . count($heads)
                    . ' cards, exceeding the maximum of ' . $settings->maxQuantity . ' per batch.']));
        }

        try {
            $result = (new QrCardPdfGenerator())->generate($heads);
        } catch (\Throwable $error) {
            log_message('error', 'QR card generation failed: {message}', ['message' => $error->getMessage()]);

            return $this->withFreshCsrf($this->response->setStatusCode(500)
                ->setJSON(['error' => 'Generation failed. Please try again, or contact support.']));
        }

        $this->recordBatchAudit(count($heads), $filter);

        return $this->streamDownload($result);
    }

    public function card(int $memberID): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = model(MemberModel::class);
        $head  = $model->findHead($memberID);
        if ($head === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Not an active head of family.');
        }

        $single = $model->headsForCards(['memberID' => $memberID]);
        if ($single === []) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Head not found.');
        }

        try {
            $result = (new QrCardPdfGenerator())->generate($single);
        } catch (\Throwable $error) {
            log_message('error', 'QR card generation failed: {message}', ['message' => $error->getMessage()]);

            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Generation failed. Please try again.']);
        }

        $this->recordReprintAudit($memberID);

        return $this->streamDownload($result);
    }

    /**
     * Resolves any active member's control number to the family head record.
     * Scanning a head's own id or any non-head member's id both land on the
     * family head's view page. Returns 404 for unknown or inactive members.
     */
    public function lookup(string $control): \CodeIgniter\HTTP\RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $controlNo = ControlNumber::parse($control);
        if ($controlNo === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Unknown control number.');
        }

        // The printed control number maps to a head via qr_control (same source the
        // scanner uses), so a scanned card always resolves to the right family.
        $headId = model(QrControlModel::class)->headForControl($controlNo);
        if ($headId === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Unknown control number.');
        }

        return redirect()->to(site_url('admin/manage-family/view/' . $headId));
    }

    private function streamDownload(array $result): ResponseInterface
    {
        $contentType = $result['type'] === 'zip' ? 'application/zip' : 'application/pdf';

        return $this->withFreshCsrf($this->response->setStatusCode(200)
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setBody($result['bytes']));
    }

    /**
     * batch() streams a binary PDF/ZIP, so the rotated CSRF token can't ride in a
     * JSON body (the pattern used elsewhere, e.g. FamilyController). Carrying it
     * as a response header lets the batch form's JS refresh its hidden field
     * after every request, success or failure, so a second submit doesn't 403.
     */
    private function withFreshCsrf(ResponseInterface $response): ResponseInterface
    {
        return $response->setHeader('X-CSRF-TOKEN', csrf_hash());
    }

    private function recordBatchAudit(int $cardCount, array $filter): void
    {
        $scope = $filter === [] ? 'all active heads' : http_build_query($filter);
        (new AuditTrailsModel())->logAction(
            (int) session()->get('user_id'),
            null, // batch issuance is not tied to a single member
            'Generated QR cards',
            sprintf('Generated %d QR access card(s) for %s.', $cardCount, $scope),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );
    }

    private function recordReprintAudit(int $memberID): void
    {
        (new AuditTrailsModel())->logAction(
            (int) session()->get('user_id'),
            $memberID,
            'Reprinted QR card',
            sprintf('Reprinted QR access card for head #%d.', $memberID),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );
    }
}
