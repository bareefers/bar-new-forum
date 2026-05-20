#!/usr/bin/env bash

set -Eeuo pipefail

TARGET="/var/www/bareefers.org/forum/src/addons/DL6/WNTPL/XF/Widget/NewThreadsPrefixLimit.php"
BACKUP="${TARGET}.bak.$(date +%Y%m%d-%H%M%S)"

cp "$TARGET" "$BACKUP"

python3 - <<'PY'
from pathlib import Path

path = Path("/var/www/bareefers.org/forum/src/addons/DL6/WNTPL/XF/Widget/NewThreadsPrefixLimit.php")
text = path.read_text()
old = """\t\tif ($style == 'full' || $style == 'expanded')\n\t\t{\n\t\t\t$threadFinder->forFullView(true);\n\t\t\tif ($style == 'expanded')\n\t\t\t{\n\t\t\t\t$threadFinder->with('FirstPost');\n\t\t\t}\n\t\t}\n"""
new = """\t\tif ($style == 'full')\n\t\t{\n\t\t\t$threadFinder->with('fullForum');\n\t\t}\n\t\tif ($style == 'expanded')\n\t\t{\n\t\t\t$threadFinder->with('FirstPost');\n\t\t}\n"""
if old not in text:
    raise SystemExit("target snippet not found")
path.write_text(text.replace(old, new, 1))
PY

php -l "$TARGET"
echo "$BACKUP"
