#!/usr/bin/env bash

set -Eeuo pipefail

DUMP_GZ="${1:-}"
TABLE_NAME="${2:-}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-baraforo}"
DB_USER="${DB_USER:-barausr}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ -z "${DUMP_GZ}" || -z "${TABLE_NAME}" ]]; then
  echo "usage: $0 /path/to/backup.sql.gz xf_table_name" >&2
  exit 1
fi

if [[ -z "${DB_PASSWORD}" ]]; then
  echo "DB_PASSWORD is required" >&2
  exit 1
fi

backup_file="/var/tmp/${TABLE_NAME}-before-restore-$(date +%Y%m%d-%H%M%S).sql"
restore_file="/var/tmp/${TABLE_NAME}-restore-$(date +%Y%m%d-%H%M%S).sql"

existing_table="$(
  MYSQL_PWD="${DB_PASSWORD}" mysql -Nse "SHOW TABLES LIKE '${TABLE_NAME}'" -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" "${DB_NAME}"
)"

if [[ "${existing_table}" == "${TABLE_NAME}" ]]; then
  MYSQL_PWD="${DB_PASSWORD}" mysqldump \
    -h "${DB_HOST}" \
    -P "${DB_PORT}" \
    -u "${DB_USER}" \
    --no-tablespaces \
    "${DB_NAME}" \
    "${TABLE_NAME}" \
    > "${backup_file}"
fi

DUMP_GZ="${DUMP_GZ}" TABLE_NAME="${TABLE_NAME}" RESTORE_FILE="${restore_file}" python3 <<'PY'
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

MYSQL_PWD="${DB_PASSWORD}" mysql \
  -h "${DB_HOST}" \
  -P "${DB_PORT}" \
  -u "${DB_USER}" \
  "${DB_NAME}" \
  < "${restore_file}"

echo "${backup_file}"
echo "${restore_file}"
