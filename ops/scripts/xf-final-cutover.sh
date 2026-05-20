#!/usr/bin/env bash

set -Eeuo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo "$0" "$@"
fi

SCRIPT_NAME="$(basename "$0")"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_OVERRIDE_DRY_RUN="$(printenv DRY_RUN 2>/dev/null || true)"
ENV_OVERRIDE_FORCE="$(printenv FORCE 2>/dev/null || true)"
ENV_OVERRIDE_BACKUP_NEW_FILES="$(printenv BACKUP_NEW_FILES 2>/dev/null || true)"
ENV_OVERRIDE_RSYNC_DELETE="$(printenv RSYNC_DELETE 2>/dev/null || true)"

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

parse_csv_value() {
    local input="$1"
    local index="$2"
    IFS=',' read -r -a parts <<< "${input}"
    [[ ${#parts[@]} -gt ${index} ]] || return 1
    printf '%s' "${parts[${index}]}"
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

join_by() {
    local delimiter="$1"
    shift
    local first=1
    for item in "$@"; do
        if [[ $first -eq 1 ]]; then
            printf '%s' "$item"
            first=0
        else
            printf '%s%s' "$delimiter" "$item"
        fi
    done
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

prefer_environment_override() {
    local name="$1"
    local explicit_override="${2:-}"
    local current_value="${!name:-}"
    local override_value="${explicit_override}"

    if [[ -z "${override_value}" ]]; then
        override_value="$(printenv "${name}" 2>/dev/null || true)"
    fi

    if [[ -n "${override_value}" ]]; then
        printf -v "${name}" '%s' "${override_value}"
    else
        printf -v "${name}" '%s' "${current_value}"
    fi
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

mysql_exec() {
    local sql="$1"
    MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        "${NEW_DB_NAME}" \
        -e "${sql}"
}

mysql_table_exists() {
    local table_name="$1"
    MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
        -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${NEW_DB_NAME}' AND table_name = '${table_name}';" \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        "${NEW_DB_NAME}"
}

# Cron / web can hit XF while mysqldump import is in flight; ACP then shows harmless 1146 rows.
clear_transient_error_log_after_import() {
    if [[ "${CLEAR_TRANSIENT_ERROR_LOG_AFTER_IMPORT:-1}" != "1" ]]; then
        return 0
    fi
    if [[ "$(mysql_table_exists "${DB_PREFIX}error_log")" != "1" ]]; then
        return 0
    fi

    log "Clearing transient ${DB_PREFIX}error_log rows (1146 missing xf_job/xf_user during DB swap)"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] DELETE FROM ${DB_PREFIX}error_log WHERE message LIKE '%1146%' AND (message LIKE '%xf_job%' OR message LIKE '%xf_user%')"
        return 0
    fi

    local n
    n="$(mysql_query "SELECT COUNT(*) FROM ${DB_PREFIX}error_log WHERE message LIKE '%1146%' AND (message LIKE '%xf_job%' OR message LIKE '%xf_user%');" 2>/dev/null | tr -d '\r\n' || true)"
    [[ -n "${n}" ]] && [[ "${n}" == "${n//[^0-9]/}" ]] || n=0
    if [[ "${n}" -eq 0 ]]; then
        log "No matching transient ${DB_PREFIX}error_log rows"
        return 0
    fi

    mysql_exec "DELETE FROM ${DB_PREFIX}error_log WHERE message LIKE '%1146%' AND (message LIKE '%xf_job%' OR message LIKE '%xf_user%');" || true
    log "Deleted ${n} transient ${DB_PREFIX}error_log row(s)"
}

validate_new_db_state() {
    local default_style_id

    [[ "$(mysql_table_exists "${DB_PREFIX}style")" == "1" ]] || die "Current new DB is missing ${DB_PREFIX}style; restore beta backup before cutover."
    [[ "$(mysql_table_exists "${DB_PREFIX}option")" == "1" ]] || die "Current new DB is missing ${DB_PREFIX}option; restore beta backup before cutover."

    default_style_id="$(mysql_query "SELECT option_value FROM ${DB_PREFIX}option WHERE option_id = 'defaultStyleId' LIMIT 1;" || true)"
    [[ -n "${default_style_id}" ]] || die "Current new DB does not have a defaultStyleId option value."
}

run_ssh() {
    if [[ -n "${OLD_SSH_PASSWORD:-}" ]]; then
        sshpass -p "${OLD_SSH_PASSWORD}" ssh -o StrictHostKeyChecking=accept-new -p "${OLD_SSH_PORT}" "${OLD_SSH_USER}@${OLD_HOST}" "$@"
    else
        ssh -p "${OLD_SSH_PORT}" "${OLD_SSH_USER}@${OLD_HOST}" "$@"
    fi
}

run_rsync() {
    local -a ssh_parts
    ssh_parts=(ssh -o StrictHostKeyChecking=accept-new -p "${OLD_SSH_PORT}")
    if [[ -n "${OLD_SSH_PASSWORD:-}" ]]; then
        sshpass -p "${OLD_SSH_PASSWORD}" rsync "$@" -e "$(printf '%q ' "${ssh_parts[@]}")"
    else
        rsync "$@" -e "$(printf '%q ' "${ssh_parts[@]}")"
    fi
}

usage() {
    cat <<'EOF'
Usage:
  sudo xenforo/scripts/xf-final-cutover.sh [/path/to/xf-final-cutover.env]

Environment file variables:
  OLD_HOST                Old XenForo server host or IP
  OLD_SSH_USER            SSH user on old server
  OLD_DB_NAME             Old XenForo database name
  OLD_DB_USER             Old XenForo database user
  OLD_DB_PASSWORD         Old XenForo database password

Optional:
  OLD_SSH_PORT=22
  OLD_FORUM_ROOT=/var/www/bareefers.org/forum
  NEW_FORUM_ROOT=/var/www/bareefers.org/forum
  NEW_DB_HOST=127.0.0.1
  NEW_DB_PORT=3306
  DB_PREFIX=xf_
  NEW_DB_CHARSET=utf8mb4
  NEW_DB_COLLATION=utf8mb4_unicode_ci
  PHP_USER=www-data
  XF_SYNC_ITEMS="internal_data data"
  PRESERVE_FILE_ITEMS="data/assets data/local data/styles styles sponsor_banners"
  PRESERVE_STYLES=1
  # Default PRESERVE_CONFIG_TABLES includes xf_payment_profile (PayPal client/secret + webhook settings on new server).
  RSYNC_DELETE=1
  DRY_RUN=0
  FORCE=0
  BACKUP_NEW_FILES=0
  WORK_ROOT_BASE=/var/tmp/xf-final-cutover
  OLD_RSYNC_SUDO=0
  OLD_USE_ROOT_MYSQL=1
  OLD_MAINTENANCE_CMD=
  OLD_POST_CUTOVER_CMD=

  Post-cutover ACP hygiene (defaults on):
  POST_CUTOVER_ACP_FIXES=1
  POST_CUTOVER_CHOWN_FORUM_ROOT=1
  POST_CUTOVER_XF_FILE_CLEANUP_CLI=1
  POST_CUTOVER_PATCH_ONE_CLICK_UPGRADE=1
  POST_CUTOVER_DELETE_UPGRADE_CHECK_JOBS=1
  # Remove harmless ACP rows from cron/web hitting XF while tables were missing mid-import (default 1).
  CLEAR_TRANSIENT_ERROR_LOG_AFTER_IMPORT=1

  Optional BAR style16 primary snapshot (VERIFY.txt + SHA256SUMS + /home/.../bar-style16-primary):
  RUN_BAR_STYLE16_PRIMARY_SNAPSHOT=1
  BAR_STYLE16_PRIMARY_BACKUP_SCRIPT=/tmp/xf-backup-bar-style16-visual.sh

Before a test cutover, run a backup-only pass (same DB/style/file artifacts, no import):
  sudo xenforo/scripts/xf-pre-cutover-backup.sh [/path/to/xf-final-cutover.env]
  Set BACKUP_NEW_FILES=1 in the env to include internal_data and data in the snapshot.
  By default it also snapshots nginx/TLS/acme/php, tars everything, and copies the tarball to
  /home/xf-emergency-rollback/ with LATEST-EMERGENCY-BACKUP.tar.gz (override ROLLBACK_ARCHIVE_DIR as needed).

What it does:
  1. Optionally puts old forum into maintenance mode via OLD_MAINTENANCE_CMD
  2. Backs up the current new-server DB and src/config.php
  3. Dumps the old live XenForo DB over SSH
  4. Recreates the new XenForo DB and imports the old dump
  5. Rsyncs XenForo file data from old to new
  6. Fixes ownership (forum root for XF 2.3+ FileCleanUp, internal_data/data chmod) and runs xf:rebuild-master-data on the new server
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
    for candidate in "${SCRIPT_DIR}/xf-final-cutover.env" "/root/xf-final-cutover.env" "${SCRIPT_DIR}/xf-final-cutover.env.example"; do
        if [[ -f "${candidate}" ]]; then
            # shellcheck disable=SC1090
            source "${candidate}"
            ENV_FILE="${candidate}"
            break
        fi
    done
fi

if [[ -n "${BC_XF_DB_SSH_CREDENTIALS:-}" ]]; then
    OLD_HOST="${OLD_HOST:-$(parse_csv_value "${BC_XF_DB_SSH_CREDENTIALS}" 0 || true)}"
    OLD_SSH_PORT="${OLD_SSH_PORT:-$(parse_csv_value "${BC_XF_DB_SSH_CREDENTIALS}" 1 || true)}"
    OLD_SSH_USER="${OLD_SSH_USER:-$(parse_csv_value "${BC_XF_DB_SSH_CREDENTIALS}" 2 || true)}"
    OLD_SSH_PASSWORD="${OLD_SSH_PASSWORD:-$(parse_csv_value "${BC_XF_DB_SSH_CREDENTIALS}" 3 || true)}"
fi

if [[ -n "${BC_XF_DB_CREDENTIALS:-}" ]]; then
    OLD_DB_NAME="${OLD_DB_NAME:-$(parse_csv_value "${BC_XF_DB_CREDENTIALS}" 0 || true)}"
    OLD_DB_USER="${OLD_DB_USER:-$(parse_csv_value "${BC_XF_DB_CREDENTIALS}" 1 || true)}"
    OLD_DB_PASSWORD="${OLD_DB_PASSWORD:-$(parse_csv_value "${BC_XF_DB_CREDENTIALS}" 2 || true)}"
fi

OLD_SSH_PORT="${OLD_SSH_PORT:-22}"
OLD_FORUM_ROOT="${OLD_FORUM_ROOT:-/var/www/bareefers.org/forum}"
NEW_FORUM_ROOT="${NEW_FORUM_ROOT:-/var/www/bareefers.org/forum}"
NEW_DB_HOST="${NEW_DB_HOST:-127.0.0.1}"
NEW_DB_PORT="${NEW_DB_PORT:-3306}"
DB_PREFIX="${DB_PREFIX:-xf_}"
NEW_DB_CHARSET="${NEW_DB_CHARSET:-utf8mb4}"
NEW_DB_COLLATION="${NEW_DB_COLLATION:-utf8mb4_unicode_ci}"
PHP_USER="${PHP_USER:-www-data}"
XF_SYNC_ITEMS="${XF_SYNC_ITEMS:-internal_data data}"
PRESERVE_FILE_ITEMS="${PRESERVE_FILE_ITEMS:-data/assets data/local data/styles styles sponsor_banners}"
PRESERVE_STYLES="${PRESERVE_STYLES:-1}"
RSYNC_DELETE="${RSYNC_DELETE:-1}"
DRY_RUN="${DRY_RUN:-0}"
FORCE="${FORCE:-0}"
BACKUP_NEW_FILES="${BACKUP_NEW_FILES:-0}"
WORK_ROOT_BASE="${WORK_ROOT_BASE:-/var/tmp/xf-final-cutover}"
OLD_RSYNC_SUDO="${OLD_RSYNC_SUDO:-0}"
OLD_USE_ROOT_MYSQL="${OLD_USE_ROOT_MYSQL:-0}"
OLD_MAINTENANCE_CMD="${OLD_MAINTENANCE_CMD:-}"
OLD_POST_CUTOVER_CMD="${OLD_POST_CUTOVER_CMD:-}"
# Include ${DB_PREFIX}payment_profile so new-server PayPal (etc.) survives re-import of old DB; secrets live there, not only in xf_option.
PRESERVE_CONFIG_TABLES="${PRESERVE_CONFIG_TABLES:-${DB_PREFIX}addon ${DB_PREFIX}advertising ${DB_PREFIX}advertising_position ${DB_PREFIX}help_page ${DB_PREFIX}link_forum ${DB_PREFIX}navigation ${DB_PREFIX}notice ${DB_PREFIX}option ${DB_PREFIX}option_group ${DB_PREFIX}option_group_relation ${DB_PREFIX}payment_profile ${DB_PREFIX}phrase ${DB_PREFIX}route_filter ${DB_PREFIX}style ${DB_PREFIX}style_property ${DB_PREFIX}style_property_group ${DB_PREFIX}template ${DB_PREFIX}template_map ${DB_PREFIX}template_modification ${DB_PREFIX}widget ${DB_PREFIX}widget_definition ${DB_PREFIX}widget_position}"
PRESERVE_TABLE_PATTERNS="${PRESERVE_TABLE_PATTERNS:-${DB_PREFIX}mg\\_% ${DB_PREFIX}rm\\_%}"
PRESERVE_MASTER_TEMPLATES="${PRESERVE_MASTER_TEMPLATES:-1}"
# After rsync / tar unpack, root-owned trees break XF 2.3+ FileCleanUp (legacy deletes under js/ src/ styles/, not only internal_data).
POST_CUTOVER_ACP_FIXES="${POST_CUTOVER_ACP_FIXES:-1}"
# chown entire NEW_FORUM_ROOT to PHP_USER so FileCleanUp can unlink legacy files (default on; set 0 only if you manage ownership elsewhere).
POST_CUTOVER_CHOWN_FORUM_ROOT="${POST_CUTOVER_CHOWN_FORUM_ROOT:-1}"
# Run CLI legacy cleanup once after chown (xf:file-clean-up XF -n); failures are logged but do not abort cutover. Set 0 to skip.
POST_CUTOVER_XF_FILE_CLEANUP_CLI="${POST_CUTOVER_XF_FILE_CLEANUP_CLI:-1}"
# Outbound XenForo upgrade API may return HTML (WAF/proxy) -> Guzzle json_decode errors in UpgradeCheck job.
POST_CUTOVER_PATCH_ONE_CLICK_UPGRADE="${POST_CUTOVER_PATCH_ONE_CLICK_UPGRADE:-1}"
POST_CUTOVER_DELETE_UPGRADE_CHECK_JOBS="${POST_CUTOVER_DELETE_UPGRADE_CHECK_JOBS:-1}"
# Delete ${DB_PREFIX}error_log rows matching 1146 + xf_job/xf_user (noise from import window; set 0 to keep).
CLEAR_TRANSIENT_ERROR_LOG_AFTER_IMPORT="${CLEAR_TRANSIENT_ERROR_LOG_AFTER_IMPORT:-1}"
# Optional: run BAR style16 VERIFY/SHA256 snapshot before DB import (set RUN=1 and place script on server, e.g. /tmp).
RUN_BAR_STYLE16_PRIMARY_SNAPSHOT="${RUN_BAR_STYLE16_PRIMARY_SNAPSHOT:-0}"
BAR_STYLE16_PRIMARY_BACKUP_SCRIPT="${BAR_STYLE16_PRIMARY_BACKUP_SCRIPT:-}"

prefer_environment_override DRY_RUN "${ENV_OVERRIDE_DRY_RUN}"
prefer_environment_override FORCE "${ENV_OVERRIDE_FORCE}"
prefer_environment_override BACKUP_NEW_FILES "${ENV_OVERRIDE_BACKUP_NEW_FILES}"
prefer_environment_override RSYNC_DELETE "${ENV_OVERRIDE_RSYNC_DELETE}"

require_command ssh
require_command rsync
require_command mysqldump
require_command mysql
require_command gzip
require_command php
if [[ -n "${OLD_SSH_PASSWORD:-}" ]]; then
    require_command sshpass
fi

require_var OLD_HOST
require_var OLD_SSH_USER
require_var OLD_DB_NAME
require_var OLD_DB_USER
require_var OLD_DB_PASSWORD

[[ -d "${NEW_FORUM_ROOT}" ]] || die "New forum root not found: ${NEW_FORUM_ROOT}"
[[ -f "${NEW_FORUM_ROOT}/cmd.php" ]] || die "Missing XenForo cmd.php under ${NEW_FORUM_ROOT}"
[[ -f "${NEW_FORUM_ROOT}/src/config.php" ]] || die "Missing XenForo src/config.php under ${NEW_FORUM_ROOT}"

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
[[ ${#SYNC_ITEMS[@]} -gt 0 ]] || die "XF_SYNC_ITEMS cannot be empty"

read -r -a PRESERVE_FILE_PATHS <<< "${PRESERVE_FILE_ITEMS}"

RUN_ID="$(date +%Y%m%d-%H%M%S)"
WORK_ROOT="${WORK_ROOT_BASE}/${RUN_ID}"
BACKUP_ROOT="${WORK_ROOT}/backups"
OLD_DUMP_GZ="${WORK_ROOT}/old-live.sql.gz"
NEW_BACKUP_GZ="${BACKUP_ROOT}/new-before-cutover.sql.gz"
CONFIG_BACKUP="${BACKUP_ROOT}/config.php"
STYLE_BACKUP_ROOT="${WORK_ROOT}/style-backups"
STYLE_METADATA="${STYLE_BACKUP_ROOT}/styles.tsv"
DEFAULT_STYLE_FILE="${STYLE_BACKUP_ROOT}/default-style-id.txt"
CONFIG_TABLE_BACKUP_GZ="${BACKUP_ROOT}/config-tables.sql.gz"
MASTER_TEMPLATE_BACKUP_GZ="${BACKUP_ROOT}/master-templates.sql.gz"
PRESERVED_FILES_ROOT="${BACKUP_ROOT}/preserved-files"

log "Cutover summary"
echo "  old host:       ${OLD_SSH_USER}@${OLD_HOST}:${OLD_SSH_PORT}"
echo "  old forum root: ${OLD_FORUM_ROOT}"
echo "  new forum root: ${NEW_FORUM_ROOT}"
echo "  new db:         ${NEW_DB_NAME}@${NEW_DB_HOST}:${NEW_DB_PORT}"
echo "  db prefix:      ${DB_PREFIX}"
echo "  sync items:     $(join_by ', ' "${SYNC_ITEMS[@]}")"
echo "  preserve files: ${PRESERVE_FILE_ITEMS}"
echo "  preserve style: ${PRESERVE_STYLES}"
echo "  preserve cfg:   ${PRESERVE_CONFIG_TABLES}"
echo "  rsync delete:   ${RSYNC_DELETE}"
echo "  dry run:        ${DRY_RUN}"
echo "  work root:      ${WORK_ROOT}"

if [[ "${FORCE}" != "1" ]]; then
    echo
    read -r -p "Type CUTOVER to continue: " CONFIRM
    [[ "${CONFIRM}" == "CUTOVER" ]] || die "Aborted."
fi

log "Validating current new-server database state"
validate_new_db_state

maybe_run mkdir -p "${BACKUP_ROOT}"
maybe_run cp "${NEW_FORUM_ROOT}/src/config.php" "${CONFIG_BACKUP}"

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

backup_styles() {
    local rows default_style_id
    rows="$(mysql_query "SELECT style_id, title, parent_id FROM ${DB_PREFIX}style WHERE style_id > 1 ORDER BY parent_id, style_id;")"
    default_style_id="$(mysql_query "SELECT option_value FROM ${DB_PREFIX}option WHERE option_id = 'defaultStyleId' LIMIT 1;")"

    if [[ -z "${rows}" ]]; then
        log "No custom styles found to export"
        return
    fi

    log "Backing up beta XenForo styles"
    mkdir -p "${STYLE_BACKUP_ROOT}"
    printf '%s\n' "${default_style_id}" > "${DEFAULT_STYLE_FILE}"
    printf '%s\n' "${rows}" > "${STYLE_METADATA}"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] prepared default style id ${default_style_id} in ${DEFAULT_STYLE_FILE}"
        echo "[dry-run] prepared style metadata in ${STYLE_METADATA}"
    fi

    # Some imported themes reference asset paths that no longer exist but are still
    # needed for archive export to succeed. Recreate the directories if they are missing.
    if [[ ! -d "${NEW_FORUM_ROOT}/styles/adminjunkies" ]]; then
        maybe_run mkdir -p "${NEW_FORUM_ROOT}/styles/adminjunkies"
    fi
    while IFS=$'\t' read -r style_id title parent_id; do
        [[ -n "${style_id}" ]] || continue
        maybe_run mkdir -p "${NEW_FORUM_ROOT}/data/styles/${style_id}/styles/adminjunkies"
    done <<< "${rows}"
    maybe_run chown -R "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}/styles/adminjunkies" "${NEW_FORUM_ROOT}/data/styles"

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

    log "Backing up beta XenForo config tables"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] mysqldump selected config tables to ${CONFIG_TABLE_BACKUP_GZ}"
        return
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

    log "Backing up beta XenForo master templates"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] mysqldump master templates to ${MASTER_TEMPLATE_BACKUP_GZ}"
        return
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

restore_table_from_backup() {
    local dump_gz="$1"
    local table_name="$2"
    local restore_file="${WORK_ROOT}/${table_name}-restore.sql"

    [[ -f "${dump_gz}" ]] || return 1

    DUMP_GZ="${dump_gz}" TABLE_NAME="${table_name}" RESTORE_FILE="${restore_file}" python3 <<'PY'
import gzip
import os
from pathlib import Path

dump_path = Path(os.environ["DUMP_GZ"])
restore_path = Path(os.environ["RESTORE_FILE"])
table_name = f"`{os.environ['TABLE_NAME']}`"

capturing = False
lines = []

with gzip.open(dump_path, "rt", encoding="utf-8", errors="ignore") as f:
    for line in f:
        if line.startswith("-- Table structure for table "):
            if table_name in line:
                capturing = True
            elif capturing:
                break
        if capturing:
            lines.append(line)

if not lines:
    raise SystemExit(f"{table_name} section not found in dump")

restore_path.write_text("".join(lines), encoding="utf-8")
PY

    MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        "${NEW_DB_NAME}" \
        < "${restore_file}"
}

restore_known_compat_objects() {
    local similar_table="${DB_PREFIX}es_thread_similar"
    local attachment_table="${DB_PREFIX}attachment_data"

    if [[ "$(mysql_table_exists "${similar_table}")" != "1" ]]; then
        log "Restoring missing ${similar_table} from beta backup"
        restore_table_from_backup "${NEW_BACKUP_GZ}" "${similar_table}"
    fi

    if [[ -z "$(mysql_query "SHOW COLUMNS FROM ${attachment_table} LIKE 'xfmg_mirror_media_id';" || true)" ]]; then
        log "Adding missing ${attachment_table}.xfmg_mirror_media_id compatibility column"
        mysql_query "ALTER TABLE ${attachment_table} ADD COLUMN xfmg_mirror_media_id int unsigned NOT NULL DEFAULT 0 AFTER attach_count;"
    fi
}

backup_preserve_files() {
    [[ ${#PRESERVE_FILE_PATHS[@]} -gt 0 ]] || return 0

    log "Backing up beta-owned file paths"
    for item in "${PRESERVE_FILE_PATHS[@]}"; do
        [[ -n "${item}" ]] || continue
        if [[ ! -e "${NEW_FORUM_ROOT}/${item}" ]]; then
            continue
        fi

        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] preserve ${NEW_FORUM_ROOT}/${item} -> ${PRESERVED_FILES_ROOT}/${item}"
            continue
        fi

        maybe_run mkdir -p "${PRESERVED_FILES_ROOT}/${item}"
        if [[ -d "${NEW_FORUM_ROOT}/${item}" ]]; then
            maybe_run rsync -aHAX "${NEW_FORUM_ROOT}/${item}/" "${PRESERVED_FILES_ROOT}/${item}/"
        else
            maybe_run mkdir -p "$(dirname "${PRESERVED_FILES_ROOT}/${item}")"
            maybe_run cp "${NEW_FORUM_ROOT}/${item}" "${PRESERVED_FILES_ROOT}/${item}"
        fi
    done
}

ensure_internal_data_layout_and_perms() {
    if [[ "${POST_CUTOVER_ACP_FIXES:-1}" != "1" ]]; then
        return 0
    fi

    log "Ensuring internal_data/data layout and permissions (ACP FileCleanUp)"
    local sub
    for sub in temp code_cache attachments sitemaps logs; do
        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] mkdir -p ${NEW_FORUM_ROOT}/internal_data/${sub}"
        else
            mkdir -p "${NEW_FORUM_ROOT}/internal_data/${sub}"
        fi
    done

    for item in internal_data data; do
        if [[ ! -e "${NEW_FORUM_ROOT}/${item}" ]]; then
            continue
        fi
        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] chown/chmod ${NEW_FORUM_ROOT}/${item}"
            continue
        fi
        chown -R "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}/${item}"
        chmod -R ug+rwX "${NEW_FORUM_ROOT}/${item}"
    done

    if [[ "${POST_CUTOVER_CHOWN_FORUM_ROOT:-1}" != "1" ]]; then
        return 0
    fi
    if [[ ! -d "${NEW_FORUM_ROOT}" ]]; then
        return 0
    fi
    log "Chown forum root to ${PHP_USER} (XF FileCleanUp deletes legacy files outside internal_data)"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] chown -R ${PHP_USER}:${PHP_USER} ${NEW_FORUM_ROOT}"
    else
        chown -R "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}"
    fi
}

run_post_cutover_xf_file_cleanup_cli() {
    if [[ "${POST_CUTOVER_ACP_FIXES:-1}" != "1" ]]; then
        return 0
    fi
    if [[ "${POST_CUTOVER_XF_FILE_CLEANUP_CLI:-1}" != "1" ]]; then
        return 0
    fi
    if [[ ! -f "${NEW_FORUM_ROOT}/cmd.php" ]]; then
        log "SKIP xf:file-clean-up: missing ${NEW_FORUM_ROOT}/cmd.php"
        return 0
    fi
    log "Running xf:file-clean-up XF as ${PHP_USER} (CLI legacy cleanup)"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] sudo -u ${PHP_USER} php ${NEW_FORUM_ROOT}/cmd.php xf:file-clean-up XF -n"
        return 0
    fi
    if sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" xf:file-clean-up XF -n; then
        return 0
    fi
    log "WARN: xf:file-clean-up failed; check ownership (POST_CUTOVER_CHOWN_FORUM_ROOT) or run manually with --dry-run first"
}

post_cutover_acp_mitigations() {
    if [[ "${POST_CUTOVER_ACP_FIXES:-1}" != "1" ]]; then
        return 0
    fi

    log "Post-cutover ACP mitigations (upgrade check JSON errors)"

    if [[ "${POST_CUTOVER_PATCH_ONE_CLICK_UPGRADE:-1}" == "1" ]] && [[ "${DRY_RUN}" != "1" ]]; then
        local cfg="${NEW_FORUM_ROOT}/src/config.php"
        if [[ -f "${cfg}" ]] && ! grep -qF "BAR post-cutover patches" "${cfg}"; then
            cat >>"${cfg}" <<'PHP'

// BAR post-cutover patches: outbound XenForo API may return HTML (WAF/proxy). Remove this block after egress is verified.
$config['enableOneClickUpgrade'] = false;
PHP
        fi
    fi

    if [[ "${POST_CUTOVER_DELETE_UPGRADE_CHECK_JOBS:-1}" == "1" ]] && [[ "${DRY_RUN}" != "1" ]]; then
        local n
        n="$(mysql_query "SELECT COUNT(*) FROM ${DB_PREFIX}job WHERE execute_class LIKE '%UpgradeCheck%';" || echo 0)"
        log "Removing ${n} queued UpgradeCheck job row(s)"
        mysql_exec "DELETE FROM ${DB_PREFIX}job WHERE execute_class LIKE '%UpgradeCheck%';" || true
    fi
}

restore_preserve_files() {
    [[ ${#PRESERVE_FILE_PATHS[@]} -gt 0 ]] || return 0

    log "Restoring beta-owned file paths"
    for item in "${PRESERVE_FILE_PATHS[@]}"; do
        [[ -n "${item}" ]] || continue
        if [[ ! -e "${PRESERVED_FILES_ROOT}/${item}" ]]; then
            continue
        fi

        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] restore ${PRESERVED_FILES_ROOT}/${item} -> ${NEW_FORUM_ROOT}/${item}"
            continue
        fi

        if [[ -d "${PRESERVED_FILES_ROOT}/${item}" ]]; then
            maybe_run mkdir -p "${NEW_FORUM_ROOT}/${item}"
            maybe_run rsync -aHAX --delete "${PRESERVED_FILES_ROOT}/${item}/" "${NEW_FORUM_ROOT}/${item}/"
            maybe_run chown -R "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}/${item}"
        else
            maybe_run mkdir -p "$(dirname "${NEW_FORUM_ROOT}/${item}")"
            maybe_run cp "${PRESERVED_FILES_ROOT}/${item}" "${NEW_FORUM_ROOT}/${item}"
            maybe_run chown "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}/${item}"
        fi
    done
}

rebuild_active_addons() {
    local addons addon
    addons="$(mysql_query "SELECT addon_id FROM ${DB_PREFIX}addon WHERE active = 1 AND addon_id <> 'XF' ORDER BY addon_id;" || true)"
    [[ -n "${addons}" ]] || return 0

    log "Rebuilding active XenForo add-ons (--force --no-interaction; failures are non-fatal)"
    while IFS= read -r addon; do
        [[ -n "${addon}" ]] || continue
        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] rebuild add-on ${addon}"
        else
            # XF 2.3+ prompts multiple times per add-on; piping one "y" is not enough.
            # --force skips "unexpected file contents" guards; --no-interaction skips prompts.
            if ! sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" xf:addon-rebuild \
                --force --no-interaction "${addon}"; then
                log "WARNING: xf:addon-rebuild failed for addon_id=${addon}; continuing cutover"
            fi
        fi
    done <<< "${addons}"
}

restore_styles() {
    local default_style_id rows
    [[ -f "${STYLE_METADATA}" ]] || return 0
    [[ -f "${DEFAULT_STYLE_FILE}" ]] || return 0

    default_style_id="$(tr -d '\r\n' < "${DEFAULT_STYLE_FILE}")"
    rows="$(cat "${STYLE_METADATA}")"

    if [[ -z "${rows}" ]]; then
        return
    fi

    log "Restoring beta XenForo styles"

    declare -A STYLE_ID_MAP

    while IFS=$'\t' read -r style_id title parent_id; do
        [[ -n "${style_id}" ]] || continue

        local archive_path before_max after_max import_parent target_style_id
        archive_path="$(ls -1 "${STYLE_BACKUP_ROOT}/style-${style_id}"/*.zip 2>/dev/null | head -1 || true)"
        if [[ -z "${archive_path}" ]]; then
            if [[ "${DRY_RUN}" == "1" ]]; then
                archive_path="${STYLE_BACKUP_ROOT}/style-${style_id}/style-${style_id}.zip"
            else
                die "Missing archived style for style_id=${style_id}"
            fi
        fi

        if mysql_query "SELECT style_id FROM ${DB_PREFIX}style WHERE style_id = ${style_id} LIMIT 1;" >/dev/null 2>&1 && \
            [[ -n "$(mysql_query "SELECT style_id FROM ${DB_PREFIX}style WHERE style_id = ${style_id} LIMIT 1;")" ]]; then
            maybe_run sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" \
                xf:style-archive-import "${archive_path}" \
                --target overwrite \
                --overwrite-style-id "${style_id}" \
                --force \
                --no-interaction
            STYLE_ID_MAP["${style_id}"]="${style_id}"
            continue
        fi

        import_parent=0
        if [[ -n "${parent_id}" && "${parent_id}" != "0" ]]; then
            import_parent="${STYLE_ID_MAP[${parent_id}]:-${parent_id}}"
        fi

        before_max="$(mysql_query "SELECT IFNULL(MAX(style_id), 0) FROM ${DB_PREFIX}style;")"
        maybe_run sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" \
            xf:style-archive-import "${archive_path}" \
            --target child \
            --parent-style-id "${import_parent}" \
            --force \
            --no-interaction
        after_max="$(mysql_query "SELECT IFNULL(MAX(style_id), 0) FROM ${DB_PREFIX}style;")"
        target_style_id="${after_max}"
        [[ "${after_max}" -gt "${before_max}" ]] || die "Failed to detect imported style id for archived style ${style_id}"
        STYLE_ID_MAP["${style_id}"]="${target_style_id}"
    done <<< "${rows}"

    if [[ -n "${default_style_id}" ]]; then
        local mapped_default="${STYLE_ID_MAP[${default_style_id}]:-${default_style_id}}"
        if [[ "${DRY_RUN}" == "1" ]]; then
            echo "[dry-run] set ${DB_PREFIX}option.defaultStyleId=${mapped_default}"
        else
            mysql_query "UPDATE ${DB_PREFIX}option SET option_value = '${mapped_default}' WHERE option_id = 'defaultStyleId';"
        fi
    fi
}

run_bar_style16_primary_snapshot() {
    if [[ "${RUN_BAR_STYLE16_PRIMARY_SNAPSHOT:-0}" != "1" ]]; then
        return 0
    fi
    local snap_script="${BAR_STYLE16_PRIMARY_BACKUP_SCRIPT:-/tmp/xf-backup-bar-style16-visual.sh}"
    if [[ ! -x "${snap_script}" ]]; then
        die "RUN_BAR_STYLE16_PRIMARY_SNAPSHOT=1 but script not executable: ${snap_script} (scp scripts from repo or set BAR_STYLE16_PRIMARY_BACKUP_SCRIPT)"
    fi
    log "BAR style16 primary snapshot (${snap_script}) — see xenforo/docs/BAREEFERS-STYLE16-BACKUP.md"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] bash ${snap_script} ${NEW_FORUM_ROOT}"
    else
        bash "${snap_script}" "${NEW_FORUM_ROOT}" || die "BAR style16 primary backup failed"
    fi
}

run_bar_style16_primary_snapshot

if [[ "${PRESERVE_STYLES}" == "1" ]]; then
    backup_styles
fi

backup_config_tables
backup_master_templates
backup_preserve_files

if [[ "${BACKUP_NEW_FILES}" == "1" ]]; then
    log "Backing up current new-server file data"
    for item in "${SYNC_ITEMS[@]}"; do
        if [[ -e "${NEW_FORUM_ROOT}/${item}" ]]; then
            maybe_run mkdir -p "${BACKUP_ROOT}/files/${item}"
            maybe_run rsync -aHAX "${NEW_FORUM_ROOT}/${item}/" "${BACKUP_ROOT}/files/${item}/"
        fi
    done
fi

if [[ -n "${OLD_MAINTENANCE_CMD}" ]]; then
    log "Enabling maintenance mode on old server"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] ssh old host ${OLD_MAINTENANCE_CMD}"
    else
        run_ssh "${OLD_MAINTENANCE_CMD}"
    fi
else
    log "Skipping old-server maintenance command (OLD_MAINTENANCE_CMD unset)"
fi

log "Backing up current new-server database"
if [[ "${DRY_RUN}" == "1" ]]; then
    echo "[dry-run] mysqldump current new DB to ${NEW_BACKUP_GZ}"
else
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
fi

log "Dumping old live database over SSH"
if [[ "${DRY_RUN}" == "1" ]]; then
    echo "[dry-run] ssh old host and dump ${OLD_DB_NAME} to ${OLD_DUMP_GZ}"
else
    if [[ "${OLD_USE_ROOT_MYSQL}" == "1" ]]; then
        run_ssh "mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 ${OLD_DB_NAME}" | gzip -1 > "${OLD_DUMP_GZ}"
    else
        run_ssh bash -s -- "${OLD_DB_PASSWORD}" "${OLD_DB_USER}" "${OLD_DB_NAME}" <<'EOF' | gzip -1 > "${OLD_DUMP_GZ}"
set -Eeuo pipefail
MYSQL_PWD="$1" mysqldump \
    -u "$2" \
    --single-transaction \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$3"
EOF
    fi
    gzip -t "${OLD_DUMP_GZ}"
fi

log "Recreating new database and importing old live dump"
if [[ "${DRY_RUN}" == "1" ]]; then
    echo "[dry-run] drop/create ${NEW_DB_NAME} then import ${OLD_DUMP_GZ}"
else
    mysql <<EOF
DROP DATABASE IF EXISTS \`${NEW_DB_NAME}\`;
CREATE DATABASE \`${NEW_DB_NAME}\` CHARACTER SET ${NEW_DB_CHARSET} COLLATE ${NEW_DB_COLLATION};
EOF
    gunzip -c "${OLD_DUMP_GZ}" | MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
        -h "${NEW_DB_HOST}" \
        -P "${NEW_DB_PORT}" \
        -u "${NEW_DB_USER}" \
        "${NEW_DB_NAME}"
fi

log "Upgrading imported XenForo database to match current code"
maybe_run sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" xf:upgrade --no-interaction

if [[ -f "${CONFIG_TABLE_BACKUP_GZ}" ]]; then
    log "Restoring beta XenForo config tables"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] import preserved config tables from ${CONFIG_TABLE_BACKUP_GZ}"
    else
        gunzip -c "${CONFIG_TABLE_BACKUP_GZ}" | MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
            -h "${NEW_DB_HOST}" \
            -P "${NEW_DB_PORT}" \
            -u "${NEW_DB_USER}" \
            "${NEW_DB_NAME}"
    fi
fi

if [[ -f "${MASTER_TEMPLATE_BACKUP_GZ}" ]]; then
    log "Restoring beta XenForo master templates"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] import preserved master templates from ${MASTER_TEMPLATE_BACKUP_GZ}"
    else
        gunzip -c "${MASTER_TEMPLATE_BACKUP_GZ}" | MYSQL_PWD="${NEW_DB_PASSWORD}" mysql \
            -h "${NEW_DB_HOST}" \
            -P "${NEW_DB_PORT}" \
            -u "${NEW_DB_USER}" \
            "${NEW_DB_NAME}"
    fi
fi

restore_known_compat_objects

rebuild_active_addons

RSYNC_ARGS=(-aHAX --numeric-ids)
if [[ "${RSYNC_DELETE}" == "1" ]]; then
    RSYNC_ARGS+=(--delete)
fi

if [[ "${OLD_RSYNC_SUDO}" == "1" ]]; then
    RSYNC_ARGS+=(--rsync-path="sudo rsync")
fi

log "Syncing XenForo file data from old server"
for item in "${SYNC_ITEMS[@]}"; do
    maybe_run mkdir -p "${NEW_FORUM_ROOT}/${item}"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] rsync ${OLD_FORUM_ROOT}/${item}/ -> ${NEW_FORUM_ROOT}/${item}/"
    else
        run_rsync "${RSYNC_ARGS[@]}" \
            "${OLD_SSH_USER}@${OLD_HOST}:${OLD_FORUM_ROOT}/${item}/" \
            "${NEW_FORUM_ROOT}/${item}/"
    fi
done

log "Fixing ownership on synced paths"
for item in "${SYNC_ITEMS[@]}"; do
    if [[ -e "${NEW_FORUM_ROOT}/${item}" ]]; then
        maybe_run chown -R "${PHP_USER}:${PHP_USER}" "${NEW_FORUM_ROOT}/${item}"
    fi
done

restore_preserve_files

ensure_internal_data_layout_and_perms

log "Rebuilding XenForo master data"
maybe_run sudo -u "${PHP_USER}" php "${NEW_FORUM_ROOT}/cmd.php" xf:rebuild-master-data

if [[ "${PRESERVE_STYLES}" == "1" ]]; then
    restore_styles
fi

ensure_internal_data_layout_and_perms
run_post_cutover_xf_file_cleanup_cli
post_cutover_acp_mitigations
clear_transient_error_log_after_import

if [[ -n "${OLD_POST_CUTOVER_CMD}" ]]; then
    log "Running post-cutover command on old server"
    if [[ "${DRY_RUN}" == "1" ]]; then
        echo "[dry-run] ssh old host ${OLD_POST_CUTOVER_CMD}"
    else
        run_ssh "${OLD_POST_CUTOVER_CMD}"
    fi
fi

log "Done"
echo "  old DB dump:     ${OLD_DUMP_GZ}"
echo "  new DB backup:   ${NEW_BACKUP_GZ}"
echo "  config backup:   ${CONFIG_BACKUP}"
if [[ "${PRESERVE_STYLES}" == "1" ]]; then
    echo "  style backups:   ${STYLE_BACKUP_ROOT}"
fi
echo
echo "Next steps:"
echo "  1. Test the new forum with hosts-file or beta URL."
echo "  2. Confirm uploads, logins, and add-ons work."
echo "  3. Flip DNS for bareefers.org / www.bareefers.org."

# Machine-grep friendly completion marker (works when script is renamed to *.unix.sh).
echo "CUTOVER_EXIT=0 at $(date -u +"%Y-%m-%dT%H:%M:%SZ") work_root=${WORK_ROOT}"

# Be explicit for remote callers (ssh/sudo wrappers) that we are done.
exit 0
