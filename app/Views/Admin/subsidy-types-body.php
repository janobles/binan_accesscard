<?php
/**
 * Subsidy Types reference body: Add button + search/size controls + the
 * subsidy-type table. Rendered inside components/card by the Reference Data
 * page's subsidy-types tab, which supplies the server-side search/pagination
 * bundle the Sectors/Services/Categories tabs also use. Lifecycle buttons
 * render only for Admin/Developer.
 */
$canManageSubsidyTypes = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>


        <?= view('components/table_controls', [
            'searchId' => 'subsidyTypeLocalSearch',
            'searchAria' => 'Search shown subsidy types',
            'searchFormAttrs' => 'data-lookup-search',
            'searchInputAttrs' => 'data-lookup-search-input',
            'sizeId' => 'subsidyTypePerPage',
            'sizeAction' => site_url($listRoute),
            'sizeHiddenHtml' => '<input type="hidden" name="tab" value="subsidy-types">'
                . ($keyword !== '' ? '<input type="hidden" name="q" value="' . esc($keyword, 'attr') . '">' : '')
                . ($status !== 'active' ? '<input type="hidden" name="status" value="' . esc($status, 'attr') . '">' : ''),
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]) ?>

        <div class="table-responsive">
          <table class="table manage-record-table table-stack align-middle lookup-management-table lookup-management-table--subsidy-types">
            <thead>
              <tr><th class="lookup-col-name">Name</th><th class="lookup-col-status">Status</th><th class="lookup-col-actions text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($subsidyTypes as $t): ?>
                <?php $archived = ! empty($t['dt_deleted']); ?>
                <tr data-row-archived="<?= $archived ? '1' : '0' ?>">
                  <td><span class="sector-name"><?= esc($t['name']) ?></span></td>
                  <td class="lookup-col-status"><span class="badge rounded-pill <?= $archived ? 'text-bg-secondary' : 'text-bg-success' ?>"><?= $archived ? 'Archived' : 'Active' ?></span></td>
                  <td class="text-end">
                    <?php if ($canManageSubsidyTypes): ?>
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Subsidy type actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
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
                      </div>
                    </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($subsidyTypes === []): ?>
                <tr><td colspan="3" class="sector-empty-state"><?= $keyword !== '' ? 'No subsidy types match your search.' : 'No subsidy types defined.' ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
