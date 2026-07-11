<?php
/**
 * Bootstrap modal shell, props-only (same pattern as components/card).
 *
 * Variables:
 * - $id          string       modal id (JS hook, required in practice)
 * - $title       string       modal-title text
 * - $titleId     string|null  id on the h5 title (aria hooks)
 * - $size        string       dialog size class, e.g. 'modal-xl' | 'modal-lg' | ''
 * - $modalClass  string       extra classes on .modal (e.g. 'floating-family-modal')
 * - $attrs       string       extra raw attributes on .modal (caller-escaped),
 *                             e.g. ' data-bs-backdrop="static"'
 * - $bodyId      string|null  id on .modal-body (AJAX-content target)
 * - $bodyView    string|null  view rendered inside modal-body
 * - $bodyData    array        data for $bodyView
 * - $bodyHtml    string|null  pre-rendered body HTML (caller-escaped)
 * - $footerHtml  string|null  footer HTML; null renders no footer
 */
$id = $id ?? '';
$title = $title ?? '';
$titleId = $titleId ?? null;
$size = $size ?? '';
$modalClass = $modalClass ?? '';
$attrs = $attrs ?? '';
$bodyId = $bodyId ?? null;
$bodyView = $bodyView ?? null;
$bodyData = $bodyData ?? [];
$bodyHtml = $bodyHtml ?? null;
$footerHtml = $footerHtml ?? null;
?>
<div class="modal fade<?= $modalClass !== '' ? ' ' . esc($modalClass, 'attr') : '' ?>" id="<?= esc($id, 'attr') ?>" tabindex="-1" aria-hidden="true"<?= $attrs !== '' ? ' ' . trim($attrs) : '' ?>>
    <div class="modal-dialog modal-dialog-centered<?= $size !== '' ? ' ' . esc($size, 'attr') : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"<?= $titleId !== null ? ' id="' . esc($titleId, 'attr') . '"' : '' ?>><?= esc($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"<?= $bodyId !== null ? ' id="' . esc($bodyId, 'attr') . '"' : '' ?>>
                <?php if ($bodyView !== null): ?>
                    <?= view($bodyView, $bodyData) ?>
                <?php elseif ($bodyHtml !== null): ?>
                    <?= $bodyHtml ?>
                <?php endif; ?>
            </div>
            <?php if ($footerHtml !== null): ?>
            <div class="modal-footer"><?= $footerHtml ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
