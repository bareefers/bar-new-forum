#!/bin/bash
# Locked-down fail2ban helper for XenForo ACP (BAR Fail2ban Tools).
# Installed as: /usr/local/sbin/bareefers-fail2ban-acl
# Allowed via sudoers for www-data only.
set -euo pipefail

F2B="/usr/bin/fail2ban-client"
ALLOWED_JAILS=(
  bareefers-nginx-limit
  bareefers-nginx-whatsnew
  bareefers-nginx-botua
  bareefers-recidive
)

die() { echo "$*" >&2; exit 1; }

is_allowed_jail() {
  local j="$1"
  local x
  for x in "${ALLOWED_JAILS[@]}"; do
    [[ "$x" == "$j" ]] && return 0
  done
  return 1
}

valid_ip() {
  local ip="$1"
  # rough but safe: fail2ban + filter_var already validate in PHP; tighten here
  [[ "$ip" =~ ^[0-9a-fA-F:.]+$ ]] || return 1
  [[ ${#ip} -le 45 ]] || return 1
  return 0
}

cmd="${1:-}"
shift || true

case "$cmd" in
  lookup)
    ip="${1:-}"
    [[ -n "$ip" ]] || die "usage: lookup <ip>"
    valid_ip "$ip" || die "invalid ip"
    for j in "${ALLOWED_JAILS[@]}"; do
      if "$F2B" status "$j" 2>/dev/null | grep -Fq "$ip"; then
        echo -e "${j}\tbanned"
      else
        echo -e "${j}\tok"
      fi
    done
    ;;
  unban)
    ip="${1:-}"
    [[ -n "$ip" ]] || die "usage: unban <ip>"
    valid_ip "$ip" || die "invalid ip"
    for j in "${ALLOWED_JAILS[@]}"; do
      if "$F2B" status "$j" 2>/dev/null | grep -Fq "$ip"; then
        if "$F2B" set "$j" unbanip "$ip" >/dev/null 2>&1; then
          echo "UNBANNED ${j} ${ip}"
        else
          echo "FAILED ${j} ${ip}" >&2
        fi
      fi
    done
    ;;
  *)
    die "usage: bareefers-fail2ban-acl {lookup|unban} <ip>"
    ;;
esac
