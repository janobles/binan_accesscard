<?php
/**
 * KPI stat tile (dashboard overview + scanner reports).
 * Grid/typography rules live in public/css/theme.css
 * (.overview-stats / .reports-stats / .stat-card*).
 *
 * Variables:
 * - $label   string       tile caption (e.g. 'Total Records')
 * - $value   string       pre-formatted value; caller casts/escapes numbers
 * - $icon    string       bootstrap-icons name without "bi-" prefix
 * - $variant string       modifier class, e.g. 'stat-card--records'
 * - $valueId string|null  id on the value <strong>, so a live poll can repaint
 *                         just this number in place (e.g. dashboard-batch-body.php)
 */
$label = $label ?? '';
$value = $value ?? '0';
$icon = $icon ?? 'graph-up';
$variant = $variant ?? '';
$valueId = $valueId ?? null;
?>
<article class="stat-card <?= esc($variant, 'attr') ?> card h-100 py-2">
    <div class="card-body">
        <div class="stat-card-content">
            <div><p><?= esc($label) ?></p><strong<?= $valueId !== null ? ' id="' . esc($valueId, 'attr') . '"' : '' ?>><?= esc($value) ?></strong></div>
            <i class="bi bi-<?= esc($icon, 'attr') ?> stat-card-icon" aria-hidden="true"></i>
        </div>
    </div>
</article>
