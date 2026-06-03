#!/usr/bin/env bash
#
# PayPal / XenForo payment webhook sanity (read-only).
# Use after May 2026 cutover fixes or when ACP / xf_payment_provider_log shows payment noise.
#
# Run ON the forum server:
#   sudo bash /var/www/bareefers.org/bar-new-forum/ops/scripts/xf-payment-health.sh
#
# From Windows (bar-new-forum or barcode repo with this path):
#   wsl bash -lc "ssh bareefers 'sudo bash -s --'" < bar-new-forum/ops/scripts/xf-payment-health.sh
#
# Optional cron (install once on server):
#   sudo cp ops/cron/xf-payment-health.cron /etc/cron.d/xf-payment-health
#   sudo chmod 644 /etc/cron.d/xf-payment-health
#
# Exit 0 = OK for production; non-zero = investigate (recent failures or missing patches).
#
# Full runbook: docs/PAYPAL-PAYMENT-MAY2026.md (in bar-new-forum repo root).
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
	exec sudo bash "$0" "$@"
fi

_sn="${BASH_SOURCE[0]:-$0}"
case "${_sn}" in '' | '-' | bash | */bash) SCRIPT_NAME='xf-payment-health.sh' ;; *) SCRIPT_NAME="$(basename "${_sn}")" ;; esac
DB="${XF_DB:-baraforo}"
FORUM="${XF_FORUM_ROOT:-/var/www/bareefers.org/forum}"
HOURS_RECENT="${XF_PAYMENT_HEALTH_HOURS:-24}"
FAIL=0

while [[ $# -gt 0 ]]; do
	case "$1" in
		--db)
			DB="${2:?}"
			shift 2
			;;
		--forum)
			FORUM="${2:?}"
			shift 2
			;;
		--hours)
			HOURS_RECENT="${2:?}"
			shift 2
			;;
		-h | --help)
			sed -n '1,22p' "$0"
			exit 0
			;;
		*)
			printf '%s: unknown option %q\n' "$SCRIPT_NAME" "$1" >&2
			exit 1
			;;
	esac
done

PAYPAL_REST="${FORUM}/src/XF/Payment/PayPalRest.php"
SINCE_UNIX="$(date -u -d "${HOURS_RECENT} hours ago" '+%s' 2>/dev/null || date -u -v-"${HOURS_RECENT}"H '+%s')"

echo ""
echo "=== ${SCRIPT_NAME} $(date -u '+%Y-%m-%d %H:%M:%S UTC') ==="
echo "MySQL DB: ${DB}"
echo "Forum root: ${FORUM}"
echo "Recent window: last ${HOURS_RECENT}h (since ${SINCE_UNIX})"
echo ""

if [[ ! -f "${PAYPAL_REST}" ]]; then
	printf '%s: missing %s\n' "$SCRIPT_NAME" "${PAYPAL_REST}" >&2
	exit 1
fi

echo "--- 1) PayPalRest.php patches (must be present after xf:upgrade) ---"
if grep -q 'BAR: use paypalrest profile when purchase request profile lacks webhook_id' "${PAYPAL_REST}"; then
	echo "profile_fallback_patch=ok"
else
	echo "profile_fallback_patch=MISSING"
	FAIL=1
fi
if grep -q "sprintf('%u', crc32" "${PAYPAL_REST}"; then
	echo "crc32_unsigned_patch=ok"
else
	echo "crc32_unsigned_patch=MISSING"
	FAIL=1
fi
if grep -q "webhook_id'] ?? null" "${PAYPAL_REST}"; then
	echo "webhook_id_null_coalesce=ok"
else
	echo "webhook_id_null_coalesce=warn (optional but recommended)"
fi
echo ""

echo "--- 2) Payment profiles (no secrets printed) ---"
sudo mysql "${DB}" -Nse "
SELECT CONCAT('profile_', payment_profile_id, '_provider=', provider_id)
FROM xf_payment_profile
ORDER BY payment_profile_id;
" 2>/dev/null || { echo "mysql query failed"; exit 1; }

OPTS_CAST="CAST(options AS CHAR CHARACTER SET utf8mb4)"
LEGACY_EMAIL="$(sudo mysql "${DB}" -Nse "
SELECT JSON_UNQUOTE(JSON_EXTRACT(${OPTS_CAST}, '$.primary_account'))
FROM xf_payment_profile WHERE payment_profile_id = 1;
" 2>/dev/null || true)"
REST_WEBHOOK="$(sudo mysql "${DB}" -Nse "
SELECT IF(
  JSON_EXTRACT(${OPTS_CAST}, '$.webhook_id') IS NULL
  OR JSON_UNQUOTE(JSON_EXTRACT(${OPTS_CAST}, '$.webhook_id')) = '',
  'missing', 'set')
FROM xf_payment_profile WHERE payment_profile_id = 2;
" 2>/dev/null || true)"

echo "legacy_primary_account=${LEGACY_EMAIL:-unknown}"
echo "paypalrest_webhook_id=${REST_WEBHOOK:-unknown}"

if [[ "${LEGACY_EMAIL}" == *'1paypal@'* ]] || [[ "${LEGACY_EMAIL}" == '1paypal@bareefers.org' ]]; then
	echo "legacy_primary_account=INVALID (typo 1paypal@)"
	FAIL=1
elif [[ "${LEGACY_EMAIL}" != 'paypal@bareefers.org' ]]; then
	echo "legacy_primary_account=warn (expected paypal@bareefers.org, got ${LEGACY_EMAIL})"
fi
if [[ "${REST_WEBHOOK}" != 'set' ]]; then
	echo "paypalrest_webhook_id=MISSING"
	FAIL=1
fi
echo ""

echo "--- 3) enableLivePayments (src/config.php) ---"
if grep -q "enableLivePayments" "${FORUM}/src/config.php" 2>/dev/null; then
	grep 'enableLivePayments' "${FORUM}/src/config.php" | head -1 | sed 's/^[[:space:]]*//'
else
	echo "enableLivePayments not set in config (defaults apply)"
fi
echo ""

echo "--- 4) xf_payment_provider_log (recent failures) ---"
VERIFY_RECENT="$(sudo mysql "${DB}" -Nse "
SELECT COUNT(*) FROM xf_payment_provider_log
WHERE log_message LIKE '%could not be verified%'
  AND log_date > ${SINCE_UNIX};
" 2>/dev/null || echo '')"
INVALID_BIZ_RECENT="$(sudo mysql "${DB}" -Nse "
SELECT COUNT(*) FROM xf_payment_provider_log
WHERE log_message = 'Invalid business or receiver_email.'
  AND log_date > ${SINCE_UNIX};
" 2>/dev/null || echo '')"
VERIFY_7D="$(sudo mysql "${DB}" -Nse "
SELECT COUNT(*) FROM xf_payment_provider_log
WHERE log_message LIKE '%could not be verified%'
  AND log_date > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY));
" 2>/dev/null || echo '')"

echo "webhook_verify_fail_last_${HOURS_RECENT}h=${VERIFY_RECENT:-?}"
echo "invalid_business_ipn_last_${HOURS_RECENT}h=${INVALID_BIZ_RECENT:-?}"
echo "webhook_verify_fail_last_7d=${VERIFY_7D:-?} (includes pre-fix history)"

if [[ "${VERIFY_RECENT:-0}" -gt 0 ]] || [[ "${INVALID_BIZ_RECENT:-0}" -gt 0 ]]; then
	FAIL=1
fi
echo ""

echo "--- 5) Last 5 payment provider errors (any message) ---"
sudo mysql "${DB}" -e "
SELECT provider_log_id,
       FROM_UNIXTIME(log_date) AS log_utc,
       log_type,
       LEFT(log_message, 100) AS log_message
FROM xf_payment_provider_log
WHERE log_type = 'error'
ORDER BY provider_log_id DESC
LIMIT 5;
" 2>/dev/null || true
echo ""

echo "--- 6) xf_error_log PayPalRest / webhook_id (last ${HOURS_RECENT}h) ---"
WEBHOOK_ERR="$(sudo mysql "${DB}" -Nse "
SELECT COUNT(*) FROM xf_error_log
WHERE (message LIKE '%webhook_id%' OR filename LIKE '%PayPalRest%')
  AND exception_date > ${SINCE_UNIX};
" 2>/dev/null || echo '0')"
echo "paypalrest_php_errors_last_${HOURS_RECENT}h=${WEBHOOK_ERR}"
if [[ "${WEBHOOK_ERR:-0}" -gt 0 ]]; then
	FAIL=1
fi

if [[ "${WEBHOOK_ERR:-0}" -gt 0 ]]; then
	sudo mysql "${DB}" -e "
SELECT error_id, FROM_UNIXTIME(exception_date) AS err_utc, LEFT(message, 90) AS message
FROM xf_error_log
WHERE (message LIKE '%webhook_id%' OR filename LIKE '%PayPalRest%')
  AND exception_date > ${SINCE_UNIX}
ORDER BY exception_date DESC
LIMIT 5;
" 2>/dev/null || true
	echo ""
fi

echo "--- 7) Recent payment provider log volume (last ${HOURS_RECENT}h) ---"
sudo mysql "${DB}" -e "
SELECT log_type, COUNT(*) AS n
FROM xf_payment_provider_log
WHERE log_date > ${SINCE_UNIX}
GROUP BY log_type
ORDER BY n DESC;
" 2>/dev/null || true
echo ""

if [[ "${FAIL}" -eq 0 ]]; then
	echo "=== ${SCRIPT_NAME} OK (no issues in last ${HOURS_RECENT}h; patches present) ==="
else
	echo "=== ${SCRIPT_NAME} FAILED — see items above ===" >&2
fi
echo ""

exit "${FAIL}"
