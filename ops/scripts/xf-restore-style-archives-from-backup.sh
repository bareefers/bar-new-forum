#!/usr/bin/env bash

set -Eeuo pipefail

BACKUP_TARBALL="${1:?Usage: xf-restore-style-archives-from-backup.sh /path/to/backup.tar.gz}"

TS="$(date +%Y%m%d-%H%M%S)"
RESTORE_ROOT="/var/tmp/xf-style-restore-${TS}"
EXTRACT_DIR="${RESTORE_ROOT}/extracted"

mkdir -p "${EXTRACT_DIR}"
tar -C "${EXTRACT_DIR}" -xzf "${BACKUP_TARBALL}"

BACKUP_DIR="$(python3 - "${EXTRACT_DIR}" <<'PY'
import os
import sys

base = sys.argv[1]
for name in sorted(os.listdir(base)):
    path = os.path.join(base, name)
    if os.path.isdir(path) and name.startswith("xf-prelaunch-backup-"):
        print(path)
        break
PY
)"

[[ -n "${BACKUP_DIR}" ]] || {
    echo "Failed to locate extracted backup dir" >&2
    exit 1
}

mkdir -p /var/www/bareefers.org/forum/styles/adminjunkies
chown -R www-data:www-data /var/www/bareefers.org/forum/styles/adminjunkies

for style_id in 2 8 12 13 14 4 16; do
    archive_path="$(ls -1 "${BACKUP_DIR}/styles/style-${style_id}"/*.zip 2>/dev/null | head -1 || true)"
    [[ -n "${archive_path}" ]] || continue

    echo "Importing style ${style_id} from ${archive_path}"
    sudo -u www-data php /var/www/bareefers.org/forum/cmd.php \
        xf:style-archive-import "${archive_path}" \
        --target overwrite \
        --overwrite-style-id "${style_id}" \
        --force \
        --no-interaction
done

redis-cli -n 3 FLUSHDB >/dev/null
redis-cli -n 5 FLUSHDB >/dev/null
redis-cli -n 7 FLUSHDB >/dev/null
systemctl reload php8.3-fpm
sudo -u www-data php /var/www/bareefers.org/forum/cmd.php xf:rebuild-master-data
sudo -u www-data php /var/www/bareefers.org/forum/cmd.php xf:run-jobs

echo "${RESTORE_ROOT}"
