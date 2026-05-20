#!/usr/bin/env bash

set -Eeuo pipefail

TARGET="/var/www/bareefers.org/forum/src/XF/Style.php"
BACKUP="${TARGET}.bak.$(date +%Y%m%d-%H%M%S)"

cp "$TARGET" "$BACKUP"

python3 - <<'PY'
from pathlib import Path

path = Path("/var/www/bareefers.org/forum/src/XF/Style.php")
text = path.read_text()
old = """\tpublic function isUsable(User $user)\n\t{\n\t\tif ($this->options['user_selectable'])\n\t\t{\n\t\t\treturn true;\n\t\t}\n"""
new = """\tpublic function isUsable(User $user)\n\t{\n\t\tif (($this->options['user_selectable'] ?? false))\n\t\t{\n\t\t\treturn true;\n\t\t}\n"""
if old not in text:
    raise SystemExit("target snippet not found")
path.write_text(text.replace(old, new, 1))
PY

php -l "$TARGET"
echo "$BACKUP"
