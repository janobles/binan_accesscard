<?php
/**
 * In-card controls row, Manage Records standard: page search on the left
 * (input-group with an integrated btn-primary search icon), "Show N entries"
 * on the right. Pure Bootstrap utilities - it sits inside the card-body's own
 * padding and must never re-pad (ui-design-system.md Rule 6).
 *
 * Variables:
 * - $showSearch        bool         false hides the page-search box, for lists
 *                                   where a page keyword would not match what
 *                                   the page's action operates on
 * - $searchId          string       id for the page-search input
 * - $searchAria        string       aria-label for the search form
 * - $searchFormAttrs   string       extra raw attrs on the search <form>
 *                                   (caller-escaped), e.g. ' data-lookup-search'
 * - $searchInputAttrs  string       extra raw attrs on the input
 *                                   (caller-escaped), e.g. ' data-lookup-search-input'
 * - $searchButtonAttrs string       extra raw attrs on the icon button
 *                                   (caller-escaped); client-only tables can
 *                                   wire an onclick here
 * - $searchAction      string|null  GET action for the search form; null (the
 *                                   default) keeps the client-local behavior
 *                                   above. Set this when the table itself is
 *                                   server-paginated, so a client filter over
 *                                   the one loaded page would miss every row
 *                                   not on screen - the search has to be a
 *                                   real GET like the page-size form below.
 * - $searchName        string       name attribute for the search input when
 *                                   $searchAction is set (default 'q')
 * - $searchValue       string       current keyword, echoed into the input's
 *                                   value when $searchAction is set
 * - $searchPlaceholder string       input placeholder; default "Search this
 *                                   page..." (client mode wording), override
 *                                   for a $searchAction server search
 * - $searchHiddenHtml  string       hidden inputs preserving current filters
 *                                   on the search form (caller-escaped),
 *                                   only used when $searchAction is set
 * - $sizeId            string       id for the page-size <select>
 * - $sizeAttrs         string       extra raw attrs on the client-side page-size
 *                                   <select> (caller-escaped), e.g.
 *                                   ' data-paginate-size="accounts"'
 * - $sizeAction        string|null  GET action for the page-size form; null =
 *                                   client-side select (no form, no submit)
 * - $sizeHiddenHtml    string       hidden inputs preserving current filters
 *                                   (caller-escaped values)
 * - $perPage           int          current page size
 * - $perPageOptions    array        value => label; plain lists use value as label
 */
$showSearch = (bool) ($showSearch ?? true);
$searchId = (string) ($searchId ?? 'tableLocalSearch');
$searchAria = (string) ($searchAria ?? 'Search this page');
$searchFormAttrs = (string) ($searchFormAttrs ?? '');
$searchInputAttrs = (string) ($searchInputAttrs ?? '');
$searchButtonAttrs = (string) ($searchButtonAttrs ?? '');
$searchAction = $searchAction ?? null;
$searchName = (string) ($searchName ?? 'q');
$searchValue = (string) ($searchValue ?? '');
$searchPlaceholder = (string) ($searchPlaceholder ?? 'Search this page...');
$searchHiddenHtml = (string) ($searchHiddenHtml ?? '');
$sizeId = (string) ($sizeId ?? 'tablePerPage');
$sizeAttrs = (string) ($sizeAttrs ?? '');
$sizeAction = $sizeAction ?? null;
$sizeHiddenHtml = (string) ($sizeHiddenHtml ?? '');
$perPage = (int) ($perPage ?? 25);
$perPageOptions = (array) ($perPageOptions ?? [10, 25, 50, 100]);
// Plain lists ([10, 25, 50]) become value => label maps; assoc arrays pass
// through so client tables can offer e.g. 0 => 'All'.
if (array_is_list($perPageOptions)) {
    $perPageOptions = array_combine($perPageOptions, $perPageOptions);
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <?php if ($showSearch && $searchAction !== null): ?>
    <form class="records-table-search-form mb-0" role="search" method="get" action="<?= esc($searchAction, 'attr') ?>" aria-label="<?= esc($searchAria, 'attr') ?>"<?= $searchFormAttrs !== '' ? ' ' . trim($searchFormAttrs) : '' ?>>
        <?= $searchHiddenHtml ?>
        <div class="input-group input-group-sm">
            <input class="form-control" type="search" id="<?= esc($searchId, 'attr') ?>" name="<?= esc($searchName, 'attr') ?>" value="<?= esc($searchValue, 'attr') ?>" placeholder="<?= esc($searchPlaceholder, 'attr') ?>" autocomplete="off" aria-label="<?= esc($searchPlaceholder, 'attr') ?>"<?= $searchInputAttrs !== '' ? ' ' . trim($searchInputAttrs) : '' ?>>
            <button class="btn btn-primary" type="submit" aria-label="<?= esc($searchPlaceholder, 'attr') ?>"<?= $searchButtonAttrs !== '' ? ' ' . trim($searchButtonAttrs) : '' ?>><i class="bi bi-search" aria-hidden="true"></i></button>
        </div>
    </form>
    <?php elseif ($showSearch): ?>
    <form class="records-table-search-form mb-0" role="search" aria-label="<?= esc($searchAria, 'attr') ?>"<?= $searchFormAttrs !== '' ? ' ' . trim($searchFormAttrs) : '' ?>>
        <div class="input-group input-group-sm">
            <input class="form-control" type="search" id="<?= esc($searchId, 'attr') ?>" placeholder="Search this page..." autocomplete="off" aria-label="Search this page"<?= $searchInputAttrs !== '' ? ' ' . trim($searchInputAttrs) : '' ?>>
            <button class="btn btn-primary" type="submit" aria-label="Search this page"<?= $searchButtonAttrs !== '' ? ' ' . trim($searchButtonAttrs) : '' ?>><i class="bi bi-search" aria-hidden="true"></i></button>
        </div>
    </form>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
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
        <select class="form-select form-select-sm w-auto" id="<?= esc($sizeId, 'attr') ?>"<?= $sizeAttrs !== '' ? ' ' . trim($sizeAttrs) : '' ?>>
            <?php foreach ($perPageOptions as $value => $label): ?>
                <option value="<?= esc((string) $value, 'attr') ?>" <?= (string) $perPage === (string) $value ? 'selected' : '' ?>><?= esc((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
        <span>entries</span>
    </div>
    <?php endif; ?>
</div>
