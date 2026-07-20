<?php
/**
 * Distribution Batches pane: active-batch banner + close control + past list.
 * Opening a batch happens through the New Batch modal
 * (Admin/batch-create-modal.php), which binds an aid type from the aid_type
 * reference table. Each batch row shows its bound aid type. Lifecycle
 * buttons render only for Admin/Developer. Rendered inside components/card
 * by Admin/layout.php's batches block.
 */
$canManageBatches = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<?php if (($activeBatch ?? null) !== null): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <span>
      <strong><?= esc($activeBatch['name']) ?></strong>
      <span class="badge bg-light text-dark border"><?= esc((string) ($activeBatch['aid_type_name'] ?? '')) ?></span>
      — open since <?= esc($activeBatch['started_at']) ?>
    </span>
    <?php if ($canManageBatches): ?>
    <form method="post" action="<?= site_url('admin/batches/close/' . (int) $activeBatch['batch_id']) ?>"
          onsubmit="return confirm('Close this batch? Statistics reset for the next batch.');">
      <?= csrf_field() ?>
      <button class="btn btn-warning btn-sm" type="submit">Close batch</button>
    </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="alert alert-secondary d-flex justify-content-between align-items-center">
    <span>No active batch. Scanning is paused until one is opened.</span>
    <?php if ($canManageBatches): ?>
    <button type="button" class="<?= btn('add') ?>" data-bs-toggle="modal" data-bs-target="#newBatchModal">
      <i class="bi bi-plus-lg" aria-hidden="true"></i> New Batch
    </button>
    <?php endif; ?>
  </div>
<?php endif; ?>

<table class="table manage-record-table align-middle w-100 mb-0">
  <thead><tr><th>Batch</th><th>Subsidy Type</th><th>Started</th><th>Closed</th></tr></thead>
  <tbody>
    <?php foreach (($batches ?? []) as $b): ?>
      <tr>
        <td><?= esc($b['name']) ?></td>
        <td><?= esc((string) ($b['aid_type_name'] ?? '')) ?></td>
        <td><?= esc($b['started_at']) ?></td>
        <td><?= $b['closed_at'] === null ? '<span class="badge bg-success">Open</span>' : esc($b['closed_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (($batches ?? []) === []): ?>
      <tr><td colspan="4" class="text-muted">No batches yet.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
