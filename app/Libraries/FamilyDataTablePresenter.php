<?php

namespace App\Libraries;

use App\Libraries\Qr\ControlNumber;

/**
 * Shapes Manage Records rows into the server-side DataTables cell map consumed
 * by assets/js/dashboard/family-datatable.js. Pure presentation: the caller
 * (FamilyDataTableController) resolves the route base and the session role and
 * passes them in — this class never reads the request or session. The output
 * HTML and the payload() envelope are frontend contracts.
 */
class FamilyDataTablePresenter
{
    public function __construct(
        private readonly string $routeBase,
        private readonly string $role,
    ) {
    }

    /**
     * Shapes one member row into the DataTables cell map the client expects
     * (name HTML, sector shortcodes, address, birthday, actions dropdown).
     *
     * @param array<int, string> $sectorShortcodes
     * @param array<int, int>    $controlNumbers   [headID => qr_control.control_no]
     */
    public function row(array $row, bool $allMembersScope, array $sectorShortcodes, array $controlNumbers = []): array
    {
        $memberId = (int) ($row['memberID'] ?? 0);
        $headId = $allMembersScope ? (int) ($row['headID'] ?? $memberId) : $memberId;
        $name = $this->displayName($row);
        $relationship = trim((string) ($row['relationship'] ?? ''));
        $nameHtml = '<span class="entity-title">' . esc(mb_strtoupper($name)) . '</span>';

        if ($allMembersScope && $relationship !== '') {
            $nameHtml .= '<small class="text-muted d-block">' . esc(mb_strtoupper($relationship)) . '</small>';
        }

        $controlNo = (int) ($controlNumbers[$headId] ?? 0);

        $sectors = [];

        foreach (SectorIds::normalize($row['sectorID'] ?? null) as $sectorId) {
            if (isset($sectorShortcodes[$sectorId])) {
                $sectors[] = $sectorShortcodes[$sectorId];
            }
        }

        $birthday = strtotime((string) ($row['birthday'] ?? ''));

        return [
            'qr' => $this->qrCell($controlNo),
            'name' => $nameHtml,
            'sector' => esc(implode(', ', array_values(array_unique($sectors)))),
            'address' => esc(mb_strtoupper((string) ($row['address'] ?? ''))),
            'birthday' => $birthday === false ? '-' : date('Y-m-d', $birthday),
            'actions' => $this->actions($row, $headId, $name),
        ];
    }

    /** Standard DataTables JSON envelope (+ optional error message). */
    public function payload(int $draw, int $total, int $filtered, array $data, ?string $error = null): array
    {
        $payload = [
            'draw' => $draw,
            'recordsTotal' => max(0, $total),
            'recordsFiltered' => max(0, $filtered),
            'data' => $data,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        return $payload;
    }

    /**
     * QR NO. cell: a modest Bootstrap badge with the zero-padded control number,
     * or a muted dash when the family has no QR mapping yet.
     */
    private function qrCell(int $controlNo): string
    {
        if ($controlNo <= 0) {
            return '<span class="text-muted">&mdash;</span>';
        }

        return '<span class="badge bg-light text-dark border fw-semibold fs-6 text-nowrap">'
            . esc(ControlNumber::format($controlNo)) . '</span>';
    }

    /** "Surname Suffix, Firstname M." display name for a member row. */
    private function displayName(array $row): string
    {
        $lastName = trim((string) ($row['lastname'] ?? ''));
        $suffix = trim((string) ($row['suffix'] ?? ''));
        $firstName = trim((string) ($row['firstname'] ?? ''));
        $middleName = trim((string) ($row['middlename'] ?? ''));
        $surname = trim($lastName . ($suffix !== '' ? ' ' . $suffix : ''));
        $givenName = trim($firstName . ($middleName !== '' ? ' ' . mb_substr($middleName, 0, 1) . '.' : ''));

        return $surname !== '' && $givenName !== '' ? $surname . ', ' . $givenName : trim($surname . ' ' . $givenName);
    }

    /**
     * Builds the per-row Actions dropdown HTML for the DataTable. View is shown to
     * any viewer; Update only to entry-access roles (Developer/Admin/Employee);
     * Archive/Restore only to Developer/Admin. Empty string hides the menu.
     */
    private function actions(array $row, int $headId, string $displayName): string
    {
        if ($headId <= 0) {
            return '';
        }

        $canEdit = in_array($this->role, ['Developer', 'Admin', 'Employee'], true);
        $canArchive = in_array($this->role, ['Developer', 'Admin'], true);
        $archived = trim((string) ($row['dt_deleted'] ?? '')) !== '';

        if ($archived && ! $canArchive) {
            return '';
        }

        $routeBase = $this->routeBase;

        // The trigger markup (modal callers + archive/restore form) lives in the
        // view; this class only supplies the permission flags and URLs.
        return view('Family/row-actions', [
            'archived'       => $archived,
            'canEdit'        => $canEdit,
            'canArchive'     => $canArchive,
            'displayName'    => $displayName,
            'viewUrl'        => $archived ? '' : site_url($routeBase . '/view/' . $headId . '?partial=1'),
            'updateUrl'      => (! $archived && $canEdit) ? site_url($routeBase . '/create?partial=1&mode=update&id=' . $headId) : '',
            'formAction'     => $canArchive ? site_url($routeBase . '/' . ($archived ? 'restore' : 'archive') . '/' . $headId) : '',
            'actionLabel'    => $archived ? 'Restore' : 'Archive',
            'actionPast'     => $archived ? 'restored' : 'archived',
            'confirmMessage' => $archived
                ? 'Restore this record to the active list?'
                : 'Archive this record? This keeps the record in the database, marks it as archived, and hides it from active lists.',
        ]);
    }
}
