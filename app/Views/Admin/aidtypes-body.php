<?php
/**
 * Subsidy Types reference body: search/toolbar rows + aid-type table.
 * Rendered inside components/card by Admin/aidtypes.php (bodyData is that
 * view's get_defined_vars(), matching the Sectors/Services/Categories
 * convention). Lifecycle buttons render only for Admin/Developer. The Add
 * trigger lives in the toolbar above this card (Admin/aidtypes.php).
 */
$canManageAidTypes = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<?= view('components/table_controls', [
    'searchId' => 'aidTypeLocalSearch',
    'searchAria' => 'Search shown subsidy types',
    'searchFormAttrs' => 'data-lookup-search',
    'searchInputAttrs' => 'data-lookup-search-input',
    'sizeId' => 'aidTypePerPage',
    'sizeAction' => site_url($listRoute),
    'sizeHiddenHtml' => ($keyword !== '' ? '<input type="hidden" name="q" value="' . esc($keyword, 'attr') . '">' : '')
        . ($status !== 'active' ? '<input type="hidden" name="status" value="' . esc($status, 'attr') . '">' : ''),
    'perPage' => $perPage,
    'perPageOptions' => $perPageOptions,
]) ?>

        <div class="table-responsive">
          <table class="table manage-record-table align-middle lookup-management-table lookup-management-table--aidtypes">
            <thead>
              <tr><th class="lookup-col-name">Name</th><th class="lookup-col-status">Status</th><th class="lookup-col-actions text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($aidTypes as $t): ?>
                <?php $archived = ! empty($t['dt_deleted']); ?>
                <tr data-row-archived="<?= $archived ? '1' : '0' ?>">
                  <td><span class="sector-name"><?= esc($t['name']) ?></span></td>
                  <td class="lookup-col-status"><span class="sector-status-badge <?= $archived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $archived ? 'Archived' : 'Active' ?></span></td>
                  <td class="text-end">
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Subsidy type actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if ($canManageAidTypes): ?>
                        <?php if ($archived): ?>
                          <form method="post" action="<?= esc(site_url('admin/aidtypes/restore/' . $t['subsidy_type_id']), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore</button>
                          </form>
                          <form method="post" action="<?= esc(site_url('admin/aidtypes/delete/' . $t['subsidy_type_id']), 'attr') ?>"
                                onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i>Delete</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="<?= esc(site_url('admin/aidtypes/archive/' . $t['subsidy_type_id']), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-archive" aria-hidden="true"></i>Archive</button>
                          </form>
                        <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($aidTypes === []): ?>
                <tr><td colspan="3" class="sector-empty-state"><?= $keyword !== '' ? 'No subsidy types match your search.' : 'No subsidy types defined.' ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
