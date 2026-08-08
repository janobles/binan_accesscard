#!/usr/bin/env bash
# Proves a commit changed only CSS comments.
#
# Usage: bash scripts/assert-css-unchanged.sh <git-ref>
#
# Strips comments and blank lines (scripts/strip-css-comments.php) from each custom stylesheet at
# <git-ref> and in the working tree, then compares. The file list is the
# union of what public/css held at <ref> and what it holds now, so a
# stylesheet deleted (or added) since <ref> is not silently skipped by a
# working-tree-only glob; it is reported as a failure, same reasoning as
# an added or deleted PHP file failing the token gate. Exits 0 when every
# file matches and none were added or deleted, 1 otherwise.

set -uo pipefail

ref="${1:?usage: assert-css-unchanged.sh <git-ref>}"
failed=0

# Quote-aware, so a comment opener inside a string or a url() stays put. A plain
# regex stripped it from both sides, which made an edit there compare equal and
# let a real rule change through.
strip() {
    php scripts/strip-css-comments.php
}

files=$( { git ls-tree -r --name-only "$ref" -- public/css 2>/dev/null | grep '\.css$'
           ls public/css/*.css 2>/dev/null; } | sort -u )

while IFS= read -r file; do
    [ -n "$file" ] || continue

    oldExists=0
    git cat-file -e "${ref}:${file}" 2>/dev/null && oldExists=1

    newExists=0
    [ -f "$file" ] && newExists=1

    if [ "$oldExists" -eq 1 ] && [ "$newExists" -eq 0 ]; then
        echo "DELETED: $file" >&2
        failed=1
        continue
    fi

    if [ "$oldExists" -eq 0 ] && [ "$newExists" -eq 1 ]; then
        echo "ADDED (not permitted on this branch): $file" >&2
        failed=1
        continue
    fi

    # A stripper that dies has to fail the file. Swallowing its status left both
    # sides empty, which compared equal and passed the gate on a real edit.
    if ! old=$(git show "${ref}:${file}" 2>/dev/null | strip); then
        echo "STRIP FAILED: ${ref}:${file}" >&2
        failed=1
        continue
    fi

    if ! new=$(strip < "$file"); then
        echo "STRIP FAILED: $file" >&2
        failed=1
        continue
    fi

    if [ "$old" = "$new" ]; then
        echo "OK       $file"
    else
        echo "CSS RULES CHANGED: $file" >&2
        failed=1
    fi
done <<< "$files"

if [ "$failed" -ne 0 ]; then
    echo "" >&2
    echo "This branch is documentation only. Revert the rule change." >&2
    exit 1
fi

echo ""
echo "Gate B passed: no CSS rule changed."
