#!/usr/bin/env bash

set -Eeuo pipefail

SOURCE_DB="${1:-baraforo_beta_compare}"
TARGET_DB="${2:-baraforo}"

mapfile -t tables < <(
    mysql -Nse "
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = '${SOURCE_DB}'
          AND (table_name LIKE 'xf_mg\\_%' OR table_name LIKE 'xf_rm\\_%')
        ORDER BY table_name;
    "
)

if [[ ${#tables[@]} -eq 0 ]]; then
    echo "No addon tables found in ${SOURCE_DB}"
    exit 0
fi

mysqldump --single-transaction --triggers --no-tablespaces --default-character-set=utf8mb4 "${SOURCE_DB}" "${tables[@]}" | mysql "${TARGET_DB}"
