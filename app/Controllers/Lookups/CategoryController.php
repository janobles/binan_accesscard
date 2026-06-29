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
 * the admin "Manage Categories" page. Read/listing is done by
 * Admin\DashboardController::categories; every action here is Developer/Admin-only
 * and redirects back to `admin/categories` with a flash message.
 *
 * Every category is editable, archivable, and restorable; categories are never
 * permanently deleted (archive is the only retirement path). Archiving a category
 * cascades to its active sectors (they are retired together but existing records keep
 * them); restoring the category brings back exactly that cascaded batch.
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
     * POST `admin/categories/archive/{id}`: soft-archive a category and cascade-archive
     * its still-active sectors. The sectors are stamped with the same dt_deleted as the
     * category so restore() can bring back exactly this batch. Existing records keep
     * any cascaded sector (the family edit form preserves archived-but-assigned sectors).
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

        if (! $model->archive($categoryId)) {
            return $this->redirect('error', 'Unable to archive category.');
        }

        // Cascade: archive the category's active sectors with the category's own
        // dt_deleted timestamp, so restore() can match this exact batch.
        $archivedAt = (string) ($model->find($categoryId)['dt_deleted'] ?? '');
        $sectorCount = $archivedAt === '' ? 0 : (new SectorModel())->archiveByCategory($categoryId, $archivedAt);

        $this->audit(
            'CATEGORY_ARCHIVE',
            'Archived ' . $this->categoryLabel($category, $categoryId) . $this->sectorSuffix($sectorCount) . '.'
        );

        return $this->redirect('success', 'Category archived successfully.' . $this->cascadeMessage($sectorCount, 'archived'));
    }

    /**
     * POST `admin/categories/restore/{id}`: un-archive a category and restore the
     * sectors that were cascade-archived with it (those whose dt_deleted matches the
     * category's archive timestamp). Sectors archived independently stay archived.
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
        $archivedAt = (string) ($category['dt_deleted'] ?? '');

        if (! $model->restore($categoryId)) {
            return $this->redirect('error', 'Unable to restore category.');
        }

        $sectorCount = $archivedAt === '' ? 0 : (new SectorModel())->restoreByCategoryArchivedAt($categoryId, $archivedAt);

        $this->audit(
            'CATEGORY_RESTORE',
            'Restored ' . $this->categoryLabel($category, $categoryId) . $this->sectorSuffix($sectorCount) . '.'
        );

        return $this->redirect('success', 'Category restored successfully.' . $this->cascadeMessage($sectorCount, 'restored'));
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

    /** Redirect back to the categories page with a typed flash message. */
    private function redirect(string $type, string $message): RedirectResponse
    {
        return $this->redirectAdmin('admin/categories', $type, $message);
    }

    /** Audit-suffix for the linked sectors touched by a cascade, e.g. " and 3 linked sectors". */
    private function sectorSuffix(int $count): string
    {
        return $count > 0 ? ' and ' . $count . ' linked sector' . ($count === 1 ? '' : 's') : '';
    }

    /** Flash-message tail for the cascade, e.g. " 3 linked sectors archived too.". */
    private function cascadeMessage(int $count, string $verb): string
    {
        if ($count <= 0) {
            return '';
        }

        return ' ' . $count . ' linked sector' . ($count === 1 ? '' : 's') . ' ' . $verb . ' too.';
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
