#!/usr/bin/env bash
#
# Bump version, commit, tag, and push to trigger a GitHub release.
# Run from the plugin root: ./scripts/release.sh <version>
#
# Example: ./scripts/release.sh 1.0.4
#
# This script is excluded from release zips (scripts/ is not in the dist).
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

if [[ -z "$1" ]]; then
  echo "Usage: $0 <version>"
  echo "Example: $0 1.0.4"
  exit 1
fi

VERSION="$1"
DATE="$(date +%Y-%m-%d)"

cd "$PLUGIN_DIR"

# Ensure clean working tree (allow untracked files)
if [[ -n "$(git status --porcelain | grep -v '^??')" ]]; then
  echo "Working tree has uncommitted changes. Commit or stash first."
  exit 1
fi

echo "Releasing v$VERSION..."

# Update plugin version
sed -i.bak "s/Version: [0-9.]*/Version: $VERSION/" gravity-forms-git-sync.php
sed -i.bak "s/define( 'GF_GIT_SYNC_VERSION', '[0-9.]*' );/define( 'GF_GIT_SYNC_VERSION', '$VERSION' );/" gravity-forms-git-sync.php
rm -f gravity-forms-git-sync.php.bak

# Update readme.txt
sed -i.bak "s/Stable tag: [0-9.]*/Stable tag: $VERSION/" readme.txt
rm -f readme.txt.bak
# Add changelog entry (insert after == Changelog ==)
perl -i -0pe "s/(== Changelog ==\n\n)(= [\d.]+)/\1= $VERSION =\n* Release\n\n\2/" readme.txt

# Promote [Unreleased] to new version in CHANGELOG.md
perl -i -0pe "s/## \[Unreleased\]/## [Unreleased]\n\n## [$VERSION] - $DATE/" CHANGELOG.md

git add gravity-forms-git-sync.php readme.txt CHANGELOG.md
git commit -m "v$VERSION: Release"
git tag -a "v$VERSION" -m "Release $VERSION"
git push origin main
git push origin "v$VERSION"

echo "Done. Release workflow: https://github.com/studiorepublic/Gravity-Forms-Git-Sync/actions"
