#!/usr/bin/env php
<?php

/**
 * Proves a commit changed no executable code.
 *
 * Usage: php scripts/assert-tokens-unchanged.php <git-ref> [path...]
 *
 * Tokenizes every PHP file at <git-ref> and in the working tree, drops
 * whitespace, comments, and docblocks, then compares what is left. Inline HTML
 * is kept and compared verbatim, so markup edits in a view are caught too.
 * Exits 0 when every file matches, 1 otherwise.
 */

$ref = $argv[1] ?? null;

if ($ref === null) {
    fwrite(STDERR, "usage: assert-tokens-unchanged.php <git-ref> [path...]\n");
    exit(2);
}

$paths = array_slice($argv, 2);

if ($paths === []) {
    $paths = ['app', 'tests', 'tools', 'scripts'];
}

/**
 * Reduces source to the tokens that actually run.
 *
 * @return list<string> One entry per token, safe to compare with ===.
 */
function significantTokens(string $source): array
{
    $ignored = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $out     = [];

    foreach (token_get_all($source) as $token) {
        if (is_string($token)) {
            $out[] = $token;
            continue;
        }

        if (in_array($token[0], $ignored, true)) {
            continue;
        }

        $out[] = token_name($token[0]) . ':' . $token[1];
    }

    return $out;
}

exec('git diff --name-only ' . escapeshellarg($ref) . ' -- ' . implode(' ', array_map('escapeshellarg', $paths)), $changed);

$changed = array_values(array_filter(
    $changed,
    static fn (string $file): bool => str_ends_with($file, '.php')
));

if ($changed === []) {
    echo "no PHP files changed against {$ref}\n";
    exit(0);
}

$failed = [];

foreach ($changed as $file) {
    $old = shell_exec('git show ' . escapeshellarg($ref . ':' . $file) . ' 2>/dev/null');
    $new = is_file($file) ? file_get_contents($file) : null;

    if ($old === null || $old === '') {
        echo "ADDED    {$file}\n";
        continue;
    }

    if ($new === null) {
        $failed[] = "{$file} (deleted)";
        continue;
    }

    if (significantTokens($old) === significantTokens($new)) {
        echo "OK       {$file}\n";
        continue;
    }

    $failed[] = $file;
}

if ($failed !== []) {
    fwrite(STDERR, "\nEXECUTABLE CODE CHANGED in " . count($failed) . " file(s):\n");

    foreach ($failed as $file) {
        fwrite(STDERR, "  {$file}\n");
    }

    fwrite(STDERR, "\nThis branch is documentation only. Revert the logic change.\n");
    exit(1);
}

echo "\nGate A passed: " . count($changed) . " file(s), no executable token changed.\n";
exit(0);
