<?php
/**
 * Distribution Batches pane: active-batch banner + open/close controls + past
 * list. Ported from Scanner/manage-batches-body.php for the admin distribution
 * hub (routes moved from scanner/batches/* to admin/batches/*). Opening a
 * batch now requires choosing an aid type ($activeAidTypes); each batch row
 * shows its bound aid type. Lifecycle buttons render only for Admin/Developer.
 * Rendered inside components/card by Admin/layout.php's distribution block.
 */
$canManageBatches = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
$activeAidTypes   = $activeAidTypes ?? [];
?>
<?php if (($activeBatch ?? null) !== null): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <span><strong><?= esc($activeBatch['name']) ?></strong> (<?= esc((string) ($activeBatch['aid_type_name'] ?? '')) ?>) — open since <?= esc($activeBatch['started_at']) ?></span>
    <?php if ($canManageBatches): ?>
    <form method="post" action="<?= site_url('admin/batches/close/' . (int) $activeBatch['batch_id']) ?>"
          onsubmit="return confirm('Close this batch? Statistics reset for the next batch.');">
      <?= csrf_field() ?>
      <button class="btn btn-warning btn-sm" type="submit">Close batch</button>
    </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="alert alert-secondary">No active batch. Scanning is paused until one is opened.</div>
  <?php if ($canManageBatches): ?>
  <form method="post" action="<?= site_url('admin/batches/open') ?>" class="row g-2 align-items-end mb-3">
    <?= csrf_field() ?>
    <div class="col-auto flex-grow-1">
      <label for="batchName" class="form-label mb-0">Batch name</label>
      <input class="form-control" type="text" id="batchName" name="name" maxlength="100"
             placeholder="e.g. Rice Distribution — <?= esc(date('M j, Y')) ?>" required>
    </div>
    <div class="col-auto">
      <label for="batchAidType" class="form-label mb-0">Aid type</label>
      <select class="form-select" id="batchAidType" name="aid_type_id" required>
        <option value="">Select aid type</option>
        <?php foreach ($activeAidTypes as $t): ?>
          <option value="<?= esc((string) $t['aid_type_id'], 'attr') ?>"><?= esc($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" type="submit">Open batch</button>
    </div>
  </form>
  <?php endif; ?>
<?php endif; ?>

<table class="table manage-record-table align-middle w-100 mb-0">
  <thead><tr><th>Batch</th><th>Aid Type</th><th>Started</th><th>Closed</th></tr></thead>
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
