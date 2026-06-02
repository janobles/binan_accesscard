<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\AuditTrailsModel;
use App\Models\DashboardModel;
use App\Models\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;

class MainLayoutController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Admin/mainlayout', $this->dashboardViewData());
        }

        return view('Admin/dashboard', [
            'activePage' => 'dashboard',
            'pageTitle' => 'Dashboard',
        ]);
    }

    private function dashboardViewData(): array
    {
        $dashboardModel = new DashboardModel();

        return [
            'stats' => array_merge([
                'families' => 0,
                'members' => 0,
                'sectors' => 0,
                'assistance' => 0,
            ], $dashboardModel->stats()),
            'recentFamilies' => array_map(
                fn (array $family): array => $this->formatFamily($family),
                $dashboardModel->recentFamilies(5)
            ),
            'recentAudits' => array_map(
                fn (array $audit): array => $this->formatAudit($audit),
                (new AuditTrailsModel())->getRecent(5)
            ),
            'sectorOptions' => (new SectorModel())->getSectorOptions(),
        ];
    }

    private function formatFamily(array $family): array
    {
        return array_merge($family, [
            'display_name' => $this->formatName($family),
            'display_date' => $this->formatDate($family['dt_created'] ?? ''),
            'display_time' => $this->formatTime($family['dt_created'] ?? ''),
        ]);
    }

    private function formatAudit(array $audit): array
    {
        return array_merge($audit, [
            'display_username' => $this->valueOrDash($audit['username'] ?? ''),
            'display_member' => $this->formatName($audit, 'member_name'),
            'display_action' => $this->valueOrDash($audit['user_action'] ?? ''),
            'display_description' => $this->valueOrDash($audit['description'] ?? ''),
            'display_date' => $this->formatDate($audit['dt_created'] ?? ''),
            'display_time' => $this->formatTime($audit['dt_created'] ?? ''),
        ]);
    }

    private function formatName(array $row, string $key = ''): string
    {
        if ($key !== '' && trim((string) ($row[$key] ?? '')) !== '') {
            return trim((string) $row[$key]);
        }

        return $this->valueOrDash(
            trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? ''))
        );
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
