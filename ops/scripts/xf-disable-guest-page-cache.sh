#!/usr/bin/env bash

set -Eeuo pipefail

CONFIG="/var/www/bareefers.org/forum/src/config.php"
BACKUP="${CONFIG}.bak.$(date +%Y%m%d-%H%M%S)"

cp "$CONFIG" "$BACKUP"

python3 <<'PY'
from pathlib import Path

path = Path("/var/www/bareefers.org/forum/src/config.php")
text = path.read_text()
old = "$config['pageCache']['enabled'] = true;"
new = "$config['pageCache']['enabled'] = false;"
if old not in text:
    raise SystemExit("page cache config line not found")
path.write_text(text.replace(old, new, 1))
PY

php -l "$CONFIG"
echo "$BACKUP"
