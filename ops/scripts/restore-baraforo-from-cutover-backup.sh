#!/usr/bin/env bash
# Run ON bareefers as root. Restores baraforo from a pre-cutover mysqldump (minutes, not seconds).
set -euo pipefail
DUMP="${1:-/var/tmp/xf-final-cutover/20260417-011642/backups/new-before-cutover.sql.gz}"
LOG="${2:-/tmp/baraforo-restore.log}"
exec >"$LOG" 2>&1
echo "START $(date -Is)"
[[ -f "$DUMP" ]] || { echo "Missing dump: $DUMP"; exit 1; }
mysql -e "DROP DATABASE IF EXISTS baraforo; CREATE DATABASE baraforo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "$DUMP" | mysql --init-command="SET SESSION foreign_key_checks=0; SET SESSION unique_checks=0;" baraforo
echo "IMPORT_DONE $(date -Is)"
mysql baraforo -Nse "SELECT COUNT(*) AS xf_user_rows FROM xf_user;"
echo "END $(date -Is)"
