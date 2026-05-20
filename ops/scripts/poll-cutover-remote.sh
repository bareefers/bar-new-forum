#!/usr/bin/env bash
# Poll bareefers (or SSH host in $1) until xf cutover log shows completion or error.
set -euo pipefail
HOST="${1:-bareefers}"
LOG="${2:-/tmp/xf-cutover-latest.log}"
SLEEP="${3:-25}"
MAX="${4:-120}"
for ((i = 1; i <= MAX; i++)); do
    if ssh -o ConnectTimeout=15 "${HOST}" "test -f ${LOG} && grep -q 'CUTOVER_EXIT=0' ${LOG}"; then
        echo "=== CUTOVER_COMPLETE ==="
        ssh -o ConnectTimeout=15 "${HOST}" "tail -30 ${LOG}"
        exit 0
    fi
    if ssh -o ConnectTimeout=15 "${HOST}" "test -f ${LOG} && grep -qE '\\[xf-final-cutover.*\\] ERROR' ${LOG}"; then
        echo "=== CUTOVER_ERROR ==="
        ssh -o ConnectTimeout=15 "${HOST}" "tail -50 ${LOG}"
        exit 1
    fi
    echo "[$(date -u +%H:%M:%SZ)] poll ${i}/${MAX}"
    ssh -o ConnectTimeout=15 "${HOST}" "tail -3 ${LOG} 2>/dev/null" || true
    sleep "${SLEEP}"
done
echo "=== TIMEOUT ==="
ssh -o ConnectTimeout=15 "${HOST}" "tail -60 ${LOG}"
exit 2
