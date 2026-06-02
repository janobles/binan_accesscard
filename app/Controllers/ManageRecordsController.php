<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\MemberModel;
use App\Models\SearchModel;
use App\Models\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;

class ManageRecordsController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $state = $this->recordsState();

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/managerecord', $this->recordsViewData($state));
        }

        return view('Admin/dashboard', [
            'activePage' => 'family-manage',
            'pageTitle' => 'Manage Records',
            'workspaceUrl' => $this->recordsUrl($state),
        ]);
    }

    private function recordsState(): array
    {
        $status = strtolower(trim((string) $this->request->getGet('status'))) === 'archived'
            ? 'archived'
            : 'active';
        $searchScope = strtolower(trim((string) $this->request->getGet('search_scope'))) === 'all'
            ? 'all'
            : 'heads';
        $keyword = trim((string) $this->request->getGet('q'));
        $sectorId = trim((string) $this->request->getGet('sectorID'));
        $date = trim((string) $this->request->getGet('date'));
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';

        return [
            'status' => $status,
            'searchScope' => $searchScope,
            'keyword' => $keyword,
            'sectorId' => $sectorId,
            'date' => $date,
            'page' => max(1, (int) $this->request->getGet('page')),
        ];
    }

    private function recordsViewData(array $state): array
    {
        $status = $state['status'];
        $searchScope = $state['searchScope'];
        $keyword = $state['keyword'];
        $sectorId = $state['sectorId'];
        $date = $state['date'];
        $page = $state['page'];
        $perPage = 25;
        $filters = [
            'status' => $status,
            'sectorID' => $sectorId,
            'date' => $date,
        ];

        if ($searchScope === 'all') {
            $model = new SearchModel();
            $totalRecords = $model->countAllMembers($keyword, $filters);
            $totalPages = max(1, (int) ceil($totalRecords / $perPage));
            $page = min($page, $totalPages);
            $records = $model->allMembers($keyword, $filters, $perPage, ($page - 1) * $perPage);
        } else {
            $model = new MemberModel();
            $searchKeyword = $keyword === '' ? null : $keyword;
            $totalRecords = $model->countSearchFamilies($searchKeyword, $status === 'archived', $filters);
            $totalPages = max(1, (int) ceil($totalRecords / $perPage));
            $page = min($page, $totalPages);
            $records = $model->searchFamilies($searchKeyword, $perPage, ($page - 1) * $perPage, $status === 'archived', $filters);
        }

        $viewData = [
            'status' => $status,
            'searchScope' => $searchScope,
            'keyword' => $keyword,
            'sectorId' => $sectorId,
            'date' => $date,
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
            'records' => array_map(fn (array $record): array => $this->formatRecord($record), $records),
            'page' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'fromRecord' => $totalRecords === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'toRecord' => min($totalRecords, $page * $perPage),
        ];

        return array_merge($viewData, [
            'activeUrl' => $this->recordsUrl(array_merge($viewData, ['status' => 'active', 'page' => 1])),
            'archivedUrl' => $this->recordsUrl(array_merge($viewData, ['status' => 'archived', 'page' => 1])),
            'previousUrl' => $page > 1
                ? $this->recordsUrl(array_merge($viewData, ['page' => $page - 1]))
                : null,
            'nextUrl' => $page < $totalPages
                ? $this->recordsUrl(array_merge($viewData, ['page' => $page + 1]))
                : null,
        ]);
    }

    private function formatRecord(array $record): array
    {
        return array_merge($record, [
            'display_name' => $this->valueOrDash(
                trim((string) ($record['firstname'] ?? '') . ' ' . (string) ($record['lastname'] ?? ''))
            ),
            'display_sector' => $this->valueOrDash($record['sector_name'] ?? ''),
            'display_date' => $this->formatDate($record['dt_created'] ?? ''),
            'display_time' => $this->formatTime($record['dt_created'] ?? ''),
        ]);
    }

    private function recordsUrl(array $state): string
    {
        $query = array_filter([
            'status' => ($state['status'] ?? 'active') === 'archived' ? 'archived' : null,
            'q' => trim((string) ($state['keyword'] ?? '')) ?: null,
            'sectorID' => trim((string) ($state['sectorId'] ?? '')) ?: null,
            'date' => trim((string) ($state['date'] ?? '')) ?: null,
            'search_scope' => ($state['searchScope'] ?? 'heads') === 'all' ? 'all' : null,
            'page' => (int) ($state['page'] ?? 1) > 1 ? (int) $state['page'] : null,
        ], static fn (mixed $value): bool => $value !== null);

        return site_url('admin/manage-records') . ($query === [] ? '' : '?' . http_build_query($query));
    }

    private function formatDate(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '-' : date('Y-m-d', $timestamp);
    }

    private function formatTime(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '-' : date('h:i A', $timestamp);
    }

    private function valueOrDash(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }
}
