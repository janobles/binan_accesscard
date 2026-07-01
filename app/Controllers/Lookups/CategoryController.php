<?php

namespace App\Controllers\Lookups;

use App\Controllers\BaseController;
use App\Controllers\Concerns\LookupControllerTrait;
use App\Libraries\RoleAccess;
use App\Models\Lookups\CategoryModel;
use App\Models\Lookups\SectorModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Handles the write/mutation actions for the `category` lookup table, posted from
 * the admin "Manage Categories" page. After the Phase A restructure a category is a
 * SERVICE category (services link to it by the name stored in `services.category`).
 * Read/listing is done by Admin\DashboardController::categories; every action here is
 * Developer/Admin-only and redirects back to `admin/categories` with a flash message.
 *
 * Every category is editable, archivable, and restorable; categories are never
 * permanently deleted (archive is the only retirement path). A category still used by
 * an active service cannot be archived until those services are reassigned/archived.
 */
class CategoryController extends BaseController
{
    use LookupControllerTrait;

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
     * POST `admin/categories/archive/{id}`: soft-archive a category. Blocked if any
     * active service still uses it (matched by the category name in services.category),
     * so archiving never orphans a service's category label from the managed list.
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

        $category = $model->find($categoryId);

        if ($category !== null && $model->isUsedByServices((string) ($category['name'] ?? ''))) {
            return $this->redirect('error', 'This category is used by one or more services and cannot be archived. Reassign or archive those services first.');
        }

        if (! $model->archive($categoryId)) {
            return $this->redirect('error', 'Unable to archive category.');
        }

        $this->audit('CATEGORY_ARCHIVE', 'Archived ' . $this->categoryLabel($category, $categoryId) . '.');

        return $this->redirect('success', 'Category archived successfully.');
    }

    /**
     * POST `admin/categories/restore/{id}`: un-archive a category and audit it.
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
        $description = trim((string) $this->request->getPost('description'));

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

        // A sector already acts as its own service category, so a category may not
        // duplicate one — keep the two lists disjoint (Phase B).
        if ((new SectorModel())->activeCodeOrNameExists($code, $name)) {
            return $this->redirect('error', 'A sector already uses this code or name. A sector acts as its own service category — assign programs to it in Sector Management instead of adding a duplicate category here.');
        }

        $data = [
            'code' => $code,
            'name' => $name,
        ];

        if ($model->supportsDescription()) {
            $data['description'] = $description;
        }

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

    /** Redirect back to the categories page with a typed flash message. */
    private function redirect(string $type, string $message): RedirectResponse
    {
        return $this->redirectAdmin('admin/categories', $type, $message);
    }

    /** Human-readable category label for audit descriptions, e.g. "category SC (Senior Citizen) #3". */
    private function categoryLabel(?array $category, int $categoryId): string
    {
        $code = trim((string) ($category['code'] ?? ''));
        $name = trim((string) ($category['name'] ?? ''));
        $label = trim($code . ($name !== '' ? ' (' . $name . ')' : ''));

        return ($label === '' ? 'category' : 'category ' . $label) . ' #' . $categoryId;
    }
}
