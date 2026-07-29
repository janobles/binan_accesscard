<?php
/**
 * Dashboard body (every role).
 *
 * Rendered inside layout.php as the `dashboard` page body; data comes from
 * DashboardPageBuilder::buildViewData(). Two role differences survive here and
 * both are decided by the builder, not by this view: the distribution tiles and
 * section only render for Developer/Admin (narrower than the Distribution
 * *page*, which the navigation manifest also opens to Viewer - see
 * DashboardPageBuilder::buildViewData() for why), and the activity panel only
 * renders for an Encoder, who has no Audit Trails page of their own.
 */

$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$myAudits = $myAudits ?? [];
$seesDistribution = (bool) ($seesDistribution ?? false);
$reportsSummary = $reportsSummary ?? ['total' => 0, 'received' => 0, 'notReceived' => 0, 'coverage' => 0];
?>
<div class="dashboard-overview" data-dashboard-overview>
    <?php /* One unified KPI row: records first, then either the distribution
             numbers or the reference-data counts, depending on what this role
             may see. The distribution tiles use the sectors/services variants
             (unique on this page) so the live poll can target them. */ ?>
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
        <?php if ($seesDistribution): ?>
            <?= view('components/stat_card', [
                'label' => 'Received Subsidy',
                'value' => ($reportsSummary['received'] ?? 0) . ' of ' . ($reportsSummary['total'] ?? 0),
                'icon' => 'check-circle-fill',
                'variant' => 'stat-card--sectors',
            ]) ?>
            <?= view('components/stat_card', [
                'label' => 'Subsidy Coverage',
                'value' => ((string) ($reportsSummary['coverage'] ?? 0)) . '%',
                'icon' => 'pie-chart-fill',
                'variant' => 'stat-card--services',
            ]) ?>
        <?php else: ?>
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
        <?php endif; ?>
    </section>

    <?php if ($seesDistribution): ?>
        <?php /* Subsidy Distribution section (header + charts/tables);
                 variables come from buildReportsData(). */ ?>
        <?= view('Admin/reports-body') ?>
    <?php endif; ?>

    <div class="dashboard-section-head">
        <h2><i class="bi bi-people-fill" aria-hidden="true"></i>Family Records</h2>
        <div class="section-actions">
            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('records') ?>">View All <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>
    <?php
    $recentFamilyRows = [];
    foreach ($recentFamilies as $family) {
        $contact = trim((string) ($family['contactnumber'] ?? ''));
        $recentFamilyRows[] = [
            esc(trim(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))),
            esc((string) ($family['sector_name'] ?? '-')),
            $contact === '' ? '<span class="text-muted">-</span>' : esc($contact),
            esc($formatDate($family['dt_created'] ?? '')),
        ];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'table',
        'title' => 'Recent Records',
        'columns' => ['Name (Head)', 'Sector', 'Contact', 'Date Added'],
        'rows' => $recentFamilyRows,
        'emptyMessage' => 'No records yet.',
        'tableClass' => 'table overview-table mb-0',
        'cardClass' => 'dashboard-table-panel',
        // reports-body renders first and CI4 shares view data
        // between view() calls, so clear its leaked vars.
        'headerActions' => null,
        'footer' => null,
    ]) ?>

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
