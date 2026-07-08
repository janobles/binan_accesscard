<?php
/**
 * Aid types CRUD body: Add button + aid-type table. Ported from
 * Scanner/manage-aidtypes-body.php for the admin distribution hub
 * (routes moved from scanner/aid-types/* to admin/aid-types/*).
 * Rendered inside components/card by Admin/distribution.php (vars: aidTypes).
 */
?>
<div class="records-search-panel">
          <div class="records-search-row justify-content-end">
            <button class="btn btn-primary records-search-action" type="button" data-bs-toggle="modal" data-bs-target="#addAidTypeModal"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Aid Type</span></button>
          </div>
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
                        <?php if ($archived): ?>
                          <form method="post" action="<?= esc(site_url('admin/aid-types/restore/' . $t['aid_type_id']), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore</button>
                          </form>
                          <form method="post" action="<?= esc(site_url('admin/aid-types/delete/' . $t['aid_type_id']), 'attr') ?>"
                                onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i>Delete</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="<?= esc(site_url('admin/aid-types/archive/' . $t['aid_type_id']), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-archive" aria-hidden="true"></i>Archive</button>
                          </form>
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
