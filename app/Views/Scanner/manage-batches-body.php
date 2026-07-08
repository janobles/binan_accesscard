<?php
/** Distribution Batches pane: active-batch banner + open/close controls + past
 * list. Lifecycle buttons render only for Admin/Developer; Scanner role sees
 * the read-only state. Rendered inside components/card by Scanner/manage.php. */
$canManageBatches = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<?php if (($activeBatch ?? null) !== null): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <span><strong><?= esc($activeBatch['name']) ?></strong> — open since <?= esc($activeBatch['started_at']) ?></span>
    <?php if ($canManageBatches): ?>
    <form method="post" action="<?= site_url('scanner/batches/close/' . (int) $activeBatch['batch_id']) ?>"
          onsubmit="return confirm('Close this batch? Statistics reset for the next batch.');">
      <?= csrf_field() ?>
      <button class="btn btn-warning btn-sm" type="submit">Close batch</button>
    </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="alert alert-secondary">No active batch. Scanning is paused until one is opened.</div>
  <?php if ($canManageBatches): ?>
  <form method="post" action="<?= site_url('scanner/batches/open') ?>" class="row g-2 align-items-end mb-3">
    <?= csrf_field() ?>
    <div class="col-auto flex-grow-1">
      <label for="batchName" class="form-label mb-0">Batch name</label>
      <input class="form-control" type="text" id="batchName" name="name" maxlength="100"
             placeholder="e.g. Rice Distribution — <?= esc(date('M j, Y')) ?>" required>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" type="submit">Open batch</button>
    </div>
  </form>
  <?php endif; ?>
<?php endif; ?>

<table class="table manage-record-table align-middle w-100 mb-0">
  <thead><tr><th>Batch</th><th>Started</th><th>Closed</th></tr></thead>
  <tbody>
    <?php foreach (($batches ?? []) as $b): ?>
      <tr>
        <td><?= esc($b['name']) ?></td>
        <td><?= esc($b['started_at']) ?></td>
        <td><?= $b['closed_at'] === null ? '<span class="badge bg-success">Open</span>' : esc($b['closed_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (($batches ?? []) === []): ?>
      <tr><td colspan="3" class="text-muted">No batches yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
