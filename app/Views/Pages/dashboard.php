<?php
/**
 * Dashboard body (every role).
 *
 * Two panes share this page and the outer tab strip picks between them with
 * ?view=. Overview covers the program end to end and never moves with the
 * batch selector; Distribution covers one batch. Both render only for the
 * roles that already see distribution data (see
 * DashboardPageBuilder::buildViewData() for why $seesDistribution is narrower
 * than the Distribution page itself).
 *
 * The activity panel renders only for an Encoder, who has no Audit Trails page
 * of their own and no tabs at all.
 */

$myAudits = $myAudits ?? [];
$seesDistribution = (bool) ($seesDistribution ?? false);
$dashboardView = ($dashboardView ?? 'overview') === 'distribution' ? 'distribution' : 'overview';
$selectedBatchId = (int) ($selectedBatchId ?? 0);
?>
<div class="dashboard-overview" data-dashboard-overview>
    <?php if ($seesDistribution): ?>
        <?= view('components/page_tabs', [
            'tabs' => [
                ['key' => 'overview', 'label' => 'Overview'],
                ['key' => 'distribution', 'label' => 'Distribution'],
            ],
            'active' => $dashboardView,
            'baseUrl' => 'dashboard',
            'param' => 'view',
            // An explicitly picked batch survives the hop to Overview and back.
            // Without it the return trip lands on whatever batch the default
            // resolves to, which is rarely the one the reader was looking at.
            'queryParams' => $selectedBatchId > 0 ? ['batch' => $selectedBatchId] : [],
        ]) ?>

        <?php if ($dashboardView === 'distribution'): ?>
            <?= view('Admin/batch-overview') ?>
        <?php else: ?>
            <?= view('Pages/dashboard-overview') ?>
        <?php endif; ?>
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
