#!/usr/bin/env bash

set -Eeuo pipefail

BACKUP_TARBALL="${1:?Usage: xf-remote-restore-beta-helper.sh /path/to/backup.tar.gz}"

TS="$(date +%Y%m%d-%H%M%S)"
RESTORE_ROOT="/var/tmp/xf-restore-beta-$TS"
EXTRACT_DIR="$RESTORE_ROOT/extracted"
BROKEN_SNAPSHOT="$RESTORE_ROOT/baraforo-before-restore.sql.gz"

mkdir -p "$EXTRACT_DIR"

# Keep a snapshot of the current DB state before restore.
mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 baraforo | gzip -1 > "$BROKEN_SNAPSHOT"

tar -C "$EXTRACT_DIR" -xzf "$BACKUP_TARBALL"

BACKUP_DIR="$(find "$EXTRACT_DIR" -maxdepth 1 -type d -name 'xf-prelaunch-backup-*' | head -1)"
[[ -n "$BACKUP_DIR" ]] || {
    echo "Failed to locate extracted backup dir" >&2
    exit 1
}

cp "$BACKUP_DIR/config.php" /var/www/bareefers.org/forum/src/config.php
chown root:root /var/www/bareefers.org/forum/src/config.php
chmod 644 /var/www/bareefers.org/forum/src/config.php

mysql <<'SQL'
DROP DATABASE IF EXISTS `baraforo`;
CREATE DATABASE `baraforo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

gunzip -c "$BACKUP_DIR/baraforo-beta-before-cutover.sql.gz" | mysql baraforo

{
    echo "restore_root=$RESTORE_ROOT"
    echo "backup_tarball=$BACKUP_TARBALL"
    echo "broken_snapshot=$BROKEN_SNAPSHOT"
    echo "backup_dir=$BACKUP_DIR"
    echo "default_style_id=$(mysql -Nse "USE baraforo; SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId' LIMIT 1;")"
    echo "style_count=$(mysql -Nse "USE baraforo; SELECT COUNT(*) FROM xf_style;")"
    echo "styles=$(mysql -Nse "USE baraforo; SELECT CONCAT(style_id, ':', title) FROM xf_style ORDER BY style_id;" | paste -sd ' | ' -)"
} | tee "$RESTORE_ROOT/SUMMARY.txt"

echo "$RESTORE_ROOT/SUMMARY.txt"
