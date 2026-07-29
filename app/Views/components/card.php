<?php
/**
 * SB Admin 1 card shell - the one card anatomy every panel uses:
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

/*
 * Two different leaks here, from two different parts of CI4's renderer, fixed
 * two different ways.
 *
 * 1. Within this one render chain: View::$tempData is a single property the
 *    renderer reuses for every nested view() call, not a fresh scope per
 *    call, and setData() merges onto it rather than replacing it. So a
 *    nested view($bodyView, $bodyData) call that doesn't mention "bodyView"
 *    in its own data (data_table.php composing components/card with just
 *    $bodyHtml, say) leaves THIS card's $bodyView string sitting in
 *    $tempData, and the next nested view() call inherits it - card.php runs
 *    again seeing a $bodyView it never received, walks the same branch, and
 *    recurses until memory runs out. Nulling this file's own prop names in
 *    $tempData now (top), before the nested render happens, breaks that
 *    chain: the local PHP variables above already hold this card's real
 *    values, so this only removes the stale copy the renderer would
 *    otherwise still be carrying.
 *
 * 2. Across separate renders: CI4's view() defaults to $saveData = true,
 *    which copies $tempData into the persistent View::$data at the *start*
 *    of every render() call, before the view file runs. That copy captures
 *    whatever this card last merged into $tempData - including $bodyHtml -
 *    and nothing this file does to $tempData afterwards reaches $data again,
 *    because nothing re-renders here to trigger another copy. The next,
 *    unrelated view('components/card', ...) call (the next card on the
 *    page) then inherits that stale $data as its own starting point and can
 *    render a body it was never given. resetData() at the bottom empties
 *    $data directly, sidestepping that copy timing, so the next unrelated
 *    card starts from nothing.
 */
service('renderer')->setData([
    'bodyView'      => null,
    'bodyData'      => [],
    'bodyHtml'      => null,
    'headerActions' => null,
    'footer'        => null,
    'icon'          => null,
    'id'            => null,
    'attrs'         => '',
    'cardClass'     => '',
    'bodyClass'     => '',
], 'raw');
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
<?php service('renderer')->resetData(); ?>
