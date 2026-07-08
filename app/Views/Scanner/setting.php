<?= $this->extend('Scanner/kiosk-layout') ?>
<?= $this->section('content') ?>

<?php if (($activeBatch ?? null) === null): ?>
  <div class="text-center py-5">
    <i class="bi bi-pause-circle display-3 text-secondary" aria-hidden="true"></i>
    <div class="fw-bold mt-3">No active distribution</div>
    <div class="text-muted">Ask an administrator to open a batch, then refresh this page.</div>
  </div>
<?php else: ?>
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card border-0 rounded-3">
        <div class="card-body text-center">
          <div class="fw-bold fs-4 mb-1"><?= esc($activeBatch['name']) ?></div>
          <div class="text-muted small mb-4">Open since <?= esc($activeBatch['started_at']) ?></div>
          <form method="get" action="<?= site_url('scanner/scan') ?>">
            <label for="aidTypePick" class="form-label fw-bold">Aid type to distribute</label>
            <select class="form-select form-select-lg mb-3" id="aidTypePick" name="aid_type" required autofocus>
              <option value="">Choose aid type&hellip;</option>
              <?php foreach ($aidTypes as $type): ?>
                <option value="<?= esc($type['aid_type_id'], 'attr') ?>"><?= esc($type['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-success btn-lg w-100" type="submit">
              <i class="bi bi-upc-scan me-1" aria-hidden="true"></i> Start scanning
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
