#!/usr/bin/env bash
# Restore style 16 public extra.less from a snapshot created by xf-backup-bar-extra-less-snapshot.sh
#
#   sudo bash xf-restore-bar-extra-less-snapshot.sh /var/tmp/.../extra-less-style16-YYYYMMDD-HHMMSS.sql.gz
#   sudo bash xf-restore-bar-extra-less-snapshot.sh --less /var/tmp/.../extra-less-style16-YYYYMMDD-HHMMSS.less
#
# Env: XF_FORUM_ROOT (default /var/www/bareefers.org/forum)
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FORUM_ROOT="${XF_FORUM_ROOT:-/var/www/bareefers.org/forum}"

if [[ "${1:-}" == "--less" ]]; then
  LESSFILE="${2:?usage: $0 --less /path/to/file.less}"
  if [[ ! -f "${SCRIPT_DIR}/xf-set-template-from-file.php" ]]; then
    echo "Missing xf-set-template-from-file.php in ${SCRIPT_DIR}" >&2
    exit 1
  fi
  sudo -u www-data php "${SCRIPT_DIR}/xf-set-template-from-file.php" \
    "${FORUM_ROOT}" 16 public extra.less "${LESSFILE}"
else
  DUMP_GZ="${1:?usage: $0 /path/to/extra-less-style16-*.sql.gz   OR   $0 --less /path/to/file.less}"
  if [[ ! -f "${SCRIPT_DIR}/xf-restore-extra-less-from-row-backup.sh" ]]; then
    echo "Missing xf-restore-extra-less-from-row-backup.sh in ${SCRIPT_DIR}" >&2
    exit 1
  fi
  bash "${SCRIPT_DIR}/xf-restore-extra-less-from-row-backup.sh" "${DUMP_GZ}"
  if [[ ! -f "${SCRIPT_DIR}/xf-reparse-template.php" ]]; then
    echo "Missing xf-reparse-template.php in ${SCRIPT_DIR}" >&2
    exit 1
  fi
  sudo -u www-data php "${SCRIPT_DIR}/xf-reparse-template.php" \
    "${FORUM_ROOT}" 16 public extra.less
fi

redis-cli -n 7 FLUSHDB >/dev/null
echo "OK: extra.less restored + CSS cache DB 7 flushed. Hard-refresh the forum."
