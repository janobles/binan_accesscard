<?php
/**
 * The 404 page, shown for any route that does not resolve.
 *
 * Diverges from stock CodeIgniter in one way: the inline CSS block was lifted into
 * _error_styles.php, which error_400.php shares. Outside production the page names
 * the missing route; in production it stays vague on purpose.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= lang('Errors.pageNotFound') ?></title>

    <?php include __DIR__ . '/_error_styles.php'; ?>
</head>
<body>
    <div class="wrap">
        <h1>404</h1>

        <p>
            <?php if (ENVIRONMENT !== 'production') : ?>
                <?= nl2br(esc($message)) ?>
            <?php else : ?>
                <?= lang('Errors.sorryCannotFind') ?>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>
