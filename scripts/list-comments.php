#!/usr/bin/env php
<?php

/**
 * Prints every comment in the given files, one line each, as `file:line:text`.
 *
 * Usage: php scripts/list-comments.php <file>...
 *
 * check-comment-style.sh scans this output rather than the raw files, so its
 * banned-pattern checks see comments and nothing else. Scanning raw text made
 * an em dash or a `----` run inside a PHP string, a regex, or inline HTML read
 * as a comment violation.
 *
 * PHP files are tokenized, so only T_COMMENT and T_DOC_COMMENT are reported;
 * a view's inline `<script>` is T_INLINE_HTML and never appears here, which is
 * the same exclusion the divider scan used to spell out by hand. CSS is not
 * tokenizable by PHP, so it gets a small quote-aware reader below.
 *
 * A multi-line comment is reported once per line, numbered from where that
 * line sits in the file, so a hit points at the line a reader would open.
 */

$files = array_slice($argv, 1);

if ($files === []) {
    fwrite(STDERR, "usage: list-comments.php <file>...\n");
    exit(2);
}

/**
 * Emits one `file:line:text` row per line of a comment.
 */
function emit(string $file, int $startLine, string $text): void
{
    foreach (explode("\n", $text) as $offset => $line) {
        echo $file . ':' . ($startLine + $offset) . ':' . rtrim($line, "\r") . "\n";
    }
}

/**
 * Every comment in a PHP file, via the tokenizer.
 */
function phpComments(string $file): void
{
    $source = (string) file_get_contents($file);

    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            emit($file, $token[2], $token[1]);
        }
    }
}

/**
 * Every `/* ... *\/` comment in a stylesheet. Reads character by character so a
 * comment opener inside a quoted string or a url() is text, not a comment.
 */
function cssComments(string $file): void
{
    $source = (string) file_get_contents($file);
    $length = strlen($source);
    $line   = 1;
    $quote  = '';
    $i      = 0;

    while ($i < $length) {
        $char = $source[$i];

        if ($char === "\n") {
            $line++;
            $i++;
            continue;
        }

        if ($quote !== '') {
            // A backslash escapes the next character, including the closing
            // quote. When that character is a newline the line still advances,
            // or every comment after it is reported one line early.
            if ($char === '\\') {
                if (($source[$i + 1] ?? '') === "\n") {
                    $line++;
                }

                $i += 2;
                continue;
            }

            if ($char === $quote) {
                $quote = '';
            }

            $i++;
            continue;
        }

        if ($char === '"' || $char === "'") {
            $quote = $char;
            $i++;
            continue;
        }

        if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
            $end = strpos($source, '*/', $i + 2);
            $end = $end === false ? $length : $end + 2;
            $text = substr($source, $i, $end - $i);

            emit($file, $line, $text);

            $line += substr_count($text, "\n");
            $i = $end;
            continue;
        }

        $i++;
    }
}

foreach ($files as $file) {
    if (! is_file($file)) {
        continue;
    }

    if (str_ends_with($file, '.css')) {
        cssComments($file);
        continue;
    }

    phpComments($file);
}
