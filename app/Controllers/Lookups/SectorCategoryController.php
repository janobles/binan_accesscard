<?php

namespace App\Controllers\Lookups;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Libraries\SectorCategoryStore;
use App\Models\Audit\AuditTrailsModel;
use App\Support\FamilyProfilingFormV2;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Names/renames custom sector categories from the "Manage Categories" modal on
 * the admin sectors page. Custom category names live in a JSON file
 * (App\Libraries\SectorCategoryStore) — no database table is involved. Official
 * categories (SC, PWD, …) keep their fixed names and cannot be edited here.
 * Every action is Developer/Admin-only and redirects back to `admin/sectors`.
 */
class SectorCategoryController extends BaseController
{
    /**
     * POST `admin/sector-categories/save`: set or rename the display name for a
     * custom category prefix. Frontend: the "Manage Categories" modal forms.
     */
    public function save(): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $prefix = strtoupper(trim((string) $this->request->getPost('prefix')));
        $name = trim((string) $this->request->getPost('name'));

        if ($prefix === '' || preg_match('/^[A-Z]+$/', $prefix) !== 1) {
            return $this->redirect('error', 'Category code must be letters only (e.g. NEW).');
        }

        if (isset(FamilyProfilingFormV2::SECTOR_CATEGORIES[$prefix])) {
            return $this->redirect('error', 'Category "' . $prefix . '" is an official category and cannot be renamed.');
        }

        if ($name === '') {
            return $this->redirect('error', 'Category name is required.');
        }

        if (! SectorCategoryStore::save($prefix, $name)) {
            return $this->redirect('error', 'Unable to save category name.');
        }

        $this->audit('SECTOR_CATEGORY_SAVE', 'Named sector category ' . $prefix . ' as "' . $name . '".');

        return $this->redirect('success', 'Category name saved.');
    }

    /**
     * POST `admin/sector-categories/delete`: remove a custom category name so the
     * prefix reverts to its bare code. Does not touch any sector.
     */
    public function delete(): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $prefix = strtoupper(trim((string) $this->request->getPost('prefix')));

        if ($prefix === '') {
            return $this->redirect('error', 'Missing category code.');
        }

        if (isset(FamilyProfilingFormV2::SECTOR_CATEGORIES[$prefix])) {
            return $this->redirect('error', 'Official categories cannot be removed.');
        }

        if (! SectorCategoryStore::delete($prefix)) {
            return $this->redirect('error', 'Unable to remove category name.');
        }

        $this->audit('SECTOR_CATEGORY_DELETE', 'Removed custom name for sector category ' . $prefix . '.');

        return $this->redirect('success', 'Category name removed.');
    }

    /** Redirect back to the sectors page with a typed flash message. */
    private function redirect(string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url('admin/sectors'))->with($type, $message);
    }

    /**
     * Write a category action to the audit trail. Category actions have no
     * affected member, so memberID is null.
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
