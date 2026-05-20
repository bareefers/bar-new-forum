#!/usr/bin/env bash

set -Eeuo pipefail

BASE="${1:?Usage: xf-remote-finish-rehearsal.sh /var/tmp/xf-cutover-rehearsal-YYYYmmdd-HHMMSS}"
FORUM_ROOT="$BASE/forum"
BACKUP_DIR="$BASE/backup-extracted/xf-prelaunch-backup-20260408-220202"
TEST_DB="$(sed -n "s/.*\\['dbname'\\] = '\\([^']*\\)'.*/\\1/p" "$FORUM_ROOT/src/config.php" | head -1)"

[[ -n "$TEST_DB" ]] || {
    echo "Could not determine temp db name from $FORUM_ROOT/src/config.php" >&2
    exit 1
}

sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:rebuild-master-data

sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:style-archive-import "$BACKUP_DIR/styles/style-2/style-Style-2.0.zip" --target overwrite --overwrite-style-id 2 --force --no-interaction
sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:style-archive-import "$BACKUP_DIR/styles/style-8/style-Revo.zip" --target overwrite --overwrite-style-id 8 --force --no-interaction
sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:style-archive-import "$BACKUP_DIR/styles/style-12/style-BAR-Revo-(Old-Theme).zip" --target overwrite --overwrite-style-id 12 --force --no-interaction
sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:style-archive-import "$BACKUP_DIR/styles/style-13/style-side-bar-fix.zip" --target overwrite --overwrite-style-id 13 --force --no-interaction
sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:style-archive-import "$BACKUP_DIR/styles/style-14/style-Bareefers-XF-2-Theme.zip" --target overwrite --overwrite-style-id 14 --force --no-interaction

before_max="$(mysql -Nse "USE \`$TEST_DB\`; SELECT IFNULL(MAX(style_id), 0) FROM xf_style;")"
sudo -u www-data php "$FORUM_ROOT/cmd.php" xf:style-archive-import "$BACKUP_DIR/styles/style-16/style-Aurora.zip" --target child --parent-style-id 14 --force --no-interaction
after_max="$(mysql -Nse "USE \`$TEST_DB\`; SELECT IFNULL(MAX(style_id), 0) FROM xf_style;")"

[[ "$after_max" -gt "$before_max" ]] || {
    echo "Failed to detect imported Aurora style id" >&2
    exit 1
}

mysql -e "USE \`$TEST_DB\`; UPDATE xf_option SET option_value = '$after_max' WHERE option_id = 'defaultStyleId';"

{
    echo "temp_db=$TEST_DB"
    echo "aurora_style_id=$after_max"
    echo "thread_count=$(mysql -Nse "USE \`$TEST_DB\`; SELECT COUNT(*) FROM xf_thread;")"
    echo "post_count=$(mysql -Nse "USE \`$TEST_DB\`; SELECT COUNT(*) FROM xf_post;")"
    echo "default_style_id=$(mysql -Nse "USE \`$TEST_DB\`; SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId' LIMIT 1;")"
    echo "styles=$(mysql -Nse "USE \`$TEST_DB\`; SELECT CONCAT(style_id, ':', title) FROM xf_style ORDER BY style_id;" | paste -sd ' | ' -)"
} | tee "$BASE/FINAL-SUMMARY.txt"

echo "$BASE/FINAL-SUMMARY.txt"
