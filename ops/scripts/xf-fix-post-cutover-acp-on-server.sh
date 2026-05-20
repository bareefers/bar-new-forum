#!/usr/bin/env bash
# One-off on production after cutover: fix FileCleanUp permissions + reduce UpgradeCheck noise.
#   sudo bash xf-fix-post-cutover-acp-on-server.sh [/path/to/forum]
set -euo pipefail
FORUM_ROOT="${1:-/var/www/bareefers.org/forum}"
PHP_USER="${PHP_USER:-www-data}"
CFG="${FORUM_ROOT}/src/config.php"

for d in temp code_cache attachments sitemaps logs; do
	mkdir -p "${FORUM_ROOT}/internal_data/${d}"
done

# XF 2.3+ FileCleanUp removes legacy files under js/, src/, styles/, etc.; chown only internal_data/data is not enough.
chown -R "${PHP_USER}:${PHP_USER}" "${FORUM_ROOT}"
chmod -R ug+rwX "${FORUM_ROOT}/internal_data" "${FORUM_ROOT}/data"

if [[ -f "${FORUM_ROOT}/cmd.php" ]]; then
	sudo -u "${PHP_USER}" php "${FORUM_ROOT}/cmd.php" xf:file-clean-up XF -n || echo "WARN: xf:file-clean-up failed (check error log)" >&2
fi

if [[ -f "${CFG}" ]] && ! grep -qF "BAR post-cutover patches" "${CFG}"; then
	cat >>"${CFG}" <<'PHP'

// BAR post-cutover patches: outbound XenForo API may return HTML (WAF/proxy). Remove this block after egress is verified.
$config['enableOneClickUpgrade'] = false;
PHP
fi

DB="$(php -r "require '${FORUM_ROOT}/src/config.php'; echo \$config['db']['dbname'];")"
mysql "${DB}" -e "DELETE FROM xf_job WHERE execute_class LIKE '%UpgradeCheck%';" || true

echo "OK: post-cutover ACP fixes applied for ${FORUM_ROOT}"
