#!/usr/bin/env bash
#
# Build a self-contained plugin zip including vendor/ for GitHub release.
# Run from the plugin root: ./scripts/build-release.sh [version]
#
# The zip is placed in dist/ and named gravity-forms-git-sync-{version}.zip
# so that it can be attached to a GitHub release. Plugin Update Checker
# will use this asset (enableReleaseAssets with /\.zip$/) instead of the
# auto-generated source zip (which excludes vendor).
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="$PLUGIN_DIR/dist"
BUILD_DIR="$PLUGIN_DIR/build"

VERSION="${1:-$(grep "Version:" "$PLUGIN_DIR/gravity-forms-git-sync.php" | sed 's/.*: *//' | tr -d ' ')}"
ZIP_NAME="gravity-forms-git-sync-${VERSION}.zip"

cd "$PLUGIN_DIR"

echo "Building $ZIP_NAME (version $VERSION)..."

# Clean previous build
rm -rf "$BUILD_DIR" "$DIST_DIR"
mkdir -p "$BUILD_DIR" "$DIST_DIR"

# Ensure vendor is present (production deps only)
if [[ ! -d vendor ]] || [[ vendor/autoload.php -ot composer.json ]]; then
  echo "Running composer install --no-dev..."
  composer install --no-dev --no-interaction
fi

# Copy plugin files (exclude build artifacts, dev files)
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='build' \
  --exclude='dist' \
  --exclude='.DS_Store' \
  --exclude='*.log' \
  --exclude='node_modules' \
  --exclude='scripts' \
  --exclude='.github' \
  . "$BUILD_DIR/gravity-forms-git-sync/"

# Create zip with plugin directory as root (wp-content/plugins/gravity-forms-git-sync/)
cd "$BUILD_DIR"
zip -r "$DIST_DIR/$ZIP_NAME" "gravity-forms-git-sync" -x "*.git*" -x "*.DS_Store"
cd "$PLUGIN_DIR"

# Cleanup
rm -rf "$BUILD_DIR"

echo "Built: $DIST_DIR/$ZIP_NAME"
echo "Attach this file to the v$VERSION GitHub release."
