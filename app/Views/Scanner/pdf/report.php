<?php
/**
 * Subsidy Distribution report PDF body, rendered by the admin Reports PDF action.
 *
 * Built by dompdf, so no JavaScript runs and the barangay coverage is drawn as CSS
 * bars rather than a chart. The figures are the same ones the on-screen report shows;
 * only the presentation differs.
 */
$window = ($batchName ?? null) !== null ? 'Batch: ' . $batchName : 'All batches';
?>
<?= $this->include('Scanner/pdf/_styles') ?>
<h1>Subsidy Distribution Report</h1>
<p class="sub">City of Bi&ntilde;an CSWD &middot; <?= esc($window) ?> &middot; Generated <?= esc(date('Y-m-d H:i')) ?></p>

<table class="kpis" style="width:100%; border-collapse:collapse;">
  <tr>
    <td>Eligible<br><span class="n"><?= esc((string) $coverage['eligible']) ?></span></td>
    <td>Served<br><span class="n"><?= esc((string) $coverage['served']) ?></span></td>
    <td>Remaining<br><span class="n"><?= esc((string) $coverage['remaining']) ?></span></td>
    <td>Coverage<br><span class="n"><?= esc((string) $coverage['coverage']) ?>%</span></td>
  </tr>
</table>

<h2>Coverage by barangay</h2>
<table class="data">
  <thead><tr><th>Barangay</th><th>Families</th><th>Received</th><th>Coverage</th></tr></thead>
  <tbody>
  <?php foreach ($byBarangay as $b): ?>
    <tr>
      <td><?= esc($b['barangay']) ?></td>
      <td><?= esc((string) $b['total']) ?></td>
      <td><?= esc((string) $b['received']) ?></td>
      <td><span class="bar" style="width: <?= (int) $b['coverage'] ?>px;"></span> <?= esc((string) $b['coverage']) ?>%</td>
    </tr>
  <?php endforeach; ?>
  <?php if ($byBarangay === []): ?>
    <tr><td colspan="4">No data for this range.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php if (($perScanner ?? []) !== []): ?>
<h2>Scanner performance</h2>
<table class="data">
  <thead><tr><th>Scanner</th><th>Families served</th><th>Handouts logged</th></tr></thead>
  <tbody>
  <?php foreach ($perScanner as $p): ?>
    <tr>
      <td><?= esc($p['scanner']) ?></td>
      <td><?= esc((string) $p['families']) ?></td>
      <td><?= esc((string) $p['handouts']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Remaining families (<?= esc((string) count($remaining)) ?>)</h2>
<table class="data">
  <thead><tr><th>Name</th><th>Barangay</th><th>Contact</th></tr></thead>
  <tbody>
  <?php foreach ($remaining as $r): ?>
    <tr>
      <td><?= esc($r['name']) ?></td>
      <td><?= esc($r['barangay']) ?></td>
      <td><?= esc($r['contact']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($remaining === []): ?>
    <tr><td colspan="3">No families remaining.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
