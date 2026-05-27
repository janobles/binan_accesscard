<?php
$headView = (array) ($headView ?? []);
$memberViews = (array) ($memberViews ?? []);
?>

<div class="family-detail">
    <div class="family-detail-hero">
        <div>
            <span class="family-detail-kicker">Head of Family</span>
            <h2><?= esc((string) ($headView['fullName'] ?? '-')) ?></h2>
            <p><?= esc((string) ($headView['sectorName'] ?? '-')) ?></p>
        </div>
        <div class="family-detail-date">
            <span>Created</span>
            <strong><?= esc((string) ($headView['createdDate'] ?? '-')) ?></strong>
            <small><?= esc((string) ($headView['createdTime'] ?? '-')) ?></small>
        </div>
    </div>

    <section class="family-detail-section">
        <div class="family-detail-section-title">Profile</div>
        <div class="family-detail-grid">
            <?php foreach ((array) ($headView['details'] ?? []) as $detail): ?>
                <div class="family-detail-item">
                    <span><?= esc((string) ($detail['label'] ?? '')) ?></span>
                    <strong><?= esc((string) ($detail['value'] ?? '-')) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="family-service-list mt-3">
            <span class="family-service-label">Services availed</span>
            <div>
                <?php foreach ((array) ($headView['services'] ?? []) as $serviceName): ?>
                    <span class="family-service-chip"><?= esc((string) $serviceName) ?></span>
                <?php endforeach; ?>
                <?php if (($headView['services'] ?? []) === []): ?>
                    <span class="text-muted">No services availed.</span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="family-detail-section">
        <div class="family-detail-section-title">Family Members</div>
        <?php if ($memberViews === []): ?>
            <p class="text-muted mb-0">No family members found.</p>
        <?php endif; ?>

        <div class="family-member-list">
            <?php foreach ($memberViews as $member): ?>
                <article class="family-member-item">
                    <div class="family-member-heading">
                        <div>
                            <strong><?= esc((string) ($member['fullName'] ?? '-')) ?></strong>
                            <span><?= esc((string) ($member['relationship'] ?? 'Member')) ?></span>
                        </div>
                        <small><?= esc((string) ($member['sectorName'] ?? '-')) ?></small>
                    </div>
                    <div class="family-detail-grid is-compact">
                        <?php foreach ((array) ($member['details'] ?? []) as $detail): ?>
                            <div class="family-detail-item">
                                <span><?= esc((string) ($detail['label'] ?? '')) ?></span>
                                <strong><?= esc((string) ($detail['value'] ?? '-')) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="family-service-list">
                        <span class="family-service-label">Services availed</span>
                        <div>
                            <?php foreach ((array) ($member['services'] ?? []) as $serviceName): ?>
                                <span class="family-service-chip"><?= esc((string) $serviceName) ?></span>
                            <?php endforeach; ?>
                            <?php if (($member['services'] ?? []) === []): ?>
                                <span class="text-muted">No services availed.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
