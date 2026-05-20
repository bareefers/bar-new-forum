#!/usr/bin/env bash
# Push xf-install-google-tag-manager.php to bareefers and install GTM on XenForo (bareefers.org forum).
#
# From repo root (Windows: use WSL so ssh bareefers resolves):
#   wsl bash xenforo/scripts/xf-deploy-google-tag-manager-bareefers.sh
#
# Env overrides:
#   SSH_HOST=bareefers  REMOTE_FORUM_ROOT=/var/www/bareefers.org/forum  GTM_ID=GTM-WS6J8RBD  STYLE_ID=16
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_HOST="${SSH_HOST:-bareefers}"
REMOTE_TMP="${REMOTE_TMP:-/tmp}"
REMOTE_FORUM_ROOT="${REMOTE_FORUM_ROOT:-/var/www/bareefers.org/forum}"
STYLE_ID="${STYLE_ID:-16}"
GTM_ID="${GTM_ID:-GTM-WS6J8RBD}"

scp "${HERE}/xf-install-google-tag-manager.php" "${SSH_HOST}:${REMOTE_TMP}/"

ssh "${SSH_HOST}" "sudo -u www-data php '${REMOTE_TMP}/xf-install-google-tag-manager.php' '${REMOTE_FORUM_ROOT}' '${STYLE_ID}' '${GTM_ID}'"

echo "OK: GTM ${GTM_ID} applied on ${SSH_HOST} (style_id=${STYLE_ID})"
