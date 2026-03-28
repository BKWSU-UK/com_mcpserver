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

# Clean previous build
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# Copy manifest and install script
cp "$SCRIPT_DIR/mcpserver.xml" "$BUILD_DIR/"
cp "$SCRIPT_DIR/script.php" "$BUILD_DIR/"

# Install production dependencies
composer install --no-dev --optimize-autoloader --working-dir="$SCRIPT_DIR/admin" --quiet

# Copy admin files
rsync -a --exclude='.DS_Store' "$SCRIPT_DIR/admin/" "$BUILD_DIR/admin/"

# Copy site files
rsync -a --exclude='.DS_Store' "$SCRIPT_DIR/site/" "$BUILD_DIR/site/"

# Create the zip package
cd "$BUILD_DIR"
zip -rq "$SCRIPT_DIR/$PACKAGE" . -x '*.git*'
cd "$SCRIPT_DIR"

# Clean up
rm -rf "$BUILD_DIR"

SIZE=$(du -h "$PACKAGE" | cut -f1)
echo "Package created: ${PACKAGE} (${SIZE})"
