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
 * Handles the write/mutation actions for the `category` lookup table, posted from
 * the admin "Manage Categories" page. After the Phase A restructure a category is a
 * SERVICE category (services link to it by the name stored in `services.category`).
 * Read/listing is done by Admin\DashboardController::categories; every action here is
 * Developer/Admin-only and redirects back to `admin/categories` with a flash message.
 *
 * Every category is editable, archivable, and restorable; categories are never
 * permanently deleted (archive is the only retirement path). Archiving a category
 * cascades onto its linked services (services.category = category name), archiving
 * them alongside it.
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
     * POST `admin/categories/archive/{id}`: soft-archive a category and cascade the
     * archive onto every active service linked to it (matched by the category name in
     * services.category), so a category and its programs retire together.
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

        if ($category === null) {
            return $this->redirect('error', 'Category not found.');
        }

        if (! $model->archive($categoryId)) {
            return $this->redirect('error', 'Unable to archive category.');
        }

        $categoryName     = trim((string) ($category['name'] ?? ''));
        $archivedAt       = (string) ($model->find($categoryId)['dt_deleted'] ?? '');
        $archivedServices = ($categoryName !== '' && $archivedAt !== '') ? (new ServiceModel())->archiveByCategory($categoryName, $archivedAt) : 0;

        $this->audit('CATEGORY_ARCHIVE', 'Archived ' . $this->categoryLabel($category, $categoryId) . '.' . $this->cascadeNote($archivedServices));

        return $this->redirect('success', 'Category archived successfully.' . $this->cascadeMessage($archivedServices));
    }

    /**
     * POST `admin/categories/restore/{id}`: un-archive a category and cascade the
     * restore onto the services its archive retired (matched by category name + the
     * shared archive timestamp), so the pair comes back together. Services archived
     * separately keep their archived state.
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

        $category   = $model->find($categoryId);
        $archivedAt = (string) ($category['dt_deleted'] ?? '');

        if (! $model->restore($categoryId)) {
            return $this->redirect('error', 'Unable to restore category.');
        }

        $categoryName     = trim((string) ($category['name'] ?? ''));
        $restoredServices = ($categoryName !== '' && $archivedAt !== '') ? (new ServiceModel())->restoreByCategoryArchivedAt($categoryName, $archivedAt) : 0;

        $this->audit('CATEGORY_RESTORE', 'Restored ' . $this->categoryLabel($category, $categoryId) . '.' . $this->cascadeNote($restoredServices, 'restored'));

        return $this->redirect('success', 'Category restored successfully.' . $this->cascadeMessage($restoredServices, 'restored'));
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

        if ($model->nameExists($name, $categoryId)) {
            return $this->redirect('error', 'Duplicate name "' . $name . '". Please enter another name.');
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
        return $this->redirectAdmin('admin/reference-data?tab=categories', $type, $message);
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
