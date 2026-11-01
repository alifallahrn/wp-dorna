#!/usr/bin/env bash
# Builds the installable zip: dist/wp-dorna-<version>.zip (the folder inside is wp-dorna/,
# so WordPress installs it as wp-content/plugins/wp-dorna). Run `composer install --no-dev`
# first when vendor/ is not committed or is out of date.
#
#   bin/build-release.sh 1.5.0
set -euo pipefail

version="${1:?usage: bin/build-release.sh <version>}"
slug="wp-dorna"
zip_name="$slug-$version.zip"

cd "$(dirname "$0")/.."

rm -rf dist
mkdir -p "dist/$slug"
rsync -a --exclude-from=.distignore ./ "dist/$slug/"

if command -v zip >/dev/null 2>&1; then
  (cd dist && zip -qr "$zip_name" "$slug")
else
  (cd dist && python3 -m zipfile -c "$zip_name" "$slug")
fi

# The zip must work as-is on a customer's site: the entry file and the runtime
# dependency are there, development files are not.
listing="$(python3 -m zipfile -l "dist/$zip_name")"

for required in "$slug/wp-dorna.php" "$slug/vendor/autoload.php" "$slug/src/Core/Plugin.php" "$slug/assets/css/admin.css" "$slug/uninstall.php"; do
  grep -q "$required" <<<"$listing" || { echo "Missing from the zip: $required" >&2; exit 1; }
done

for forbidden in "$slug/tests/" "$slug/.github/" "$slug/.git/" "$slug/bin/" "$slug/CLAUDE.md" "$slug/AGENTS.md" "$slug/logs/"; do
  if grep -q "$forbidden" <<<"$listing"; then echo "Must not be in the zip: $forbidden" >&2; exit 1; fi
done

echo "dist/$zip_name ($(du -h "dist/$zip_name" | cut -f1))"
