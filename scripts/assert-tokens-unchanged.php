#!/usr/bin/env php
<?php

/**
 * Proves a commit changed no executable code.
 *
 * Usage: php scripts/assert-tokens-unchanged.php <git-ref> [--allow-added] [path...]
 *
 * Tokenizes every PHP file at <git-ref> and in the working tree, drops
 * whitespace, comments, and docblocks, then compares what is left. Inline HTML
 * is kept and compared verbatim, so markup edits in a view are caught too.
 *
 * Renames are disabled in the diff so a rename-plus-edit cannot hide behind a
 * single "added" path: it surfaces as a delete of the old path and an add of
 * the new one, and both are failures. Added and deleted files fail by default
 * because a documentation branch adds and removes no PHP files in the
 * compared paths; pass --allow-added for the rare task that legitimately
 * adds one.
 *
 * Exits 0 when every file matches and none were added or deleted, 1 otherwise.
 */

$args = array_slice($argv, 1);

$allowAdded = false;
$positional = [];

foreach ($args as $arg) {
    if ($arg === '--allow-added') {
        $allowAdded = true;
        continue;
    }

    $positional[] = $arg;
}

$ref = $positional[0] ?? null;

if ($ref === null) {
    fwrite(STDERR, "usage: assert-tokens-unchanged.php <git-ref> [--allow-added] [path...]\n");
    exit(2);
}

$paths = array_slice($positional, 1);

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

exec(
    'git diff --no-renames --name-status ' . escapeshellarg($ref)
    . ' -- ' . implode(' ', array_map('escapeshellarg', $paths)),
    $rawChanged
);

$changed = [];

foreach ($rawChanged as $line) {
    $parts = explode("\t", $line);

    if (count($parts) < 2) {
        continue;
    }

    [$status, $file] = $parts;

    if (! str_ends_with($file, '.php')) {
        continue;
    }

    $changed[] = ['status' => $status, 'file' => $file];
}

if ($changed === []) {
    echo "no PHP files changed against {$ref}\n";
    exit(0);
}

$failed   = [];
$compared = 0;
$added    = 0;
$deleted  = 0;

foreach ($changed as $entry) {
    $status = $entry['status'];
    $file   = $entry['file'];

    if ($status === 'A') {
        $added++;

        if ($allowAdded) {
            echo "ADDED    {$file} (allowed)\n";
            continue;
        }

        echo "ADDED    {$file} (not permitted on this branch)\n";
        $failed[] = "{$file} (added)";
        continue;
    }

    if ($status === 'D') {
        $deleted++;
        echo "DELETED  {$file}\n";
        $failed[] = "{$file} (deleted)";
        continue;
    }

    // Modified (or any other in-place status git-diff may report, e.g. type change).
    $old = shell_exec('git show ' . escapeshellarg($ref . ':' . $file) . ' 2>/dev/null');
    $new = is_file($file) ? file_get_contents($file) : null;

    if ($new === null) {
        $failed[] = "{$file} (missing from working tree)";
        continue;
    }

    $compared++;

    if (significantTokens((string) $old) === significantTokens($new)) {
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

echo "\nGate A passed: {$compared} file(s) compared, {$added} added, {$deleted} deleted, no executable token changed.\n";
exit(0);
