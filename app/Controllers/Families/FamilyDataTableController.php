<?php

namespace App\Controllers\Families;

use App\Controllers\BaseController;
use App\Libraries\FamilyDataTablePresenter;
use App\Libraries\RoleAccess;
use App\Models\Families\MemberModel;
use App\Models\Lookups\SectorModel;
use App\Models\SearchModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Server-side DataTables list (GET records/data).
 *
 * Powers the Manage Records DataTable (assets/js/dashboard/family-datatable.js).
 * Reuses MemberModel::searchFamilies() (unchanged) for the family-heads scope,
 * and SearchModel::allMembersHeadIds()/countAllMembersHeads() for the whole-
 * database scope; both are called with the optional, append-only
 * $orderKey/$orderDirection arguments for column sorting. The table always
 * shows one row per household: allMembersHeadIds() groups the whole-database
 * search by headID, so a keyword match on a non-head member surfaces that
 * member's household, and LIMIT/OFFSET/recordsFiltered all operate on
 * households rather than the members that matched. Row/envelope shaping lives
 * in FamilyDataTablePresenter.
 */
class FamilyDataTableController extends BaseController
{
    use FamilyRequestContext;

    /**
     * Returns one page of the Manage Records table as a DataTables JSON payload:
     * search, sort, and paginate either the family heads or the whole database,
     * depending on the requested scope. Returns a 403 payload instead of HTML
     * when the viewer lacks family-view access.
     */
    public function dataTable()
    {
        $presenter = new FamilyDataTablePresenter(
            (string) RoleAccess::normalizeRole((string) session()->get('role'))
        );
        $draw = max(0, (int) $this->request->getGet('draw'));
        $guard = $this->requireFamilyViewAccess();

        if ($guard instanceof RedirectResponse) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON($presenter->payload($draw, 0, 0, [], 'You do not have permission to view family records.'));
        }

        $start = max(0, (int) $this->request->getGet('start'));
        $requestedLength = (int) $this->request->getGet('length');
        $length = in_array($requestedLength, [10, 25, 50, 100], true) ? $requestedLength : 25;
        $scope = strtolower(trim((string) $this->request->getGet('scope'))) === 'all' ? 'all' : 'heads';
        $keyword = trim((string) $this->request->getGet('q'));
        $dataTablesSearch = $this->request->getGet('search');

        if ($keyword === '' && is_array($dataTablesSearch)) {
            $keyword = trim((string) ($dataTablesSearch['value'] ?? ''));
        }

        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['all', 'active', 'archived'], true) ? $status : 'all';
        $filters = [
            'sectorID' => $this->request->getGet('sectorID'),
            'barangay' => $this->request->getGet('barangay'),
        ];
        [$orderKey, $orderDirection] = $this->dataTableOrder();

        try {
            if ($scope === 'all') {
                $searchModel = new SearchModel();
                $searchFilters = array_merge(['status' => $status], $filters);
                // Households, not members: allMembersHeadIds()/countAllMembersHeads()
                // group the same search by headID, so a keyword match on a non-head
                // member still surfaces that member's household, LIMIT/OFFSET slice
                // whole households, and recordsFiltered counts households too.
                $total = $searchModel->countAllMembersHeads('', ['status' => 'all']);
                $filtered = $searchModel->countAllMembersHeads($keyword, $searchFilters);
                $headIds = $searchModel->allMembersHeadIds($keyword, $searchFilters, $length, $start, $orderKey, $orderDirection);

                $headsById = [];

                if ($headIds !== []) {
                    foreach ((new MemberModel())->whereIn('memberID', $headIds)->findAll() as $head) {
                        $headsById[(int) $head['memberID']] = $head;
                    }
                }

                $rows = array_values(array_filter(array_map(
                    static fn (int $headId): ?array => $headsById[$headId] ?? null,
                    $headIds
                )));
            } else {
                $memberModel = new MemberModel();
                $searchKeyword = $keyword === '' ? null : $keyword;
                $total = $memberModel->countSearchFamilies(null, 'all');
                $filtered = $memberModel->countSearchFamilies($searchKeyword, $status, $filters);
                $rows = $memberModel->searchFamilies($searchKeyword, $length, $start, $status, $filters, $orderKey, $orderDirection);
            }

            $sectorShortcodes = (new SectorModel())->shortcodeMap();
            $pageHeadIds = array_map(static fn (array $row): int => (int) ($row['memberID'] ?? 0), $rows);
            $controlNumbers = model(\App\Models\Scanner\QrControlModel::class)->controlsForHeads($pageHeadIds);

            // One grouped query for every head on the page instead of a per-row count.
            // A head with no member rows still counts as one person, itself.
            $memberCounts = (new MemberModel())->memberCountsForHeads($pageHeadIds, $status);

            $data = array_map(
                static fn (array $row): array => $presenter->row(
                    $row,
                    $sectorShortcodes,
                    $controlNumbers,
                    (int) ($memberCounts[(int) $row['memberID']] ?? 1)
                ),
                $rows
            );

            return $this->response->setJSON($presenter->payload($draw, $total, $filtered, $data));
        } catch (Throwable $exception) {
            $this->auditSystemError('loading the family records table', $exception);

            return $this->response
                ->setStatusCode(500)
                ->setJSON($presenter->payload($draw, 0, 0, [], 'Unable to load family records.'));
        }
    }

    /**
     * Reads the DataTables order[] request into a [columnKey, direction] pair.
     * Sortable columns: qr (default, ascending), name, address; everything else
     * falls back to the name column. The `date` parameter is intentionally NOT
     * consulted.
     *
     * @return array{0: string, 1: string}
     */
    private function dataTableOrder(): array
    {
        $order = $this->request->getGet('order');

        // No column sort requested (fresh table, or third header click which
        // DataTables sends as an empty direction). Default order is the QR
        // control number ascending so the list always reads 1 to n.
        if (! is_array($order) || ! isset($order[0]) || ! is_array($order[0])) {
            return ['qr', 'asc'];
        }

        $firstOrder = $order[0];
        $column = (int) ($firstOrder['column'] ?? 0);
        $requestedDirection = strtolower((string) ($firstOrder['dir'] ?? ''));

        if ($requestedDirection === '') {
            return ['qr', 'asc'];
        }

        $direction = $requestedDirection === 'desc' ? 'desc' : 'asc';
        // Column order: 0=QR, 1=name, 2=members, 3=sector, 4=address, 5=actions.
        // Members and sector are non-orderable; unknown columns fall back to name.
        $orderKey = match ($column) {
            0 => 'qr',
            4 => 'address',
            default => 'name',
        };

        return [$orderKey, $direction];
    }
}
