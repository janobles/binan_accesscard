#!/usr/bin/env php
<?php

/**
 * Reads a stylesheet on stdin and writes it back with comments and blank lines
 * removed.
 *
 * Usage: php scripts/strip-css-comments.php < some.css
 *
 * assert-css-unchanged.sh compares two runs of this to prove a commit touched
 * only CSS comments. The reader is quote-aware because the regex it replaced
 * removed anything between `/*` and `*\/`: a comment-like sequence inside a
 * quoted string or a url() was stripped from both sides, so an edit there
 * compared equal and the gate passed a real rule change.
 */

$source = (string) stream_get_contents(STDIN);
$length = strlen($source);
$out    = '';
$quote  = '';
$i      = 0;

while ($i < $length) {
    $char = $source[$i];

    if ($quote !== '') {
        // A backslash escapes the next character, including the closing quote.
        if ($char === '\\') {
            $out .= substr($source, $i, 2);
            $i += 2;
            continue;
        }

        if ($char === $quote) {
            $quote = '';
        }

        $out .= $char;
        $i++;
        continue;
    }

    if ($char === '"' || $char === "'") {
        $quote = $char;
        $out .= $char;
        $i++;
        continue;
    }

    if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
        $end = strpos($source, '*/', $i + 2);
        $i = $end === false ? $length : $end + 2;
        continue;
    }

    $out .= $char;
    $i++;
}

foreach (explode("\n", $out) as $line) {
    if (trim($line) !== '') {
        echo $line . "\n";
    }
}
