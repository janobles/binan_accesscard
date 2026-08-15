<?php
/**
 * Server-side page tab strip: an underline row, styled by theme.css
 * .segmented-tabs over Bootstrap's nav-pills (the shell's Bootstrap is 5.2.3,
 * which predates .nav-underline). Each tab is a plain link that reloads the
 * page with ?tab=<key>; only the active pane is rendered by the caller.
 *
 * Tabs are text only, no icons.
 *
 * Params: $tabs array of ['key' => string, 'label' => string],
 *         $active string, $baseUrl string (page URL without query),
 *         $queryParams array<string,scalar> extra query params carried through
 *         on every tab link (e.g. ['batch' => 5], so a page-level selector
 *         survives a tab switch). Defaults to [], which reproduces today's
 *         "?tab=<key>" href exactly - existing callers that don't pass it are
 *         unaffected.
 *         $param string the query key the tabs switch on, default 'tab'.
 *         A page with two tab strips gives the outer one 'view' so the two
 *         do not overwrite each other.
 *         $variantClass string extra class on the <ul>, default '' (today's
 *         appearance, unaffected). A page nesting a second strip inside a pane
 *         of the first passes 'segmented-tabs--subordinate' (theme.css) so the
 *         inner strip reads as subordinate rather than a second page nav.
 *         A strip inside a card header, switching between views of that
 *         card's own data rather than between pages, passes
 *         'segmented-tabs--card' (theme.css).
 */
$tabs = $tabs ?? [];
$active = $active ?? '';
$baseUrl = $baseUrl ?? '';
$queryParams = $queryParams ?? [];
$param = $param ?? 'tab';
$variantClass = $variantClass ?? '';

$extraQuery = '';
foreach ($queryParams as $paramName => $paramValue) {
    $extraQuery .= '&' . rawurlencode((string) $paramName) . '=' . rawurlencode((string) $paramValue);
}
?>
<ul class="nav nav-pills segmented-tabs<?= $variantClass !== '' ? ' ' . esc($variantClass, 'attr') : '' ?>">
    <?php foreach ($tabs as $tab): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab['key'] === $active ? 'active' : '' ?>"
           <?= $tab['key'] === $active ? 'aria-current="page"' : '' ?>
           <?php /* html context, not attr: the value sits inside a quoted
                    attribute, where both are safe, and attr would encode the
                    colons and slashes of a perfectly ordinary URL. */ ?>
           href="<?= esc(site_url($baseUrl)) ?>?<?= esc($param, 'attr') ?>=<?= esc($tab['key'], 'attr') ?><?= esc($extraQuery) ?>">
            <?= esc($tab['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
