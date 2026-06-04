<?php
$modalId = (string) ($modalId ?? 'sector-service-modal');
$title = (string) ($title ?? 'Add');
$kicker = (string) ($kicker ?? 'Reference Data');
$saveLabel = (string) ($saveLabel ?? 'Save');
$fields = is_array($fields ?? null) ? $fields : [];
?>

<div
    class="sector-service-modal-overlay"
    id="<?= esc($modalId, 'attr') ?>"
    data-sector-service-modal
    hidden
>
    <section
        class="sector-service-modal-window"
        role="dialog"
        aria-modal="true"
        aria-labelledby="<?= esc($modalId . '-title', 'attr') ?>"
        tabindex="-1"
    >
        <header class="sector-service-modal-header">
            <div>
                <p class="sector-service-modal-kicker"><?= esc($kicker) ?></p>
                <h2 id="<?= esc($modalId . '-title', 'attr') ?>" data-sector-service-modal-title><?= esc($title) ?></h2>
            </div>

            <button class="sector-service-modal-close" type="button" data-sector-service-modal-close aria-label="Close <?= esc($title, 'attr') ?> window">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div class="sector-service-modal-body">
            <?php foreach ($fields as $field): ?>
                <?php
                $fieldId = (string) ($field['id'] ?? '');
                $fieldLabel = (string) ($field['label'] ?? '');
                $fieldType = (string) ($field['type'] ?? 'text');

                if ($fieldId === '' || $fieldLabel === '') {
                    continue;
                }
                ?>
                <div class="sector-service-modal-field">
                    <label class="form-label" for="<?= esc($fieldId, 'attr') ?>"><?= esc($fieldLabel) ?></label>

                    <?php if ($fieldType === 'textarea'): ?>
                        <textarea class="form-control" id="<?= esc($fieldId, 'attr') ?>" rows="4" data-sector-service-modal-field data-sector-service-modal-field-id="<?= esc($fieldId, 'attr') ?>"></textarea>
                    <?php else: ?>
                        <input class="form-control" id="<?= esc($fieldId, 'attr') ?>" type="text" data-sector-service-modal-field data-sector-service-modal-field-id="<?= esc($fieldId, 'attr') ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <footer class="sector-service-modal-footer">
            <button class="btn btn-outline-secondary" type="button" data-sector-service-modal-clear>Clear</button>
            <button class="btn btn-success" type="button" data-sector-service-modal-submit><?= esc($saveLabel) ?></button>
        </footer>
    </section>
</div>
