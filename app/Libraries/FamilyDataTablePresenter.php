<?php

namespace App\Libraries;

use App\Libraries\Qr\ControlNumber;

/**
 * Shapes Manage Records rows into the server-side DataTables cell map consumed
 * by assets/js/dashboard/family-datatable.js. Pure presentation: the caller
 * (FamilyDataTableController) resolves the session role and passes it in - this
 * class never reads the request or session. Every record URL is the one flat
 * `records` path. The output HTML and the payload() envelope are frontend
 * contracts.
 */
class FamilyDataTablePresenter
{
    public function __construct(private readonly string $role) {}

    /**
     * Shapes one household into the DataTables cell map the client expects. One row
     * is one head of family; members are reached through the profile page, so the
     * table never flattens a household into several rows. $memberCount is the
     * household size including the head.
     *
     * @param array<int, array{code: string, name: string}> $sectorShortcodes sectorID => shortcode + full name, from SectorModel::shortcodeMap()
     * @param array<int, int>                               $controlNumbers   headID => qr_control.control_no
     */
    public function row(array $row, array $sectorShortcodes, array $controlNumbers, int $memberCount): array
    {
        $headId = (int) ($row['headID'] ?? $row['memberID'] ?? 0);
        $name = $this->displayName($row);

        return [
            'qr' => $this->qrCell((int) ($controlNumbers[$headId] ?? 0)),
            // Names are stored uppercase, so show them as stored. Re-casing here
            // would hide a casing bug rather than surface it.
            'name' => '<span class="entity-title">' . esc($name) . '</span>',
            'members' => (string) $memberCount,
            'sector' => ViewFormatter::sectorBadges($row['sectorID'] ?? null, $sectorShortcodes),
            'address' => esc((string) ($row['address'] ?? '')),
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

    /** QR NO. cell: plain row text, or a muted dash when no mapping exists. */
    private function qrCell(int $controlNo): string
    {
        if ($controlNo <= 0) {
            return '<span class="text-muted">-</span>';
        }

        return esc(ControlNumber::format($controlNo));
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
     * any viewer; Edit only to entry-access roles (Developer/Admin/Encoder);
     * Archive/Restore only to Developer/Admin. Empty string hides the menu.
     */
    private function actions(array $row, int $headId, string $displayName): string
    {
        if ($headId <= 0) {
            return '';
        }

        $canEdit = in_array($this->role, ['Developer', 'Admin', 'Encoder'], true);
        $canArchive = in_array($this->role, ['Developer', 'Admin'], true);
        $archived = trim((string) ($row['dt_deleted'] ?? '')) !== '';

        if ($archived && ! $canArchive) {
            return '';
        }

        // The trigger markup (the plain VIEW/EDIT links + archive/restore form)
        // lives in the view; this class only supplies the permission flags and URLs.
        return view('Family/row-actions', [
            'archived'       => $archived,
            'canEdit'        => $canEdit,
            'canArchive'     => $canArchive,
            'displayName'    => $displayName,
            // Two pages, two URLs: the bare record id prints the record read-only,
            // `/edit` is the form. EDIT is offered only to entry-access roles, and
            // the records-edit manifest key keeps everyone else off that route.
            'viewUrl'        => $archived ? '' : site_url('records/' . $headId),
            'updateUrl'      => (! $archived && $canEdit) ? site_url('records/' . $headId . '/edit') : '',
            'formAction'     => $canArchive ? site_url('records/' . $headId . '/' . ($archived ? 'restore' : 'archive')) : '',
            'actionLabel'    => $archived ? 'Restore' : 'Archive',
            'actionPast'     => $archived ? 'restored' : 'archived',
            'confirmMessage' => $archived
                ? 'Restore this record to the active list?'
                : 'Archive this record? This keeps the record in the database, marks it as archived, and hides it from active lists.',
        ]);
    }
}
