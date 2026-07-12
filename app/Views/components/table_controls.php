<?php
/**
 * In-card controls row, Manage Records standard: page search on the left
 * (input-group with an integrated btn-primary search icon), "Show N entries"
 * on the right. Pure Bootstrap utilities — it sits inside the card-body's own
 * padding and must never re-pad (ui-design-system.md Rule 6).
 *
 * Variables:
 * - $searchId          string       id for the page-search input
 * - $searchAria        string       aria-label for the search form
 * - $searchFormAttrs   string       extra raw attrs on the search <form>
 *                                   (caller-escaped), e.g. ' data-lookup-search'
 * - $searchInputAttrs  string       extra raw attrs on the input
 *                                   (caller-escaped), e.g. ' data-lookup-search-input'
 * - $searchButtonAttrs string       extra raw attrs on the icon button
 *                                   (caller-escaped); client-only tables can
 *                                   wire an onclick here
 * - $sizeId            string       id for the page-size <select>
 * - $sizeAction        string|null  GET action for the page-size form; null =
 *                                   client-side select (no form, no submit)
 * - $sizeHiddenHtml    string       hidden inputs preserving current filters
 *                                   (caller-escaped values)
 * - $perPage           int          current page size
 * - $perPageOptions    array        value => label; plain lists use value as label
 */
$searchId = (string) ($searchId ?? 'tableLocalSearch');
$searchAria = (string) ($searchAria ?? 'Search this page');
$searchFormAttrs = (string) ($searchFormAttrs ?? '');
$searchInputAttrs = (string) ($searchInputAttrs ?? '');
$searchButtonAttrs = (string) ($searchButtonAttrs ?? '');
$sizeId = (string) ($sizeId ?? 'tablePerPage');
$sizeAction = $sizeAction ?? null;
$sizeHiddenHtml = (string) ($sizeHiddenHtml ?? '');
$perPage = (int) ($perPage ?? 50);
$perPageOptions = (array) ($perPageOptions ?? [10, 25, 50, 100]);
// Plain lists ([10, 25, 50]) become value => label maps; assoc arrays pass
// through so client tables can offer e.g. 0 => 'All'.
if (array_is_list($perPageOptions)) {
    $perPageOptions = array_combine($perPageOptions, $perPageOptions);
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form class="records-table-search-form mb-0" role="search" aria-label="<?= esc($searchAria, 'attr') ?>"<?= $searchFormAttrs !== '' ? ' ' . trim($searchFormAttrs) : '' ?>>
        <div class="input-group input-group-sm">
            <input class="form-control" type="search" id="<?= esc($searchId, 'attr') ?>" placeholder="Search this page..." autocomplete="off" aria-label="Search this page"<?= $searchInputAttrs !== '' ? ' ' . trim($searchInputAttrs) : '' ?>>
            <button class="btn btn-primary" type="submit" aria-label="Search this page"<?= $searchButtonAttrs !== '' ? ' ' . trim($searchButtonAttrs) : '' ?>><i class="bi bi-search" aria-hidden="true"></i></button>
        </div>
    </form>
    <?php if ($sizeAction !== null): ?>
    <form class="d-flex align-items-center gap-2 mb-0 small text-muted" method="get" action="<?= esc((string) $sizeAction, 'attr') ?>">
        <?= $sizeHiddenHtml ?>
        <label class="mb-0" for="<?= esc($sizeId, 'attr') ?>">Show</label>
        <select class="form-select form-select-sm w-auto" id="<?= esc($sizeId, 'attr') ?>" name="per_page" onchange="this.form.submit()">
            <?php foreach ($perPageOptions as $value => $label): ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= (string) $perPage === (string) $value ? 'selected' : '' ?>><?= esc((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
        <span>entries</span>
    </form>
    <?php else: ?>
    <div class="d-flex align-items-center gap-2 small text-muted">
        <label class="mb-0" for="<?= esc($sizeId, 'attr') ?>">Show</label>
        <select class="form-select form-select-sm w-auto" id="<?= esc($sizeId, 'attr') ?>">
            <?php foreach ($perPageOptions as $value => $label): ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= (string) $perPage === (string) $value ? 'selected' : '' ?>><?= esc((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
        <span>entries</span>
    </div>
    <?php endif; ?>
</div>
