#!/usr/bin/env bash
set -euo pipefail
LOG="${1:-/tmp/xf-cutover-latest.log}"
MAX="${2:-120}"
for ((i = 1; i <= MAX; i++)); do
  if [[ -f "$LOG" ]] && grep -qE '\[xf-final-cutover(\.unix)?\.sh\] Done|CUTOVER_EXIT=0 ' "$LOG"; then
    echo "CUTOVER_DONE"
    tail -30 "$LOG"
    exit 0
  fi
  if [[ -f "$LOG" ]] && grep -qiE '\[xf-final-cutover(\.unix)?\.sh\] ERROR|CUTOVER_EXIT=[1-9]' "$LOG"; then
    echo "CUTOVER_ERROR"
    tail -50 "$LOG"
    exit 1
  fi
  sleep 30
  echo "[$(date +%H:%M:%S)] poll $i/$MAX ..."
  tail -2 "$LOG" 2>/dev/null || true
done
echo "TIMEOUT"
tail -80 "$LOG" 2>/dev/null || true
exit 2
