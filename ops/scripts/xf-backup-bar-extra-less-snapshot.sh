#!/usr/bin/env bash
# Snapshot style 16 public extra.less before a forum import / risky change.
# Writes: (1) mysqldump row .sql.gz (2) decoded .less (3) restore hint file.
#
# Run ON the XenForo server (e.g. scp xenforo/scripts/ then):
#   sudo bash xf-backup-bar-extra-less-snapshot.sh [/path/to/forum] [/path/to/snapshot_dir]
#
# Env: XF_FORUM_ROOT, BAR_EXTRA_LESS_SNAPSHOT_DIR
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FORUM_ROOT="${1:-${XF_FORUM_ROOT:-/var/www/bareefers.org/forum}}"
OUT_DIR="${2:-${BAR_EXTRA_LESS_SNAPSHOT_DIR:-/var/tmp/bar-extra-less-snapshots}}"
mkdir -p "$OUT_DIR"

if [[ ! -f "${SCRIPT_DIR}/xf-backup-template-row.php" ]] || [[ ! -f "${SCRIPT_DIR}/xf-export-template-to-less-file.php" ]]; then
  echo "Missing xf-backup-template-row.php or xf-export-template-to-less-file.php in ${SCRIPT_DIR}" >&2
  exit 1
fi

# xf-backup-template-row.php writes under /var/tmp and prints: OK: /full/path.sql.gz
SQL_GZ="$(php "${SCRIPT_DIR}/xf-backup-template-row.php" "${FORUM_ROOT}" 16 extra.less public | sed -n 's/^OK: //p' | tr -d '\r\n')"
if [[ -z "${SQL_GZ}" ]] || [[ ! -f "${SQL_GZ}" ]]; then
  echo "Backup failed or path missing from mysqldump helper" >&2
  exit 1
fi
echo "SQL: ${SQL_GZ}"

BASE="${SQL_GZ%.sql.gz}"
LESS_OUT="${BASE}.less"
HINT="${BASE}-RESTORE.txt"

sudo -u www-data php "${SCRIPT_DIR}/xf-export-template-to-less-file.php" \
  "${FORUM_ROOT}" 16 public extra.less "${LESS_OUT}"

cat >"${HINT}" <<EOF
BAR extra.less snapshot (style 16 public)
Forum: ${FORUM_ROOT}

Restore from SQL (exact xf_template row):
  sudo bash ${SCRIPT_DIR}/xf-restore-bar-extra-less-snapshot.sh '${SQL_GZ}'

Restore from .less (re-save via XenForo entity):
  sudo bash ${SCRIPT_DIR}/xf-restore-bar-extra-less-snapshot.sh --less '${LESS_OUT}'

Files:
  ${SQL_GZ}
  ${LESS_OUT}
EOF

echo "LESS: ${LESS_OUT}"

# Optional second arg: copy snapshot trio here (mysqldump helper always writes under /var/tmp)
if [[ $# -ge 2 ]]; then
  cp -a "${SQL_GZ}" "${LESS_OUT}" "${HINT}" "${OUT_DIR}/"
  echo "Copied to: ${OUT_DIR}/"
fi

echo "OK: snapshot complete. Read: ${HINT}"
