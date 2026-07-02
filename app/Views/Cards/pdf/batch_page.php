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
                            <div class="field-row">
                                <span class="field-label">Barangay:</span>
                                <span class="field-line"><?= esc($cell['barangay']) ?></span>
                            </div>
                            <div class="field-row">
                                <span class="field-label">Name:</span>
                                <span class="field-line"><?= esc($cell['fullname']) ?></span>
                            </div>
                            <div class="qr-wrap"><img class="qr" src="<?= $cell['qrDataUri'] ?>" alt="QR"></div>
                            <div class="control-label">Control No.:</div>
                            <div class="control-number"><?= esc($cell['controlNumber']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
