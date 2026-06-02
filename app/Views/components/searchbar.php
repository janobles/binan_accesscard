<?php
$keyword = (string) ($keyword ?? '');
$sectorId = (string) ($sectorId ?? '');
$sectorOptions = $sectorOptions ?? [];
$status = (string) ($status ?? 'active') === 'archived' ? 'archived' : 'active';
$pageTitle = (string) ($pageTitle ?? 'Manage Records');
?>

<form
    class="searchbar"
    method="get"
    action="<?= site_url('admin/manage-records') ?>"
    data-workspace-search-form
    data-page-title="<?= esc($pageTitle, 'attr') ?>"
    aria-label="Search records"
>
    <?php if ($status === 'archived'): ?>
        <input type="hidden" name="status" value="archived">
    <?php endif; ?>

    <input
        class="form-control"
        type="search"
        name="q"
        value="<?= esc($keyword, 'attr') ?>"
        placeholder="Search records by name, contact number, or sector"
        aria-label="Search records"
    >

    <select class="form-select" name="sectorID" aria-label="Filter records by sector">
        <option value="">All sectors</option>
        <?php foreach ($sectorOptions as $sector): ?>
            <?php $optionId = (string) ($sector['sectorID'] ?? ''); ?>
            <option value="<?= esc($optionId, 'attr') ?>" <?= $sectorId === $optionId ? 'selected' : '' ?>>
                <?= esc((string) ($sector['name'] ?? '')) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div class="searchbar-actions">
        <button class="btn btn-success searchbar-action" type="submit" name="search_scope" value="heads">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span>Search</span>
        </button>

        <button class="btn btn-outline-success searchbar-action" type="submit" name="search_scope" value="all">
            <i class="bi bi-database-search" aria-hidden="true"></i>
            <span>Search All</span>
        </button>
    </div>
</form>
