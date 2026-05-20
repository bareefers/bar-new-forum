#!/usr/bin/env bash
# PayPal signs crc32 as unsigned 32-bit decimal; PHP crc32() can be negative on 64-bit.
# Apply on server after XenForo upgrades (or keep in deploy notes).
set -euo pipefail
ROOT="${1:-/var/www/bareefers.org/forum}"
FILE="${ROOT}/src/XF/Payment/PayPalRest.php"
if [[ "${EUID}" -ne 0 ]]; then
	exec sudo "$0" "$@"
fi
[[ -f "${FILE}" ]] || { echo "Missing ${FILE}" >&2; exit 1; }
grep -q "sprintf('%u', crc32" "${FILE}" && { echo "Already patched"; exit 0; }
cp -a "${FILE}" "${FILE}.bak.crc32.$(date +%Y%m%d%H%M%S)"
sed -i "s/\$crc = crc32(\$webhookBody);/\$crc = sprintf('%u', crc32(\$webhookBody) \& 0xffffffff);/" "${FILE}"
php -l "${FILE}"
echo "OK: ${FILE}"
