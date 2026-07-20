<?php
/**
 * Server-side Bootstrap tab strip. Each tab is a plain link that reloads the
 * page with ?tab=<key>; only the active pane is rendered by the caller.
 *
 * Params: $tabs array of ['key' => string, 'label' => string, 'icon' => string],
 *         $active string, $baseUrl string (page URL without query).
 */
$tabs = $tabs ?? [];
$active = $active ?? '';
$baseUrl = $baseUrl ?? '';
?>
<ul class="nav nav-tabs manage-tabs mb-3">
    <?php foreach ($tabs as $tab): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab['key'] === $active ? 'active' : '' ?>"
           <?= $tab['key'] === $active ? 'aria-current="page"' : '' ?>
           href="<?= site_url($baseUrl) ?>?tab=<?= esc($tab['key'], 'attr') ?>">
            <i class="bi bi-<?= esc($tab['icon'], 'attr') ?>" aria-hidden="true"></i>
            <?= esc($tab['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
