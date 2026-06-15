<?php

namespace App\Controllers\Lookups;

use App\Controllers\BaseController;
use App\Libraries\RoleAccess;
use App\Models\Audit\AuditTrailsModel;
use App\Models\Lookups\CategoryModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Handles the write/mutation actions for the `category` lookup table, posted from
 * the admin "Manage Categories" page. Read/listing is done by
 * Admin\DashboardController::categories; every action here is Developer/Admin-only
 * and redirects back to `admin/categories` with a flash message.
 *
 * Every category is fully editable, archivable, and deletable; the only guard is
 * that a category still linked to sectors cannot be archived or deleted.
 */
class CategoryController extends BaseController
{
    /**
     * POST `admin/categories/create`: add a new category. Delegates to saveCategory().
     */
    public function create(): RedirectResponse
    {
        return $this->saveCategory();
    }

    /**
     * POST `admin/categories/update/{id}`: edit an existing category. Delegates to
     * saveCategory() with the id.
     */
    public function update(int $categoryId): RedirectResponse
    {
        return $this->saveCategory($categoryId);
    }

    /**
     * POST `admin/categories/archive/{id}`: soft-archive a category.
     * Refused for categories still used by sectors.
     */
    public function archive(int $categoryId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new CategoryModel();

        if (! $model->hasTable()) {
            return $this->redirect('error', 'Category table is not available.');
        }

        if ($model->countSectors($categoryId) > 0) {
            return $this->redirect('error', 'This category still has sectors. Reassign or remove them first.');
        }

        $category = $model->find($categoryId);

        if (! $model->archive($categoryId)) {
            return $this->redirect('error', 'Unable to archive category.');
        }

        $this->audit('CATEGORY_ARCHIVE', 'Archived ' . $this->categoryLabel($category, $categoryId) . '.');

        return $this->redirect('success', 'Category archived successfully.');
    }

    /**
     * POST `admin/categories/restore/{id}`: un-archive a previously archived category.
     */
    public function restore(int $categoryId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new CategoryModel();

        if (! $model->hasTable()) {
            return $this->redirect('error', 'Category table is not available.');
        }

        $category = $model->find($categoryId);

        if (! $model->restore($categoryId)) {
            return $this->redirect('error', 'Unable to restore category.');
        }

        $this->audit('CATEGORY_RESTORE', 'Restored ' . $this->categoryLabel($category, $categoryId) . '.');

        return $this->redirect('success', 'Category restored successfully.');
    }

    /**
     * POST `admin/categories/delete/{id}`: permanently delete a category.
     * Refused for categories still used by sectors.
     */
    public function delete(int $categoryId): RedirectResponse
    {
        $guard = $this->ensureAdminAccess();

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new CategoryModel();

        if (! $model->hasTable()) {
            return $this->redirect('error', 'Category table is not available.');
        }

        if ($model->countSectors($categoryId) > 0) {
            return $this->redirect('error', 'This category is used by one or more sectors and cannot be deleted.');
        }

        $category = $model->find($categoryId);

        if (! $model->delete($categoryId)) {
            return $this->redirect('error', 'Unable to delete category.');
        }

        $this->audit('CATEGORY_DELETE', 'Permanently deleted ' . $this->categoryLabel($category, $categoryId) . '.');

        return $this->redirect('success', 'Category deleted successfully.');
    }

    /**
     * Shared create/update logic. Validates the code (letters only) and name,
     * and blocks duplicate codes.
     * $categoryId null = create, otherwise update.
     */
    private function saveCategory(?int $categoryId = null): RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $model = new CategoryModel();

        if (! $model->hasTable()) {
            return $this->redirect('error', 'Category table is not available.');
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $name = trim((string) $this->request->getPost('name'));

        $isUpdate = $categoryId !== null;
        $existing = $isUpdate ? $model->find($categoryId) : null;

        if ($isUpdate && $existing === null) {
            return $this->redirect('error', 'Category not found.');
        }

        if ($code === '' || preg_match('/^[A-Z]+$/', $code) !== 1) {
            return $this->redirect('error', 'Category code must be letters only (e.g. NEW).');
        }

        if ($name === '') {
            return $this->redirect('error', 'Category name is required.');
        }

        if ($model->codeExists($code, $categoryId)) {
            return $this->redirect('error', 'Duplicate code "' . $code . '". Please enter another code.');
        }

        $data = [
            'code' => $code,
            'name' => $name,
        ];

        if ($isUpdate) {
            $saved = $model->update($categoryId, $data) !== false;
        } else {
            $saved = $model->create($data) > 0;
        }

        if (! $saved) {
            return $this->redirect('error', 'Unable to save category.');
        }

        $this->audit(
            $isUpdate ? 'CATEGORY_UPDATE' : 'CATEGORY_CREATE',
            ($isUpdate ? 'Updated' : 'Created') . ' category ' . $code . ' (' . $name . ').'
        );

        return $this->redirect('success', $isUpdate ? 'Category updated successfully.' : 'Category added successfully.');
    }

    /**
     * Role guard for mutations: returns a redirect for non Developer/Admin users,
     * or null to proceed.
     */
    private function ensureAdminAccess(): ?RedirectResponse
    {
        $guard = RoleAccess::requireRole(['Developer', 'Admin']);

        return $guard instanceof RedirectResponse ? $guard : null;
    }

    /** Redirect back to the categories page with a typed flash message. */
    private function redirect(string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url('admin/categories'))->with($type, $message);
    }

    /** Human-readable category label for audit descriptions, e.g. "category SC (Senior Citizen) #3". */
    private function categoryLabel(?array $category, int $categoryId): string
    {
        $code = trim((string) ($category['code'] ?? ''));
        $name = trim((string) ($category['name'] ?? ''));
        $label = trim($code . ($name !== '' ? ' (' . $name . ')' : ''));

        return ($label === '' ? 'category' : 'category ' . $label) . ' #' . $categoryId;
    }

    /**
     * Write a category action to the audit trail. Category actions have no
     * affected member, so memberID is null (audit_trails.memberID is nullable).
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
