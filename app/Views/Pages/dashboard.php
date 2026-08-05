<?php
/**
 * Dashboard body (every role), the page all staff land on after login.
 *
 * Three parts, in order. The stat row and Recent Records are unconditional:
 * they are the dashboard every role had before the panes arrived, and
 * `dashboard` is open to all staff, so a role that sees nothing else must
 * still see these. Between them sit the two batch-aware panes, picked by the
 * outer tab strip with ?view=. Overview covers the program end to end and
 * never moves with the batch selector; Distribution covers one batch. Both,
 * and the strip itself, are gated on $seesDistribution (see
 * DashboardPageBuilder::buildViewData() for why that is narrower than the
 * Distribution page).
 *
 * The activity panel renders only for an Encoder, who has no Audit Trails page
 * of their own.
 *
 * Data comes from DashboardPageBuilder::buildViewData().
 */

$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$sectorShortcodes = (array) ($sectorShortcodes ?? []);
$myAudits = $myAudits ?? [];
$seesDistribution = (bool) ($seesDistribution ?? false);
$dashboardView = ($dashboardView ?? 'overview') === 'distribution' ? 'distribution' : 'overview';
$selectedBatchId = (int) ($selectedBatchId ?? 0);
?>
<div class="dashboard-overview" data-dashboard-overview>
    <section class="overview-stats" aria-label="Dashboard statistics">
        <?= view('components/stat_card', [
            'label' => 'Total Records',
            'value' => (string) ($stats['families'] ?? 0),
            'icon' => 'folder-fill',
            'variant' => 'stat-card--records',
        ]) ?>
        <?= view('components/stat_card', [
            'label' => 'Registered Members',
            'value' => (string) ($stats['members'] ?? 0),
            'icon' => 'people-fill',
            'variant' => 'stat-card--members',
        ]) ?>
        <?= view('components/stat_card', [
            'label' => 'Active Sectors',
            'value' => (string) ($stats['sectors'] ?? 0),
            'icon' => 'diagram-3-fill',
            'variant' => 'stat-card--sectors',
        ]) ?>
        <?= view('components/stat_card', [
            'label' => 'Services and Programs',
            'value' => (string) ($stats['assistance'] ?? 0),
            'icon' => 'grid-fill',
            'variant' => 'stat-card--services',
        ]) ?>
    </section>

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

    <?= view('components/card', [
        'icon' => 'table',
        'title' => 'Recent Records',
        'cardClass' => 'dashboard-table-panel',
        'bodyView' => 'Pages/dashboard-recent-body',
        'bodyData' => [
            'recentFamilies' => $recentFamilies,
            'sectorShortcodes' => $sectorShortcodes,
            'formatDate' => $formatDate ?? null,
        ],
        'headerActions' => '<a class="btn btn-sm btn-outline-secondary" href="' . esc(site_url('records'), 'attr') . '">View All</a>',
        'footer' => null,
    ]) ?>

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
