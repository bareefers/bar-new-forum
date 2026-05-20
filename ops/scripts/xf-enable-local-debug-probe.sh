#!/usr/bin/env bash

set -Eeuo pipefail

CONFIG="/var/www/bareefers.org/forum/src/config.php"
BACKUP="${CONFIG}.debugprobe.$(date +%Y%m%d-%H%M%S)"

cp "$CONFIG" "$BACKUP"

python3 <<'PY'
from pathlib import Path

path = Path("/var/www/bareefers.org/forum/src/config.php")
text = path.read_text()
needle = "if (php_sapi_name() !== 'cli' && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == '181.118.37.27') { $config['debug'] = false; }"
insert = needle + "\nif (php_sapi_name() !== 'cli' && isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == '127.0.0.1') { $config['debug'] = true; }"
if "$_SERVER['REMOTE_ADDR'] == '127.0.0.1'" not in text:
    if needle not in text:
        raise SystemExit("debug needle not found")
    text = text.replace(needle, insert, 1)
    path.write_text(text)
PY

php -l "$CONFIG"
echo "$BACKUP"
