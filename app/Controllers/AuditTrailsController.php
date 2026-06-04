<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use App\Models\AuditTrailsModel;
use CodeIgniter\HTTP\RedirectResponse;

class AuditTrailsController extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $data = [
            'auditTrails' => array_map(
                fn (array $audit): array => $this->formatAudit($audit),
                (new AuditTrailsModel())->auditTrails('', [], 50)
            ),
        ];

        if ($this->request->isAJAX() || (string) $this->request->getGet('partial') === '1') {
            return view('Logs/audit_trails', $data);
        }

        return view('Admin/dashboard', [
            'activePage' => 'audit-trails',
            'pageTitle' => 'Audit Trails',
            'workspaceUrl' => site_url('admin/audit-trails'),
        ]);
    }

    private function formatAudit(array $audit): array
    {
        return array_merge($audit, [
            'display_username' => $this->valueOrDash($audit['username'] ?? ''),
            'display_member' => $this->formatName($audit, 'member_name'),
            'display_action' => $this->valueOrDash($audit['user_action'] ?? ''),
            'display_description' => $this->valueOrDash($audit['description'] ?? ''),
        ]);
    }

    private function formatName(array $row, string $key): string
    {
        if (trim((string) ($row[$key] ?? '')) !== '') {
            return trim((string) $row[$key]);
        }

        return $this->valueOrDash(
            trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? ''))
        );
    }

    private function valueOrDash(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }
}
