<?php

namespace App\Controllers\Lookups;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SectorIds;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Lookups\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Handles the write/mutation actions for the `sector` lookup table, posted from
 * the admin sectors page. Read/listing is done by Workspace\Home::adminSectors;
 * every action here is Developer/Admin-only and redirects back to `admin/sectors`
 * with a flash message.
 */
class SectorController extends BaseController
{
    /**
     * POST `admin/sectors/create`: add a new sector. Delegates to saveSector().
     * Frontend: the "Add sector" modal form.
     */
    public function create(): RedirectResponse
    {
        return $this->saveSector();
    }

    /**
     * POST `admin/sectors/update/{id}`: edit an existing sector. Delegates to
     * saveSector() with the id. Frontend: the "Edit sector" modal form.
     */
    public function update(int $sectorId): RedirectResponse
    {
        return $this->saveSector($sectorId);
    }

    /**
     * POST `admin/sectors/archive/{id}`: soft-archive a sector (hide, keep data).
     * Refuses if the sector is still assigned to any member; audits the action.
     */
    public function archive(int $sectorId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Sector table is not available.');
        }

        if ($this->sectorIsUsed($sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'This sector is assigned to one or more records and cannot be archived. Reassign them first.');
        }

        $sector = $model->find($sectorId);

        if (! $model->archive($sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Unable to archive sector.');
        }

        $this->audit('SECTOR_ARCHIVE', 'Archived ' . $this->sectorLabel($sector, $sectorId) . '.');

        return $this->redirectAdmin('admin/sectors', 'success', 'Sector archived successfully.');
    }

    /**
     * POST `admin/sectors/restore/{id}`: un-archive a previously archived sector
     * and audit it. Frontend: the "restore" control on the archived sectors view.
     */
    public function restore(int $sectorId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/sectors?status=archived', 'error', 'Sector table is not available.');
        }

        $sector = $model->find($sectorId);

        if (! $model->restore($sectorId)) {
            return $this->redirectAdmin('admin/sectors?status=archived', 'error', 'Unable to restore sector.');
        }

        $this->audit('SECTOR_RESTORE', 'Restored ' . $this->sectorLabel($sector, $sectorId) . '.');

        return $this->redirectAdmin('admin/sectors', 'success', 'Sector restored successfully.');
    }

    /**
     * POST `admin/sectors/delete/{id}`: permanently delete a sector. Blocked if
     * the sector is in use by any member; audits the hard delete.
     */
    public function delete(int $sectorId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Sector table is not available.');
        }

        if ($this->sectorIsUsed($sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'This sector is already used by one or more records and cannot be deleted.');
        }

        $sector = $model->find($sectorId);

        if (! $model->delete($sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Unable to delete sector.');
        }

        $this->audit('SECTOR_DELETE', 'Permanently deleted ' . $this->sectorLabel($sector, $sectorId) . '.');

        return redirect()->to(site_url('admin/sectors'))->with('success', 'Sector deleted successfully.');
    }

    /**
     * Shared create/update logic for sectors. Resolves the shortcode (including
     * the "__other__" custom option), validates required fields, blocks duplicate
     * codes (excluding the row being edited), saves via SectorModel, and audits.
     * $sectorId null = create, otherwise update.
     */
    private function saveSector(?int $sectorId = null): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Sector table is not available.');
        }

        $shortcode = trim((string) $this->request->getPost('shortcode'));

        if ($shortcode === '__other__') {
            $shortcode = trim((string) $this->request->getPost('shortcode_other'));
        }

        $data = [
            'shortcode' => strtoupper($shortcode),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['shortcode'] === '' && $sectorId !== null) {
            $existingSector = $model->find($sectorId);
            $data['shortcode'] = strtoupper(trim((string) ($existingSector['shortcode'] ?? '')));
        }

        if ($data['shortcode'] === '' && str_contains(strtoupper($data['name']), 'REGISTERED OSCA')) {
            $data['shortcode'] = 'OSCA1';
        }

        if ($data['shortcode'] === '' || $data['name'] === '') {
            return $this->redirectAdmin('admin/sectors', 'error', 'Shortcode and name are required.');
        }

        // Block duplicate codes (excludes the current row on edit, so re-saving an
        // unchanged code is fine). Mirrors the client-side check in the sector modal.
        if ($model->shortcodeExists($data['shortcode'], $sectorId)) {
            return $this->redirectAdmin('admin/sectors', 'error', 'Duplicate code "' . $data['shortcode'] . '". Please enter another code.');
        }

        $isUpdate = $sectorId !== null;

        if (! $model->saveSectorRecord($data, $sectorId)) {
            return redirect()->to(site_url('admin/sectors'))->with('error', 'Unable to save sector.');
        }

        $this->audit(
            $isUpdate ? 'SECTOR_UPDATE' : 'SECTOR_CREATE',
            ($isUpdate ? 'Updated' : 'Created') . ' sector ' . $data['shortcode'] . ' (' . $data['name'] . ').'
        );

        $message = $isUpdate ? 'Sector updated successfully.' : 'Sector added successfully.';

        return $this->redirectAdmin('admin/sectors', 'success', $message);
    }

    /**
     * True if any `member` row references this sector ID (sectorID stores a JSON
     * array, matched via SectorIds::containsCondition). Guards archive/delete.
     */
    private function sectorIsUsed(int $sectorId): bool
    {
        $db = db_connect();

        if (! $db->tableExists('member')) {
            return false;
        }

        return $db->table('member')
            ->where(SectorIds::containsCondition($sectorId, 'sectorID'), null, false)
            ->countAllResults() > 0;
    }

    /**
     * Role guard for archive/restore/delete: returns a redirect for non
     * Developer/Admin users, or null to proceed.
     */
    private function ensureAdminAccess(): ?RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        return $guard instanceof RedirectResponse ? $guard : null;
    }

    /**
     * Builds a redirect back to an admin path carrying a typed flash message
     * (e.g. 'success'/'error'); keeps the redirect+flash pattern in one place.
     */
    private function redirectAdmin(string $path, string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url($path))->with($type, $message);
    }

    /**
     * Human-readable sector label for audit descriptions, e.g. "SC1 (Senior Citizen) #5".
     */
    private function sectorLabel(?array $sector, int $sectorId): string
    {
        $shortcode = trim((string) ($sector['shortcode'] ?? ''));
        $name = trim((string) ($sector['name'] ?? ''));
        $label = trim($shortcode . ($name !== '' ? ' (' . $name . ')' : ''));

        return ($label === '' ? 'sector' : 'sector ' . $label) . ' #' . $sectorId;
    }

    /**
     * Write a sector action to the audit trail. Sector actions have no affected
     * member, so memberID is null (audit_trails.memberID is nullable).
     */
    private function audit(string $action, string $description): void
    {
        $auditModel = new AuditTrailsModel();

        if (! $auditModel->hasTable()) {
            return;
        }

        try {
            $auditModel->logAction(
                (int) session()->get('user_id'),
                null,
                $action,
                $description,
                $this->request->getIPAddress(),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (Throwable $exception) {
            log_message('error', 'Audit trail skipped: ' . $exception->getMessage());
        }
    }
}