<?php
/* Server-side PDF body (dompdf, no JS). Same numbers as the Reports tab; the
   barangay coverage is drawn as CSS bars in lieu of a chart. */
$window = ($batchName ?? null) !== null
    ? 'Batch: ' . $batchName
    : (($from || $to)
        ? ($from ?: 'start') . ' to ' . ($to ?: 'today')
        : 'All dates');
?>
<?= $this->include('Scanner/pdf/_styles') ?>
<h1>Aid Distribution Report</h1>
<p class="sub">City of Bi&ntilde;an CSWD &middot; <?= esc($window) ?> &middot; Generated <?= esc(date('Y-m-d H:i')) ?></p>

<table class="kpis" style="width:100%; border-collapse:collapse;">
  <tr>
    <td>Families with a QR<br><span class="n"><?= esc((string) $summary['total']) ?></span></td>
    <td>Received aid<br><span class="n"><?= esc((string) $summary['received']) ?></span></td>
    <td>Still waiting<br><span class="n"><?= esc((string) $summary['notReceived']) ?></span></td>
    <td>Coverage<br><span class="n"><?= esc((string) $summary['coverage']) ?>%</span></td>
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

<h2>Handouts by aid type</h2>
<table class="data">
  <thead><tr><th>Aid Type</th><th>Handouts</th></tr></thead>
  <tbody>
  <?php foreach ($byAidType as $a): ?>
    <tr><td><?= esc($a['aid_type']) ?></td><td><?= esc((string) $a['count']) ?></td></tr>
  <?php endforeach; ?>
  <?php if ($byAidType === []): ?>
    <tr><td colspan="2">No data for this range.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
