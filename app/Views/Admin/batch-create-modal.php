<?php
/**
 * New Batch modal: name + subsidy type pick. Subsidy types come from the
 * subsidy reference table (reference-data/subsidy-types page).
 *
 * Variables:
 * - $activeSubsidyTypes list of subsidy type rows (subsidy_type_id, name)
 */
$activeSubsidyTypes = $activeSubsidyTypes ?? [];
?>
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= site_url('distribution/batches/open') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="newBatchModalLabel">New Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="batchName" class="form-label">Batch name</label>
          <input type="text" class="form-control" id="batchName" name="name" required maxlength="100"
                 placeholder="e.g. Relief Distribution - <?= esc(date('M j, Y')) ?>">
        </div>
        <div class="mb-3">
          <label for="batchSubsidyType" class="form-label">Subsidy type</label>
          <select class="form-select" id="batchSubsidyType" name="subsidy_type_id" required>
            <option value="" selected disabled>Choose a subsidy type...</option>
            <?php foreach ($activeSubsidyTypes as $t): ?>
              <option value="<?= (int) $t['subsidy_type_id'] ?>"><?= esc($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Open Batch</button>
      </div>
    </form>
  </div>
</div>
