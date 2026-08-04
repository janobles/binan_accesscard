<?php
/**
 * Remaining tab pane inside the dashboard's batch table: the unclaimed
 * families on the batch roster. Rendered inside components/card by
 * Admin/dashboard-batch-body.php. Client-side searched and paginated by
 * assets/js/dashboard/table-paginate.js (data-paginate-* below), the same
 * pattern the Distribution Batches and Distribution Log tables use, because
 * a batch's remaining list can run to hundreds of rows.
 */
$remaining = $remaining ?? [];
?>
<?= view('components/table_controls', [
    'searchId' => 'remainingLocalSearch',
    'searchAria' => 'Search remaining families',
    'searchFormAttrs' => 'onsubmit="return false;"',
    'searchInputAttrs' => 'data-paginate-search="remaining"',
    'sizeId' => 'remainingPerPage',
    'sizeAction' => null,
    'perPage' => 25,
    'perPageOptions' => [10 => '10', 25 => '25', 50 => '50', 100 => '100', 0 => 'All'],
    'sizeAttrs' => 'data-paginate-size="remaining"',
]) ?>

<table class="table manage-record-table align-middle w-100 mb-0">
  <thead><tr><th>Name</th><th>Barangay</th><th>Contact</th></tr></thead>
  <tbody>
    <?php foreach ($remaining as $r): ?>
      <tr data-paginate-row>
        <td><?= esc($r['name']) ?></td>
        <td><?= esc($r['barangay']) ?></td>
        <td><?= $r['contact'] === '' ? '<span class="text-muted">-</span>' : esc($r['contact']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($remaining === []): ?>
      <tr><td colspan="3" class="text-muted">Everyone on the roster has been served.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
