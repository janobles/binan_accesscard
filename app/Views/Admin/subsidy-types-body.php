<?php
/**
 * Subsidy Types reference body: Add button + subsidy-type table. Rendered inside
 * components/card by the Reference Data page's subsidy-types tab. Lifecycle
 * buttons render only for Admin/Developer.
 */
$canManageSubsidyTypes = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<?php /* Action row: Bootstrap utilities inside the card-body's own padding. */ ?>
<div class="d-flex justify-content-end mb-3">
          <button class="<?= btn('add') ?>" type="button" data-bs-toggle="modal" data-bs-target="#addSubsidyTypeModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Subsidy Type</button>
        </div>

        <?= view('components/table_controls', [
            'searchId' => 'subsidyTypesLocalSearch',
            'searchAria' => 'Search shown subsidy types',
            'searchFormAttrs' => 'onsubmit="return false;"',
            'searchInputAttrs' => 'data-paginate-search="subsidy-types"',
            'sizeId' => 'subsidyTypesPerPage',
            'sizeAction' => null,
            'perPage' => 25,
            'perPageOptions' => [10 => '10', 25 => '25', 50 => '50', 100 => '100', 0 => 'All'],
            'sizeAttrs' => 'data-paginate-size="subsidy-types"',
        ]) ?>

        <div class="table-responsive">
          <table class="table manage-record-table align-middle w-100">
            <thead>
              <tr><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($subsidyTypes as $t): ?>
                <?php $archived = ! empty($t['dt_deleted']); ?>
                <tr data-paginate-row data-row-archived="<?= $archived ? '1' : '0' ?>">
                  <td><span class="sector-name"><?= esc($t['name']) ?></span></td>
                  <td><span class="sector-status-badge <?= $archived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $archived ? 'Archived' : 'Active' ?></span></td>
                  <td class="text-end">
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Subsidy type actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if ($canManageSubsidyTypes): ?>
                        <?php if ($archived): ?>
                          <form method="post" action="<?= esc(site_url('reference-data/subsidy-types/restore/' . $t['subsidy_type_id']), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore</button>
                          </form>
                          <form method="post" action="<?= esc(site_url('reference-data/subsidy-types/delete/' . $t['subsidy_type_id']), 'attr') ?>"
                                onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i>Delete</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="<?= esc(site_url('reference-data/subsidy-types/archive/' . $t['subsidy_type_id']), 'attr') ?>">
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
              <?php if ($subsidyTypes === []): ?>
                <tr><td colspan="3" class="sector-empty-state">No subsidy types defined.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
