<?php
/**
 * Renders a member's sector names as a table cell, included by the record list in the
 * employee and viewer layouts.
 *
 * Takes the comma-joined label the list query already produces rather than a sector
 * array, so the caller does not have to reshape it. Renders three ways: a dash for
 * none, the bare name for one, and a list for several.
 */

$sectorLabel = trim((string) ($sectorLabel ?? ''));
$sectorItems = array_values(array_filter(
    array_map('trim', explode(',', $sectorLabel)),
    static fn (string $item): bool => $item !== ''
));
?>
<?php if ($sectorItems === []): ?>
    -
<?php elseif (count($sectorItems) === 1): ?>
    <?= esc($sectorItems[0]) ?>
<?php else: ?>
    <ul class="sector-label-list">
        <?php foreach ($sectorItems as $sectorItem): ?>
            <li><?= esc($sectorItem) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
