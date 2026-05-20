#!/usr/bin/env bash
# Restore xf-backup-style16-visual.php snapshot after DB import.
#
#   sudo bash xf-restore-bar-style16-visual.sh /var/tmp/.../BAR-style16-visual-YYYYMMDD-HHMMSS/visual.sql.gz
#
# Env: XF_FORUM_ROOT (default /var/www/bareefers.org/forum)
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FORUM_ROOT="${XF_FORUM_ROOT:-/var/www/bareefers.org/forum}"
DUMP_GZ="${1:?usage: $0 /path/to/visual.sql.gz}"

if [[ ! -f "${SCRIPT_DIR}/xf-restore-style16-visual.php" ]]; then
  echo "Missing xf-restore-style16-visual.php in ${SCRIPT_DIR}" >&2
  exit 1
fi

php "${SCRIPT_DIR}/xf-restore-style16-visual.php" "${FORUM_ROOT}" "${DUMP_GZ}"
redis-cli -n 7 FLUSHDB >/dev/null
echo "OK: style 16 visual data restored + Redis CSS DB 7 flushed. Hard-refresh the forum."
