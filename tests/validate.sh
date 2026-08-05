#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
manifest="$root/plugin/icloudpd-status.plg"
sources=(status.php widget.php widget-poll.php IcloudpdStatus.page)
temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT

"$root/scripts/build-plugin.sh"
xmllint --noout "$manifest"

php -l "$root/status.php"
php -l "$root/widget.php"
php -l "$root/widget-poll.php"
tail -n +4 "$root/IcloudpdStatus.page" > "$temporary/IcloudpdStatus.php"
php -l "$temporary/IcloudpdStatus.php"

for source in "${sources[@]}"; do
  sed -n "/<FILE Name=\"\\/tmp\\/$source.b64\">/,/<\\/FILE>/p" "$manifest" \
    | sed -n '/<INLINE>/,/<\/INLINE>/p' \
    | sed '1d;$d' \
    | base64 -d > "$temporary/$source"
  cmp "$root/$source" "$temporary/$source"
done

if git -C "$root" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  git -C "$root" diff --exit-code -- plugin/icloudpd-status.plg
fi

printf 'Validation passed.\n'
