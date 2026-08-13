#!/bin/bash
#
# Build script for com_mcpserver Joomla component
# Creates a distributable .zip package for installation via Joomla Extension Manager
#

set -euo pipefail

COMPONENT="com_mcpserver"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Extract version from manifest
VERSION=$(grep -oP '<version>\K[^<]+' "$SCRIPT_DIR/mcpserver.xml")
PACKAGE="${COMPONENT}-${VERSION}.zip"
BUILD_DIR="$SCRIPT_DIR/build"

echo "Building ${COMPONENT} v${VERSION}..."

# Sync the README header to the manifest version (mcpserver.xml is the source of truth)
README_PATH="$SCRIPT_DIR/README.md"
if [[ -f "$README_PATH" ]]; then
    CURRENT_README_VERSION=$(grep -oP '\*\*Version:\*\*\s+\K\S+' "$README_PATH" || true)
    if [[ -n "$CURRENT_README_VERSION" && "$CURRENT_README_VERSION" != "$VERSION" ]]; then
        sed -i -E "s/(\*\*Version:\*\*[[:space:]]+)\S+/\1${VERSION}/" "$README_PATH"
        echo "Updated README version: ${CURRENT_README_VERSION} → ${VERSION}"
    fi
fi

# Sync the Joomla updater feed: prepend an <update> entry for this version
# (newest first) if one isn't already present, including infourl, tags, and
# maintainerurl. mcpserver.xml stays the source of truth; Joomla offers the
# highest version whose targetplatform/php match.
UPDATE_PATH="$SCRIPT_DIR/update.xml"
if [[ -f "$UPDATE_PATH" ]]; then
    python3 "$SCRIPT_DIR/scripts/sync_update_entry.py" "$UPDATE_PATH" "$VERSION"
    echo "update.xml entry ensured for v${VERSION}"
fi

# Sync changelog.xml from the matching ## <version> section in CHANGELOG.md.
CHANGELOG_XML_PATH="$SCRIPT_DIR/changelog.xml"
CHANGELOG_MD_PATH="$SCRIPT_DIR/CHANGELOG.md"
if [[ -f "$CHANGELOG_XML_PATH" && -f "$CHANGELOG_MD_PATH" ]]; then
    python3 "$SCRIPT_DIR/scripts/sync_changelog_entry.py" "$CHANGELOG_XML_PATH" "$VERSION" "$CHANGELOG_MD_PATH"
    echo "changelog.xml entry ensured for v${VERSION}"
fi

# Clean previous build
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# Copy manifest and install script
cp "$SCRIPT_DIR/mcpserver.xml" "$BUILD_DIR/"
cp "$SCRIPT_DIR/script.php" "$BUILD_DIR/"

# Copy release documentation required by distribution channels
for RELEASE_FILE in LICENSE README.md CHANGELOG.md SECURITY.md update.xml changelog.xml; do
    if [[ -f "$SCRIPT_DIR/$RELEASE_FILE" ]]; then
        cp "$SCRIPT_DIR/$RELEASE_FILE" "$BUILD_DIR/"
    fi
done

# Install production dependencies
composer install --no-dev --optimize-autoloader --working-dir="$SCRIPT_DIR/admin" --quiet

# Copy admin files
rsync -a --exclude='.DS_Store' --exclude='composer.lock' "$SCRIPT_DIR/admin/" "$BUILD_DIR/admin/"

# Copy site files
rsync -a --exclude='.DS_Store' "$SCRIPT_DIR/site/" "$BUILD_DIR/site/"

# Ship the MCPB template, icon and bridge so the admin can generate personalised
# bundles without depending on the site-installed bridge file
mkdir -p "$BUILD_DIR/admin/mcpb/server"
sed "s/__VERSION__/${VERSION}/" "$SCRIPT_DIR/mcpb/manifest.json" > "$BUILD_DIR/admin/mcpb/manifest.json"
cp "$SCRIPT_DIR/assets/logo.png" "$BUILD_DIR/admin/mcpb/icon.png"
cp "$SCRIPT_DIR/site/mcp-http-bridge.js" "$BUILD_DIR/admin/mcpb/server/mcp-http-bridge.js"

# JED Checker: strip vendor leftovers and patch JAMSS/framework false positives
python3 "$SCRIPT_DIR/scripts/prepare_vendor_for_jed.py" \
    "$BUILD_DIR/admin/vendor" \
    "$BUILD_DIR/admin/src"

# JED Checker requires GPL-compatible licence notices in bundled vendor PHP files
python3 "$SCRIPT_DIR/scripts/add_vendor_license_headers.py" "$BUILD_DIR/admin/vendor"

# The JED-prep scripts rewrite PHP source; fail the build if any rewrite broke syntax
find "$BUILD_DIR" -name '*.php' -print0 | xargs -0 -n1 -P4 php -l > /dev/null
echo "Packaged PHP syntax check passed"

# Create the zip package
rm -f "$SCRIPT_DIR/$PACKAGE"
cd "$BUILD_DIR"
zip -rq "$SCRIPT_DIR/$PACKAGE" . -x '*.git*'
cd "$SCRIPT_DIR"

# Clean up
rm -rf "$BUILD_DIR"

# Record the package SHA-256 in the update feed so Joomla can verify integrity.
# The hash must match the exact artifact published to the GitHub release, so the
# release workflow recomputes and commits this on master against the CI build.
if [[ -f "$UPDATE_PATH" ]]; then
    PACKAGE_SHA256=$(sha256sum "$SCRIPT_DIR/$PACKAGE" | cut -d' ' -f1)
    python3 "$SCRIPT_DIR/scripts/sync_update_entry.py" "$UPDATE_PATH" "$VERSION" "$PACKAGE_SHA256"
    echo "update.xml checksum set for v${VERSION}: ${PACKAGE_SHA256}"
fi

SIZE=$(du -h "$PACKAGE" | cut -f1)
echo "Package created: ${PACKAGE} (${SIZE})"

# Build the generic Claude Desktop extension bundle (.mcpb). It is intentionally
# unversioned so GitHub's /releases/latest/download/com_mcpserver.mcpb URL stays
# stable; the version lives in the manifest inside the bundle.
MCPB_PACKAGE="${COMPONENT}.mcpb"
MCPB_BUILD_DIR="$SCRIPT_DIR/build-mcpb"
rm -rf "$MCPB_BUILD_DIR"
mkdir -p "$MCPB_BUILD_DIR/server"

sed "s/__VERSION__/${VERSION}/" "$SCRIPT_DIR/mcpb/manifest.json" > "$MCPB_BUILD_DIR/manifest.json"
python3 -m json.tool "$MCPB_BUILD_DIR/manifest.json" > /dev/null
cp "$SCRIPT_DIR/assets/logo.png" "$MCPB_BUILD_DIR/icon.png"
cp "$SCRIPT_DIR/site/mcp-http-bridge.js" "$MCPB_BUILD_DIR/server/mcp-http-bridge.js"

rm -f "$SCRIPT_DIR/$MCPB_PACKAGE"
cd "$MCPB_BUILD_DIR"
zip -rq "$SCRIPT_DIR/$MCPB_PACKAGE" .
cd "$SCRIPT_DIR"
rm -rf "$MCPB_BUILD_DIR"

MCPB_SIZE=$(du -h "$MCPB_PACKAGE" | cut -f1)
echo "MCPB bundle created: ${MCPB_PACKAGE} (${MCPB_SIZE})"
