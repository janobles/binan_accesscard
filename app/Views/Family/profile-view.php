<?php
/**
 * Family Profile page in read view (`records/{id}`): the record printed, not the
 * record in a form. Every field is a small label above a bold value, the way a
 * fare ticket prints one, so nothing on the page can be typed into or submitted.
 * Sectors and programs print as a plain list. Editing lives on its own page
 * (`records/{id}/edit`), reached from the Edit button here.
 *
 * Data source: FamilyController::profile(), shaped by
 * App\Libraries\FamilyRecordSummary and documented as family_record_view_data()
 * in app/Helpers/dashboard_view_helper.php.
 */

$head = (array) ($head ?? []);
$members = (array) ($members ?? []);
$canEdit = (bool) ($canEdit ?? false);
$headId = (int) ($headId ?? 0);

/** Prints one label/value pair as a ticket-style column. */
$field = static function (array $entry): string {
    return '<div class="col-6 col-md-4 col-xl-3"><div class="record-view-field">'
        . '<span class="record-view-label">' . esc((string) $entry['label']) . '</span>'
        . '<span class="record-view-value">' . esc((string) $entry['value']) . '</span>'
        . '</div></div>';
};

/** Prints a plain list of sector or program labels, or a dash when there are none. */
$list = static function (string $title, array $items): string {
    $body = $items === []
        ? '<p class="record-view-value mb-0">-</p>'
        : '<ul class="record-view-list">'
            . implode('', array_map(static fn (string $item): string => '<li>' . esc($item) . '</li>', $items))
            . '</ul>';

    return '<div class="col-12 col-md-6"><span class="record-view-label">' . esc($title) . '</span>' . $body . '</div>';
};
?>
<div class="container-fluid px-4 py-4">
    <div class="card shadow-sm record-view">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-0"><?= esc((string) ($head['name'] ?? '')) ?></h2>
                <span class="text-body-secondary small">Head of Family</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= esc(site_url('records'), 'attr') ?>">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Records
                </a>
                <?php if ($canEdit): ?>
                <a class="btn btn-primary btn-sm" href="<?= esc(site_url('records/' . $headId . '/edit'), 'attr') ?>">
                    <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ((array) ($head['fields'] ?? []) as $entry): ?>
                    <?= $field((array) $entry) ?>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mt-1">
                <?= $list('Sectors', (array) ($head['sectors'] ?? [])) ?>
                <?= $list('Services and Programs', (array) ($head['services'] ?? [])) ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm record-view mt-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0">Family Members</h2>
            <span class="badge rounded-pill text-bg-light border"><?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?></span>
        </div>
        <div class="card-body">
            <?php if ($members === []): ?>
                <p class="small text-body-secondary fst-italic mb-0">No members recorded.</p>
            <?php endif; ?>
            <?php foreach ($members as $index => $member): ?>
                <?php $member = (array) $member; ?>
                <div class="record-view-member<?= $index > 0 ? ' mt-3 pt-3 border-top' : '' ?>">
                    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                        <span class="fw-semibold"><?= esc((string) ($member['name'] ?? '')) ?></span>
                        <span class="text-body-secondary small"><?= esc((string) ($member['relationship'] ?? '')) ?></span>
                    </div>
                    <div class="row g-3">
                        <?php foreach ((array) ($member['fields'] ?? []) as $entry): ?>
                            <?= $field((array) $entry) ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-3 mt-1">
                        <?= $list('Sectors', (array) ($member['sectors'] ?? [])) ?>
                        <?= $list('Services and Programs', (array) ($member['services'] ?? [])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
