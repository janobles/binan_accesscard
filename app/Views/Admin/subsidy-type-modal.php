<?php
/** Add Subsidy Type modal: single name field, posts to reference-data/subsidy-types/create. */
?>
<div class="modal fade" id="addSubsidyTypeModal" tabindex="-1" aria-labelledby="addSubsidyTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= site_url('reference-data/subsidy-types/create') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="addSubsidyTypeModalLabel">Add Subsidy Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="subsidyTypeName" class="form-label">Name</label>
          <input type="text" class="form-control" id="subsidyTypeName" name="name" required maxlength="100"
                 placeholder="e.g. Financial">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Add</button>
      </div>
    </form>
  </div>
</div>
