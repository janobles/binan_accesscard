<?php
/**
 * Remaining tab pane on the dashboard: one page of the unclaimed families on
 * the batch roster (SubsidyStatsModel::remainingPage(), gated to this tab by
 * DashboardPageBuilder::buildReportsData()). Rendered by
 * Admin/batch-overview.php, which supplies
 * the matching server-paginated components/table_footer. Against the
 * 100k-family target, a batch's remaining list can run to hundreds of rows,
 * too large to hand table-paginate.js the whole thing client-side, so the
 * search box here is a real database search (routed through
 * SubsidyStatsModel::remainingBuilder()), not the usual in-page client filter.
 */
$remaining = $remaining ?? [];
$keyword = (string) ($keyword ?? '');
$perPage = (int) ($perPage ?? 25);
$perPageOptions = (array) ($perPageOptions ?? [10, 25, 50, 100]);
$searchHiddenHtml = (string) ($searchHiddenHtml ?? '');
$sizeHiddenHtml = (string) ($sizeHiddenHtml ?? '');
?>
<?= view('components/table_controls', [
    'searchId' => 'remainingSearch',
    'searchAria' => 'Search remaining families',
    'searchAction' => site_url('dashboard'),
    'searchValue' => $keyword,
    'searchPlaceholder' => 'Search by family name or barangay...',
    'searchHiddenHtml' => $searchHiddenHtml,
    'sizeId' => 'remainingPerPage',
    'sizeAction' => site_url('dashboard'),
    'sizeHiddenHtml' => $sizeHiddenHtml,
    'perPage' => $perPage,
    'perPageOptions' => $perPageOptions,
]) ?>
<?php /* Wrapped like the Barangay and Distributions tables. Without it any
         overflow this table has pushes the page body sideways instead of
         scrolling inside its own box. */ ?>
<div class="table-responsive">
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
        <tr><td colspan="3" class="text-muted"><?= $keyword !== '' ? 'No remaining families match your search.' : 'Everyone on the roster has been served.' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
