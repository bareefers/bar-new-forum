#!/usr/bin/env bash
#
# Full XenForo backup on the NEW server only (no SSH to old host, no DB import).
# Captures the same artifacts as the first phase of xf-final-cutover.sh so you can
# safely run a test cutover: full DB (includes per-style templates like extra.less),
# config-table subset, master templates (style_id=0), style .zip exports, and
# preserved file paths (sponsor_banners, styles, assets, etc.).
#
# Usage (on the forum server, as root):
#   sudo /path/to/barcode/xenforo/scripts/xf-pre-cutover-backup.sh [/path/to/xf-final-cutover.env]
#
# Optional env (same as cutover; OLD_* not required):
#   BACKUP_NEW_FILES=1   — also rsync internal_data + data (see XF_SYNC_ITEMS) into the backup
#   DRY_RUN=1            — print actions only
#   WORK_ROOT_BASE       — default /var/tmp/xf-pre-cutover-backup
#   CREATE_TARBALL=1     — default; set CREATE_TARBALL=0 to skip tar.gz
#
# Emergency rollback (defaults ON):
#   ROLLBACK_ARCHIVE_DIR=/home/xf-emergency-rollback  — final .tar.gz copied here (mode 600)
#   COPY_TARBALL_TO_ROLLBACK_DIR=0 — skip copy to /home
#   INCLUDE_SYSTEM_ROLLBACK=0      — skip nginx/TLS/acme/php snapshot
#   NGINX_ETC=/etc/nginx
#   TLS_EXTRA_PATHS="/etc/letsencrypt" — space-separated extra TLS trees (beyond nginx/)
#   BACKUP_ACME_SH=1  ACME_SH_HOME=/root/.acme.sh
#   BACKUP_PHP_ETC=1  — copies /etc/php if present (FPM pools)

set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo "$0" "$@"
fi

SCRIPT_NAME="$(basename "$0")"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() {
    printf '\n[%s] %s\n' "$SCRIPT_NAME" "$*"
}

die() {
    printf '\n[%s] ERROR: %s\n' "$SCRIPT_NAME" "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Missing required command: $1"
}

require_var() {
    local name="$1"
    [[ -n "${!name:-}" ]] || die "Missing required setting: $name"
}

xf_config_value() {
    local config_path="$1"
    local key_path="$2"
    php -r '
        $file = $argv[1];
        $path = explode(".", $argv[2]);
        $contents = file_get_contents($file);
        $value = null;
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

mysql_query() {
    local sql="$1"
    MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
        -Nse "${sql}" \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        "${NEW_DB_NAME}"
}

usage() {
    cat <<'EOF'
Usage:
  sudo xenforo/scripts/xf-pre-cutover-backup.sh [/path/to/xf-final-cutover.env]

Creates under WORK_ROOT_BASE/<timestamp>/:
  backups/config.php
  backups/new-before-cutover.sql.gz     (full database — templates, styles, options)
  backups/config-tables.sql.gz          (style/template/option/widgets/… subset)
  backups/master-templates.sql.gz       (xf_template where style_id = 0, if enabled)
  backups/preserved-files/              (PRESERVE_FILE_ITEMS rsync)
  style-backups/                        (xf:style-archive-export per style)

Set CREATE_TARBALL=0 to skip the final .tar.gz. Set BACKUP_NEW_FILES=1 to include
XF_SYNC_ITEMS (e.g. internal_data data) under backups/files/.

By default also captures backups/system-rollback/ (nginx, extra TLS dirs, acme.sh,
/etc/php, root crontab, nginx unit snippet) and copies the tarball to
ROLLBACK_ARCHIVE_DIR with a LATEST-EMERGENCY-BACKUP.tar.gz symlink.
EOF
}

[[ $# -le 1 ]] || {
    usage
    exit 2
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

ENV_FILE="${1:-}"
if [[ -n "${ENV_FILE}" ]]; then
    [[ -f "${ENV_FILE}" ]] || die "Env file not found: ${ENV_FILE}"
    # shellcheck disable=SC1090
    source "${ENV_FILE}"
else
    for candidate in "${SCRIPT_DIR}/xf-final-cutover.env" "/root/xf-final-cutover.env"; do
        if [[ -f "${candidate}" ]]; then
            # shellcheck disable=SC1090
            source "${candidate}"
            ENV_FILE="${candidate}"
            break
        fi
    done
fi

NEW_FORUM_ROOT="${NEW_FORUM_ROOT:-/var/www/bareefers.org/forum}"
NEW_DB_HOST="${NEW_DB_HOST:-127.0.0.1}"
NEW_DB_PORT="${NEW_DB_PORT:-3306}"
DB_PREFIX="${DB_PREFIX:-xf_}"
PHP_USER="${PHP_USER:-www-data}"
XF_SYNC_ITEMS="${XF_SYNC_ITEMS:-internal_data data}"
PRESERVE_FILE_ITEMS="${PRESERVE_FILE_ITEMS:-data/assets data/local data/styles styles sponsor_banners}"
PRESERVE_STYLES="${PRESERVE_STYLES:-1}"
DRY_RUN="${DRY_RUN:-0}"
BACKUP_NEW_FILES="${BACKUP_NEW_FILES:-0}"
WORK_ROOT_BASE="${WORK_ROOT_BASE:-/var/tmp/xf-pre-cutover-backup}"
CREATE_TARBALL="${CREATE_TARBALL:-1}"
PRESERVE_CONFIG_TABLES="${PRESERVE_CONFIG_TABLES:-${DB_PREFIX}addon ${DB_PREFIX}advertising ${DB_PREFIX}advertising_position ${DB_PREFIX}help_page ${DB_PREFIX}link_forum ${DB_PREFIX}navigation ${DB_PREFIX}notice ${DB_PREFIX}option ${DB_PREFIX}option_group ${DB_PREFIX}option_group_relation ${DB_PREFIX}payment_profile ${DB_PREFIX}phrase ${DB_PREFIX}route_filter ${DB_PREFIX}style ${DB_PREFIX}style_property ${DB_PREFIX}style_property_group ${DB_PREFIX}template ${DB_PREFIX}template_map ${DB_PREFIX}template_modification ${DB_PREFIX}widget ${DB_PREFIX}widget_definition ${DB_PREFIX}widget_position}"
PRESERVE_TABLE_PATTERNS="${PRESERVE_TABLE_PATTERNS:-${DB_PREFIX}mg\\_% ${DB_PREFIX}rm\\_%}"
PRESERVE_MASTER_TEMPLATES="${PRESERVE_MASTER_TEMPLATES:-1}"

ROLLBACK_ARCHIVE_DIR="${ROLLBACK_ARCHIVE_DIR:-/home/xf-emergency-rollback}"
COPY_TARBALL_TO_ROLLBACK_DIR="${COPY_TARBALL_TO_ROLLBACK_DIR:-1}"
INCLUDE_SYSTEM_ROLLBACK="${INCLUDE_SYSTEM_ROLLBACK:-1}"
NGINX_ETC="${NGINX_ETC:-/etc/nginx}"
TLS_EXTRA_PATHS="${TLS_EXTRA_PATHS:-/etc/letsencrypt}"
BACKUP_ACME_SH="${BACKUP_ACME_SH:-1}"
ACME_SH_HOME="${ACME_SH_HOME:-/root/.acme.sh}"
BACKUP_PHP_ETC="${BACKUP_PHP_ETC:-1}"

prefer_environment_override() {
    local name="$1"
    local override_value="${2:-}"
    if [[ -z "${override_value}" ]]; then
        override_value="$(printenv "${name}" 2>/dev/null || true)"
    fi
    if [[ -n "${override_value}" ]]; then
        printf -v "${name}" '%s' "${override_value}"
    fi
}

prefer_environment_override DRY_RUN "$(printenv DRY_RUN 2>/dev/null || true)"
prefer_environment_override BACKUP_NEW_FILES "$(printenv BACKUP_NEW_FILES 2>/dev/null || true)"
prefer_environment_override CREATE_TARBALL "$(printenv CREATE_TARBALL 2>/dev/null || true)"
prefer_environment_override COPY_TARBALL_TO_ROLLBACK_DIR "$(printenv COPY_TARBALL_TO_ROLLBACK_DIR 2>/dev/null || true)"
prefer_environment_override INCLUDE_SYSTEM_ROLLBACK "$(printenv INCLUDE_SYSTEM_ROLLBACK 2>/dev/null || true)"
prefer_environment_override ROLLBACK_ARCHIVE_DIR "$(printenv ROLLBACK_ARCHIVE_DIR 2>/dev/null || true)"
prefer_environment_override BACKUP_ACME_SH "$(printenv BACKUP_ACME_SH 2>/dev/null || true)"
prefer_environment_override BACKUP_PHP_ETC "$(printenv BACKUP_PHP_ETC 2>/dev/null || true)"

require_command mysqldump
require_command mysql
require_command gzip
require_command php
require_command tar
require_command rsync

[[ -d "${NEW_FORUM_ROOT}" ]] || die "Forum root not found: ${NEW_FORUM_ROOT}"
[[ -f "${NEW_FORUM_ROOT}/cmd.php" ]] || die "Missing cmd.php under ${NEW_FORUM_ROOT}"
[[ -f "${NEW_FORUM_ROOT}/src/config.php" ]] || die "Missing src/config.php under ${NEW_FORUM_ROOT}"

NEW_DB_HOST="${NEW_DB_HOST:-$(xf_config_value "${NEW_FORUM_ROOT}/src/config.php" "db.host" || true)}"
NEW_DB_PORT="${NEW_DB_PORT:-$(xf_config_value "${NEW_FORUM_ROOT}/src/config.php" "db.port" || true)}"
NEW_DB_NAME="${NEW_DB_NAME:-$(xf_config_value "${NEW_FORUM_ROOT}/src/config.php" "db.dbname" || true)}"
NEW_DB_USER="${NEW_DB_USER:-$(xf_config_value "${NEW_FORUM_ROOT}/src/config.php" "db.username" || true)}"
NEW_DB_PASSWORD="${NEW_DB_PASSWORD:-$(xf_config_value "${NEW_FORUM_ROOT}/src/config.php" "db.password" || true)}"

NEW_DB_HOST="${NEW_DB_HOST:-127.0.0.1}"
NEW_DB_PORT="${NEW_DB_PORT:-3306}"

require_var NEW_DB_NAME
require_var NEW_DB_USER
require_var NEW_DB_PASSWORD

read -r -a SYNC_ITEMS <<< "${XF_SYNC_ITEMS}"
read -r -a PRESERVE_FILE_PATHS <<< "${PRESERVE_FILE_ITEMS}"

RUN_ID="$(date +%Y%m%d-%H%M%S)"
WORK_ROOT="${WORK_ROOT_BASE}/${RUN_ID}"
BACKUP_ROOT="${WORK_ROOT}/backups"
NEW_BACKUP_GZ="${BACKUP_ROOT}/new-before-cutover.sql.gz"
CONFIG_BACKUP="${BACKUP_ROOT}/config.php"
STYLE_BACKUP_ROOT="${WORK_ROOT}/style-backups"
STYLE_METADATA="${STYLE_BACKUP_ROOT}/styles.tsv"
DEFAULT_STYLE_FILE="${STYLE_BACKUP_ROOT}/default-style-id.txt"
CONFIG_TABLE_BACKUP_GZ="${BACKUP_ROOT}/config-tables.sql.gz"
MASTER_TEMPLATE_BACKUP_GZ="${BACKUP_ROOT}/master-templates.sql.gz"
PRESERVED_FILES_ROOT="${BACKUP_ROOT}/preserved-files"
SYSTEM_ROLLBACK_ROOT="${BACKUP_ROOT}/system-rollback"

tls_path_tag() {
    local p="$1"
    p="${p#/}"
    p="${p//\//_}"
    printf '%s' "${p:-root}"
}

write_system_rollback_howto() {
    local dest="$1"
    cat > "${dest}" <<'HOWTO'
# Emergency rollback (read me first)

This tree was created by xf-pre-cutover-backup.sh. The parent tarball contains XenForo
(DB dumps, style zips, preserved files) plus this system-rollback/ directory.

## 1. Stop web stack (adjust for your host)
    systemctl stop nginx
    systemctl stop php8.3-fpm   # or your PHP version

## 2. Restore nginx + extra TLS + PHP config
From the extracted backup directory (where you see backups/system-rollback/):

    rsync -aHAX ./backups/system-rollback/nginx/ /etc/nginx/
    # Merge tls-extra/* back to original paths (dir names use slashes -> underscores).
    # Single files (if any) are under tls-extra/_files/
    rsync -aHAX ./backups/system-rollback/tls-extra/etc_letsencrypt/ /etc/letsencrypt/
    rsync -aHAX ./backups/system-rollback/php-etc/ /etc/php/

Review diffs first if configs changed since backup: use `rsync -n` dry run.

## 3. Restore acme.sh (if present)
    rsync -aHAX ./backups/system-rollback/acme-sh/ /root/.acme.sh/

## 4. Root crontab
Compare ./backups/system-rollback/root-crontab.txt with `crontab -l` and restore if needed.

## 5. Test nginx
    nginx -t && systemctl start php8.3-fpm && systemctl start nginx

## 6. Restore MySQL
    gunzip -c backups/new-before-cutover.sql.gz | mysql -u ... -p ... YOUR_DB

## 7. Restore XenForo files
Use preserved-files/ and style-backups/ with your normal restore procedure.

HOWTO
}

backup_system_rollback() {
    if [[ "${INCLUDE_SYSTEM_ROLLBACK}" != "1" ]]; then
        return 0
    fi

    log "Capturing nginx / TLS / acme / PHP config for emergency rollback"

    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] rsync ${NGINX_ETC} -> ${SYSTEM_ROLLBACK_ROOT}/nginx/"
        echo "[dry-run] tls-extra paths: ${TLS_EXTRA_PATHS}"
        [[ "${BACKUP_ACME_SH}" == "1" ]] && echo "[dry-run] rsync ${ACME_SH_HOME} -> .../acme-sh/"
        [[ "${BACKUP_PHP_ETC}" == "1" ]] && echo "[dry-run] rsync /etc/php -> .../php-etc/"
        return 0
    fi

    mkdir -p "${SYSTEM_ROLLBACK_ROOT}"

    if [[ -d "${NGINX_ETC}" ]]; then
        mkdir -p "${SYSTEM_ROLLBACK_ROOT}/nginx"
        rsync -aHAX "${NGINX_ETC}/" "${SYSTEM_ROLLBACK_ROOT}/nginx/"
    fi

    read -r -a _tls_extra <<< "${TLS_EXTRA_PATHS}"
    if [[ ${#_tls_extra[@]} -gt 0 ]]; then
        mkdir -p "${SYSTEM_ROLLBACK_ROOT}/tls-extra"
        for p in "${_tls_extra[@]}"; do
            [[ -n "${p}" ]] || continue
            if [[ ! -e "${p}" ]]; then
                continue
            fi
            local tag
            tag="$(tls_path_tag "${p}")"
            if [[ -d "${p}" ]]; then
                mkdir -p "${SYSTEM_ROLLBACK_ROOT}/tls-extra/${tag}"
                rsync -aHAX "${p}/" "${SYSTEM_ROLLBACK_ROOT}/tls-extra/${tag}/"
            elif [[ -f "${p}" ]]; then
                mkdir -p "${SYSTEM_ROLLBACK_ROOT}/tls-extra/_files"
                cp -a "${p}" "${SYSTEM_ROLLBACK_ROOT}/tls-extra/_files/${tag}"
            fi
        done
    fi

    if [[ "${BACKUP_ACME_SH}" == "1" && -d "${ACME_SH_HOME}" ]]; then
        mkdir -p "${SYSTEM_ROLLBACK_ROOT}/acme-sh"
        rsync -aHAX "${ACME_SH_HOME}/" "${SYSTEM_ROLLBACK_ROOT}/acme-sh/"
    fi

    if [[ "${BACKUP_PHP_ETC}" == "1" && -d /etc/php ]]; then
        mkdir -p "${SYSTEM_ROLLBACK_ROOT}/php-etc"
        rsync -aHAX /etc/php/ "${SYSTEM_ROLLBACK_ROOT}/php-etc/"
    fi

    crontab -l > "${SYSTEM_ROLLBACK_ROOT}/root-crontab.txt" 2>/dev/null || true
    {
        date -uIs 2>/dev/null || date
        echo "hostname: $(hostname -f 2>/dev/null || hostname)"
        systemctl is-enabled nginx 2>/dev/null || true
    } > "${SYSTEM_ROLLBACK_ROOT}/host-meta.txt"
    systemctl cat nginx > "${SYSTEM_ROLLBACK_ROOT}/systemd-nginx-unit.cat.txt" 2>/dev/null || true
    nginx -V 2>&1 | head -3 > "${SYSTEM_ROLLBACK_ROOT}/nginx-build-info.txt" 2>/dev/null || true

    write_system_rollback_howto "${SYSTEM_ROLLBACK_ROOT}/ROLLBACK-HOWTO.txt"
}

expand_preserve_tables() {
    local -A seen
    local -a expanded_tables pattern_matches
    local table pattern

    for table in ${PRESERVE_CONFIG_TABLES}; do
        [[ -n "${table}" ]] || continue
        if [[ -z "${seen[${table}]:-}" ]]; then
            expanded_tables+=("${table}")
            seen["${table}"]=1
        fi
    done

    for pattern in ${PRESERVE_TABLE_PATTERNS}; do
        [[ -n "${pattern}" ]] || continue
        mapfile -t pattern_matches < <(mysql_query "SHOW TABLES LIKE '${pattern}';" || true)
        for table in "${pattern_matches[@]}"; do
            [[ -n "${table}" ]] || continue
            if [[ -z "${seen[${table}]:-}" ]]; then
                expanded_tables+=("${table}")
                seen["${table}"]=1
            fi
        done
    done

    printf '%s\n' "${expanded_tables[@]}"
}

mapfile -t CONFIG_TABLES < <(expand_preserve_tables)

validate_new_db_state() {
    local default_style_id

    mysql_query "SELECT 1 FROM ${DB_PREFIX}style LIMIT 1;" >/dev/null || die "Database ${NEW_DB_NAME} missing ${DB_PREFIX}style."
    mysql_query "SELECT 1 FROM ${DB_PREFIX}option LIMIT 1;" >/dev/null || die "Database ${NEW_DB_NAME} missing ${DB_PREFIX}option."

    default_style_id="$(mysql_query "SELECT option_value FROM ${DB_PREFIX}option WHERE option_id = 'defaultStyleId' LIMIT 1;" || true)"
    [[ -n "${default_style_id}" ]] || die "No defaultStyleId in ${DB_PREFIX}option."
}

maybe_run() {
    if [[ "${DRY_RUN}" == "1" ]]; then
        printf '[dry-run] '
        printf '%q ' "$@"
        printf '\n'
    else
        "$@"
    fi
}

ensure_style_export_dirs() {
    maybe_run mkdir -p "${NEW_FORUM_ROOT}/styles/adminjunkies"
    if [[ -d "${NEW_FORUM_ROOT}/data/styles" ]]; then
        while IFS= read -r -d '' child; do
            maybe_run mkdir -p "${child}/styles/adminjunkies"
        done < <(find "${NEW_FORUM_ROOT}/data/styles" -mindepth 1 -maxdepth 1 -type d -print0 2>/dev/null || true)
    fi
    maybe_run chown -R "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}/styles/adminjunkies" "${NEW_FORUM_ROOT}/data/styles" 2>/dev/null || true
}

backup_styles() {
    local rows default_style_id
    rows="$(mysql_query "SELECT style_id, title, parent_id FROM ${DB_PREFIX}style WHERE style_id > 1 ORDER BY parent_id, style_id;")"
    default_style_id="$(mysql_query "SELECT option_value FROM ${DB_PREFIX}option WHERE option_id = 'defaultStyleId' LIMIT 1;")"

    if [[ -z "${rows}" ]]; then
        log "No custom styles (style_id > 1) to export"
        return 0
    fi

    log "Exporting style archives (includes templates like extra.less per style)"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] mkdir ${STYLE_BACKUP_ROOT}; write styles.tsv / defaultStyleId.txt; xf:style-archive-export per row"
    else
        mkdir -p "${STYLE_BACKUP_ROOT}"
        printf '%s\n' "${default_style_id}" > "${DEFAULT_STYLE_FILE}"
        printf '%s\n' "${rows}" > "${STYLE_METADATA}"
    fi

    ensure_style_export_dirs

    while IFS=$'\t' read -r style_id title parent_id; do
        [[ -n "${style_id}" ]] || continue
        local style_dir="${STYLE_BACKUP_ROOT}/style-${style_id}"
        maybe_run mkdir -p "${style_dir}"
        maybe_run chown -R "${PHP_USER}:${PHP_USER}" "${style_dir}"
        maybe_run sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" \
            xf:style-archive-export "${style_id}" \
            --destination "${style_dir}" \
            --independent 1 \
            --no-interaction
    done <<< "${rows}"
}

backup_config_tables() {
    [[ ${#CONFIG_TABLES[@]} -gt 0 ]] || return 0

    log "Dumping config-related tables to config-tables.sql.gz"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] mysqldump -> ${CONFIG_TABLE_BACKUP_GZ}"
        return 0
    fi

    MYSQL_PWD="${NEW_DB_PASSWORD}" mysqldump \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        --single-transaction \
        --triggers \
        --no-tablespaces \
        --default-character-set=utf8mb4 \
        "${NEW_DB_NAME}" \
        "${CONFIG_TABLES[@]}" | gzip -1 > "${CONFIG_TABLE_BACKUP_GZ}"

    gzip -t "${CONFIG_TABLE_BACKUP_GZ}"
}

backup_master_templates() {
    if [[ "${PRESERVE_MASTER_TEMPLATES}" != "1" ]]; then
        return 0
    fi

    log "Dumping master templates (style_id = 0)"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] mysqldump -> ${MASTER_TEMPLATE_BACKUP_GZ}"
        return 0
    fi

    MYSQL_PWD="${NEW_DB_PASSWORD}" mysqldump \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        --single-transaction \
        --triggers \
        --no-tablespaces \
        --default-character-set=utf8mb4 \
        --where="style_id = 0" \
        "${NEW_DB_NAME}" \
        "${DB_PREFIX}template" | gzip -1 > "${MASTER_TEMPLATE_BACKUP_GZ}"

    gzip -t "${MASTER_TEMPLATE_BACKUP_GZ}"
}

backup_preserve_files() {
    [[ ${#PRESERVE_FILE_PATHS[@]} -gt 0 ]] || return 0

    log "Preserving file paths: ${PRESERVE_FILE_ITEMS}"
    for item in "${PRESERVE_FILE_PATHS[@]}"; do
        [[ -n "${item}" ]] || continue
        if [[ ! -e "${NEW_FORUM_ROOT}/${item}" ]]; then
            continue
        fi

        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] rsync ${NEW_FORUM_ROOT}/${item} -> ${PRESERVED_FILES_ROOT}/${item}"
            continue
        fi

        mkdir -p "${PRESERVED_FILES_ROOT}/${item}"
        if [[ -d "${NEW_FORUM_ROOT}/${item}" ]]; then
            rsync -aHAX "${NEW_FORUM_ROOT}/${item}/" "${PRESERVED_FILES_ROOT}/${item}/"
        else
            mkdir -p "$(dirname "${PRESERVED_FILES_ROOT}/${item}")"
            cp "${NEW_FORUM_ROOT}/${item}" "${PRESERVED_FILES_ROOT}/${item}"
        fi
    done
}

backup_full_database() {
    log "Dumping full database to new-before-cutover.sql.gz"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] mysqldump full -> ${NEW_BACKUP_GZ}"
        return 0
    fi

    MYSQL_PWD="${NEW_DB_PASSWORD}" mysqldump \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        --single-transaction \
        --routines \
        --triggers \
        --no-tablespaces \
        --default-character-set=utf8mb4 \
        "${NEW_DB_NAME}" | gzip -1 > "${NEW_BACKUP_GZ}"

    gzip -t "${NEW_BACKUP_GZ}"
}

backup_sync_items() {
    if [[ "${BACKUP_NEW_FILES}" != "1" ]]; then
        return 0
    fi

    log "Backing up XF_SYNC_ITEMS under backups/files/"
    for item in "${SYNC_ITEMS[@]}"; do
        [[ -n "${item}" ]] || continue
        if [[ ! -e "${NEW_FORUM_ROOT}/${item}" ]]; then
            continue
        fi
        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] rsync ${NEW_FORUM_ROOT}/${item} -> ${BACKUP_ROOT}/files/${item}"
            continue
        fi
        mkdir -p "${BACKUP_ROOT}/files/${item}"
        rsync -aHAX "${NEW_FORUM_ROOT}/${item}/" "${BACKUP_ROOT}/files/${item}/"
    done
}

log "Pre-cutover backup"
echo "  env file:        ${ENV_FILE:-<defaults + config.php>}"
echo "  forum root:      ${NEW_FORUM_ROOT}"
echo "  database:        ${NEW_DB_NAME} @ ${NEW_DB_HOST}:${NEW_DB_PORT}"
echo "  work root:       ${WORK_ROOT}"
echo "  dry run:         ${DRY_RUN}"
echo "  backup uploads:  ${BACKUP_NEW_FILES} (internal_data/data when 1)"
echo "  tarball:         ${CREATE_TARBALL}"
echo "  system rollback: ${INCLUDE_SYSTEM_ROLLBACK} (nginx/TLS/acme/php → backups/system-rollback/)"
echo "  copy to /home:   ${COPY_TARBALL_TO_ROLLBACK_DIR} → ${ROLLBACK_ARCHIVE_DIR}"

validate_new_db_state

maybe_run mkdir -p "${BACKUP_ROOT}"
maybe_run cp "${NEW_FORUM_ROOT}/src/config.php" "${CONFIG_BACKUP}"

if [[ "${PRESERVE_STYLES}" == "1" ]]; then
    backup_styles
fi

backup_config_tables
backup_master_templates
backup_preserve_files
backup_sync_items
backup_system_rollback
backup_full_database

MANIFEST="${BACKUP_ROOT}/BACKUP-MANIFEST.txt"
if [[ "${DRY_RUN}" != "1" ]]; then
    {
        echo "Created: ${RUN_ID}"
        echo "Forum: ${NEW_FORUM_ROOT}"
        echo "Database: ${NEW_DB_NAME}"
        echo ""
        echo "Includes:"
        echo "  - Full DB: new-before-cutover.sql.gz (all xf_template rows, options, styles)"
        echo "  - Config subset: config-tables.sql.gz"
        echo "  - Master templates: master-templates.sql.gz (style_id=0, if enabled)"
        echo "  - Style ZIP exports: ../style-backups/style-*/"
        echo "  - Files: preserved-files/ (${PRESERVE_FILE_ITEMS})"
        if [[ "${BACKUP_NEW_FILES}" == "1" ]]; then
            echo "  - Large trees: files/ (${XF_SYNC_ITEMS})"
        fi
        if [[ "${INCLUDE_SYSTEM_ROLLBACK}" == "1" ]]; then
            echo "  - Emergency system snapshot: system-rollback/ (nginx, tls-extra, acme-sh, php-etc, crontab)"
            echo "    Read: backups/system-rollback/ROLLBACK-HOWTO.txt"
        fi
        echo ""
        echo "Restore hints: see ${SCRIPT_DIR}/xf-restore-*.sh and xf-final-cutover.sh"
        if [[ "${COPY_TARBALL_TO_ROLLBACK_DIR}" == "1" && "${CREATE_TARBALL}" == "1" ]]; then
            echo ""
            echo "Tarball copy for panic rollback: ${ROLLBACK_ARCHIVE_DIR}/"
            echo "  Symlink: ${ROLLBACK_ARCHIVE_DIR}/LATEST-EMERGENCY-BACKUP.tar.gz"
        fi
    } > "${MANIFEST}"
fi

TAR_PATH="${WORK_ROOT_BASE}/${RUN_ID}.tar.gz"
TAR_BASENAME="$(basename "${TAR_PATH}")"
if [[ "${CREATE_TARBALL}" == "1" && "${DRY_RUN}" != "1" ]]; then
    log "Creating tarball ${TAR_PATH}"
    tar -C "${WORK_ROOT_BASE}" -czf "${TAR_PATH}" "${RUN_ID}"
fi

if [[ "${COPY_TARBALL_TO_ROLLBACK_DIR}" == "1" && "${CREATE_TARBALL}" == "1" && "${DRY_RUN}" != "1" ]]; then
    log "Copying tarball to ${ROLLBACK_ARCHIVE_DIR} (root-only, mode 600)"
    install -d -m 0700 -o root -g root "${ROLLBACK_ARCHIVE_DIR}"
    cp -a "${TAR_PATH}" "${ROLLBACK_ARCHIVE_DIR}/${TAR_BASENAME}"
    chmod 600 "${ROLLBACK_ARCHIVE_DIR}/${TAR_BASENAME}"
    ln -sfn "${TAR_BASENAME}" "${ROLLBACK_ARCHIVE_DIR}/LATEST-EMERGENCY-BACKUP.tar.gz"
fi

log "Done"
echo "  directory: ${WORK_ROOT}"
if [[ "${CREATE_TARBALL}" == "1" && "${DRY_RUN}" != "1" ]]; then
    echo "  tarball:   ${TAR_PATH}"
    if [[ "${COPY_TARBALL_TO_ROLLBACK_DIR}" == "1" ]]; then
        echo "  rollback:  ${ROLLBACK_ARCHIVE_DIR}/${TAR_BASENAME}"
        echo "  latest →:  ${ROLLBACK_ARCHIVE_DIR}/LATEST-EMERGENCY-BACKUP.tar.gz"
    fi
    ls -lh "${TAR_PATH}" "${NEW_BACKUP_GZ}" "${CONFIG_TABLE_BACKUP_GZ}" 2>/dev/null || ls -lh "${WORK_ROOT}"
fi
