<?php
/**
 * Dashboard body (every role).
 *
 * Rendered inside layout.php as the `dashboard` page body. Zone 1 (program to
 * date) comes from DashboardModel::programStats() via DashboardPageBuilder;
 * Zone 2 (this batch) is Admin/dashboard-batch-body, fed by
 * DashboardPageBuilder::buildReportsData() and shown only to Developer/Admin
 * (see DashboardPageBuilder::buildViewData() for why $seesDistribution is
 * narrower than the Distribution *page*). The activity panel only renders for
 * an Encoder, who has no Audit Trails page of their own.
 */

$programStats = $programStats ?? ['families' => 0, 'neverServed' => 0];
$myAudits = $myAudits ?? [];
$seesDistribution = (bool) ($seesDistribution ?? false);
?>
<div class="dashboard-overview" data-dashboard-overview>
    <section class="program-strip" aria-label="Program to date">
        <div><span>Families profiled</span><strong><?= esc((string) ($programStats['families'] ?? 0)) ?></strong></div>
        <div><span>Never served</span><strong><?= esc((string) ($programStats['neverServed'] ?? 0)) ?></strong></div>
    </section>

    <?php if ($seesDistribution): ?>
        <?= view('Admin/dashboard-batch-body') ?>
    <?php endif; ?>

    <?php if (($role ?? '') === 'Encoder'): ?>
        <?php
        $myAuditRows = [];
        foreach ($myAudits as $audit) {
            $myAuditRows[] = [
                '<span class="status-pill is-muted">' . esc((string) ($audit['user_action'] ?? '')) . '</span>',
                esc(isset($formatAuditMember) ? $formatAuditMember($audit) : ''),
                esc((string) ($audit['description'] ?? '')),
            ];
        }
        ?>
        <?= view('components/data_table', [
            'icon' => 'clock-history',
            'title' => 'My Recent Activity',
            'columns' => ['Action', 'Member', 'Description'],
            'rows' => $myAuditRows,
            'emptyMessage' => 'No activity yet.',
            'tableClass' => 'table overview-table mb-0',
            'cardClass' => 'dashboard-table-panel',
            'headerActions' => null,
            'footer' => null,
        ]) ?>
    <?php endif; ?>
</div>
