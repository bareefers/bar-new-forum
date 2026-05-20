#!/usr/bin/env bash
#
# Run on the XenForo server (e.g. bareefers). Creates a timestamped backup under
# /var/tmp and a .tar.gz. DB credentials are read from src/config.php (no secrets in this file).
#
# Usage:
#   sudo bash /path/to/xf-remote-backup-helper.sh
# Optional:
#   FORUM_ROOT=/var/www/example.org/forum

set -Eeuo pipefail

FORUM_ROOT="${FORUM_ROOT:-/var/www/bareefers.org/forum}"

xf_config_value() {
    local config_path="$1"
    local key_path="$2"
    php -r '
        $file = $argv[1];
        $contents = file_get_contents($file);
        if (preg_match_all("/\\\$config\\x5B\\x27([^\\x27]+)\\x27\\x5D\\x5B\\x27([^\\x27]+)\\x27\\x5D\\s*=\\s*\\x27([^\\x27]*)\\x27\\s*;/", $contents, $matches, PREG_SET_ORDER))
        {
            $map = [];
            foreach ($matches as $match)
            {
                $map[$match[1] . "." . $match[2]] = $match[3];
            }
            if (isset($map[$argv[2]]))
            {
                echo $map[$argv[2]];
            }
        }
    ' "${config_path}" "${key_path}"
}

CONFIG_PHP="${FORUM_ROOT}/src/config.php"
[[ -f "${CONFIG_PHP}" ]] || {
    echo "Missing ${CONFIG_PHP}" >&2
    exit 1
}

DB_HOST="$(xf_config_value "${CONFIG_PHP}" "db.host")"
DB_PORT="$(xf_config_value "${CONFIG_PHP}" "db.port")"
DB_NAME="$(xf_config_value "${CONFIG_PHP}" "db.dbname")"
DB_USER="$(xf_config_value "${CONFIG_PHP}" "db.username")"
DB_PASSWORD="$(xf_config_value "${CONFIG_PHP}" "db.password")"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

[[ -n "${DB_NAME}" && -n "${DB_USER}" ]] || {
    echo "Could not read dbname/username from ${CONFIG_PHP}" >&2
    exit 1
}
[[ -n "${DB_PASSWORD}" ]] || {
    echo "Could not read db.password from ${CONFIG_PHP}" >&2
    exit 1
}

TS=$(date +%Y%m%d-%H%M%S)
BASE="/var/tmp/xf-prelaunch-backup-$TS"
STYLE_DIR="$BASE/styles"
PRESERVE_FILES_DIR="$BASE/preserved-files"

mkdir -p "$STYLE_DIR"
mkdir -p "$PRESERVE_FILES_DIR"

cd "${FORUM_ROOT}"

mkdir -p styles/adminjunkies
if [[ -d "data/styles" ]]; then
    while IFS= read -r -d '' child; do
        mkdir -p "${child}/styles/adminjunkies"
    done < <(find "data/styles" -mindepth 1 -maxdepth 1 -type d -print0 2>/dev/null || true)
fi
chown -R www-data:www-data styles/adminjunkies data/styles "$STYLE_DIR"

cp src/config.php "$BASE/config.php"

MYSQL_PWD="${DB_PASSWORD}" mysqldump \
    -h "${DB_HOST}" \
    -P "${DB_PORT}" \
    -u "${DB_USER}" \
    --single-transaction \
    --routines \
    --triggers \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    "${DB_NAME}" | gzip -1 > "$BASE/${DB_NAME}-before-cutover.sql.gz"

MYSQL_PWD="${DB_PASSWORD}" mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -Nse "USE ${DB_NAME}; SELECT style_id, title FROM xf_style WHERE style_id > 1 ORDER BY style_id;" > "$BASE/styles.tsv"
MYSQL_PWD="${DB_PASSWORD}" mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -Nse "USE ${DB_NAME}; SELECT option_value FROM xf_option WHERE option_id = 'defaultStyleId' LIMIT 1;" > "$BASE/defaultStyleId.txt"

while IFS=$'\t' read -r style_id style_title; do
    [[ -n "$style_id" ]] || continue
    mkdir -p "$STYLE_DIR/style-$style_id"
    chown -R www-data:www-data "$STYLE_DIR/style-$style_id"
    sudo -u www-data php cmd.php xf:style-archive-export "$style_id" \
        --destination "$STYLE_DIR/style-$style_id" \
        --independent 1 \
        --no-interaction >/dev/null
    printf "%s\t%s\n" "$style_id" "$style_title"
done < "$BASE/styles.tsv" > "$BASE/exported-styles.tsv"

for item in data/assets data/local data/styles styles sponsor_banners; do
    if [[ -e "$item" ]]; then
        mkdir -p "$PRESERVE_FILES_DIR/$item"
        if [[ -d "$item" ]]; then
            rsync -aHAX "$item/" "$PRESERVE_FILES_DIR/$item/"
        else
            mkdir -p "$(dirname "$PRESERVE_FILES_DIR/$item")"
            cp "$item" "$PRESERVE_FILES_DIR/$item"
        fi
    fi
done

cat > "$BASE/README.txt" <<TXT
Backup created: $TS
Forum root: ${FORUM_ROOT}
Database: ${DB_NAME}
Contains: config.php, DB dump, style archives, defaultStyleId, preserved paths
For a fuller pre-cutover snapshot (config tables + master templates + manifest), use:
  xenforo/scripts/xf-pre-cutover-backup.sh
TXT

tar -C /var/tmp -czf "$BASE.tar.gz" "$(basename "$BASE")"
echo "$BASE.tar.gz"
