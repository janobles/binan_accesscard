<?php
/**
 * One printed page of QR access cards, rendered repeatedly by the card PDF build.
 *
 * Nine cards to a page, laid out three per row. Short rows are padded with blank
 * cells so the CSS table keeps its shape, and every page after the first carries a
 * page break. Rendered through dompdf, not a browser, which is why the grid is a CSS
 * table rather than flex or grid.
 *
 * @var array $cells       The cards on this page, already ordered.
 * @var bool  $isFirstPage Suppresses the leading page break.
 */
?>
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
                            <div class="qr-wrap"><img class="qr" src="<?= esc($cell['qrDataUri'], 'attr') ?>" alt="QR"></div>
                            <div class="control-label">Control No.:</div>
                            <div class="control-number"><?= esc($cell['controlNumber']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
