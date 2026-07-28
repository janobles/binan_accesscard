<?php
/**
 * The page a visitor sees for an uncaught exception in production.
 *
 * Stock CodeIgniter, unmodified. Deliberately says nothing about what failed: the
 * detail goes to the log, never to the browser. Shown whenever CI_ENVIRONMENT is
 * production, so the debug template never reaches a live user.
 */
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">

    <title><?= lang('Errors.whoops') ?></title>

    <style>
        <?= preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.css')) ?>
    </style>
</head>
<body>

    <div class="container text-center">

        <h1 class="headline"><?= lang('Errors.whoops') ?></h1>

        <p class="lead"><?= lang('Errors.weHitASnag') ?></p>

    </div>

</body>

</html>
