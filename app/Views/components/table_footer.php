<?php
/**
 * Shared "Showing X–Y of Z" + Previous/Next pagination row for card footers.
 * Render with view() and pass the result as components/card's $footer.
 *
 * Variables:
 * - $leftContent string|null  (optional override for the Showing text)
 * - $fromRecord int, $toRecord int, $totalRows int
 * - $page int, $totalPages int
 * - $prevUrl string, $nextUrl string  (already-built page URLs)
 */
$leftContent = $leftContent ?? null;
$fromRecord = $fromRecord ?? 0;
$toRecord = $toRecord ?? 0;
$totalRows = $totalRows ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$prevUrl = $prevUrl ?? '#';
$nextUrl = $nextUrl ?? '#';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
    <div class="table-footer-left">
        <?php if ($leftContent !== null): ?>
            <?= $leftContent ?>
        <?php else: ?>
            Showing <?= esc((string) $fromRecord) ?>&ndash;<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?>
        <?php endif; ?>
    </div>
    <div class="table-footer-right">
        <?php if ($totalPages > 1): ?>
            <ul class="pagination pagination-sm m-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= esc($prevUrl, 'attr') ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
                </li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= esc($nextUrl, 'attr') ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
                </li>
            </ul>
        <?php endif; ?>
    </div>
</div>
