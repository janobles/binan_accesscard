<?php
/**
 * New Batch modal: name + aid type pick. Aid types come from the aid_type
 * reference table (admin/aidtypes page).
 *
 * Variables:
 * - $activeAidTypes list of aid type rows (aid_type_id, name)
 */
$activeAidTypes = $activeAidTypes ?? [];
?>
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('admin/batches/open') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="newBatchModalLabel">New Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="batchName" class="form-label">Batch name</label>
          <input type="text" class="form-control" id="batchName" name="name" required maxlength="100"
                 placeholder="e.g. Relief Distribution — <?= esc(date('M j, Y')) ?>">
        </div>
        <div class="mb-3">
          <label for="batchAidType" class="form-label">Aid type</label>
          <select class="form-select" id="batchAidType" name="aid_type_id" required>
            <option value="" selected disabled>Choose an aid type...</option>
            <?php foreach ($activeAidTypes as $t): ?>
              <option value="<?= (int) $t['aid_type_id'] ?>"><?= esc($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Open Batch</button>
      </div>
    </form>
  </div>
</div>
