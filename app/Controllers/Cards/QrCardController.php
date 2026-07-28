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
 * - batch():  POST cards/generate  -> PDF or ZIP of cards for a filter.
 * - card():   GET  cards/card/{id} -> single-head card (reprint).
 * - lookup(): GET  cards/lookup/{control} -> redirect to the head record.
 *
 * The control number is the head's paper QR number, stored in qr_control (the
 * same source the scanner reads), so a printed card always resolves on scan.
 * Heads without a qr_control mapping are excluded from generation. Batch
 * generation writes ONE audit row.
 */
class QrCardController extends BaseController
{
    /**
     * POST cards/generate: prints a batch of QR access cards (PDF for one
     * card, ZIP for several) for every active head of family matching the
     * barangay/control-number filter. Developer/Admin only. Writes one audit
     * trail row for the whole batch, not one per card.
     */
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
        if (($from = (int) $this->request->getPost('from')) > 0) {
            $filter['controlFrom'] = $from;
        }
        if (($to = (int) $this->request->getPost('to')) > 0) {
            $filter['controlTo'] = $to;
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

    /**
     * GET cards/heads. JSON feed for the Control Numbers page: powers both
     * the Batch preview table and the Single-card head autocomplete. Returns the
     * full match count plus one page of rows, drawn from the same MemberModel
     * selection the printed PDF uses so preview and output never diverge.
     *
     * The preview pages server-side (page + per_page, matching the standard
     * footer); the autocomplete (mode=search) always takes the first 15.
     * Developer/Admin only.
     */
    public function heads(): ResponseInterface
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $mode = (string) $this->request->getGet('mode');

        if ($mode === 'search') {
            $perPage = 15;
            $page    = 1;
        } else {
            $perPage = (int) $this->request->getGet('per_page');
            $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
            $page    = max(1, (int) $this->request->getGet('page'));
        }

        $filter = ['limit' => $perPage, 'offset' => ($page - 1) * $perPage];
        if (($keyword = trim((string) $this->request->getGet('q'))) !== '') {
            $filter['keyword'] = $keyword;
        }
        if (($barangay = trim((string) $this->request->getGet('barangay'))) !== '') {
            $filter['barangay'] = $barangay;
        }
        if (($from = (int) $this->request->getGet('from')) > 0) {
            $filter['controlFrom'] = $from;
        }
        if (($to = (int) $this->request->getGet('to')) > 0) {
            $filter['controlTo'] = $to;
        }

        $model = model(MemberModel::class);
        $shape = static fn (array $h): array => [
            'memberID'  => $h['memberID'],
            'controlNo' => $h['controlNo'],
            'name'      => $h['fullname'],
            'barangay'  => $h['barangay'],
        ];
        $rows = array_map($shape, $model->headsForCards($filter));

        $countFilter = $filter;
        unset($countFilter['limit'], $countFilter['offset']);

        $count = $model->countHeadsForCards($countFilter);
        $totalPages = max(1, (int) ceil($count / $perPage));

        // A page can fall past the end when the filters narrow the selection;
        // re-query the last page instead of answering with an empty table.
        if ($page > $totalPages && $count > 0) {
            $page = $totalPages;
            $rows = array_map($shape, $model->headsForCards(
                ['limit' => $perPage, 'offset' => ($page - 1) * $perPage] + $countFilter
            ));
        }

        return $this->response->setJSON([
            'count'      => $count,
            'rows'       => $rows,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * GET cards/card/{id}: reprints a single head of family's QR access
     * card as a PDF. Developer/Admin only. Writes an audit trail row for the
     * reprint.
     */
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

        return redirect()->to(site_url('records/' . $headId));
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
