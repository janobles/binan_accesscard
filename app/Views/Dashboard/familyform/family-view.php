<?php
$headView = (array) ($headView ?? []);
$memberViews = (array) ($memberViews ?? []);
$splitList = static function (mixed $value): array {
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', array_map('strval', $value))));
    }

    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
};
?>

<div class="family-detail">
    <div class="family-detail-hero">
        <div>
            <span class="family-detail-kicker">Head of Family</span>
            <h2><?= esc((string) ($headView['fullName'] ?? '-')) ?></h2>
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
        <div class="family-detail-list mt-3">
            <span class="family-service-label">Sectors</span>
            <?php $headSectors = $splitList($headView['sectorName'] ?? ''); ?>
            <?php if ($headSectors !== []): ?>
                <ul>
                    <?php foreach ($headSectors as $sectorName): ?>
                        <li><?= esc($sectorName) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span class="text-muted">No sectors listed.</span>
            <?php endif; ?>
        </div>
        <div class="family-service-list mt-3">
            <span class="family-service-label">Services availed</span>
            <?php if (($headView['services'] ?? []) !== []): ?>
                <ul>
                <?php foreach ((array) ($headView['services'] ?? []) as $serviceName): ?>
                    <li><?= esc((string) $serviceName) ?></li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span class="text-muted">No services availed.</span>
            <?php endif; ?>
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
                    </div>
                    <div class="family-detail-grid is-compact">
                        <?php foreach ((array) ($member['details'] ?? []) as $detail): ?>
                            <div class="family-detail-item">
                                <span><?= esc((string) ($detail['label'] ?? '')) ?></span>
                                <strong><?= esc((string) ($detail['value'] ?? '-')) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="family-detail-list">
                        <span class="family-service-label">Sectors</span>
                        <?php $memberSectors = $splitList($member['sectorName'] ?? ''); ?>
                        <?php if ($memberSectors !== []): ?>
                            <ul>
                                <?php foreach ($memberSectors as $sectorName): ?>
                                    <li><?= esc($sectorName) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted">No sectors listed.</span>
                        <?php endif; ?>
                    </div>
                    <div class="family-service-list">
                        <span class="family-service-label">Services availed</span>
                        <?php if (($member['services'] ?? []) !== []): ?>
                            <ul>
                            <?php foreach ((array) ($member['services'] ?? []) as $serviceName): ?>
                                <li><?= esc((string) $serviceName) ?></li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted">No services availed.</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
