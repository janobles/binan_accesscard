<?php
/**
 * Aid Types reference body: Add button + aid-type table. Rendered inside
 * components/card by Admin/layout.php's aidtypes block (vars: aidTypes,
 * currentRole). Lifecycle buttons render only for Admin/Developer.
 */
$canManageAidTypes = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<?php /* Action row: Bootstrap utilities inside the card-body's own padding. */ ?>
<div class="d-flex justify-content-end mb-3">
          <button class="<?= btn('add') ?>" type="button" data-bs-toggle="modal" data-bs-target="#addAidTypeModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Aid Type</button>
        </div>

        <div class="table-responsive">
          <table class="table manage-record-table align-middle w-100">
            <thead>
              <tr><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($aidTypes as $t): ?>
                <?php $archived = ! empty($t['dt_deleted']); ?>
                <tr data-row-archived="<?= $archived ? '1' : '0' ?>">
                  <td><span class="sector-name"><?= esc($t['name']) ?></span></td>
                  <td><span class="sector-status-badge <?= $archived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $archived ? 'Archived' : 'Active' ?></span></td>
                  <td class="text-end">
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Aid type actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if ($canManageAidTypes): ?>
                        <?php if ($archived): ?>
                          <form method="post" action="<?= esc(site_url('admin/aidtypes/restore/' . $t['aid_type_id']), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore</button>
                          </form>
                          <form method="post" action="<?= esc(site_url('admin/aidtypes/delete/' . $t['aid_type_id']), 'attr') ?>"
                                onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i>Delete</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="<?= esc(site_url('admin/aidtypes/archive/' . $t['aid_type_id']), 'attr') ?>">
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
                <tr><td colspan="3" class="sector-empty-state">No aid types defined.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
