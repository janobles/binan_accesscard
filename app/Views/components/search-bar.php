<?php
/**
 * Reusable jade-style search bar (text + "All sectors" + Search / Search All).
 *
 * Used by the dashboard "Recent Records" panel and the Manage Records list. Styled
 * with public/css/searchbar.css (.searchbar / .searchbar-actions / .searchbar-action),
 * already loaded by the admin/employee shells.
 *
 * Params:
 *   $searchTerm        string  current keyword (the `q` input value)
 *   $sectorOptions     array   [['sectorID'=>.., 'name'=>..], ...] for the dropdown
 *   $selectedSectorId  string  currently selected sectorID
 *   $searchAction      string  form action — where "Search" submits (in-page filter)
 *   $searchAllAction   string  where the "Search All" button submits (whole-database deep search)
 *   $status            string  optional 'archived' to preserve the Active/Archived tab
 *   $searchPlaceholder string  optional input placeholder
 *
 * "Search" submits q + sectorID to $searchAction. "Search All" additionally submits
 * search_scope=all to $searchAllAction, which DashboardPageBuilder turns into a deep
 * (whole-database) search.
 */
$searchTerm        = (string) ($searchTerm ?? '');
$sectorOptions     = $sectorOptions ?? [];
$selectedSectorId  = (string) ($selectedSectorId ?? '');
$searchAction      = (string) ($searchAction ?? '');
$searchAllAction   = (string) ($searchAllAction ?? $searchAction);
$status            = (string) ($status ?? '');
$searchPlaceholder = (string) ($searchPlaceholder ?? 'Search records by name, contact number, or sector');
?>
<form class="searchbar" method="get" action="<?= esc($searchAction, 'attr') ?>" aria-label="Search records">
    <?php if ($status === 'archived'): ?>
        <input type="hidden" name="status" value="archived">
    <?php endif; ?>

    <input
        class="form-control"
        type="search"
        name="q"
        value="<?= esc($searchTerm, 'attr') ?>"
        placeholder="<?= esc($searchPlaceholder, 'attr') ?>"
        aria-label="Search records"
    >

    <select class="form-select" name="sectorID" aria-label="Filter records by sector">
        <option value="">All sectors</option>
        <?php foreach ($sectorOptions as $sector): ?>
            <?php $optionId = (string) ($sector['sectorID'] ?? ''); ?>
            <option value="<?= esc($optionId, 'attr') ?>" <?= $selectedSectorId === $optionId ? 'selected' : '' ?>>
                <?= esc((string) ($sector['name'] ?? '')) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div class="searchbar-actions">
        <button class="btn btn-success searchbar-action" type="submit" data-search-mode="local">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span>Search</span>
        </button>

        <button
            class="btn btn-outline-success searchbar-action"
            type="submit"
            data-search-mode="all"
            name="search_scope"
            value="all"
            formaction="<?= esc($searchAllAction, 'attr') ?>"
        >
            <i class="bi bi-database-search" aria-hidden="true"></i>
            <span>Search All</span>
        </button>
    </div>
</form>
