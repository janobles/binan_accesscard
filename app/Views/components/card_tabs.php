<?php
/**
 * A tab strip inside a card, switching between views of that card's own data.
 *
 * The sibling components/page_tabs.php renders links that reload the page with
 * a query parameter, which is right for a strip that changes what the page is
 * about. This one renders buttons instead, because switching Hours for Weekdays
 * or Map for Table changes nothing outside the card it sits in: a reload would
 * cost the reader their scroll position and every other card's state to move
 * one control. The visual class is the same (segmented-tabs--card, theme.css),
 * so the two strips are not two designs.
 *
 * No JavaScript here. The card declares data-strip on itself and the panes
 * declare data-strip-pane; batch-heatmap.js does the showing and hiding, so a
 * card gets a strip by naming its panes, not by carrying a script.
 *
 * Params: $tabs list of ['key' => string, 'label' => string],
 *         $active string, the key whose button starts pressed.
 */
$tabs = $tabs ?? [];
$active = $active ?? '';
?>
<ul class="nav nav-pills segmented-tabs segmented-tabs--card" role="tablist">
    <?php foreach ($tabs as $tab): ?>
    <li class="nav-item" role="presentation">
        <button type="button" role="tab"
                class="nav-link<?= $tab['key'] === $active ? ' active' : '' ?>"
                aria-selected="<?= $tab['key'] === $active ? 'true' : 'false' ?>"
                data-strip-target="<?= esc($tab['key'], 'attr') ?>">
            <?= esc($tab['label']) ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>
