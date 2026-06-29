<?php

namespace App\Controllers\Cards;

use App\Controllers\BaseController;
use App\Libraries\Qr\ControlNumber;
use App\Libraries\Qr\QrCardPdfGenerator;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Families\MemberModel;
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
 * The control number is derived from memberID (see ControlNumber), so there is
 * one card per head and no stored code. Batch generation writes ONE audit row.
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
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'No heads of family match the selected filter.']);
        }

        if (count($heads) > $settings->maxQuantity) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'The selection covers ' . count($heads)
                    . ' cards, exceeding the maximum of ' . $settings->maxQuantity . ' per batch.']);
        }

        try {
            $result = (new QrCardPdfGenerator())->generate($heads);
        } catch (\Throwable $error) {
            log_message('error', 'QR card generation failed: {message}', ['message' => $error->getMessage()]);

            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Generation failed. Please try again, or contact support.']);
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

        return $this->streamDownload($result);
    }

    public function lookup(string $control): \CodeIgniter\HTTP\RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $memberID = ControlNumber::parse($control);
        if ($memberID === null || model(MemberModel::class)->findHead($memberID) === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Unknown control number.');
        }

        return redirect()->to(site_url('admin/manage-family/view/' . $memberID));
    }

    private function streamDownload(array $result): ResponseInterface
    {
        $contentType = $result['type'] === 'zip' ? 'application/zip' : 'application/pdf';

        return $this->response->setStatusCode(200)
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setBody($result['bytes']);
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
}
