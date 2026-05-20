#!/usr/bin/env bash

set -Eeuo pipefail

BACKUP_TARBALL="${1:?Usage: xf-remote-rehearsal-helper.sh /path/to/backup.tar.gz}"

TS=$(date +%Y%m%d-%H%M%S)
BASE="/var/tmp/xf-cutover-rehearsal-$TS"
EXTRACT_DIR="$BASE/backup-extracted"
TEMP_FORUM_ROOT="$BASE/forum"
REMOTE_DUMP_GZ="$BASE/old-live.sql.gz"
TEST_DB="baraforo_rehearsal_${TS//-/}"
STYLE_METADATA_REL=""
DEFAULT_STYLE_REL=""
PHP_USER="www-data"

log() {
    printf '[rehearsal] %s\n' "$*"
}

mysql_query_temp() {
    mysql -Nse "$1" "$TEST_DB"
}

patch_temp_config() {
    perl -0pi -e "s/\\\$config\\['db'\\]\\['dbname'\\]\\s*=\\s*'[^']*';/\\\$config['db']['dbname'] = '$TEST_DB';/g" "$TEMP_FORUM_ROOT/src/config.php"
    perl -0pi -e "s/\\\$config\\['db'\\]\\['host'\\]\\s*=\\s*'[^']*';/\\\$config['db']['host'] = 'localhost';/g" "$TEMP_FORUM_ROOT/src/config.php"
    perl -0pi -e "s/\\\$config\\['db'\\]\\['port'\\]\\s*=\\s*'[^']*';/\\\$config['db']['port'] = '3306';/g" "$TEMP_FORUM_ROOT/src/config.php"
    perl -0pi -e "s/\\\$config\\['db'\\]\\['username'\\]\\s*=\\s*'[^']*';/\\\$config['db']['username'] = 'barausr';/g" "$TEMP_FORUM_ROOT/src/config.php"
    perl -0pi -e "s/\\\$config\\['db'\\]\\['password'\\]\\s*=\\s*'[^']*';/\\\$config['db']['password'] = '7R*3ITe0ad5#xtU9rn';/g" "$TEMP_FORUM_ROOT/src/config.php"
}

restore_styles() {
    local backup_dir="$1"
    local rows default_style_id

    STYLE_METADATA_REL="$backup_dir/exported-styles.tsv"
    DEFAULT_STYLE_REL="$backup_dir/defaultStyleId.txt"

    [[ -f "$STYLE_METADATA_REL" ]] || {
        log "No style metadata found; skipping style restore"
        return
    }
    [[ -f "$DEFAULT_STYLE_REL" ]] || {
        log "No default style metadata found; skipping style restore"
        return
    }

    rows="$(cat "$STYLE_METADATA_REL")"
    default_style_id="$(tr -d '\r\n' < "$DEFAULT_STYLE_REL")"

    mkdir -p "$TEMP_FORUM_ROOT/styles/adminjunkies"
    chown -R "$PHP_USER:$PHP_USER" "$TEMP_FORUM_ROOT/styles" "$TEMP_FORUM_ROOT/internal_data" "$TEMP_FORUM_ROOT/data"

    declare -A STYLE_ID_MAP

    while IFS=$'\t' read -r style_id title; do
        [[ -n "$style_id" ]] || continue

        local archive_path before_max after_max target_style_id
        archive_path="$(ls -1 "$backup_dir/styles/style-$style_id"/*.zip 2>/dev/null | head -1 || true)"
        [[ -n "$archive_path" ]] || {
            printf 'Missing archived style for style_id=%s\n' "$style_id" >&2
            exit 1
        }

        if [[ -n "$(mysql_query_temp "SELECT style_id FROM xf_style WHERE style_id = ${style_id} LIMIT 1;")" ]]; then
            sudo -u "$PHP_USER" php "$TEMP_FORUM_ROOT/cmd.php" \
                xf:style-archive-import "$archive_path" \
                --target overwrite \
                --overwrite-style-id "$style_id" \
                --force \
                --no-interaction
            STYLE_ID_MAP["$style_id"]="$style_id"
            continue
        fi

        before_max="$(mysql_query_temp "SELECT IFNULL(MAX(style_id), 0) FROM xf_style;")"
        sudo -u "$PHP_USER" php "$TEMP_FORUM_ROOT/cmd.php" \
            xf:style-archive-import "$archive_path" \
            --target child \
            --parent-style-id 0 \
            --force \
            --no-interaction
        after_max="$(mysql_query_temp "SELECT IFNULL(MAX(style_id), 0) FROM xf_style;")"
        target_style_id="$after_max"
        [[ "$after_max" -gt "$before_max" ]] || {
            printf 'Failed to detect imported style id for archived style %s\n' "$style_id" >&2
            exit 1
        }
        STYLE_ID_MAP["$style_id"]="$target_style_id"
    done <<< "$rows"

    if [[ -n "$default_style_id" ]]; then
        local mapped_default="${STYLE_ID_MAP[$default_style_id]:-$default_style_id}"
        mysql_query_temp "UPDATE xf_option SET option_value = '${mapped_default}' WHERE option_id = 'defaultStyleId';"
    fi
}

mkdir -p "$EXTRACT_DIR"
tar -C "$EXTRACT_DIR" -xzf "$BACKUP_TARBALL"

BACKUP_DIR="$(find "$EXTRACT_DIR" -maxdepth 1 -type d -name 'xf-prelaunch-backup-*' | head -1)"
[[ -n "$BACKUP_DIR" ]] || {
    echo "Failed to locate extracted backup directory" >&2
    exit 1
}

log "Copying current new-server forum code into temp root"
rsync -a --delete \
    --exclude '/internal_data/*' \
    --exclude '/data/*' \
    /var/www/bareefers.org/forum/ "$TEMP_FORUM_ROOT/"

mkdir -p "$TEMP_FORUM_ROOT/internal_data" "$TEMP_FORUM_ROOT/data"

log "Patching temp config to use rehearsal DB"
patch_temp_config

log "Dumping old live DB from old server"
sshpass -p 'lI1\WBmZ2uRF^(P.t.266#0<L3\%e' \
    ssh -o StrictHostKeyChecking=accept-new root@45.79.101.66 \
    "mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 baraforo" \
    | gzip -1 > "$REMOTE_DUMP_GZ"

gzip -t "$REMOTE_DUMP_GZ"

log "Creating rehearsal DB and importing old live dump"
mysql -e "DROP DATABASE IF EXISTS \`$TEST_DB\`; CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "GRANT ALL PRIVILEGES ON \`$TEST_DB\`.* TO 'barausr'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
gunzip -c "$REMOTE_DUMP_GZ" | mysql "$TEST_DB"

log "Syncing old live internal_data"
sshpass -p 'lI1\WBmZ2uRF^(P.t.266#0<L3\%e' \
    rsync -aHAX --numeric-ids -e "ssh -o StrictHostKeyChecking=accept-new" \
    root@45.79.101.66:/home/nginx/domains/bareefers.org/public/forum/internal_data/ \
    "$TEMP_FORUM_ROOT/internal_data/"

log "Syncing old live data"
sshpass -p 'lI1\WBmZ2uRF^(P.t.266#0<L3\%e' \
    rsync -aHAX --numeric-ids -e "ssh -o StrictHostKeyChecking=accept-new" \
    root@45.79.101.66:/home/nginx/domains/bareefers.org/public/forum/data/ \
    "$TEMP_FORUM_ROOT/data/"

log "Fixing temp forum ownership"
chown -R "$PHP_USER:$PHP_USER" "$TEMP_FORUM_ROOT"

log "Running XenForo rebuild against rehearsal DB"
sudo -u "$PHP_USER" php "$TEMP_FORUM_ROOT/cmd.php" xf:rebuild-master-data

log "Restoring beta styles into rehearsal DB"
restore_styles "$BACKUP_DIR"

{
    echo "backup_tarball=$BACKUP_TARBALL"
    echo "backup_dir=$BACKUP_DIR"
    echo "rehearsal_root=$BASE"
    echo "temp_forum_root=$TEMP_FORUM_ROOT"
    echo "temp_db=$TEST_DB"
    echo "old_live_dump=$REMOTE_DUMP_GZ"
    echo "backup_tarball_size=$(du -sh "$BACKUP_TARBALL" | awk '{print $1}')"
    echo "old_live_dump_size=$(du -sh "$REMOTE_DUMP_GZ" | awk '{print $1}')"
    echo "temp_internal_data_size=$(du -sh "$TEMP_FORUM_ROOT/internal_data" | awk '{print $1}')"
    echo "temp_data_size=$(du -sh "$TEMP_FORUM_ROOT/data" | awk '{print $1}')"
    echo "thread_count=$(mysql_query_temp "SELECT COUNT(*) FROM xf_thread;")"
    echo "post_count=$(mysql_query_temp "SELECT COUNT(*) FROM xf_post;")"
    echo "default_style_id=$(mysql_query_temp "SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId' LIMIT 1;")"
    echo "styles=$(mysql_query_temp "SELECT CONCAT(style_id, ':', title) FROM xf_style ORDER BY style_id;" | paste -sd ' | ' -)"
} | tee "$BASE/SUMMARY.txt"

echo "$BASE/SUMMARY.txt"
