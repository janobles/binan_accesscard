<?php
/**
 * The 400 page, shown when a request is rejected as malformed.
 *
 * Diverges from stock CodeIgniter in one way: the inline CSS block was lifted into
 * _error_styles.php, which error_404.php shares. Outside production the page names
 * the reason; in production it stays vague on purpose.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= lang('Errors.badRequest') ?></title>

    <?php include __DIR__ . '/_error_styles.php'; ?>
</head>
<body>
<div class="wrap">
    <h1>400</h1>

    <p>
        <?php if (ENVIRONMENT !== 'production') : ?>
            <?= nl2br(esc($message)) ?>
        <?php else : ?>
            <?= lang('Errors.sorryBadRequest') ?>
        <?php endif; ?>
    </p>
</div>
</body>
</html>
