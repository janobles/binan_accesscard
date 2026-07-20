<?php
/** Add Aid Type modal: single name field, posts to admin/aidtypes/create. */
?>
<div class="modal fade" id="addAidTypeModal" tabindex="-1" aria-labelledby="addAidTypeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('admin/aidtypes/create') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="addAidTypeModalLabel">Add Subsidy Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="aidTypeName" class="form-label">Name</label>
          <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="100"
                 placeholder="e.g. Financial">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Add Subsidy Type</button>
      </div>
    </form>
  </div>
</div>
