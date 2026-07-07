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
 * Server-side DataTables list (GET {role}/manage-family/data).
 *
 * Powers the Manage Records DataTable (assets/js/dashboard/family-datatable.js).
 * Reuses the existing, untouched search models: MemberModel::searchFamilies()
 * for the family-heads scope and SearchModel::allMembers() for the whole-database
 * scope. Both are called with the optional, append-only $orderKey/$orderDirection
 * arguments for column sorting; everything else is the same query used elsewhere.
 * Row/envelope shaping lives in FamilyDataTablePresenter.
 */
class FamilyDataTableController extends BaseController
{
    use FamilyRequestContext;

    /** Returns the server-side DataTables payload for Manage Records. */
    public function dataTable()
    {
        $presenter = new FamilyDataTablePresenter(
            $this->dataTableRouteBase(),
            RoleAccess::normalizeRole((string) session()->get('role'))
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
                $total = $searchModel->countAllMembers('', ['status' => 'all']);
                $filtered = $searchModel->countAllMembers($keyword, $searchFilters);
                $rows = $searchModel->allMembers($keyword, $searchFilters, $length, $start, $orderKey, $orderDirection);
            } else {
                $memberModel = new MemberModel();
                $searchKeyword = $keyword === '' ? null : $keyword;
                $total = $memberModel->countSearchFamilies(null, 'all');
                $filtered = $memberModel->countSearchFamilies($searchKeyword, $status, $filters);
                $rows = $memberModel->searchFamilies($searchKeyword, $length, $start, $status, $filters, $orderKey, $orderDirection);
            }

            $sectorShortcodes = $this->dataTableSectorShortcodes();
            $headIdKey = $scope === 'all' ? 'headID' : 'memberID';
            $controlNumbers = model(\App\Models\Scanner\QrControlModel::class)->controlsForHeads(
                array_map(static fn (array $row): int => (int) ($row[$headIdKey] ?? 0), $rows)
            );
            $data = array_map(
                static fn (array $row): array => $presenter->row($row, $scope === 'all', $sectorShortcodes, $controlNumbers),
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
     * Only the name/address/birthday columns are sortable; everything else falls
     * back to the name column. The `date` parameter is intentionally NOT consulted.
     *
     * @return array{0: string, 1: string}
     */
    private function dataTableOrder(): array
    {
        $order = $this->request->getGet('order');

        // No column sort requested (the table's default) -> newest records first, so
        // a just-added or just-imported family is visible at the top of the list
        // instead of being sorted by surname into a large dataset. 'newest' is
        // unrecognized by applyMemberOrder(), which falls back to memberID DESC.
        if (! is_array($order) || ! isset($order[0]) || ! is_array($order[0])) {
            return ['newest', 'desc'];
        }

        $firstOrder = $order[0];
        $column = (int) ($firstOrder['column'] ?? 0);
        $direction = strtolower((string) ($firstOrder['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        // Column order: 0=QR, 1=name, 2=sector, 3=address, 4=birthday, 5=actions.
        // QR/sector/actions are non-orderable, so only address/birthday map here;
        // everything else (incl. the name column) falls back to the name sort.
        $orderKey = match ($column) {
            3 => 'address',
            4 => 'birthday',
            default => 'name',
        };

        return [$orderKey, $direction];
    }

    /** [sectorID => SHORTCODE] map for rendering the DataTable's Sector column. */
    private function dataTableSectorShortcodes(): array
    {
        $map = [];

        foreach ((new SectorModel())->getSectorOptions() as $sector) {
            $sectorId = (int) ($sector['sectorID'] ?? $sector['id'] ?? 0);
            $shortcode = trim((string) ($sector['shortcode'] ?? ''));

            if ($sectorId > 0 && $shortcode !== '') {
                $map[$sectorId] = mb_strtoupper($shortcode);
            }
        }

        return $map;
    }

    /** Role-aware route base for the DataTable action URLs. */
    private function dataTableRouteBase(): string
    {
        if (str_starts_with(uri_string(), 'employee/')) {
            return 'employee/manage-family';
        }

        if (str_starts_with(uri_string(), 'viewer/')) {
            return 'viewer/manage-family';
        }

        return 'admin/manage-family';
    }
}
