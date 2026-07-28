#!/usr/bin/env bash
# Proves a commit changed only CSS comments.
#
# Usage: bash scripts/assert-css-unchanged.sh <git-ref>
#
# Strips /* ... */ comments and blank lines from each custom stylesheet at
# <git-ref> and in the working tree, then compares. Exits 0 when every file
# matches, 1 otherwise.

set -uo pipefail

ref="${1:?usage: assert-css-unchanged.sh <git-ref>}"
failed=0

strip() {
    perl -0777 -pe 's{/\*.*?\*/}{}gs' | grep -v '^[[:space:]]*$' || true
}

for file in public/css/*.css; do
    old=$(git show "${ref}:${file}" 2>/dev/null | strip)
    new=$(strip < "$file")

    if [ "$old" = "$new" ]; then
        echo "OK       $file"
    else
        echo "CSS RULES CHANGED: $file" >&2
        failed=1
    fi
done

if [ "$failed" -ne 0 ]; then
    echo "" >&2
    echo "This branch is documentation only. Revert the rule change." >&2
    exit 1
fi

echo ""
echo "Gate B passed: no CSS rule changed."
