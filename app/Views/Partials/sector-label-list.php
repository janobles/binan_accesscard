<?php
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
