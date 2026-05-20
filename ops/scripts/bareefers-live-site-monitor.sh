#!/usr/bin/env bash
# Live site guard for bareefers XenForo stack: DB error log, nginx access (5xx), nginx error,
# MySQL server log, PHP-FPM log, disk headroom, Redis ping, MySQL ping.
#
# Deploy to server (e.g. /tmp/) and run as root under nohup:
#   scp xenforo/scripts/bareefers-live-site-monitor.sh bareefers:/tmp/
#   ssh bareefers 'chmod +x /tmp/bareefers-live-site-monitor.sh && nohup /tmp/bareefers-live-site-monitor.sh &'
#
# Tail combined log:
#   ssh bareefers 'tail -f /tmp/bareefers-live-site-monitor.log'
set -euo pipefail

DB="${DB:-baraforo}"
FORUM="${FORUM:-/var/www/bareefers.org/forum}"
STATE_DIR="${STATE_DIR:-/tmp/bareefers-live-site-monitor.state}"
LOG="${LOG:-/tmp/bareefers-live-site-monitor.log}"
POLL_SEC="${POLL_SEC:-3}"

NGINX_ACCESS="${NGINX_ACCESS:-/var/log/nginx/access.log}"
NGINX_ERROR="${NGINX_ERROR:-/var/log/nginx/error.log}"
MYSQL_ERR="${MYSQL_ERR:-/var/log/mysql/error.log}"
PHP_FPM_LOG="${PHP_FPM_LOG:-/var/log/php8.3-fpm.log}"

mkdir -p "$STATE_DIR"

log_line() {
  printf '[%s] %s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$*" >>"$LOG"
}

# Append new bytes since last offset into $3 (must run in main shell so offset file updates).
append_new_bytes_since_offset() {
  local file="$1" key="$2" out="$3"
  [[ -f "$file" ]] || { : >"$out"; return 0; }
  local state="$STATE_DIR/$key.offset"
  local old=0 sz
  sz="$(stat -c%s "$file" 2>/dev/null || echo 0)"
  [[ "$sz" =~ ^[0-9]+$ ]] || sz=0
  if [[ ! -f "$state" ]]; then
    # First run: start at EOF so we only watch new traffic (avoid megabytes of backlog).
    echo "$sz" >"$state"
    old="$sz"
  else
    old="$(cat "$state" 2>/dev/null || echo 0)"
  fi
  [[ "$old" =~ ^[0-9]+$ ]] || old=0
  if (( sz < old )); then
    old=0
  fi
  if (( sz > old )); then
    tail -c "+$((old + 1))" "$file" 2>/dev/null >"$out" || : >"$out"
  else
    : >"$out"
  fi
  echo "$sz" >"$state"
}

fix_xfm_attachment_schema() {
  mysql "$DB" -Nse "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='xf_attachment' AND column_name='xfmg_is_mirror_handler';" | grep -q '^1$' || \
    mysql "$DB" -e "ALTER TABLE xf_attachment ADD COLUMN xfmg_is_mirror_handler TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER unassociated;" || true

  mysql "$DB" -Nse "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='xf_attachment_data' AND column_name='xfmg_mirror_media_id';" | grep -q '^1$' || \
    mysql "$DB" -e "ALTER TABLE xf_attachment_data ADD COLUMN xfmg_mirror_media_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER attach_count;" || true

  mysql "$DB" -Nse "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$DB' AND table_name='xf_attachment_data' AND column_name='xfmg_is_mirror_handler';" | grep -q '^1$' || \
    mysql "$DB" -e "ALTER TABLE xf_attachment_data ADD COLUMN xfmg_is_mirror_handler TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER xfmg_mirror_media_id;" || true
}

fix_templates_baseline() {
  if [[ -f /tmp/xf-post-db-restore-repair.php ]] && [[ -f /tmp/extra-less-aurora16-source.less ]]; then
    php /tmp/xf-post-db-restore-repair.php "$FORUM" /tmp/extra-less-aurora16-source.less >/dev/null 2>&1 || true
  fi
}

last_xf_error_id=0
xf_state="$STATE_DIR/xf_error_log.last_id"
[[ -f "$xf_state" ]] && last_xf_error_id="$(cat "$xf_state" 2>/dev/null || echo 0)"
[[ "$last_xf_error_id" =~ ^[0-9]+$ ]] || last_xf_error_id=0

log_line "bareefers-live-site-monitor started poll=${POLL_SEC}s"

while true; do
  # --- XenForo DB error log ---
  max_id="$(mysql "$DB" -Nse "SELECT IFNULL(MAX(error_id),0) FROM xf_error_log;" 2>/dev/null || echo "$last_xf_error_id")"
  [[ "$max_id" =~ ^[0-9]+$ ]] || max_id="$last_xf_error_id"
  if (( max_id > last_xf_error_id )); then
    while IFS=$'\t' read -r id dt msg; do
      [[ -n "${id:-}" ]] || continue
      log_line "XF_ERROR id=$id dt=$dt msg=${msg:0:240}"
      if [[ "$msg" == *"xfmg_is_mirror_handler"* ]] || [[ "$msg" == *"xfmg_mirror_media_id"* ]]; then
        log_line "AUTO_FIX schema XFMG attachment columns"
        fix_xfm_attachment_schema
      fi
      if [[ "$msg" == *"Template public:"*" unknown"* ]] || [[ "$msg" == *"Error rendering template"* ]] || [[ "$msg" == *"CssRenderException"* ]]; then
        log_line "AUTO_FIX templates baseline (extra.less repair)"
        fix_templates_baseline
      fi
    done < <(mysql "$DB" -Nse "SELECT error_id, FROM_UNIXTIME(exception_date), message FROM xf_error_log WHERE error_id > $last_xf_error_id ORDER BY error_id ASC;" 2>/dev/null || true)
    last_xf_error_id="$max_id"
    echo "$last_xf_error_id" >"$xf_state"
  fi

  buf="$(mktemp)"

  # --- nginx error log ---
  append_new_bytes_since_offset "$NGINX_ERROR" nginx_error "$buf"
  while IFS= read -r line || [[ -n "${line:-}" ]]; do
    [[ -z "${line:-}" ]] && continue
    log_line "NGINX_ERROR $line"
  done <"$buf"

  # --- MySQL server error log ---
  append_new_bytes_since_offset "$MYSQL_ERR" mysql_error "$buf"
  while IFS= read -r line || [[ -n "${line:-}" ]]; do
    [[ -z "${line:-}" ]] && continue
    log_line "MYSQL_ERR $line"
  done <"$buf"

  # --- PHP-FPM log ---
  append_new_bytes_since_offset "$PHP_FPM_LOG" php_fpm "$buf"
  while IFS= read -r line || [[ -n "${line:-}" ]]; do
    [[ -z "${line:-}" ]] && continue
    log_line "PHP_FPM $line"
  done <"$buf"

  # --- nginx access: recent 5xx / critical statuses ---
  append_new_bytes_since_offset "$NGINX_ACCESS" nginx_access "$buf"
  while IFS= read -r line || [[ -n "${line:-}" ]]; do
    [[ -z "${line:-}" ]] && continue
    if echo "$line" | grep -Eq '"(GET|POST|HEAD|PUT|DELETE) [^"]+" (5[0-9]{2}|499) '; then
      log_line "NGINX_ACCESS_BAD $line"
    fi
  done <"$buf"

  rm -f "$buf"

  # --- infra quick checks (every ~30s) ---
  now_ts="$(date +%s)"
  last_infra=0
  [[ -f "$STATE_DIR/last_infra.ts" ]] && last_infra="$(cat "$STATE_DIR/last_infra.ts" 2>/dev/null || echo 0)"
  [[ "$last_infra" =~ ^[0-9]+$ ]] || last_infra=0
  if (( now_ts - last_infra > 30 )); then
    echo "$now_ts" >"$STATE_DIR/last_infra.ts"
    if ! mysql "$DB" -Nse "SELECT 1" >/dev/null 2>&1; then
      log_line "MYSQL_PING_FAIL"
    fi
    if ! redis-cli -n 7 PING >/dev/null 2>&1; then
      log_line "REDIS_DB7_PING_FAIL"
    fi
    df_out="$(df -h / /var/www 2>/dev/null | tail -n +2 | awk '{print $5,$6}' | head -5 | tr '\n' ' ')"
    log_line "DISK $df_out"
  fi

  sleep "$POLL_SEC"
done
