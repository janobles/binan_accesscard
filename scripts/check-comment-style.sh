#!/usr/bin/env bash
# Layer 3 of the documentation standard: the checks phpcs and php-cs-fixer
# cannot reach. Views have no class to hang a docblock on, and stylesheets are
# not PHP at all.
#
# Usage: bash scripts/check-comment-style.sh

set -uo pipefail

failed=0

report() {
    echo "$1" >&2
    failed=1
}

# A view's docblock must actually open the file: the first non-blank line is
# either the docblock itself, or a bare `<?php` immediately followed by the
# docblock on the next non-blank line. A `/**` merely present somewhere in
# the first few lines (e.g. after real code) does not count. This only
# proves a header is present and first; it cannot verify the header names
# the right page or the right data source, which is a review matter.
opens_with_docblock() {
    awk '
    {
        line = $0
        gsub(/^[ \t]+|[ \t]+$/, "", line)
        if (line == "") next
        if (state == "") {
            if (line == "<?php") { state = "afterphp"; next }
            print (line ~ /^\/\*\*/) ? "OK" : "FAIL"
            found = 1
            exit
        }
        print (line ~ /^\/\*\*/) ? "OK" : "FAIL"
        found = 1
        exit
    }
    END { if (!found) print "FAIL" }
    ' "$1"
}

while IFS= read -r file; do
    if [ "$(opens_with_docblock "$file")" != "OK" ]; then
        report "MISSING VIEW HEADER: $file"
    fi
done < <(find app/Views -name '*.php')

# A stylesheet's header must be the first non-blank line, not merely present
# on line 1 in some other sense. This proves presence and position only, not
# that the header describes the right thing; that is a review matter.
opens_with_css_header() {
    awk '
    {
        line = $0
        gsub(/^[ \t]+|[ \t]+$/, "", line)
        if (line == "") next
        print (line ~ /^\/\*/) ? "OK" : "FAIL"
        found = 1
        exit
    }
    END { if (!found) print "FAIL" }
    ' "$1"
}

for file in public/css/*.css; do
    if [ "$(opens_with_css_header "$file")" != "OK" ]; then
        report "MISSING CSS HEADER: $file"
    fi
done

# Both bans below are about prose, so they run over comment text only, never the
# raw file. list-comments.php tokenizes the PHP and reads the CSS quote-aware, so
# an em dash or a `----` run inside a string, a regex, or inline HTML is no longer
# reported as a comment violation. A view's inline <script> is T_INLINE_HTML and
# so never reaches these scans; covering JS comments means giving the JS files
# their own extractor, which is its own job (see docs/reference/violations.md).
# app/Config is stock framework code and public/assets is vendored.
comments="$(mktemp)"
trap 'rm -f "$comments"' EXIT

find app public/css \( -name '*.php' -o -name '*.css' \) -not -path 'app/Config/*' -print0 \
    | xargs -0 php scripts/list-comments.php > "$comments" 2>/dev/null

# Em dashes read as AI slop in this codebase. Plain language only.
while IFS= read -r hit; do
    report "EM DASH: $hit"
done < <(grep -F '—' "$comments" 2>/dev/null)

# Divider comments are replaced by prose or by splitting the unit.
while IFS= read -r hit; do
    report "DIVIDER COMMENT: $hit"
done < <(grep -E '(//|/\*|\*)[[:space:]]*[-=]{4,}' "$comments" 2>/dev/null)

if [ "$failed" -ne 0 ]; then
    echo "" >&2
    echo "See docs/reference/comment-standard.md" >&2
    exit 1
fi

echo "Layer 3 passed: headers present, no banned comment patterns."
