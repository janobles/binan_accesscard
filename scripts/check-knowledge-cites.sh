#!/usr/bin/env bash
# Verify every `path:line` cite in docs/knowledge resolves to a real file and line.
# Cite format in docs: `app/Path/File.php:123` (backtick-wrapped, repo-relative).
set -u
cd "$(dirname "$0")/.."
fail=0
count=0
while IFS=: read -r file line; do
  [ -z "$file" ] && continue
  count=$((count + 1))
  if [ ! -f "$file" ]; then
    echo "MISSING FILE: $file (cited as $file:$line)"
    fail=1
  elif [ "$(wc -l < "$file" | tr -d ' ')" -lt "$line" ]; then
    echo "LINE OUT OF RANGE: $file:$line"
    fail=1
  fi
done < <(grep -rhoE '`(app|public|tests|scripts)/[^`]+:[0-9]+`' docs/knowledge --include='*.md' 2>/dev/null | tr -d '`' | sort -u)
if [ "$fail" -eq 0 ]; then
  echo "OK: $count unique cites resolve."
fi
exit $fail
