<?php
$head           = (array) ($head ?? []);
$members        = (array) ($members ?? []);
$serviceMap     = (array) ($serviceMap ?? []);
$serviceNameMap = (array) ($serviceNameMap ?? []);

$fullName = static function (array $person): string {
    $name = trim(
        ($person['firstname'] ?? '') . ' '
        . ($person['middlename'] ?? '') . ' '
        . ($person['lastname'] ?? '') . ' '
        . ($person['suffix'] ?? '')
    );

    return $name !== '' ? $name : '-';
};

$display = static fn (mixed $value): string => trim((string) $value) !== '' ? (string) $value : '-';

$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '-' : date('Y-m-d', $timestamp);
};

$formatTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '-' : date('h:i A', $timestamp);
};

$serviceNames = static function (array $person) use ($serviceMap, $serviceNameMap): array {
    $memberId = (int) ($person['memberID'] ?? 0);
    $names    = [];

    foreach (($serviceMap[$memberId] ?? []) as $serviceId) {
        $serviceId = (int) $serviceId;
        $names[]   = (string) ($serviceNameMap[$serviceId] ?? ('Service #' . $serviceId));
    }

    return $names;
};

$detailItem = static function (string $label, mixed $value) use ($display): string {
    return '<div class="family-detail-item"><span>' . esc($label) . '</span><strong>' . esc($display($value)) . '</strong></div>';
};

$renderServices = static function (array $names): string {
    if ($names === []) {
        return '<span class="text-muted">No services availed.</span>';
    }

    $items = array_map(
        static fn (string $name): string => '<span class="family-service-chip">' . esc($name) . '</span>',
        $names
    );

    return implode('', $items);
};
?>

<div class="family-detail">
    <div class="family-detail-hero">
        <div>
            <span class="family-detail-kicker">Head of Family</span>
            <h2><?= esc($fullName($head)) ?></h2>
            <p><?= esc($display($head['sector_name'] ?? '')) ?></p>
        </div>
        <div class="family-detail-date">
            <span>Created</span>
            <strong><?= esc($formatDate($head['dt_created'] ?? '')) ?></strong>
            <small><?= esc($formatTime($head['dt_created'] ?? '')) ?></small>
        </div>
    </div>

    <section class="family-detail-section">
        <div class="family-detail-section-title">Profile</div>
        <div class="family-detail-grid">
            <?= $detailItem('Birthday', $head['birthday'] ?? '-') ?>
            <?= $detailItem('Sex', $head['sex'] ?? '-') ?>
            <?= $detailItem('Civil status', $head['civilstatus'] ?? '-') ?>
            <?= $detailItem('Contact number', $head['contactnumber'] ?? '-') ?>
            <?= $detailItem('Education', $head['education'] ?? '-') ?>
            <?= $detailItem('Job', $head['job'] ?? '-') ?>
            <?= $detailItem('Monthly income', $head['Salary'] ?? '-') ?>
            <?= $detailItem('Last updated', ($head['dt_updated'] ?? '') !== '' ? $formatDate($head['dt_updated'] ?? '') . ' ' . $formatTime($head['dt_updated'] ?? '') : '-') ?>
        </div>
        <div class="family-service-list mt-3">
            <span class="family-service-label">Services availed</span>
            <div><?= $renderServices($serviceNames($head)) ?></div>
        </div>
    </section>

    <section class="family-detail-section">
        <div class="family-detail-section-title">Family Members</div>
        <?php if ($members === []): ?>
            <p class="text-muted mb-0">No family members found.</p>
        <?php endif; ?>

        <div class="family-member-list">
            <?php foreach ($members as $member): ?>
                <article class="family-member-item">
                    <div class="family-member-heading">
                        <div>
                            <strong><?= esc($fullName($member)) ?></strong>
                            <span><?= esc($display($member['relationship'] ?? 'Member')) ?></span>
                        </div>
                        <small><?= esc($display($member['sector_name'] ?? '')) ?></small>
                    </div>
                    <div class="family-detail-grid is-compact">
                        <?= $detailItem('Birthday', $member['birthday'] ?? '-') ?>
                        <?= $detailItem('Sex', $member['sex'] ?? '-') ?>
                        <?= $detailItem('Civil status', $member['civilstatus'] ?? '-') ?>
                        <?= $detailItem('Contact number', $member['contactnumber'] ?? '-') ?>
                        <?= $detailItem('Education', $member['education'] ?? '-') ?>
                        <?= $detailItem('Job', $member['job'] ?? '-') ?>
                        <?= $detailItem('Monthly income', $member['Salary'] ?? '-') ?>
                        <?= $detailItem('Created', ($member['dt_created'] ?? '') !== '' ? $formatDate($member['dt_created'] ?? '') . ' ' . $formatTime($member['dt_created'] ?? '') : '-') ?>
                    </div>
                    <div class="family-service-list">
                        <span class="family-service-label">Services availed</span>
                        <div><?= $renderServices($serviceNames($member)) ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
