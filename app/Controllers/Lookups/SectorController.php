<?php

namespace App\Controllers\Lookups;

use App\Controllers\BaseController;
use App\Controllers\Concerns\LookupControllerTrait;
use App\Libraries\RoleAccess;
use App\Models\Lookups\CategoryModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles the write/mutation actions for the `sector` lookup table, posted from
 * the admin sectors page. Read/listing is done by Admin\DashboardController::sectors;
 * every action here is Developer/Admin-only and redirects back to `admin/sectors`
 * with a flash message.
 */
class SectorController extends BaseController
{
    use LookupControllerTrait;

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
     * POST `admin/sectors/archive/{id}`: soft-archive a sector (hide, keep data) and
     * cascade the archive onto every active service that uses the sector as its
     * category (services.category = sector name), so a sector and the programs filed
     * under it retire together. Still allowed when the sector is assigned to members:
     * archiving only retires it from new selections; existing records keep the sector
     * (the family edit form preserves archived-but-assigned sectors). Permanent delete
     * is still guarded. Audits the action.
     */
    public function archive(int $sectorId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Sector table is not available.');
        }

        $sector = $model->find($sectorId);

        if (! $model->archive($sectorId)) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Unable to archive sector.');
        }

        $sectorName       = trim((string) ($sector['name'] ?? ''));
        $archivedAt       = (string) ($model->find($sectorId)['dt_deleted'] ?? '');
        $archivedServices = ($sectorName !== '' && $archivedAt !== '') ? (new ServiceModel())->archiveByCategory($sectorName, $archivedAt) : 0;

        $this->audit('SECTOR_ARCHIVE', 'Archived ' . $this->sectorLabel($sector, $sectorId) . '.' . $this->cascadeNote($archivedServices));

        return $this->redirectAdmin('admin/reference-data?tab=sectors', 'success', 'Sector archived successfully.' . $this->cascadeMessage($archivedServices));
    }

    /**
     * POST `admin/sectors/restore/{id}`: un-archive a previously archived sector and
     * cascade the restore onto the services its archive retired (matched by the sector
     * name + the shared archive timestamp), so the pair comes back together. Services
     * archived separately keep their archived state. Frontend: the "restore" control on
     * the archived sectors view.
     */
    public function restore(int $sectorId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new SectorModel();

        if (! $model->hasTable()) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors&status=archived', 'error', 'Sector table is not available.');
        }

        $sector     = $model->find($sectorId);
        $archivedAt = (string) ($sector['dt_deleted'] ?? '');

        if (! $model->restore($sectorId)) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors&status=archived', 'error', 'Unable to restore sector.');
        }

        $sectorName       = trim((string) ($sector['name'] ?? ''));
        $restoredServices = ($sectorName !== '' && $archivedAt !== '') ? (new ServiceModel())->restoreByCategoryArchivedAt($sectorName, $archivedAt) : 0;

        $this->audit('SECTOR_RESTORE', 'Restored ' . $this->sectorLabel($sector, $sectorId) . '.' . $this->cascadeNote($restoredServices, 'restored'));

        return $this->redirectAdmin('admin/reference-data?tab=sectors', 'success', 'Sector restored successfully.' . $this->cascadeMessage($restoredServices, 'restored'));
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
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Sector table is not available.');
        }

        if ($model->isInUse($sectorId)) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'This sector is already used by one or more records and cannot be deleted.');
        }

        $sector = $model->find($sectorId);

        // A sector doubles as a service category; block deleting one that still backs a
        // service's category label (archive stays allowed — it only retires new picks).
        if ($sector !== null && $model->usedAsServiceCategory((string) ($sector['name'] ?? ''))) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'This sector is used as a service category by one or more services and cannot be deleted. Reassign or archive those services first.');
        }

        if (! $model->delete($sectorId)) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Unable to delete sector.');
        }

        $this->audit('SECTOR_DELETE', 'Permanently deleted ' . $this->sectorLabel($sector, $sectorId) . '.');

        return redirect()->to(site_url('admin/reference-data?tab=sectors'))->with('success', 'Sector deleted successfully.');
    }

    /**
     * Shared create/update logic for sectors. Sectors are flat classifications
     * (no category), so this validates the code + name, blocks duplicate codes
     * (excluding the row being edited), saves via SectorModel, and audits.
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
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Sector table is not available.');
        }

        $data = [
            'shortcode' => strtoupper(trim((string) $this->request->getPost('shortcode'))),
            'name' => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($data['shortcode'] === '' && $sectorId !== null) {
            $existingSector = $model->find($sectorId);
            $data['shortcode'] = strtoupper(trim((string) ($existingSector['shortcode'] ?? '')));
        }

        if ($data['shortcode'] === '' || $data['name'] === '') {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Shortcode and name are required.');
        }

        // Block duplicate codes (excludes the current row on edit, so re-saving an
        // unchanged code is fine). Mirrors the client-side check in the sector modal.
        if ($model->shortcodeExists($data['shortcode'], $sectorId)) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Duplicate code "' . $data['shortcode'] . '". Please enter another code.');
        }

        if ($model->nameExists($data['name'], $sectorId)) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'Duplicate name "' . $data['name'] . '". Please enter another name.');
        }

        // Keep sectors and standalone service categories disjoint (Phase B): a sector
        // may not duplicate a Manage-Categories entry (FA/SWPS/EDA).
        if ((new CategoryModel())->activeCodeOrNameExists($data['shortcode'], $data['name'])) {
            return $this->redirectAdmin('admin/reference-data?tab=sectors', 'error', 'A service category already uses this code or name. Manage it under Manage Categories, or choose a different sector code/name.');
        }

        $isUpdate = $sectorId !== null;

        if (! $model->saveSectorRecord($data, $sectorId)) {
            return redirect()->to(site_url('admin/reference-data?tab=sectors'))->with('error', 'Unable to save sector.');
        }

        $this->audit(
            $isUpdate ? 'SECTOR_UPDATE' : 'SECTOR_CREATE',
            ($isUpdate ? 'Updated' : 'Created') . ' sector ' . $data['shortcode'] . ' (' . $data['name'] . ').'
        );

        $message = $isUpdate ? 'Sector updated successfully.' : 'Sector added successfully.';

        return $this->redirectAdmin('admin/reference-data?tab=sectors', 'success', $message);
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
}