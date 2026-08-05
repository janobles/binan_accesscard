<?php
/**
 * Dashboard body (every role).
 *
 * Rendered inside layout.php as the `dashboard` page body. Zone 1 (program to
 * date) comes from DashboardModel::programStats() via DashboardPageBuilder;
 * Zone 2 (this batch) is Admin/batch-overview, fed by
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
        <?= view('Admin/batch-overview') ?>
    <?php endif; ?>

    <?php if (($role ?? '') === 'Encoder'): ?>
        <?= view('components/card', [
            'icon' => 'clock-history',
            'title' => 'My Recent Activity',
            'cardClass' => 'dashboard-table-panel',
            'bodyView' => 'Pages/dashboard-activity-body',
            'bodyData' => [
                'myAudits' => $myAudits,
                'formatAuditMember' => $formatAuditMember ?? null,
            ],
            'footer' => null,
        ]) ?>
    <?php endif; ?>
</div>
