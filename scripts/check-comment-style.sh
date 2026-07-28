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

# Every view opens with a docblock naming the page and its data source.
while IFS= read -r file; do
    if ! head -5 "$file" | grep -q '/\*\*'; then
        report "MISSING VIEW HEADER: $file"
    fi
done < <(find app/Views -name '*.php')

# Every custom stylesheet opens with a header naming what it styles.
for file in public/css/*.css; do
    if ! head -1 "$file" | grep -q '/\*'; then
        report "MISSING CSS HEADER: $file"
    fi
done

# Em dashes read as AI slop in this codebase. Plain language only.
while IFS= read -r hit; do
    report "EM DASH: $hit"
done < <(grep -rn '—' app public/css --include='*.php' --include='*.css' 2>/dev/null)

# Divider comments are replaced by prose or by splitting the unit. app/Config is
# stock framework code and public/assets is vendored.
while IFS= read -r hit; do
    report "DIVIDER COMMENT: $hit"
done < <(grep -rn -E '(//|/\*|\*)[[:space:]]*[-=]{4,}' app public/css \
    --include='*.php' --include='*.css' 2>/dev/null \
    | grep -v '^app/Config/')

if [ "$failed" -ne 0 ]; then
    echo "" >&2
    echo "See docs/knowledge/php-practices/comments.md" >&2
    exit 1
fi

echo "Layer 3 passed: headers present, no banned comment patterns."
