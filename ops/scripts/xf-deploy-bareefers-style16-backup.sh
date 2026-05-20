#!/usr/bin/env bash
# Copy style-16 backup/restore scripts to bareefers and run full visual snapshot (before old-server import).
#
# From Windows repo root:
#   wsl bash xenforo/scripts/xf-deploy-bareefers-style16-backup.sh
#
# Env: SSH_HOST=bareefers  REMOTE_TMP=/tmp  REMOTE_FORUM_ROOT=/var/www/bareefers.org/forum
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_HOST="${SSH_HOST:-bareefers}"
REMOTE_TMP="${REMOTE_TMP:-/tmp}"
REMOTE_FORUM_ROOT="${REMOTE_FORUM_ROOT:-/var/www/bareefers.org/forum}"

FILES=(
  xf-backup-style16-visual.php
  xf-restore-style16-visual.php
  xf-export-template-to-less-file.php
  xf-backup-bar-style16-visual.sh
  xf-restore-bar-style16-visual.sh
  xf-backup-bar-extra-less-snapshot.sh
  xf-restore-bar-extra-less-snapshot.sh
  xf-backup-template-row.php
  xf-restore-extra-less-from-row-backup.sh
  xf-set-template-from-file.php
  xf-reparse-template.php
)

args=()
for f in "${FILES[@]}"; do
  args+=("${HERE}/${f}")
done
scp "${args[@]}" "${SSH_HOST}:${REMOTE_TMP}/"

ssh "${SSH_HOST}" "sudo bash '${REMOTE_TMP}/xf-backup-bar-style16-visual.sh' '${REMOTE_FORUM_ROOT}'"
echo "OK: primary snapshot on ${SSH_HOST}: /var/tmp/bar-style16-snapshots/BAR-style16-visual-* and /home/xf-emergency-rollback/bar-style16-primary/latest — copy off-server; see xenforo/docs/BAREEFERS-STYLE16-BACKUP.md"
