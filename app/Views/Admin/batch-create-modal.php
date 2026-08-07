<?php
/**
 * New Batch modal: name, subsidy type, and the eligibility filters that decide
 * which families the batch covers. Subsidy types come from the subsidy
 * reference table (reference-data/subsidy-types page); barangay and sector
 * options come from BarangayModel::activeList() and SectorModel::getActive()
 * (DashboardPageBuilder). The live count under the filters calls
 * GET distribution/batches/preview, which runs the same EligibilityBuilder
 * query the batch freezes on open, so the number shown here is the number the
 * batch actually gets. Wired by public/assets/js/dashboard/batch-create-modal.js.
 *
 * Variables:
 * - $activeSubsidyTypes list of subsidy type rows (subsidy_type_id, name)
 * - $barangayOptions    list of barangay rows (barangayID, name)
 * - $sectorOptions      list of sector rows (sectorID, name, ...)
 */
$activeSubsidyTypes = $activeSubsidyTypes ?? [];
$barangayOptions    = $barangayOptions ?? [];
$sectorOptions      = $sectorOptions ?? [];
?>
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= site_url('distribution/batches/open') ?>" id="newBatchForm">
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
        <div class="row g-3 mb-2">
          <div class="col-sm-6">
            <label for="batchBarangayIds" class="form-label">Barangays</label>
            <select class="form-select" id="batchBarangayIds" name="barangay_ids[]" multiple size="5">
              <?php foreach ($barangayOptions as $b): ?>
                <option value="<?= (int) $b['barangayID'] ?>"><?= esc($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Nothing selected covers every barangay.</div>
          </div>
          <div class="col-sm-6">
            <label for="batchSectorIds" class="form-label">Sectors</label>
            <select class="form-select" id="batchSectorIds" name="sector_ids[]" multiple size="5">
              <?php foreach ($sectorOptions as $s): ?>
                <option value="<?= (int) $s['sectorID'] ?>"><?= esc($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Nothing selected covers every sector.</div>
          </div>
        </div>
        <p class="text-muted small mb-0" id="eligiblePreview">
          This batch will cover <strong data-eligible-count>every eligible family</strong>.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>" id="newBatchSubmit">Open Batch</button>
      </div>
    </form>
  </div>
</div>
<script>
  window.batchPreviewUrl = <?= json_encode(site_url('distribution/batches/preview')) ?>;
</script>
