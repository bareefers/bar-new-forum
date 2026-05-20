#!/usr/bin/env bash
# Fetch Xon's Standard Library from public GitHub (MIT), repackage like a Resource Manager
# zip (top-level upload/), and run xf:addon-upgrade. No XenForo login required.
#
# Tag names on GitHub are e.g. 2.23.7; addon version inside is 1.23.7 — see:
#   https://github.com/Xon/XenForo2-StandardLib/tags
#
# Usage (on forum server):
#   sudo STDLIB_TAG=2.23.7 FORUM_ROOT=/var/www/bareefers.org/forum bash xf-deploy-stdlib-from-github.sh
#
set -euo pipefail
FORUM_ROOT="${FORUM_ROOT:-/var/www/bareefers.org/forum}"
PHP_USER="${PHP_USER:-www-data}"
STDLIB_TAG="${STDLIB_TAG:-2.23.7}"
WORK=/tmp/xf-stdlib-github-$STDLIB_TAG
OUT_ZIP="/tmp/SV-StandardLib-from-github-${STDLIB_TAG}.zip"
URL="https://github.com/Xon/XenForo2-StandardLib/archive/refs/tags/${STDLIB_TAG}.zip"

command -v wget >/dev/null || { echo "wget required" >&2; exit 1; }
command -v zip >/dev/null || { echo "zip required" >&2; exit 1; }

rm -rf "$WORK"
mkdir -p "$WORK"
cd "$WORK"
echo "Downloading $URL"
wget -nv -O stdlib-src.zip "$URL"
unzip -qo stdlib-src.zip
SRC=$(find . -maxdepth 1 -type d -name "XenForo2-StandardLib-*" | head -1)
[[ -d "$SRC/upload" ]] || { echo "No upload/ in archive" >&2; exit 1; }
( cd "$SRC" && zip -qr "$OUT_ZIP" upload )
echo "Built $OUT_ZIP ($(du -h "$OUT_ZIP" | awk '{print $1}'))"
sudo -u "$PHP_USER" php "$FORUM_ROOT/cmd.php" xf:addon-upgrade --no-interaction "$OUT_ZIP"
echo "OK: Standard Library upgraded from GitHub tag $STDLIB_TAG"
