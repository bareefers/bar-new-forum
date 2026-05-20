#!/usr/bin/env bash
# Smoke-check OLD_* XenForo DB over SSH (same SSH path as xf-final-cutover.sh).
# MySQL on the old host uses root via local socket when OLD_USE_ROOT_MYSQL=1
# (barausr TCP to localhost often fails with 1045 — that is OK for cutover).
#
# From WSL (recommended):
#   bash xenforo/scripts/xf-old-host-db-smoke.sh [path/to/xf-final-cutover.env]
# From Git Bash on Windows, avoid `wsl bash -lc '...$OLD...'` (host strips $);
#   use: wsl bash /mnt/c/.../barcode/xenforo/scripts/xf-old-host-db-smoke.sh
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${1:-${ROOT_DIR}/xf-final-cutover.env.example}"
if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Missing env file: ${ENV_FILE}" >&2
    exit 1
fi

TMP="$(mktemp)"
trap 'rm -f "${TMP}"' EXIT
tr -d '\r' < "${ENV_FILE}" > "${TMP}"
# shellcheck source=/dev/null
set -a
# shellcheck disable=SC1090
. "${TMP}"
set +a

require_var() { [[ -n "${!1:-}" ]] || { echo "Missing ${1} in ${ENV_FILE}" >&2; exit 1; }; }
require_var OLD_HOST
require_var OLD_SSH_USER
require_var OLD_SSH_PORT
require_var OLD_DB_NAME

run_ssh() {
    if [[ -n "${OLD_SSH_PASSWORD:-}" ]]; then
        command -v sshpass >/dev/null 2>&1 || {
            echo "Install sshpass or unset OLD_SSH_PASSWORD and use SSH keys." >&2
            exit 1
        }
        sshpass -p "${OLD_SSH_PASSWORD}" ssh -o StrictHostKeyChecking=accept-new -p "${OLD_SSH_PORT}" \
            "${OLD_SSH_USER}@${OLD_HOST}" "$@"
    else
        ssh -o StrictHostKeyChecking=accept-new -p "${OLD_SSH_PORT}" \
            "${OLD_SSH_USER}@${OLD_HOST}" "$@"
    fi
}

echo "== OLD host ${OLD_SSH_USER}@${OLD_HOST}:${OLD_SSH_PORT} db=${OLD_DB_NAME} =="
run_ssh "hostname; mysql \"${OLD_DB_NAME}\" -Nse \"
SELECT
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${OLD_DB_NAME}' AND table_name='xf_user') AS has_xf_user,
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${OLD_DB_NAME}' AND table_name='xf_job') AS has_xf_job;
SELECT (SELECT COUNT(*) FROM xf_user) AS xf_user_rows, (SELECT COUNT(*) FROM xf_job) AS xf_job_rows;
\""
