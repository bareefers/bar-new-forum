#!/usr/bin/env bash
# On XenForo server as root: temporarily enable development, recompile parsed templates, remove line.
set -euo pipefail
CFG="${1:-/var/www/bareefers.org/forum/src/config.php}"
ROOT="$(cd "$(dirname "$CFG")/.." && pwd)"

[[ -f "$CFG" ]] || { echo "Missing $CFG" >&2; exit 1; }
grep -qF "\$config['development']['enabled'] = true" "$CFG" && { echo "Development already enabled in config; run recompile manually." >&2; exit 1; }

{
	echo ""
	echo "# XF_DEV_RECOMPILE_TEMP (removed by xf-dev-recompile-templates-once.sh)"
	echo "\$config['development']['enabled'] = true;"
} | sudo tee -a "$CFG" >/dev/null

sudo -u www-data php "$ROOT/cmd.php" xf-dev:recompile-templates -n

sudo sed -i '/# XF_DEV_RECOMPILE_TEMP/d' "$CFG"
sudo sed -i "/\\\$config\\['development'\\]\\['enabled'\\] = true;/d" "$CFG"

echo "OK: xf-dev:recompile-templates finished; development line removed from config.php"
