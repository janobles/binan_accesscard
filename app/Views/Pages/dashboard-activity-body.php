<?php
/**
 * The Encoder's own recent audit entries, rendered inside components/card by
 * Pages/dashboard.php. Only an Encoder sees it: every other role reaches the
 * same records through the Audit Trails page.
 *
 * Rows come from DashboardPageBuilder as raw audit_trails rows; the member
 * label is formatted by the $formatAuditMember helper the builder supplies.
 */

$myAudits = $myAudits ?? [];
$formatAuditMember = $formatAuditMember ?? null;
?>
<div class="table-responsive">
  <table class="table overview-table align-middle mb-0">
    <thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead>
    <tbody>
      <?php foreach ($myAudits as $audit): ?>
      <tr>
        <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
        <td><?= esc((string) ($formatAuditMember !== null ? $formatAuditMember($audit) : '')) ?></td>
        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($myAudits === []): ?>
      <tr><td colspan="3" class="text-muted">No activity yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
