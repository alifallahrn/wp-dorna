#!/usr/bin/env bash
# Prints the plugin version after checking it is a plain x.y.z and that the header
# ("Version:") and the WP_DORNA_VERSION constant in wp-dorna.php agree.
set -euo pipefail

cd "$(dirname "$0")/.."
file="wp-dorna.php"

header="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$file" | head -n1 | tr -d '\r[:space:]')"
constant="$(sed -n "s/^define('WP_DORNA_VERSION',[[:space:]]*'\([^']*\)');.*/\1/p" "$file" | head -n1 | tr -d '\r[:space:]')"

if [[ ! "$header" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "The 'Version:' header in $file is missing or not x.y.z (got '$header')." >&2
  exit 1
fi

if [[ "$header" != "$constant" ]]; then
  echo "Version mismatch in $file: header '$header' vs WP_DORNA_VERSION '$constant'. Update both." >&2
  exit 1
fi

echo "$header"
