<?php
/**
 * Remaining tab pane inside the dashboard's batch table: one page of the
 * unclaimed families on the batch roster (SubsidyStatsModel::remainingPage(),
 * gated to this tab by DashboardPageBuilder::buildReportsData()). Rendered
 * inside components/card by Admin/dashboard-batch-body.php, which supplies
 * the matching server-paginated components/table_footer. Against the
 * 100k-family target, a batch's remaining list can run to hundreds of rows,
 * too large to hand table-paginate.js the whole thing client-side.
 */
$remaining = $remaining ?? [];
?>
<table class="table manage-record-table align-middle w-100 mb-0">
  <thead><tr><th>Name</th><th>Barangay</th><th>Contact</th></tr></thead>
  <tbody>
    <?php foreach ($remaining as $r): ?>
      <tr>
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
