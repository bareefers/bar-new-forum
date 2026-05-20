#!/usr/bin/env bash

set -Eeuo pipefail

BACKUP_TARBALL="${1:?Usage: xf-remote-live-test-helper.sh /path/to/backup.tar.gz}"

TS=$(date +%Y%m%d-%H%M%S)
BASE="/var/tmp/xf-cutover-live-test-$TS"
EXTRACT_DIR="$BASE/backup-extracted"
REMOTE_FORUM_DIR="$BASE/forum"
REMOTE_DUMP_GZ="$BASE/old-live.sql.gz"
TEST_DB="baraforo_cutover_test_${TS//-/}"

mkdir -p "$EXTRACT_DIR" "$REMOTE_FORUM_DIR/internal_data" "$REMOTE_FORUM_DIR/data"

tar -C "$EXTRACT_DIR" -xzf "$BACKUP_TARBALL"

BACKUP_DIR="$(find "$EXTRACT_DIR" -maxdepth 1 -type d -name 'xf-prelaunch-backup-*' | head -1)"
[[ -n "$BACKUP_DIR" ]] || {
    echo "Failed to locate extracted backup directory" >&2
    exit 1
}

gzip -t "$BACKUP_DIR/baraforo-beta-before-cutover.sql.gz"

while IFS= read -r archive; do
    unzip -tq "$archive" >/dev/null
done < <(find "$BACKUP_DIR/styles" -type f -name '*.zip' | sort)

sshpass -p 'lI1\WBmZ2uRF^(P.t.266#0<L3\%e' \
    ssh -o StrictHostKeyChecking=accept-new root@45.79.101.66 \
    "mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 baraforo" \
    | gzip -1 > "$REMOTE_DUMP_GZ"

gzip -t "$REMOTE_DUMP_GZ"

mysql -e "DROP DATABASE IF EXISTS \`$TEST_DB\`; CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "$REMOTE_DUMP_GZ" | mysql "$TEST_DB"

sshpass -p 'lI1\WBmZ2uRF^(P.t.266#0<L3\%e' \
    rsync -aHAX --numeric-ids -e "ssh -o StrictHostKeyChecking=accept-new" \
    root@45.79.101.66:/home/nginx/domains/bareefers.org/public/forum/internal_data/ \
    "$REMOTE_FORUM_DIR/internal_data/"

sshpass -p 'lI1\WBmZ2uRF^(P.t.266#0<L3\%e' \
    rsync -aHAX --numeric-ids -e "ssh -o StrictHostKeyChecking=accept-new" \
    root@45.79.101.66:/home/nginx/domains/bareefers.org/public/forum/data/ \
    "$REMOTE_FORUM_DIR/data/"

{
    echo "backup_tarball=$BACKUP_TARBALL"
    echo "extracted_backup_dir=$BACKUP_DIR"
    echo "remote_dump_gz=$REMOTE_DUMP_GZ"
    echo "test_db=$TEST_DB"
    echo "remote_forum_dir=$REMOTE_FORUM_DIR"
    echo "style_archives=$(find "$BACKUP_DIR/styles" -type f -name '*.zip' | wc -l | tr -d ' ')"
    echo "beta_backup_db_size=$(du -sh "$BACKUP_DIR/baraforo-beta-before-cutover.sql.gz" | awk '{print $1}')"
    echo "remote_dump_size=$(du -sh "$REMOTE_DUMP_GZ" | awk '{print $1}')"
    echo "remote_internal_data_size=$(du -sh "$REMOTE_FORUM_DIR/internal_data" | awk '{print $1}')"
    echo "remote_data_size=$(du -sh "$REMOTE_FORUM_DIR/data" | awk '{print $1}')"
    echo "styles_in_test_db=$(mysql -Nse "USE \`$TEST_DB\`; SELECT GROUP_CONCAT(CONCAT(style_id, ':', title) ORDER BY style_id SEPARATOR ' | ') FROM xf_style;")"
    echo "default_style_in_test_db=$(mysql -Nse "USE \`$TEST_DB\`; SELECT option_value FROM xf_option WHERE option_id='defaultStyleId' LIMIT 1;")"
    echo "thread_count_in_test_db=$(mysql -Nse "USE \`$TEST_DB\`; SELECT COUNT(*) FROM xf_thread;")"
    echo "post_count_in_test_db=$(mysql -Nse "USE \`$TEST_DB\`; SELECT COUNT(*) FROM xf_post;")"
} | tee "$BASE/SUMMARY.txt"

echo "$BASE/SUMMARY.txt"
