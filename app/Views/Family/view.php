<?php
use App\Libraries\ViewFormatter;

$headView = (array) ($headView ?? []);
$memberViews = (array) ($memberViews ?? []);

/**
 * Build up to two-letter initials from a display name for the avatar badge.
 */
$familyInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));

    if ($parts === []) {
        return '–';
    }

    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

    return mb_strtoupper($first . $last);
};

$headName = (string) ($headView['fullName'] ?? '-');
?>

<div class="family-detail">
    <div class="family-detail-hero">
        <div class="family-detail-hero-main">
            <span class="family-avatar" aria-hidden="true"><?= esc($familyInitials($headName)) ?></span>
            <div>
                <span class="family-detail-kicker">Head of Family</span>
                <h2><?= esc($headName) ?></h2>
            </div>
        </div>
        <div class="family-detail-date">
            <span>Created</span>
            <strong><?= esc((string) ($headView['createdDate'] ?? '-')) ?></strong>
            <small><?= esc((string) ($headView['createdTime'] ?? '-')) ?></small>
        </div>
    </div>

    <section class="family-detail-section">
        <div class="family-detail-section-title">
            <i class="bi bi-person-vcard" aria-hidden="true"></i>Profile
        </div>
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
            <?php $headSectors = ViewFormatter::splitList($headView['sectorName'] ?? ''); ?>
            <?php if ($headSectors !== []): ?>
                <div class="family-chip-group">
                    <?php foreach ($headSectors as $sectorName): ?>
                        <span class="family-chip"><?= esc($sectorName) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <span class="family-empty">No sectors listed.</span>
            <?php endif; ?>
        </div>
        <div class="family-service-list mt-3">
            <span class="family-service-label">Services availed</span>
            <?php if (($headView['services'] ?? []) !== []): ?>
                <div class="family-chip-group">
                    <?php foreach ((array) ($headView['services'] ?? []) as $serviceName): ?>
                        <span class="family-chip is-service"><?= esc((string) $serviceName) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <span class="family-empty">No services availed.</span>
            <?php endif; ?>
        </div>
    </section>

    <section class="family-detail-section">
        <div class="family-detail-section-title">
            <i class="bi bi-people" aria-hidden="true"></i>Family Members
            <span class="family-detail-count"><?= count($memberViews) ?></span>
        </div>
        <?php if ($memberViews === []): ?>
            <p class="family-empty mb-0">No family members found.</p>
        <?php endif; ?>

        <div class="family-member-list">
            <?php foreach ($memberViews as $member): ?>
                <?php $memberName = (string) ($member['fullName'] ?? '-'); ?>
                <article class="family-member-item">
                    <div class="family-member-heading">
                        <div class="family-member-identity">
                            <span class="family-avatar is-sm" aria-hidden="true"><?= esc($familyInitials($memberName)) ?></span>
                            <strong><?= esc($memberName) ?></strong>
                        </div>
                        <span class="family-relationship"><?= esc((string) ($member['relationship'] ?? 'Member')) ?></span>
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
                        <?php $memberSectors = ViewFormatter::splitList($member['sectorName'] ?? ''); ?>
                        <?php if ($memberSectors !== []): ?>
                            <div class="family-chip-group">
                                <?php foreach ($memberSectors as $sectorName): ?>
                                    <span class="family-chip"><?= esc($sectorName) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="family-empty">No sectors listed.</span>
                        <?php endif; ?>
                    </div>
                    <div class="family-service-list">
                        <span class="family-service-label">Services availed</span>
                        <?php if (($member['services'] ?? []) !== []): ?>
                            <div class="family-chip-group">
                                <?php foreach ((array) ($member['services'] ?? []) as $serviceName): ?>
                                    <span class="family-chip is-service"><?= esc((string) $serviceName) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="family-empty">No services availed.</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
