<?php
$recentAudits = $recentAudits ?? ($myAudits ?? []);
$activityUrl = (string) ($activityUrl ?? site_url('admin/audit-trails'));
$manageRecordsUrl = (string) ($manageRecordsUrl ?? site_url('admin/manage-records'));
$activityLinkLabel = (string) ($activityLinkLabel ?? 'View All');
?>

<section class="overview-stats" aria-label="Dashboard statistics">
    <article class="stat-card">
        <p>Total Records</p>
        <strong><?= esc((string) $stats['families']) ?></strong>
    </article>
    <article class="stat-card">
        <p>Registered Members</p>
        <strong><?= esc((string) $stats['members']) ?></strong>
    </article>
    <article class="stat-card">
        <p>Active Sectors</p>
        <strong><?= esc((string) $stats['sectors']) ?></strong>
    </article>
    <article class="stat-card">
        <p>Services and Programs</p>
        <strong><?= esc((string) $stats['assistance']) ?></strong>
    </article>
</section>

<section class="overview-panel">
    <header class="panel-header">
        <h2>Recent Records</h2>
    </header>

    <?= view('components/searchbar', [
        'sectorOptions' => $sectorOptions ?? [],
        'pageTitle' => 'Manage Records',
        'routeAction' => $manageRecordsUrl,
    ]) ?>

    <div class="table-responsive">
        <table class="table overview-table">
            <thead>
                <tr>
                    <th scope="col">Name (Head)</th>
                    <th scope="col">Sector</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentFamilies === []): ?>
                    <tr>
                        <td class="empty-state" colspan="2">No recent records available.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentFamilies as $family): ?>
                        <tr>
                            <td><?= esc($family['display_name']) ?></td>
                            <td><?= esc((string) ($family['sector_name'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="overview-panel">
    <header class="panel-header">
        <h2>Recent Activity</h2>
        <a class="btn btn-sm panel-action" href="<?= esc($activityUrl, 'attr') ?>" data-workspace-partial-link>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
            <span><?= esc($activityLinkLabel) ?></span>
        </a>
    </header>

    <div class="table-responsive">
        <table class="table overview-table">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Member</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentAudits === []): ?>
                    <tr>
                        <td class="empty-state" colspan="4">No recent activity available.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentAudits as $audit): ?>
                        <tr>
                            <td><?= esc($audit['display_username']) ?></td>
                            <td><?= esc($audit['display_member']) ?></td>
                            <td><?= esc($audit['display_action']) ?></td>
                            <td><?= esc($audit['display_description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
