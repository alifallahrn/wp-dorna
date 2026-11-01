#!/usr/bin/env bash
# Prints the release notes (markdown): how to install, and the commits since the
# previous release.
#
#   bin/release-notes.sh <version> [previous_tag]
set -euo pipefail

version="${1:?usage: bin/release-notes.sh <version> [previous_tag]}"
previous="${2:-}"

cd "$(dirname "$0")/.."

repo="${GITHUB_REPOSITORY:-}"
if [[ -z "$repo" ]]; then
  repo="$(git config --get remote.origin.url 2>/dev/null | sed -E 's#^(git@github.com:|https://github.com/)##; s#\.git$##')"
fi

if [[ -n "$previous" ]]; then
  range="$previous..HEAD"
else
  range="HEAD"
fi

changes="$(git log "$range" --no-merges --pretty='- %s (%h)' | head -n 100)"

echo "## wp-dorna v$version"
echo
echo "**Install:** download \`wp-dorna-$version.zip\` from the assets below, then in WordPress go to Plugins > Add New > Upload Plugin. Sites that already run the plugin update on their own."
echo
echo "### Changes"
echo
if [[ -n "$changes" ]]; then
  echo "$changes"
else
  echo "- No changes since $previous."
fi

if [[ -n "$previous" && -n "$repo" ]]; then
  echo
  echo "**Full changelog:** https://github.com/$repo/compare/$previous...v$version"
fi
