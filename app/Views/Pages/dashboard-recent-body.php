<?php
/**
 * The newest family heads, rendered inside components/card by
 * Pages/dashboard.php. Every role sees it: profiling is the part of the
 * program nobody is shut out of.
 *
 * Rows come from DashboardPageBuilder as raw member rows (its search results
 * when the page carries a search term, otherwise DashboardModel's newest ten).
 * Sector badges are built from the shortcode map the builder supplies, the
 * same way Manage Records builds them.
 */

$recentFamilies = $recentFamilies ?? [];
$sectorShortcodes = (array) ($sectorShortcodes ?? []);
$formatDate = $formatDate ?? null;
?>
<div class="table-responsive">
  <table class="table overview-table align-middle mb-0">
    <thead><tr><th>Name (Head)</th><th>Sector</th><th>Contact</th><th>Date Added</th></tr></thead>
    <tbody>
      <?php foreach ($recentFamilies as $family): ?>
      <?php $contact = trim((string) ($family['contactnumber'] ?? '')); ?>
      <tr>
        <td><?= esc(trim((string) ($family['firstname'] ?? '') . ' ' . (string) ($family['lastname'] ?? ''))) ?></td>
        <td><?= \App\Libraries\ViewFormatter::sectorBadges($family['sectorID'] ?? null, $sectorShortcodes) ?></td>
        <td><?= $contact === '' ? '<span class="text-muted">-</span>' : esc($contact) ?></td>
        <td><?= esc($formatDate !== null ? $formatDate($family['dt_created'] ?? '') : '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($recentFamilies === []): ?>
      <tr><td colspan="4" class="text-muted">No records yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
