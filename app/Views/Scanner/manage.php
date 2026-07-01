<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php if (session('success')): ?>
  <div class="alert alert-success"><?= esc(session('success')) ?></div>
<?php elseif (session('error')): ?>
  <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<?php /* Neutral section switcher (navigation, not an action) — nav-tabs, gray until active. */ ?>
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dist" type="button" role="tab">All Distributions</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-types" type="button" role="tab">Aid Types</button></li>
</ul>

<div class="tab-content">
  <!-- All distributions -->
  <div class="tab-pane fade show active" id="tab-dist" role="tabpanel">
    <div class="table-responsive">
      <table class="table table-sm manage-record-table align-middle w-100" id="distTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Family Head</th>
            <th>Claimant</th>
            <th>Aid Type</th>
            <th>Scanned By</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($distributions as $d): ?>
            <tr>
              <td><?= esc($d['claim_date']) ?></td>
              <td><span class="sector-name"><?= esc($d['head']) ?></span></td>
              <td><?= esc($d['claimant']) ?></td>
              <td><span class="badge bg-light text-dark border"><?= esc($d['aid_type']) ?></span></td>
              <td><?= esc($d['scanned_by']) ?></td>
              <td class="text-end">
                <div class="dropdown actions-menu">
                  <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Distribution actions">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <form method="post" action="<?= site_url('scanner/distributions/void/' . $d['aidID']) ?>"
                          onsubmit="return confirm('Void this distribution? This permanently removes the record.');">
                      <?= csrf_field() ?>
                      <button class="dropdown-item text-danger" type="submit"><i class="bi bi-x-circle" aria-hidden="true"></i>Void</button>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($distributions === []): ?>
            <tr><td colspan="6" class="sector-empty-state">No aid distributions logged yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Aid types CRUD -->
  <div class="tab-pane fade" id="tab-types" role="tabpanel">
    <?php /* Toolbar mirrors the Lookups pages: color-coded actions on the right. */ ?>
    <div class="records-search-panel">
      <div class="records-search-row justify-content-end">
        <button class="btn btn-primary records-search-action" type="button" data-bs-toggle="modal" data-bs-target="#addAidTypeModal"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Aid Type</span></button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm manage-record-table align-middle w-100">
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
                      <form method="post" action="<?= site_url('scanner/aid-types/restore/' . $t['aid_type_id']) ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore</button>
                      </form>
                      <form method="post" action="<?= site_url('scanner/aid-types/delete/' . $t['aid_type_id']) ?>"
                            onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                        <?= csrf_field() ?>
                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i>Delete</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="<?= site_url('scanner/aid-types/archive/' . $t['aid_type_id']) ?>">
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
  </div>
</div>

<!-- Add aid type modal -->
<div class="modal fade" id="addAidTypeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('scanner/aid-types/create') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title">Add Aid Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="aidTypeName" class="form-label">Name</label>
        <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="100">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('#distTable').DataTable({ order: [[0, 'desc']] });
  }
});
</script>
<?= $this->endSection() ?>
