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
