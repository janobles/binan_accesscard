<?php
/**
 * SB Admin 1 card shell — the one card anatomy every panel uses:
 * card-header (icon + title) > card-body > optional card-footer.
 *
 * Deterministic, props-only component: same args, same markup. Body content
 * comes from a named view (React "children" via composition), never inline.
 *
 * Variables (all defaulted defensively):
 * - $title      string       header text (required in practice)
 * - $icon       string|null  bootstrap-icons name without "bi-" prefix (e.g. 'table')
 * - $footer     string|null  footer HTML; caller esc()'s any dynamic parts. null = no footer
 * - $bodyView   string|null  view name rendered inside card-body (e.g. 'Scanner/reports-chart')
 * - $bodyData   array        data passed to $bodyView
 * - $bodyHtml   string|null  pre-rendered body HTML (alternative to $bodyView);
 *                            caller esc()'s any dynamic parts
 * - $headerActions string|null  raw HTML rendered right-aligned in the header
 *                               (caller-escaped), e.g. a small "View All" link
 * - $id         string|null  id attribute on the card element
 * - $attrs      string       extra raw attributes on the card element
 *                            (caller-escaped), e.g. ' data-audit-management-root'
 * - $cardClass  string       extra classes on the card element
 * - $bodyClass  string       extra classes on card-body
 */
$title = $title ?? '';
$icon = $icon ?? null;
$footer = $footer ?? null;
$bodyView = $bodyView ?? null;
$bodyData = $bodyData ?? [];
$bodyHtml = $bodyHtml ?? null;
$headerActions = $headerActions ?? null;
$id = $id ?? null;
$attrs = $attrs ?? '';
$cardClass = $cardClass ?? '';
$bodyClass = $bodyClass ?? '';
?>
<div class="card mb-4<?= $cardClass !== '' ? ' ' . esc($cardClass, 'attr') : '' ?>"<?= $id !== null ? ' id="' . esc($id, 'attr') . '"' : '' ?><?= $attrs !== '' ? ' ' . trim($attrs) : '' ?>>
    <div class="card-header<?= $headerActions !== null ? ' d-flex justify-content-between align-items-center' : '' ?>">
        <span><?php if ($icon !== null): ?><i class="bi bi-<?= esc($icon, 'attr') ?> me-1" aria-hidden="true"></i><?php endif; ?><?= esc($title) ?></span>
        <?php if ($headerActions !== null): ?><span><?= $headerActions ?></span><?php endif; ?>
    </div>
    <div class="card-body<?= $bodyClass !== '' ? ' ' . esc($bodyClass, 'attr') : '' ?>">
        <?php if ($bodyView !== null): ?>
            <?= view($bodyView, $bodyData) ?>
        <?php elseif ($bodyHtml !== null): ?>
            <?= $bodyHtml ?>
        <?php endif; ?>
    </div>
    <?php if ($footer !== null): ?>
    <div class="card-footer small text-muted"><?= $footer ?></div>
    <?php endif; ?>
</div>
