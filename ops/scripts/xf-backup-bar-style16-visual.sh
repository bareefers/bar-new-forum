#!/usr/bin/env bash
# Primary restore snapshot for style 16 before old-forum import:
#   mysqldump (visual.sql.gz) + VERIFY.txt + extra.less + checksums + durable copy under PRIMARY_ROOT.
#
# Run ON the server:
#   sudo bash xf-backup-bar-style16-visual.sh [/path/to/forum] [/parent_snapshot_dir]
#
# Env:
#   XF_FORUM_ROOT
#   BAR_STYLE16_SNAPSHOT_PARENT  (default /var/tmp/bar-style16-snapshots)
#   BAR_STYLE16_PRIMARY_ARCHIVE  (default /home/xf-emergency-rollback/bar-style16-primary)
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FORUM_ROOT="${1:-${XF_FORUM_ROOT:-/var/www/bareefers.org/forum}}"
PARENT="${2:-${BAR_STYLE16_SNAPSHOT_PARENT:-/var/tmp/bar-style16-snapshots}}"
PRIMARY_ROOT="${BAR_STYLE16_PRIMARY_ARCHIVE:-/home/xf-emergency-rollback/bar-style16-primary}"
mkdir -p "$PARENT"

if [[ ! -f "${SCRIPT_DIR}/xf-backup-style16-visual.php" ]]; then
  echo "Missing xf-backup-style16-visual.php in ${SCRIPT_DIR}" >&2
  exit 1
fi
if [[ ! -f "${SCRIPT_DIR}/xf-export-template-to-less-file.php" ]]; then
  echo "Missing xf-export-template-to-less-file.php in ${SCRIPT_DIR}" >&2
  exit 1
fi

OUT_LINE="$(php "${SCRIPT_DIR}/xf-backup-style16-visual.php" "${FORUM_ROOT}" "${PARENT}" | tr -d '\r\n')"
echo "${OUT_LINE}"
GZIP="${OUT_LINE#OK: }"
SNAP_DIR="$(dirname "${GZIP}")"
if [[ ! -f "${GZIP}" ]] || [[ ! -d "${SNAP_DIR}" ]]; then
  echo "Backup output missing or not OK: .../visual.sql.gz (got: ${OUT_LINE})" >&2
  exit 1
fi

gzip -t "${GZIP}"

sudo -u www-data php "${SCRIPT_DIR}/xf-export-template-to-less-file.php" \
  "${FORUM_ROOT}" 16 public extra.less "${SNAP_DIR}/extra.less"

LESS_BYTES=$(wc -c <"${SNAP_DIR}/extra.less" | tr -d ' ')
if [[ "${LESS_BYTES}" -lt 200 ]]; then
  echo "SANITY_FAIL: extra.less too small (${LESS_BYTES} bytes)" >&2
  exit 1
fi

RESTORE="${SNAP_DIR}/RESTORE.txt"
cat >"${RESTORE}" <<EOF
BAR style 16 — PRIMARY visual snapshot (keep an off-server copy)
Forum: ${FORUM_ROOT}
Created: $(date -u +"%Y-%m-%dT%H:%MZ")

Verify before trusting:
  gzip -t '${GZIP}'
  (cd '${SNAP_DIR}' && sha256sum -c SHA256SUMS.txt)
  cat '${SNAP_DIR}/VERIFY.txt'

After old-server DB import, restore:
  sudo bash ${SCRIPT_DIR}/xf-restore-bar-style16-visual.sh '${GZIP}'

Then hard-refresh the forum (restore flushes Redis CSS DB 7).

Files in this folder:
  visual.sql.gz   — REPLACE rows: xf_style, xf_style_property, xf_style_property_map, xf_template (style_id=16)
  VERIFY.txt      — row counts + size audit
  SHA256SUMS.txt  — integrity
  extra.less      — decoded template for diff / emergency re-apply

Lightweight extra.less-only snapshot:
  sudo bash ${SCRIPT_DIR}/xf-backup-bar-extra-less-snapshot.sh '${FORUM_ROOT}'
EOF

(
  cd "${SNAP_DIR}"
  sha256sum visual.sql.gz extra.less VERIFY.txt RESTORE.txt >SHA256SUMS.txt
)

mkdir -p "${PRIMARY_ROOT}"
STAMP_NAME="$(basename "${SNAP_DIR}")"
cp -a "${SNAP_DIR}" "${PRIMARY_ROOT}/${STAMP_NAME}"
ln -sfn "${STAMP_NAME}" "${PRIMARY_ROOT}/latest"
echo "Primary copy: ${PRIMARY_ROOT}/${STAMP_NAME}  (symlink: ${PRIMARY_ROOT}/latest)"

echo "extra.less: ${SNAP_DIR}/extra.less (${LESS_BYTES} bytes)"
echo "OK: snapshot at ${SNAP_DIR} — read ${RESTORE}"
