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
 * A tab that controls nothing is not a tab. Each button names its pane through
 * aria-controls, and the pane names the button back through aria-labelledby, so
 * a screen reader reading the strip can reach what it switches. Both ids are
 * built from $stripId and the pane key rather than the key alone, because a
 * page carries more than one of these strips and two cards both holding a
 * "table" pane would otherwise collide. Same problem, same fix as $gridId on
 * Admin/batch-heatmap.php.
 *
 * A card renders its panes itself rather than passing them in here, so it has
 * to build the two ids the same way. The two helpers below are the definition;
 * a caller mirrors them as "<stripId>-pane-<key>" and "<stripId>-tab-<key>".
 *
 * Params: $tabs list of ['key' => string, 'label' => string],
 *         $active string, the key whose button starts pressed,
 *         $stripId string, unique on the page, prefixing both id families.
 */
$tabs = $tabs ?? [];
$active = $active ?? '';
$stripId = (string) ($stripId ?? 'strip');

$tabId = static fn (string $key): string => $stripId . '-tab-' . $key;
$paneId = static fn (string $key): string => $stripId . '-pane-' . $key;
?>
<ul class="nav nav-pills segmented-tabs segmented-tabs--card" role="tablist">
    <?php foreach ($tabs as $tab): ?>
    <li class="nav-item" role="presentation">
        <button type="button" role="tab"
                id="<?= esc($tabId($tab['key']), 'attr') ?>"
                class="nav-link<?= $tab['key'] === $active ? ' active' : '' ?>"
                aria-selected="<?= $tab['key'] === $active ? 'true' : 'false' ?>"
                aria-controls="<?= esc($paneId($tab['key']), 'attr') ?>"
                data-strip-target="<?= esc($tab['key'], 'attr') ?>">
            <?= esc($tab['label']) ?>
        </button>
    </li>
    <?php endforeach; ?>
</ul>
