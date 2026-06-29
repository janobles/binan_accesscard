<?php /** @var array $cells  @var bool $isFirstPage */ ?>
<div class="page <?= $isFirstPage ? '' : 'page-break' ?>">
    <div class="grid">
        <?php foreach (array_chunk($cells, 3) as $rowCells): ?>
            <div class="row">
                <?php foreach ($rowCells as $cell): ?>
                    <?php if (($cell['controlNumber'] ?? '') === ''): ?>
                        <div class="cell blank"></div>
                    <?php else: ?>
                        <div class="cell">
                            <div class="header">CITY OF BIÑAN</div>
                            <img class="qr" src="<?= $cell['qrDataUri'] ?>" alt="QR">
                            <div class="control"><?= esc($cell['controlNumber']) ?></div>
                            <div class="name"><?= esc($cell['fullname']) ?></div>
                            <div class="barangay"><?= esc($cell['barangay']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
