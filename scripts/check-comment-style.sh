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

# Em dashes read as AI slop in this codebase. Plain language only.
while IFS= read -r hit; do
    report "EM DASH: $hit"
done < <(grep -rn '—' app public/css --include='*.php' --include='*.css' 2>/dev/null)

# Every `file:line:` that sits inside a <script> block. A view's inline script is
# T_INLINE_HTML: the token gate compares it verbatim, so editing a JS comment
# there cannot be proven to change nothing. The divider ban therefore covers PHP
# and CSS comments, not JS embedded in a view. Note that moving such a script to
# its own .js file does not bring it back under the ban either: the scan below
# only includes *.php and *.css. Covering JS means extending that include list,
# which is its own job (see docs/knowledge/violations.md).
script_block_lines="$(mktemp)"
trap 'rm -f "$script_block_lines"' EXIT

find app -name '*.php' -not -path 'app/Config/*' -print0 \
    | xargs -0 awk '
        FNR == 1 { inscript = 0 }
        /<script/ { inscript = 1 }
        inscript { print FILENAME ":" FNR ":" }
        /<\/script>/ { inscript = 0 }
    ' > "$script_block_lines" 2>/dev/null

# Divider comments are replaced by prose or by splitting the unit. app/Config is
# stock framework code and public/assets is vendored.
while IFS= read -r hit; do
    report "DIVIDER COMMENT: $hit"
done < <(grep -rn -E '(//|/\*|\*)[[:space:]]*[-=]{4,}' app public/css \
    --include='*.php' --include='*.css' 2>/dev/null \
    | grep -v '^app/Config/' \
    | grep -vF -f "$script_block_lines")

if [ "$failed" -ne 0 ]; then
    echo "" >&2
    echo "See docs/knowledge/php-practices/comments.md" >&2
    exit 1
fi

echo "Layer 3 passed: headers present, no banned comment patterns."
