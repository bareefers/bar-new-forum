#!/usr/bin/env bash
# Push xf-restore-extra-less-aurora16.py + xf-set-template-from-file.php and run restore on bareefers.
# Uses SSH host bareefers (~/.ssh/config). Interactive shell: ssh bareefers
#
# From Windows (Cursor/Git Bash often cannot resolve bareefers): use WSL from repo root:
#   wsl bash xenforo/scripts/xf-deploy-bareefers-extra-less.sh
# From WSL or Linux: bash xenforo/scripts/xf-deploy-bareefers-extra-less.sh
#
# Requires: passwordless sudo on server for www-data + redis (as used by xf-restore-extra-less-aurora16.py).
#
# Env overrides:
#   SSH_HOST=bareefers  REMOTE_FORUM_ROOT=/var/www/bareefers.org/forum  REMOTE_TMP=/tmp
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_HOST="${SSH_HOST:-bareefers}"
REMOTE_TMP="${REMOTE_TMP:-/tmp}"
REMOTE_FORUM_ROOT="${REMOTE_FORUM_ROOT:-/var/www/bareefers.org/forum}"

scp "${HERE}/xf-restore-extra-less-aurora16.py" "${HERE}/xf-set-template-from-file.php" "${HERE}/xf-append-bar-thread-meta-titles.php" "${HERE}/extra-less-aurora16-source.less" "${SSH_HOST}:${REMOTE_TMP}/"

ssh "${SSH_HOST}" "XF_FORUM_ROOT='${REMOTE_FORUM_ROOT}' XF_EXTRA_LESS_SOURCE='${REMOTE_TMP}/extra-less-aurora16-source.less' sudo -E python3 '${REMOTE_TMP}/xf-restore-extra-less-aurora16.py' && sudo -u www-data php '${REMOTE_TMP}/xf-append-bar-thread-meta-titles.php' '${REMOTE_FORUM_ROOT}' 16"

echo "OK: extra.less updated on ${SSH_HOST}; CSS cache DB 7 flushed by restore script."
