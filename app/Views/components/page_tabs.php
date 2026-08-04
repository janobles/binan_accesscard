<?php
/**
 * Server-side segmented tab strip (Bootstrap nav-pills in an enclosed track,
 * styled by theme.css .segmented-tabs). Each tab is a plain link that reloads
 * the page with ?tab=<key>; only the active pane is rendered by the caller.
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
 */
$tabs = $tabs ?? [];
$active = $active ?? '';
$baseUrl = $baseUrl ?? '';
$queryParams = $queryParams ?? [];

$extraQuery = '';
foreach ($queryParams as $paramName => $paramValue) {
    $extraQuery .= '&' . rawurlencode((string) $paramName) . '=' . rawurlencode((string) $paramValue);
}
?>
<ul class="nav nav-pills segmented-tabs mb-3">
    <?php foreach ($tabs as $tab): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab['key'] === $active ? 'active' : '' ?>"
           <?= $tab['key'] === $active ? 'aria-current="page"' : '' ?>
           href="<?= site_url($baseUrl) ?>?tab=<?= esc($tab['key'], 'attr') ?><?= esc($extraQuery) ?>">
            <?= esc($tab['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
