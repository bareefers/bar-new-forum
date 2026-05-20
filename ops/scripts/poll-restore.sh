#!/usr/bin/env bash
# Poll baraforo restore progress.
for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  sleep 20
  c=$(mysql baraforo -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()" 2>/dev/null)
  echo "[$(date +%H:%M:%S)] tables=${c}"
  if grep -q IMPORT_DONE /tmp/baraforo-restore.log 2>/dev/null; then
    echo "IMPORT_DONE detected"
    break
  fi
done
echo "---LOG---"
tail -10 /tmp/baraforo-restore.log
