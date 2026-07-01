<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php if (session('success')): ?>
  <div class="alert alert-success"><?= esc(session('success')) ?></div>
<?php elseif (session('error')): ?>
  <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<ul class="nav nav-pills mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-dist" type="button">All Distributions</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-types" type="button">Aid Types</button></li>
</ul>

<div class="tab-content">
  <!-- All distributions -->
  <div class="tab-pane fade show active" id="tab-dist">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">All Aid Distributions</div>
      <div class="card-body">
        <table class="table table-striped table-bordered w-100" id="distTable">
          <thead>
            <tr><th>Date</th><th>Family Head</th><th>Claimant</th><th>Aid Type</th><th>Scanned By</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($distributions as $d): ?>
              <tr>
                <td><?= esc($d['claim_date']) ?></td>
                <td><?= esc($d['head']) ?></td>
                <td><?= esc($d['claimant']) ?></td>
                <td><?= esc($d['aid_type']) ?></td>
                <td><?= esc($d['scanned_by']) ?></td>
                <td>
                  <form method="post" action="<?= site_url('scanner/distributions/void/' . $d['aidID']) ?>"
                        onsubmit="return confirm('Void this distribution? This permanently removes the record.');">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Void</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Aid types CRUD -->
  <div class="tab-pane fade" id="tab-types">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span>Aid Types</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAidTypeModal">Add</button>
      </div>
      <div class="card-body">
        <table class="table table-striped table-bordered w-100">
          <thead><tr><th>Name</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($aidTypes as $t): ?>
              <?php $archived = ! empty($t['dt_deleted']); ?>
              <tr>
                <td><?= esc($t['name']) ?></td>
                <td><?= $archived ? '<span class="badge bg-secondary">Archived</span>' : '<span class="badge bg-success">Active</span>' ?></td>
                <td>
                  <?php if ($archived): ?>
                    <form method="post" action="<?= site_url('scanner/aid-types/restore/' . $t['aid_type_id']) ?>" class="d-inline">
                      <button class="btn btn-sm btn-outline-success" type="submit">Restore</button>
                    </form>
                    <form method="post" action="<?= site_url('scanner/aid-types/delete/' . $t['aid_type_id']) ?>" class="d-inline"
                          onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?= site_url('scanner/aid-types/archive/' . $t['aid_type_id']) ?>" class="d-inline">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">Archive</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add aid type modal -->
<div class="modal fade" id="addAidTypeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('scanner/aid-types/create') ?>">
      <div class="modal-header">
        <h5 class="modal-title">Add Aid Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="aidTypeName" class="form-label">Name</label>
        <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="255">
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
