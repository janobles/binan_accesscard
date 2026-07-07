<?php
/**
 * Shared "Showing X–Y of Z" + Previous/Next pagination row for card footers.
 * Render with view() and pass the result as components/card's $footer.
 *
 * Variables:
 * - $fromRecord int, $toRecord int, $totalRows int
 * - $page int, $totalPages int
 * - $prevUrl string, $nextUrl string  (already-built page URLs)
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>Showing <?= esc((string) $fromRecord) ?>&ndash;<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?></span>
    <?php if ($totalPages > 1): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= esc($prevUrl, 'attr') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
            <span class="btn btn-sm disabled">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
            <a class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= esc($nextUrl, 'attr') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
        </div>
    <?php endif; ?>
</div>
