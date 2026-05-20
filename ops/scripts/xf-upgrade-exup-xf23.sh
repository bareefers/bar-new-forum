#!/usr/bin/env bash
# Upgrade Xon's dependencies for Expiring User Upgrades on XenForo 2.3+.
#
# You must download the ZIPs yourself (paid resource + free Standard Library):
#   https://xenforo.com/community/resources/standard-library-by-xon.7915/
#   https://xenforo.com/community/resources/expiring-user-upgrades.6507/
# Expiring User Upgrades 2.7.0+ requires Standard Library 1.23.0+ — upgrade StandardLib FIRST.
#
# Usage (on the forum server):
#   sudo bash xf-upgrade-exup-xf23.sh /path/to/StandardLib-*.zip /path/to/ExpiringUserUpgrades-*.zip
#
set -euo pipefail
STDLIB_ZIP="${1:?First arg: path to Standard Library upgrade .zip}"
EXUP_ZIP="${2:?Second arg: path to Expiring User Upgrades upgrade .zip}"
FORUM_ROOT="${FORUM_ROOT:-/var/www/bareefers.org/forum}"
PHP_USER="${PHP_USER:-www-data}"

for z in "$STDLIB_ZIP" "$EXUP_ZIP"; do
  [[ -f "$z" ]] || { echo "Not a file: $z" >&2; exit 1; }
done

run_upgrade() {
  local zip_path="$1"
  echo "=== Upgrading from: $zip_path ==="
  sudo -u "$PHP_USER" php "$FORUM_ROOT/cmd.php" xf:addon-upgrade --no-interaction "$zip_path"
}

run_upgrade "$STDLIB_ZIP"
run_upgrade "$EXUP_ZIP"

echo "=== Done. In ACP: Tools → Rebuild caches if suggested; check add-on versions. ==="
